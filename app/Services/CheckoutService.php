<?php

namespace App\Services;

use App\Exceptions\ApprovalException;
use App\Exceptions\CheckoutException;
use App\Exceptions\PayPalException;
use App\Models\Order;
use App\Models\PurchaseApproval;
use App\Services\PayPal\PayPalClient;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly PayPalClient $paypal,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    public function paypalConfigured(): bool
    {
        return $this->paypal->isConfigured();
    }

    /**
     * Create (or reuse) the PayPal order for an authorized approval.
     */
    public function begin(PurchaseApproval $approval): Order
    {
        $approval = $approval->fresh();

        if (! $approval->isAuthorized()) {
            throw new CheckoutException('This purchase has not been authorized, or the authorization expired.');
        }

        $product = $approval->product()->first();

        if ($product === null) {
            throw new CheckoutException('The product is no longer available.');
        }

        // Validate before the transaction so an invalidation persists even
        // though the validation itself throws.
        if ($product->price_cents !== $approval->unit_price_cents || $product->currency !== $approval->currency) {
            $this->approvals->invalidate($approval, 'price_changed_at_checkout', $product);

            throw new ApprovalException(sprintf(
                'The price changed to %s at checkout. Your previous approval is void — review and authorize the new amount.',
                Money::format($product->price_cents, $product->currency),
            ));
        }

        if ($product->stock < $approval->quantity) {
            $this->approvals->invalidate($approval, 'out_of_stock_at_checkout', $product);

            throw new CheckoutException('The product went out of stock before checkout. No payment was taken.');
        }

        $order = DB::transaction(function () use ($approval) {
            /** @var PurchaseApproval $locked */
            $locked = PurchaseApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isAuthorized()) {
                throw new CheckoutException('This purchase has not been authorized, or the authorization expired.');
            }

            $existing = $locked->orders()
                ->whereIn('status', ['created', 'pending'])
                ->whereNotNull('paypal_order_id')
                ->latest()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $locked->orders()->create([
                'provider' => 'paypal',
                'environment' => 'sandbox',
                'status' => 'created',
                'amount_cents' => $locked->total_cents,
                'currency' => $locked->currency,
            ]);
        });

        if (filled($order->paypal_order_id)) {
            return $order;
        }

        try {
            $payload = $this->paypal->createOrder(
                $order->approval,
                route('checkout.return', $order->uuid),
                route('checkout.cancel', $order->uuid),
            );
        } catch (PayPalException $e) {
            $this->markFailed($order, $e->getMessage());

            throw $e;
        }

        $order->forceFill([
            'paypal_order_id' => $payload['id'] ?? null,
            'status' => 'created',
            'payload' => $this->summarise($payload),
        ])->save();

        if (blank($order->paypal_order_id)) {
            $this->markFailed($order, 'PayPal did not return an order identifier.');

            throw new CheckoutException('PayPal did not return an order identifier. No payment was taken.');
        }

        $order->events()->create([
            'type' => 'order.created',
            'provider_event_id' => $order->paypal_order_id,
            'status' => $payload['status'] ?? 'CREATED',
            'payload' => $this->summarise($payload),
        ]);

        $this->audit->log('checkout.order_created', $order->approval->shoppingSession, [
            'order_uuid' => $order->uuid,
            'paypal_order_id' => $order->paypal_order_id,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'environment' => $order->environment,
        ], $order);

        return $order->refresh();
    }

    public function approveUrl(Order $order): ?string
    {
        $links = data_get($order->payload, 'links', []);

        if (! is_array($links)) {
            return null;
        }

        foreach (['approve', 'payer-action'] as $rel) {
            foreach ($links as $link) {
                if (! is_array($link) || ($link['rel'] ?? null) !== $rel || ! filled($link['href'] ?? null)) {
                    continue;
                }

                $host = parse_url((string) $link['href'], PHP_URL_HOST);

                // Never send the buyer to a host that is not PayPal, even if a
                // compromised or malformed provider response says so.
                if (! is_string($host) || ! str_ends_with(strtolower($host), 'paypal.com')) {
                    continue;
                }

                return (string) $link['href'];
            }
        }

        return null;
    }

    /**
     * Verify the buyer's return, capture exactly once, and record what PayPal
     * actually reported. A second return is a no-op.
     */
    public function complete(Order $order): Order
    {
        if ($order->isCompleted()) {
            return $order;
        }

        /** @var Order $order */
        $order = Order::query()->whereKey($order->id)->firstOrFail();

        // Atomic claim: only one request may move an order into capture. A
        // duplicate return sees "capturing" or "completed" and stops here.
        $claimed = DB::table('orders')
            ->where('id', $order->id)
            ->whereIn('status', ['created', 'pending'])
            ->update(['status' => 'capturing', 'updated_at' => now()]);

        if ($claimed === 0) {
            $fresh = $order->fresh();

            if ($fresh->isCompleted()) {
                return $fresh;
            }

            if ($fresh->status === 'capturing') {
                throw new CheckoutException('This payment is already being processed. Refresh in a moment to see the verified status.');
            }

            throw new CheckoutException('This order can no longer be captured (status: '.$fresh->status.'). No payment was recorded.');
        }

        $order->refresh();

        if (blank($order->paypal_order_id)) {
            $this->markFailed($order, 'Order has no PayPal identifier.');

            throw new CheckoutException('This order has no PayPal identifier.');
        }

        $approval = $order->approval;

        if (! $approval->isAuthorized()) {
            $this->markFailed($order, 'Authorization expired before capture.');

            throw new CheckoutException('The authorization expired before the payment could be captured. No payment was taken.');
        }

        try {
            $providerOrder = $this->paypal->getOrder($order->paypal_order_id);
            $this->assertAmount($providerOrder, $order);

            $providerStatus = (string) ($providerOrder['status'] ?? 'UNKNOWN');

            // The capture may have succeeded on a previous attempt whose
            // response never reached us. Record it; never capture twice.
            if ($providerStatus === 'COMPLETED') {
                $capture = data_get($providerOrder, 'purchase_units.0.payments.captures.0', []);
                $this->markCompleted($order, is_array($capture) ? $capture : [], $providerOrder);

                return $order->refresh();
            }

            if ($providerStatus !== 'APPROVED') {
                $this->markFailed($order, 'PayPal order was not approved (status '.$providerStatus.').');

                throw new CheckoutException('PayPal did not report this order as approved, so nothing was captured.');
            }

            $capture = $this->paypal->captureOrder(
                $order->paypal_order_id,
                'tc-'.substr(hash('sha256', 'capture|'.$approval->uuid), 0, 32),
            );

            $this->assertAmount($capture, $order);

            $captureStatus = (string) ($capture['status'] ?? 'UNKNOWN');

            if ($captureStatus === 'COMPLETED') {
                $captureData = data_get($capture, 'purchase_units.0.payments.captures.0', []);
                $this->markCompleted($order, is_array($captureData) ? $captureData : [], $capture);

                return $order->refresh();
            }

            if (in_array($captureStatus, ['PENDING', 'DECLINED', 'FAILED'], true)) {
                $this->record($order, 'capture.'.$captureStatus, $captureStatus, $capture);

                $order->forceFill([
                    'status' => $captureStatus === 'PENDING' ? 'pending' : 'failed',
                    'failure_reason' => $captureStatus === 'PENDING'
                        ? 'PayPal is reviewing the payment.'
                        : 'PayPal did not complete the capture.',
                    'payload' => $this->summarise($capture),
                ])->save();

                throw new CheckoutException(
                    $captureStatus === 'PENDING'
                        ? 'PayPal is reviewing this payment. It is not marked as paid yet.'
                        : 'PayPal did not complete the payment. No money was taken.',
                );
            }

            throw new CheckoutException('PayPal returned an unexpected capture status. Nothing was recorded as paid.');
        } catch (PayPalException $e) {
            $this->markFailed($order, $e->getMessage());

            throw $e;
        }
    }

    public function cancel(Order $order): Order
    {
        if ($order->isCompleted()) {
            return $order;
        }

        return DB::transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCompleted()) {
                return $order;
            }

            if ($order->status === 'capturing') {
                throw new CheckoutException('This payment is already being processed. Check the result before cancelling.');
            }

            $order->forceFill([
                'status' => 'cancelled',
                'failure_reason' => 'Buyer cancelled at PayPal.',
            ])->save();

            $order->approval->forceFill([
                'status' => 'cancelled',
                'invalidated_reason' => 'buyer_cancelled_at_paypal',
            ])->save();

            $order->events()->create([
                'type' => 'checkout.cancelled',
                'provider_event_id' => $order->paypal_order_id,
                'status' => 'CANCELLED',
                'payload' => null,
            ]);

            $this->audit->log('checkout.cancelled', $order->approval->shoppingSession, [
                'order_uuid' => $order->uuid,
                'paypal_order_id' => $order->paypal_order_id,
            ], $order);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $capture
     * @param  array<string, mixed>  $payload
     */
    private function markCompleted(Order $order, array $capture, array $payload): void
    {
        $captureId = $capture['id'] ?? null;
        $payerId = data_get($payload, 'payer.payer_id');

        $order->forceFill([
            'status' => 'completed',
            'capture_id' => is_string($captureId) ? $captureId : null,
            'payer_id' => is_string($payerId) ? $payerId : null,
            'failure_reason' => null,
            'payload' => $this->summarise($payload),
        ])->save();

        $order->approval->forceFill(['status' => 'consumed'])->save();

        $order->events()->create([
            'type' => 'capture.completed',
            'provider_event_id' => is_string($captureId) ? $captureId : $order->paypal_order_id,
            'status' => 'COMPLETED',
            'payload' => $this->summarise($payload),
        ]);

        $this->audit->log('checkout.completed', $order->approval->shoppingSession, [
            'order_uuid' => $order->uuid,
            'paypal_order_id' => $order->paypal_order_id,
            'capture_id' => $captureId,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'paypal_status' => 'COMPLETED',
        ], $order);
    }

    private function markFailed(Order $order, string $reason): void
    {
        $order->forceFill([
            'status' => 'failed',
            'failure_reason' => mb_substr($reason, 0, 500),
        ])->save();

        $this->record($order, 'checkout.failed', 'FAILED', ['reason' => $reason]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(Order $order, string $type, string $status, array $payload): void
    {
        $order->events()->create([
            'type' => $type,
            'provider_event_id' => $order->paypal_order_id,
            'status' => $status,
            'payload' => $this->summarise($payload),
        ]);

        $this->audit->log($type, $order->approval->shoppingSession, [
            'order_uuid' => $order->uuid,
            'status' => $status,
        ], $order);
    }

    /**
     * Reject any provider response whose amount or currency differs from the
     * stored approval, whichever shape PayPal returns.
     */
    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertAmount(array $payload, Order $order): void
    {
        $value = data_get($payload, 'purchase_units.0.payments.captures.0.amount.value')
            ?? data_get($payload, 'purchase_units.0.amount.value');
        $currency = data_get($payload, 'purchase_units.0.payments.captures.0.amount.currency_code')
            ?? data_get($payload, 'purchase_units.0.amount.currency_code');

        if ($value === null || $currency === null) {
            throw new CheckoutException('PayPal did not return an amount to verify. Nothing was recorded as paid.');
        }

        if (Money::fromDecimal($value) !== $order->amount_cents || strtoupper((string) $currency) !== $order->currency) {
            $this->markFailed($order, 'Provider amount did not match the approved amount.');

            throw new CheckoutException('PayPal returned an amount that does not match your approval. Nothing was recorded as paid.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summarise(array $payload): array
    {
        return array_filter([
            'id' => $payload['id'] ?? null,
            'status' => $payload['status'] ?? null,
            'payer' => isset($payload['payer']['payer_id']) ? ['payer_id' => $payload['payer']['payer_id']] : null,
            'links' => $payload['links'] ?? null,
            'purchase_units' => $payload['purchase_units'] ?? null,
            'debug_id' => $payload['debug_id'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
