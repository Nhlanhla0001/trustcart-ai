<?php

namespace Tests\Feature;

use App\Services\Ai\AiClient;
use Laravel\Ai\AiManager;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\StructuredAnonymousAgent;
use Tests\TestCase;

class AiSdkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AiManager::class)->flushState();

        config([
            'trustcart.ai.provider' => 'gemini',
            'trustcart.ai.fallback' => 'deepseek',
            'trustcart.ai.gemini.key' => 'test-gemini-key',
            'trustcart.ai.deepseek.key' => 'test-deepseek-key',
            'ai.providers.gemini.key' => 'test-gemini-key',
            'ai.providers.deepseek.key' => 'test-deepseek-key',
        ]);
    }

    protected function tearDown(): void
    {
        app(AiManager::class)->flushState();

        parent::tearDown();
    }

    public function test_requirement_parsing_uses_the_laravel_ai_sdk_structured_agent(): void
    {
        StructuredAnonymousAgent::fake([[
            'category' => 'headphones',
            'budget_cents' => 10000,
            'currency' => 'USD',
            'required_features' => ['noise_cancellation', 'wireless'],
            'min_specs' => ['battery_hours' => 20],
            'preferences' => [],
            'summary' => 'Wireless ANC headphones under $100.',
        ]]);

        $client = new AiClient;
        $result = $client->extractRequirements(
            'Find wireless headphones under $100 with active noise cancellation and 20 hours of battery.',
        );

        $this->assertSame('headphones', $result['category']);
        $this->assertSame(10000, $result['budget_cents']);
        $this->assertSame(['noise_cancellation', 'wireless'], $result['required_features']);
        $this->assertSame('gemini', $client->usedProvider());
        $this->assertNull($client->lastError());

        StructuredAnonymousAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, '<request>'),
        );
    }

    public function test_narrative_uses_the_laravel_ai_sdk_agent(): void
    {
        AnonymousAgent::fake(['The Auralis NC-40 meets every stated requirement. Nothing has been charged.']);

        $client = new AiClient;
        $text = $client->narrate([
            'verdict' => 'recommended',
            'selected' => ['name' => 'Auralis NC-40', 'price' => '$89.00'],
        ]);

        $this->assertSame(
            'The Auralis NC-40 meets every stated requirement. Nothing has been charged.',
            $text,
        );
        $this->assertSame('gemini', $client->usedProvider());

        AnonymousAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, '<facts>'),
        );
    }

    public function test_provider_failure_falls_back_to_the_next_provider(): void
    {
        $calls = 0;

        StructuredAnonymousAgent::fake(function () use (&$calls) {
            if ($calls++ === 0) {
                throw new \RuntimeException('Provider overloaded');
            }

            return [
                'category' => 'headphones',
                'budget_cents' => 10000,
                'currency' => 'USD',
                'required_features' => ['noise_cancellation'],
                'min_specs' => ['battery_hours' => 20],
                'preferences' => [],
                'summary' => 'Wireless ANC headphones under $100.',
            ];
        });

        $client = new AiClient;
        $result = $client->extractRequirements('Wireless ANC headphones under $100.');

        $this->assertNotNull($result);
        $this->assertSame('deepseek', $client->usedProvider());
        $this->assertSame('noise_cancellation', $result['required_features'][0]);
    }
}
