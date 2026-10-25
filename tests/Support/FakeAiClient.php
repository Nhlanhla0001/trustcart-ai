<?php

namespace Tests\Support;

use App\Services\Ai\AiClient;

/**
 * Deterministic stand-in for the model. It records every prompt so tests can
 * assert what the model did — and did not — receive.
 */
class FakeAiClient extends AiClient
{
    /** @var list<string> */
    public array $prompts = [];

    /**
     * @param  array<string, mixed>|null  $extraction
     */
    public function __construct(
        private readonly ?array $extraction = null,
        private readonly ?string $narrative = 'Fake narrative from the model.',
        private readonly ?string $error = null,
    ) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function usedProvider(): ?string
    {
        return 'fake';
    }

    public function lastError(): ?string
    {
        return $this->error;
    }

    public function extractRequirements(string $intent): ?array
    {
        $this->prompts[] = $intent;

        return $this->extraction;
    }

    public function narrate(array $facts): ?string
    {
        $this->prompts[] = json_encode($facts, JSON_THROW_ON_ERROR);

        return $this->narrative;
    }
}
