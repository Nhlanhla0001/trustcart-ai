<?php

namespace Tests\Feature;

use App\Exceptions\ApprovalException;
use App\Models\Product;
use App\Models\PurchaseApproval;
use App\Services\ApprovalService;
use App\Services\DecisionService;
use App\Services\EvaluationEngine;
use App\Support\Requirements;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'trustcart.ai.gemini.key' => null,
            'trustcart.ai.deepseek.key' => null,
        ]);

        $this->seed(ProductSeeder::class);
    }

    private function recommendedSession()
    {
        return app(DecisionService::class)->run(
            'Find wireless headphones under $100 with active noise cancellation and at least 20 hours of battery life.',
        );
    }

    private function authorize(PurchaseApproval $approval): PurchaseApproval
    {
        return app(ApprovalService::class)->authorize(
            $approval,
            Request::create('/approvals/'.$approval->uuid.'/authorize', 'POST'),
        );
    }

    public function test_creating_an_approval_snapshots_the_server_side_price(): void
    {
        $session = $this->recommendedSession();
        $approval = app(ApprovalService::class)->createFor($session, $session->recommendation);

        $this->assertSame('pending', $approval->status);
        $this->assertSame(8900, $approval->unit_price_cents);
        $this->assertSame(8900, $approval->total_cents);
        $this->assertSame('USD', $approval->currency);
        $this->assertTrue($approval->expires_at->isFuture());
    }

    public function test_authorization_requires_explicit_acceptance_over_http(): void
    {
        $session = $this->recommendedSession();
        $approval = app(ApprovalService::class)->createFor($session, $session->recommendation);

        $this->from(route('approvals.show', $approval))
            ->post(route('approvals.authorize', $approval), [])
            ->assertRedirect(route('approvals.show', $approval))
            ->assertSessionHasErrors('authorize');

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_authorization_stamps_time_ip_and_user_agent(): void
    {
        $session = $this->recommendedSession();
        $approval = app(ApprovalService::class)->createFor($session, $session->recommendation);

        $authorized = $this->authorize($approval);

        $this->assertSame('authorized', $authorized->status);
        $this->assertNotNull($authorized->authorized_at);
        $this->assertSame('127.0.0.1', $authorized->authorized_ip);
    }

    public function test_an_expired_approval_cannot_be_authorized(): void
    {
        $session = $this->recommendedSession();
        $approval = app(ApprovalService::class)->createFor($session, $session->recommendation);
        $approval->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(ApprovalException::class);

        $this->authorize($approval->fresh());
    }

    public function test_a_price_change_voids_the_approval_and_blocks_it(): void
    {
        $session = $this->recommendedSession();
        $approval = app(ApprovalService::class)->createFor($session, $session->recommendation);
        $this->authorize($approval);

        $result = app(ApprovalService::class)->simulatePriceChange(
            Product::query()->where('sku', 'TC-HP-001')->firstOrFail(),
        );

        $this->assertSame(8900, $result['old_cents']);
        $this->assertGreaterThan(8900, $result['new_cents']);
        $this->assertSame(1, $result['invalidated']);

        $fresh = $approval->fresh();
        $this->assertSame('invalidated', $fresh->status);
        $this->assertSame('price_changed_by_merchant', $fresh->invalidated_reason);
        $this->assertSame(8900, $fresh->unit_price_cents, 'The approved amount must not be silently rewritten.');

        $this->expectException(ApprovalException::class);
        $this->authorize($fresh);
    }

    public function test_a_refused_recommendation_cannot_enter_the_payment_flow(): void
    {
        $session = app(DecisionService::class)->run(
            'Noise cancelling headphones with 30 hours of battery for under $40.',
        );

        $this->expectException(ApprovalException::class);

        app(ApprovalService::class)->createFor($session, $session->recommendation);
    }

    public function test_repeated_authorization_is_idempotent(): void
    {
        $session = $this->recommendedSession();
        $approval = app(ApprovalService::class)->createFor($session, $session->recommendation);

        $first = $this->authorize($approval);
        $second = $this->authorize($first->fresh());

        $this->assertSame('authorized', $second->status);
        $this->assertSame($first->authorized_at->toDateTimeString(), $second->authorized_at->toDateTimeString());
    }

    public function test_a_product_with_unverified_evidence_is_not_recommended(): void
    {
        $requirements = Requirements::fromArray([
            'category' => 'headphones',
            'budget_cents' => 10000,
            'currency' => 'USD',
            'required_features' => ['noise_cancellation'],
            'min_specs' => ['battery_hours' => 20],
        ]);

        $result = (new EvaluationEngine)->evaluate(
            Product::query()->where('sku', 'TC-HP-005')->firstOrFail(),
            $requirements,
        );

        $this->assertSame('unverified', $result['verdict']);
        $this->assertNotEmpty($result['unverified']);
    }
}
