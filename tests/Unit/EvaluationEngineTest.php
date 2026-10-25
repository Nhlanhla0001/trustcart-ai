<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\EvaluationEngine;
use App\Support\Requirements;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationEngineTest extends TestCase
{
    use RefreshDatabase;

    private EvaluationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductSeeder::class);
        $this->engine = new EvaluationEngine;
    }

    private function requirements(array $overrides = []): Requirements
    {
        return Requirements::fromArray([
            'category' => 'headphones',
            'budget_cents' => 10000,
            'currency' => 'USD',
            'required_features' => ['noise_cancellation', 'wireless'],
            'min_specs' => ['battery_hours' => 20],
            ...$overrides,
        ]);
    }

    private function evaluate(string $sku, ?Requirements $requirements = null): array
    {
        return $this->engine->evaluate(
            Product::query()->where('sku', $sku)->firstOrFail(),
            $requirements ?? $this->requirements(),
        );
    }

    public function test_the_reference_request_qualifies_exactly_one_product(): void
    {
        $result = $this->evaluate('TC-HP-001');

        $this->assertSame('qualified', $result['verdict']);
        $this->assertSame([], $result['failed']);
        $this->assertSame([], $result['unverified']);
        $this->assertGreaterThan(0, $result['score']);
    }

    public function test_a_cheaper_product_fails_a_mandatory_feature(): void
    {
        $result = $this->evaluate('TC-HP-002');

        $this->assertSame('rejected', $result['verdict']);
        $this->assertContains('feature:noise_cancellation', $result['failed']);
        $this->assertStringContainsString('without Active noise cancellation', $result['reason']);
    }

    public function test_a_product_over_budget_is_rejected_even_when_it_matches_specs(): void
    {
        $result = $this->evaluate('TC-HP-003');

        $this->assertSame('rejected', $result['verdict']);
        $this->assertContains('budget', $result['failed']);
        $this->assertContains('stock', $result['failed']);
    }

    public function test_insufficient_battery_is_a_failure_not_a_preference(): void
    {
        $result = $this->evaluate('TC-HP-004');

        $this->assertSame('rejected', $result['verdict']);
        $this->assertContains('spec:battery_hours', $result['failed']);
        $this->assertStringContainsString('below your 20 h minimum', $result['reason']);
    }

    public function test_missing_evidence_is_unverified_and_never_a_pass(): void
    {
        $result = $this->evaluate('TC-HP-005');

        $this->assertSame('unverified', $result['verdict']);
        $this->assertContains('feature:noise_cancellation', $result['unverified']);
        $this->assertContains('spec:battery_hours', $result['unverified']);
        $this->assertNotContains('feature:noise_cancellation', $result['failed']);

        $anc = collect($result['checks'])->firstWhere('key', 'feature:noise_cancellation');
        $this->assertSame('unverified', $anc['result']);
    }

    public function test_currency_mismatch_fails_the_budget_check(): void
    {
        $product = Product::query()->where('sku', 'TC-HP-001')->firstOrFail();
        $product->update(['currency' => 'EUR']);

        $result = $this->engine->evaluate($product->fresh(), $this->requirements());

        $this->assertSame('rejected', $result['verdict']);
        $this->assertContains('currency', $result['failed']);
        $this->assertContains('budget', $result['failed']);
    }

    public function test_out_of_stock_is_never_recommended(): void
    {
        $result = $this->evaluate('TC-HP-003');

        $this->assertContains('stock', $result['failed']);
    }

    public function test_a_missing_requirement_does_not_get_silently_dropped(): void
    {
        // Ask for a feature no product records; every candidate must fail or be unverified.
        $requirements = $this->requirements([
            'required_features' => ['noise_cancellation', 'thunderbolt'],
        ]);

        $result = $this->evaluate('TC-HP-001', $requirements);

        $this->assertSame('unverified', $result['verdict']);
        $this->assertContains('feature:thunderbolt', $result['unverified']);
    }
}
