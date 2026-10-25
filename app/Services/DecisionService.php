<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShoppingSession;
use App\Services\Ai\AiClient;
use App\Support\Money;
use App\Support\Requirements;
use Illuminate\Support\Str;

/**
 * Orchestrates one decision: interpret, retrieve, evaluate, explain, persist.
 */
class DecisionService
{
    public const PROMPT_VERSION = '2026-10-25.1';

    public function __construct(
        private readonly RequirementExtractor $extractor,
        private readonly EvaluationEngine $engine,
        private readonly AiClient $ai,
        private readonly AuditLogger $audit,
    ) {}

    public function run(string $intent, ?string $fallbackCategory = null, ?int $fallbackBudgetCents = null): ShoppingSession
    {
        $started = microtime(true);

        $extraction = $this->extractor->extract($intent, $fallbackCategory, $fallbackBudgetCents);
        $requirements = $extraction['requirements'];

        $session = ShoppingSession::create([
            'raw_intent' => $intent,
            'requirements' => $requirements->toArray(),
            'status' => 'evaluated',
            'ai_provider' => $extraction['provider'],
            'ai_model' => $extraction['model'],
        ]);

        $this->audit->log('intent.received', $session, [
            'intent' => $intent,
            'requirements' => $requirements->toArray(),
            'extraction_source' => $extraction['source'],
            'extraction_error' => $extraction['error'],
        ]);

        $products = Product::query()
            ->active()
            ->where('category', $requirements->category)
            ->get();

        $evaluations = array_values($products
            ->map(fn (Product $product) => $this->engine->evaluate($product, $requirements))
            ->all());

        $evaluations = $this->rank($evaluations);

        $chosen = null;
        foreach ($evaluations as $evaluation) {
            if ($evaluation['verdict'] === 'qualified') {
                $chosen = $evaluation;
                break;
            }
        }

        $verdict = $chosen === null ? 'refused' : 'recommended';
        $checks = $chosen['checks'] ?? [];

        $narrative = $this->narrate($requirements, $chosen, $evaluations, $verdict);
        $duration = (int) round((microtime(true) - $started) * 1000);

        $recommendation = $session->recommendation()->create([
            'product_id' => $chosen['product']['id'] ?? null,
            'verdict' => $verdict,
            'score' => $chosen['score'] ?? 0,
            'narrative' => $narrative['text'],
            'narrative_source' => $narrative['source'],
            'checks' => $checks,
            'candidates' => $evaluations,
            'engine' => [
                'rules_version' => self::PROMPT_VERSION,
                'prompt_version' => self::PROMPT_VERSION,
                'provider' => $extraction['provider'],
                'model' => $extraction['model'],
                'extraction_source' => $extraction['source'],
                'extraction_error' => $extraction['error'],
                'narrative_source' => $narrative['source'],
                'narrative_provider' => $narrative['provider'],
                'narrative_error' => $narrative['error'],
                'duration_ms' => $duration,
                'catalog_size' => $products->count(),
                'evaluated_at' => now()->toIso8601String(),
            ],
        ]);

        $this->audit->log('rules.evaluated', $session, [
            'verdict' => $verdict,
            'catalog_size' => $products->count(),
            'checks' => $checks,
            'candidates' => array_map(fn (array $evaluation) => [
                'sku' => $evaluation['product']['sku'],
                'verdict' => $evaluation['verdict'],
                'score' => $evaluation['score'],
                'failed' => $evaluation['failed'],
                'unverified' => $evaluation['unverified'],
            ], $evaluations),
        ], $recommendation);

        $this->audit->log('recommendation.created', $session, [
            'verdict' => $verdict,
            'product' => $chosen['product']['sku'] ?? null,
            'score' => $chosen['score'] ?? null,
            'narrative_source' => $narrative['source'],
        ], $recommendation);

        return $session->load('recommendation.product');
    }

    /**
     * Qualified first (score desc, then price asc), then unverified, then
     * rejected. This ordering is evidence for the refusal, not a ranking of
     * things the buyer can actually buy.
     *
     * @param  array<int, array<string, mixed>>  $evaluations
     * @return array<int, array<string, mixed>>
     */
    private function rank(array $evaluations): array
    {
        $rank = ['qualified' => 0, 'unverified' => 1, 'rejected' => 2];

        usort($evaluations, function (array $a, array $b) use ($rank) {
            $verdict = $rank[$a['verdict']] <=> $rank[$b['verdict']];

            if ($verdict !== 0) {
                return $verdict;
            }

            $score = $b['score'] <=> $a['score'];
            if ($score !== 0) {
                return $score;
            }

            return $a['product']['price_cents'] <=> $b['product']['price_cents'];
        });

        return $evaluations;
    }

