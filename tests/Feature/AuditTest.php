<?php

namespace Tests\Feature;

use App\Services\DecisionService;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditTest extends TestCase
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

    public function test_a_browser_only_sees_its_own_records(): void
    {
        $session = app(DecisionService::class)->run(
            'Find wireless headphones under $100 with active noise cancellation and 20 hours of battery.',
        );

        $this->get(route('audit.show', $session))->assertNotFound();

        $this->withSession(['trustcart.sessions' => [$session->uuid]])
            ->get(route('audit.show', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit/show')
                ->where('session.uuid', $session->uuid)
                ->where('recommendation.verdict', 'recommended')
                ->has('auditEvents')
            );
    }

    public function test_the_audit_index_lists_tracked_decisions(): void
    {
        $session = app(DecisionService::class)->run(
            'Find wireless headphones under $100 with active noise cancellation and 20 hours of battery.',
        );

        $this->withSession(['trustcart.sessions' => [$session->uuid]])
            ->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit/index')
                ->has('sessions', 1)
                ->where('sessions.0.uuid', $session->uuid)
            );
    }

    public function test_audit_events_are_written_for_the_decision(): void
    {
        $session = app(DecisionService::class)->run(
            'Find wireless headphones under $100 with active noise cancellation and 20 hours of battery.',
        );

        $types = $session->auditEvents()->pluck('type')->all();

        $this->assertContains('intent.received', $types);
        $this->assertContains('rules.evaluated', $types);
        $this->assertContains('recommendation.created', $types);
    }
}
