<?php

namespace App\Services;

use App\Exceptions\ApprovalException;
use App\Models\Product;
use App\Models\PurchaseApproval;
use App\Models\Recommendation;
use App\Models\ShoppingSession;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Owns the gap between "the AI recommended this" and "the buyer authorized
 * this amount". Price is always re-read from the catalog, approvals expire,
 * and any material change invalidates the authorization rather than silently
 * charging the new figure.
 */
class ApprovalService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function createFor(ShoppingSession $session, Recommendation $recommendation): PurchaseApproval
    {
        if (! $recommendation->isRecommended() || ! $recommendation->product_id) {
            throw new ApprovalException('A refused recommendation cannot be taken to payment.');
        }

        $product = Product::query()->findOrFail($recommendation->product_id);

        return DB::transaction(function () use ($session, $recommendation, $product) {
            // Only one live approval per session. A new review supersedes the old one.
            $session->approvals()
                ->whereIn('status', ['pending', 'authorized'])
                ->update([
                    'status' => 'expired',
                    'invalidated_reason' => 'superseded_by_new_review',
                    'updated_at' => now(),
                ]);

            $approval = $session->approvals()->create([
                'recommendation_id' => $recommendation->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price_cents' => $product->price_cents,
                'total_cents' => $product->price_cents,
                'currency' => $product->currency,
                'status' => 'pending',
                'expires_at' => now()->addMinutes((int) config('trustcart.approval_ttl_minutes', 15)),
            ]);

            $this->audit->log('approval.created', $session, [
                'approval_uuid' => $approval->uuid,
                'product' => $product->sku,
                'unit_price_cents' => $product->price_cents,
                'currency' => $product->currency,
                'expires_at' => $approval->expires_at->toIso8601String(),
            ], $approval);

            return $approval;
        });
    }

    public function authorize(PurchaseApproval $approval, Request $request): PurchaseApproval
    {
        $approval = $approval->fresh();

        if ($approval->status === 'consumed') {
            throw new ApprovalException('This approval has already been used.');
        }

        if ($approval->status === 'authorized' && ! $approval->isExpired()) {
            return $approval;
        }

        if (! $approval->isActionable()) {
            throw new ApprovalException('This approval has expired or is no longer valid. Start a new review from the decision record.');
        }

        $product = $approval->product()->first();

        if ($product === null) {
            throw new ApprovalException('The product is no longer in the catalog.');
        }

        // Validation happens before the transaction so an invalidation is
        // persisted rather than rolled back by the exception that follows.
        if ($product->price_cents !== $approval->unit_price_cents || $product->currency !== $approval->currency) {
            $this->invalidate($approval, 'price_changed_before_authorization', $product);

            throw new ApprovalException(sprintf(
                'The catalog price changed to %s after this review was prepared. Your approval was not taken. Review the new total and authorize again.',
                Money::format($product->price_cents, $product->currency),
            ));
        }

        if ($product->stock < $approval->quantity) {
            $this->invalidate($approval, 'out_of_stock', $product);

            throw new ApprovalException('Stock changed after this review was prepared. No payment was taken.');
        }

        return DB::transaction(function () use ($approval, $request) {
            /** @var PurchaseApproval $locked */
            $locked = PurchaseApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'authorized' && ! $locked->isExpired()) {
                return $locked;
            }

            if (! $locked->isActionable()) {
                throw new ApprovalException('This approval expired while you were reviewing it. Start a new review.');
            }

            $locked->forceFill([
                'status' => 'authorized',
                'authorized_at' => now(),
                'authorized_ip' => $request->ip(),
                'authorized_user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ])->save();

            $this->audit->log('approval.authorized', $locked->shoppingSession, [
                'approval_uuid' => $locked->uuid,
                'total_cents' => $locked->total_cents,
                'currency' => $locked->currency,
                'expires_at' => $locked->expires_at->toIso8601String(),
            ], $locked);

            return $locked;
        });
    }

    public function invalidate(PurchaseApproval $approval, string $reason, ?Product $product = null): void
    {
        $approval->forceFill([
            'status' => 'invalidated',
            'invalidated_reason' => $reason,
        ])->save();

        $this->audit->log('approval.invalidated', $approval->shoppingSession, [
            'approval_uuid' => $approval->uuid,
            'reason' => $reason,
            'catalog_price_cents' => $product?->price_cents,
            'approved_price_cents' => $approval->unit_price_cents,
        ], $approval);
    }

    /**
     * Demo control: a legitimate server-side catalog update. It is the same
     * path a real merchant sync would take, and it voids any approval that
     * quoted the old price.
     *
     * @return array{old_cents: int, new_cents: int, invalidated: int}
     */
    public function simulatePriceChange(Product $product, float $percent = 0.12): array
    {
        return DB::transaction(function () use ($product, $percent) {
            $old = $product->price_cents;
            $new = (int) max(100, round($old * (1 + $percent)));

            $product->forceFill([
                'price_cents' => $new,
                'last_synced_at' => now(),
            ])->save();

            $invalidated = PurchaseApproval::query()
                ->where('product_id', $product->id)
                ->whereIn('status', ['pending', 'authorized'])
                ->update([
                    'status' => 'invalidated',
                    'invalidated_reason' => 'price_changed_by_merchant',
                    'updated_at' => now(),
                ]);

            $session = ShoppingSession::query()
                ->whereHas('recommendation', fn ($query) => $query->where('product_id', $product->id))
                ->latest()
                ->first();

            $this->audit->log('catalog.price_changed', $session, [
                'sku' => $product->sku,
                'old_price_cents' => $old,
                'new_price_cents' => $new,
                'currency' => $product->currency,
                'approvals_invalidated' => $invalidated,
                'source' => 'merchant_sync_simulation',
            ], $product);

            return ['old_cents' => $old, 'new_cents' => $new, 'invalidated' => $invalidated];
        });
    }
}
