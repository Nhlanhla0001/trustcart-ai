<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Exceptions\PayPalException;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function result(Order $order): Response
    {
        $order->load(['approval.product', 'approval.shoppingSession', 'events']);

        return Inertia::render('checkout/result', [
            'order' => [
                'uuid' => $order->uuid,
                'status' => $order->status,
                'amount' => $order->amountLabel(),
                'amount_cents' => $order->amount_cents,
                'currency' => $order->currency,
                'paypal_order_id' => $order->paypal_order_id,
                'capture_id' => $order->capture_id,
                'payer_id' => $order->payer_id,
                'failure_reason' => $order->failure_reason,
                'environment' => $order->environment,
                'created_at' => $order->created_at->toIso8601String(),
            ],
            'approval' => [
                'uuid' => $order->approval->uuid,
                'status' => $order->approval->status,
                'total' => $order->approval->totalLabel(),
            ],
            'product' => $order->approval->product?->snapshot(),
            'sessionUuid' => $order->approval->shoppingSession->uuid,
            'events' => $order->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->type,
                'status' => $event->status,
                'provider_event_id' => $event->provider_event_id,
                'created_at' => $event->created_at->toIso8601String(),
            ])->values(),
            'paypal' => [
                'configured' => filled(config('trustcart.paypal.client_id'))
                    && filled(config('trustcart.paypal.client_secret')),
                'environment' => 'sandbox',
            ],
        ]);
    }

    public function handleReturn(Order $order, CheckoutService $checkout): RedirectResponse
    {
        try {
            $order = $checkout->complete($order);
        } catch (CheckoutException|PayPalException $e) {
            return redirect()
                ->route('checkout.result', $order)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('checkout.result', $order)
                ->with('error', 'The payment status could not be verified. Nothing is recorded as paid until PayPal confirms it.');
        }

        $message = $order->isCompleted()
            ? 'PayPal confirmed the capture. The order is recorded as paid in sandbox.'
            : 'PayPal has not completed this payment. Nothing is recorded as paid.';

        return redirect()->route('checkout.result', $order)->with(
            $order->isCompleted() ? 'success' : 'error',
            $message,
        );
    }

    public function cancel(Order $order, CheckoutService $checkout): RedirectResponse
    {
        try {
            $order = $checkout->cancel($order);
        } catch (CheckoutException $e) {
            return redirect()->route('checkout.result', $order)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('checkout.result', $order)
            ->with('info', 'Checkout cancelled. No payment was captured, and the approval is closed.');
    }
}
