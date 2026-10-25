<?php

namespace App\Http\Controllers;

use App\Exceptions\ApprovalException;
use App\Exceptions\CheckoutException;
use App\Exceptions\PayPalException;
use App\Models\PurchaseApproval;
use App\Services\ApprovalService;
use App\Services\CheckoutService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApprovalController extends Controller
{
    public function show(PurchaseApproval $approval, CheckoutService $checkout): Response
    {
        $approval->load(['product', 'shoppingSession.recommendation', 'orders' => fn ($query) => $query->latest()]);
        $product = $approval->product;
        $latestOrder = $approval->orders->first();

        $priceChanged = $product->price_cents !== $approval->unit_price_cents
            || $product->currency !== $approval->currency;

        return Inertia::render('approvals/show', [
            'approval' => [
                'uuid' => $approval->uuid,
                'status' => $approval->status,
                'quantity' => $approval->quantity,
                'unit_price' => $approval->totalLabel(),
                'unit_price_cents' => $approval->unit_price_cents,
                'total' => $approval->totalLabel(),
                'total_cents' => $approval->total_cents,
                'currency' => $approval->currency,
                'expires_at' => $approval->expires_at->toIso8601String(),
                'seconds_remaining' => max(0, (int) round(now()->diffInSeconds($approval->expires_at, false))),
                'authorized_at' => $approval->authorized_at?->toIso8601String(),
                'invalidated_reason' => $approval->invalidated_reason,
            ],
            'product' => $product->snapshot(),
            'sessionUuid' => $approval->shoppingSession->uuid,
            'price_changed' => $priceChanged,
            'catalog_price' => $product->price(),
            'stock' => $product->stock,
            'paypal' => [
                'configured' => $checkout->paypalConfigured(),
                'environment' => 'sandbox',
            ],
            'order' => $latestOrder ? [
                'uuid' => $latestOrder->uuid,
                'status' => $latestOrder->status,
                'amount' => $latestOrder->amountLabel(),
            ] : null,
            'demo_mode' => (bool) config('trustcart.demo_mode'),
        ]);
    }

    public function simulatePriceChange(PurchaseApproval $approval, ApprovalService $approvals): RedirectResponse
    {
        $product = $approval->product;

        if ($product === null) {
            return back()->with('error', 'There is no product on this approval to reprice.');
        }

        $result = $approvals->simulatePriceChange($product);

        return redirect()
            ->route('approvals.show', $approval)
            ->with('info', sprintf(
                'Merchant sync changed %s from %s to %s. %d approval(s) invalidated; this authorization is now void.',
                $product->name,
                Money::format($result['old_cents'], $product->currency),
                Money::format($result['new_cents'], $product->currency),
                $result['invalidated'],
            ));
    }

    public function authorize(
        Request $request,
        PurchaseApproval $approval,
        ApprovalService $approvals,
        CheckoutService $checkout,
    ): RedirectResponse|SymfonyResponse {
        $request->validate([
            'authorize' => ['accepted'],
        ], [
            'authorize.accepted' => 'Tick the authorization box before continuing to PayPal.',
        ]);

        if (! $checkout->paypalConfigured()) {
            return redirect()
                ->route('approvals.show', $approval)
                ->with('error', 'PayPal sandbox credentials are not configured. The approval was not started, and nothing was charged.');
        }

        try {
            $approval = $approvals->authorize($approval, $request);
            $order = $checkout->begin($approval);
            $approveUrl = $checkout->approveUrl($order);
        } catch (ApprovalException|CheckoutException $e) {
            return redirect()
                ->route('approvals.show', $approval)
                ->with('error', $e->getMessage());
        } catch (PayPalException $e) {
            return redirect()
                ->route('approvals.show', $approval)
                ->with('error', $e->getMessage().($e->debugId ? ' (PayPal debug ID: '.$e->debugId.')' : ''));
        }

        if ($approveUrl === null) {
            return redirect()
                ->route('checkout.result', $order)
                ->with('error', 'PayPal created the order but did not return an approval link. Nothing was charged.');
        }

        return Inertia::location($approveUrl);
    }
}
