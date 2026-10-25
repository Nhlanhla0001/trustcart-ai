<?php

namespace Tests\Feature;

use App\Exceptions\ApprovalException;
use App\Exceptions\CheckoutException;
use App\Exceptions\PayPalNotConfigured;
use App\Models\Order;
use App\Models\Product;
use App\Services\ApprovalService;
use App\Services\CheckoutService;
use App\Services\DecisionService;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'trustcart.ai.gemini.key' => null,
            'trustcart.ai.deepseek.key' => null,
            'trustcart.paypal.client_id' => 'sandbox-client-id',
            'trustcart.paypal.client_secret' => 'sandbox-secret',
            'trustcart.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
        ]);

        Cache::flush();
        $this->seed(ProductSeeder::class);
    }

    private function fakePayPal(array $captureOverrides = []): void
    {
        $created = [
            'id' => 'PAY-ORDER-1',
            'status' => 'CREATED',
            'links' => [
                ['href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAY-ORDER-1', 'rel' => 'approve', 'method' => 'GET'],
            ],
        ];

        $approved = [
            'id' => 'PAY-ORDER-1',
            'status' => 'APPROVED',
            'purchase_units' => [[
                'amount' => ['currency_code' => 'USD', 'value' => '89.00'],
            ]],
        ];

        $capture = array_replace_recursive([
            'id' => 'PAY-ORDER-1',
            'status' => 'COMPLETED',
            'payer' => ['payer_id' => 'PAYER-123'],
            'purchase_units' => [[
                'amount' => ['currency_code' => 'USD', 'value' => '89.00'],
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-123',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '89.00'],
                ]]],
            ]],
        ], $captureOverrides);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAY-ORDER-1/capture' => Http::response($capture, 201),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAY-ORDER-1' => Http::response($approved, 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response($created, 201),
        ]);
    }

    private function approvedPurchase()
    {
        $session = app(DecisionService::class)->run(
            'Find wireless headphones under $100 with active noise cancellation and at least 20 hours of battery life.',
        );

        $approval = app(ApprovalService::class)->createFor($session, $session->recommendation);

        return app(ApprovalService::class)->authorize(
            $approval,
            Request::create('/approvals/'.$approval->uuid.'/authorize', 'POST'),
        );
    }

    private function captureCalls(): int
    {
        return collect(Http::recorded())
            ->filter(fn (array $pair) => str_contains($pair[0]->url(), '/capture'))
            ->count();
    }

    public function test_begin_creates_a_paypal_order_from_the_stored_approval(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();

        $order = app(CheckoutService::class)->begin($approval);

        $this->assertSame('PAY-ORDER-1', $order->paypal_order_id);
        $this->assertSame('created', $order->status);
        $this->assertSame(8900, $order->amount_cents);
        $this->assertSame('sandbox', $order->environment);
        $this->assertSame(
            'https://www.sandbox.paypal.com/checkoutnow?token=PAY-ORDER-1',
            app(CheckoutService::class)->approveUrl($order),
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            && $request['purchase_units'][0]['amount']['value'] === '89.00'
            && $request['intent'] === 'CAPTURE');
    }

    public function test_begin_is_idempotent_for_the_same_approval(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();

        $first = app(CheckoutService::class)->begin($approval);
        $second = app(CheckoutService::class)->begin($approval->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, collect(Http::recorded())
            ->filter(fn (array $pair) => $pair[0]->method() === 'POST'
                && $pair[0]->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders')
            ->count());
    }

    public function test_completion_records_the_capture_and_consumes_the_approval(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        $completed = app(CheckoutService::class)->complete($order);

        $this->assertSame('completed', $completed->status);
        $this->assertSame('CAPTURE-123', $completed->capture_id);
        $this->assertSame('PAYER-123', $completed->payer_id);
        $this->assertSame('consumed', $approval->fresh()->status);
        $this->assertSame(1, $this->captureCalls());
        $this->assertSame(1, $completed->events()->where('type', 'capture.completed')->count());
    }

    public function test_a_duplicate_return_never_captures_twice(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        $first = app(CheckoutService::class)->complete($order);
        $second = app(CheckoutService::class)->complete($first);

        $this->assertSame('completed', $second->status);
        $this->assertSame(1, $this->captureCalls());
        $this->assertSame(1, Order::query()->where('status', 'completed')->count());
    }

    public function test_cancel_closes_the_order_without_attempting_capture(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        $cancelled = app(CheckoutService::class)->cancel($order);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('cancelled', $approval->fresh()->status);
        $this->assertSame(0, $this->captureCalls());
        $this->assertSame(0, Order::query()->where('status', 'completed')->count());
    }

    public function test_a_mismatched_capture_amount_is_rejected_and_not_marked_paid(): void
    {
        $this->fakePayPal([
            'purchase_units' => [[
                'amount' => ['currency_code' => 'USD', 'value' => '1.00'],
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-EVIL',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '1.00'],
                ]]],
            ]],
        ]);

        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        try {
            app(CheckoutService::class)->complete($order);
            $this->fail('A mismatched amount must not complete.');
        } catch (CheckoutException) {
            // expected
        }

        $fresh = $order->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertNull($fresh->capture_id);
        $this->assertSame(0, Order::query()->where('status', 'completed')->count());
    }

    public function test_missing_credentials_never_produce_a_fake_success(): void
    {
        config([
            'trustcart.paypal.client_id' => null,
            'trustcart.paypal.client_secret' => null,
        ]);

        $approval = $this->approvedPurchase();

        $this->expectException(PayPalNotConfigured::class);

        app(CheckoutService::class)->begin($approval);
    }

    public function test_an_expired_authorization_blocks_capture(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        $this->travel(30)->minutes();

        try {
            app(CheckoutService::class)->complete($order->fresh());
            $this->fail('An expired authorization must not capture.');
        } catch (CheckoutException) {
            // expected
        }

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(0, $this->captureCalls());
    }

    public function test_the_return_route_completes_the_payment_and_redirects_to_the_result(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        $this->get(route('checkout.return', $order))
            ->assertRedirect(route('checkout.result', $order));

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('consumed', $approval->fresh()->status);
    }

    public function test_an_already_completed_provider_order_is_recorded_without_recapturing(): void
    {
        $completed = [
            'id' => 'PAY-ORDER-1',
            'status' => 'COMPLETED',
            'payer' => ['payer_id' => 'PAYER-123'],
            'purchase_units' => [[
                'amount' => ['currency_code' => 'USD', 'value' => '89.00'],
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-EXISTING',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '89.00'],
                ]]],
            ]],
        ];

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAY-ORDER-1' => Http::response($completed, 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAY-ORDER-1',
                'status' => 'CREATED',
                'links' => [['href' => 'https://sandbox/approve', 'rel' => 'approve']],
            ], 201),
        ]);

        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        $completedOrder = app(CheckoutService::class)->complete($order);

        $this->assertSame('completed', $completedOrder->status);
        $this->assertSame('CAPTURE-EXISTING', $completedOrder->capture_id);
        $this->assertSame(0, $this->captureCalls());
    }

    public function test_a_non_paypal_approval_link_is_rejected(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAY-ORDER-1',
                'status' => 'CREATED',
                'links' => [
                    ['href' => 'https://evil.example/steal', 'rel' => 'approve'],
                ],
            ], 201),
        ]);

        $approval = $this->approvedPurchase();
        $order = app(CheckoutService::class)->begin($approval);

        $this->assertNull(app(CheckoutService::class)->approveUrl($order));
    }

    public function test_the_price_is_rechecked_at_checkout_and_voids_the_approval(): void
    {
        $this->fakePayPal();
        $approval = $this->approvedPurchase();

        Product::query()->where('sku', 'TC-HP-001')->firstOrFail()->update([
            'price_cents' => 12000,
        ]);

        try {
            app(CheckoutService::class)->begin($approval->fresh());
            $this->fail('A changed price must block checkout.');
        } catch (ApprovalException) {
            // expected
        }

        $this->assertSame('invalidated', $approval->fresh()->status);
        $this->assertSame('price_changed_at_checkout', $approval->fresh()->invalidated_reason);
    }
}