    /**
     * @param  array<string, mixed>|null  $chosen
     * @param  array<int, array<string, mixed>>  $evaluations
     * @return array{text: string, source: string, error: string|null, provider: string|null}
     */
    private function narrate(Requirements $requirements, ?array $chosen, array $evaluations, string $verdict): array
    {
        $facts = [
            'requirements' => [
                'category' => $requirements->category,
                'budget' => $requirements->budgetLabel(),
                'currency' => $requirements->currency,
                'required_features' => array_map(
                    fn (array $feature) => $feature['label'],
                    $requirements->featureLabels(),
                ),
                'minimums' => array_map(
                    fn (array $spec) => $spec['label'].' ≥ '.$spec['value'],
                    $requirements->minSpecLabels(),
                ),
            ],
            'verdict' => $verdict,
            'closest' => $chosen === null && $evaluations !== [] ? [
                'name' => $evaluations[0]['product']['name'],
                'price' => $evaluations[0]['product']['price'],
                'reason' => $evaluations[0]['reason'],
            ] : null,
            'selected' => $chosen === null ? null : [
                'name' => $chosen['product']['name'],
                'price' => $chosen['product']['price'],
                'merchant' => $chosen['product']['merchant'],
                'checks' => $chosen['checks'],
            ],
            'rejected' => array_values(array_map(fn (array $evaluation) => [
                'name' => $evaluation['product']['name'],
                'price' => $evaluation['product']['price'],
                'verdict' => $evaluation['verdict'],
                'reason' => $evaluation['reason'],
            ], array_filter($evaluations, fn (array $evaluation) => $evaluation['verdict'] !== 'qualified'))),
        ];

        if ($this->ai->isConfigured()) {
            $text = $this->ai->narrate($facts);
            if ($text !== null) {
                return ['text' => $text, 'source' => 'ai', 'error' => null, 'provider' => $this->ai->usedProvider()];
            }
        }

        return [
            'text' => $this->fallbackNarrative($requirements, $chosen, $evaluations, $verdict),
            'source' => 'fallback',
            'error' => $this->ai->lastError(),
            'provider' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $chosen
     * @param  array<int, array<string, mixed>>  $evaluations
     */
    public function fallbackNarrative(Requirements $requirements, ?array $chosen, array $evaluations, string $verdict): string
    {
        if ($verdict === 'recommended' && $chosen !== null) {
            $satisfied = [];
            foreach ((array) $chosen['checks'] as $check) {
                if (($check['result'] ?? null) === 'pass' && filled($check['label'] ?? null)) {
                    $satisfied[] = (string) $check['label'];
                }
            }
            $satisfied = array_values(array_unique($satisfied));

            $sentences = [];
            $sentences[] = sprintf(
                'The %s at %s is the only product here that clears every mandatory requirement: %s.',
                $chosen['product']['name'],
                $chosen['product']['price'],
                Str::lower(implode(', ', $satisfied)),
            );

            if ($requirements->budgetCents !== null) {
                $remaining = $requirements->budgetCents - $chosen['product']['price_cents'];
                if ($remaining > 0) {
                    $sentences[] = sprintf(
                        'It leaves %s of your %s limit unspent.',
                        Money::format($remaining, $requirements->currency),
                        $requirements->budgetLabel(),
                    );
                }
            }

            $cheaper = null;
            foreach ($evaluations as $evaluation) {
                if ($evaluation['verdict'] !== 'rejected') {
                    continue;
                }

                if ($evaluation['product']['price_cents'] >= $chosen['product']['price_cents']) {
                    continue;
                }

                if ($cheaper === null || $evaluation['product']['price_cents'] < $cheaper['product']['price_cents']) {
                    $cheaper = $evaluation;
                }
            }

            if ($cheaper !== null) {
                $sentences[] = sprintf(
                    'The cheaper %s at %s was refused: %s',
                    $cheaper['product']['name'],
                    $cheaper['product']['price'],
                    Str::lcfirst($cheaper['reason']),
                );
            }

            $sentences[] = 'Nothing has been charged. You still have to authorize the payment yourself.';

            return implode(' ', $sentences);
        }

        $closest = $evaluations[0] ?? null;
        $sentences = [sprintf(
            'No %s in this catalog meets all of your mandatory requirements, so TrustCart is refusing to recommend a purchase.',
            Str::lower($requirements->categoryLabel()),
        )];

        if ($closest !== null) {
            $sentences[] = sprintf(
                'The closest option, %s at %s, fails on: %s',
                $closest['product']['name'],
                $closest['product']['price'],
                Str::lower($closest['reason']),
            );
        }

        $sentences[] = $this->unlockSuggestion($evaluations);

        return implode(' ', array_filter($sentences));
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     */
    private function unlockSuggestion(array $evaluations): string
    {
        $failures = [];
        foreach ($evaluations as $evaluation) {
            foreach ($evaluation['failed'] as $failure) {
                $failures[$failure] = true;
            }
        }

        if (isset($failures['budget'])) {
            $nearest = null;
            foreach ($evaluations as $evaluation) {
                if ($evaluation['verdict'] !== 'unverified') {
                    continue;
                }

                if ($nearest === null || $evaluation['product']['price_cents'] < $nearest['product']['price_cents']) {
                    $nearest = $evaluation;
                }
            }

            if ($nearest !== null) {
                return sprintf(
                    'The nearest match is %s at %s; raising the budget to that figure would let it qualify.',
                    $nearest['product']['name'],
                    $nearest['product']['price'],
                );
            }

            return 'Raising the budget would admit at least one of the rejected options.';
        }

        foreach (array_keys($failures) as $key) {
            if (str_starts_with((string) $key, 'feature:')) {
                return 'Dropping one required feature, or accepting a model with recorded evidence for it, would unlock a recommendation.';
            }
        }

        foreach (array_keys($failures) as $key) {
            if (str_starts_with((string) $key, 'spec:')) {
                return 'Lowering one of the minimum specifications would unlock a recommendation.';
            }
        }

        if (isset($failures['stock'])) {
            return 'Waiting for stock to return would unlock a recommendation.';
        }

        return 'Relaxing one mandatory requirement would unlock a recommendation.';
    }
}
