<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Money;
use App\Support\Requirements;

/**
 * The authority on whether a product qualifies. Pure, deterministic, and
 * independent of the language model: every number comes from the catalog row
 * passed in. Missing evidence is "unverified", never a pass.
 */
class EvaluationEngine
{
    /**
     * @return array{
     *     product: array<string, mixed>,
     *     verdict: string,
     *     score: int,
     *     checks: list<array<string, string>>,
     *     failed: list<string>,
     *     unverified: list<string>,
     *     reason: string
     * }
     */
    public function evaluate(Product $product, Requirements $requirements): array
    {
        $checks = [
            $this->budgetCheck($product, $requirements),
            $this->stockCheck($product),
            $this->currencyCheck($product, $requirements),
        ];

        foreach ($requirements->requiredFeatures as $feature) {
            $checks[] = $this->featureCheck($product, $feature);
        }

        foreach ($requirements->minSpecs as $key => $minimum) {
            $checks[] = $this->specCheck($product, $key, (float) $minimum);
        }

        $failed = array_values(array_map(
            fn (array $check) => $check['key'],
            array_filter($checks, fn (array $check) => $check['result'] === 'fail'),
        ));

        $unverified = array_values(array_map(
            fn (array $check) => $check['key'],
            array_filter($checks, fn (array $check) => $check['result'] === 'unverified'),
        ));

        $verdict = match (true) {
            $failed !== [] => 'rejected',
            $unverified !== [] => 'unverified',
            default => 'qualified',
        };

        $reason = match ($verdict) {
            'rejected' => $this->firstDetail($checks, 'fail') ?? 'A mandatory requirement is not met.',
            'unverified' => $this->firstDetail($checks, 'unverified') ?? 'A required specification is missing from the catalog.',
            default => 'Every mandatory requirement is met by recorded catalog data.',
        };

        return [
            'product' => $product->snapshot(),
            'verdict' => $verdict,
            'score' => $this->score($product, $requirements, $unverified),
            'checks' => $checks,
            'failed' => $failed,
            'unverified' => $unverified,
            'reason' => $reason,
        ];
    }

    /**
     * @param  list<array<string, string>>  $checks
     */
    private function firstDetail(array $checks, string $result): ?string
    {
        foreach ($checks as $check) {
            if ($check['result'] === $result) {
                return $check['detail'];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $unverified
     */
    private function score(Product $product, Requirements $requirements, array $unverified): int
    {
        $score = 68.0;

        if ($product->merchant_verified) {
            $score += 5;
        }

        $score += min(4, (int) floor($product->stock / 4));

        if ($requirements->budgetCents !== null && $requirements->budgetCents > 0) {
            $headroom = 1 - ($product->price_cents / $requirements->budgetCents);
            $score += max(0, min(1, $headroom)) * 12;
        }

        foreach ($requirements->minSpecs as $key => $minimum) {
            $actual = $product->spec($key);
            if (is_numeric($actual) && $minimum > 0) {
                $ratio = ((float) $actual / $minimum) - 1;
                $score += max(0, min(1, $ratio)) * 4;
            }
        }

        $score -= 12 * count($unverified);

        return (int) max(0, min(100, round($score)));
    }

    /**
     * @return array<string, string>
     */
    private function budgetCheck(Product $product, Requirements $requirements): array
    {
        if ($requirements->budgetCents === null) {
            return [
                'key' => 'budget',
                'label' => 'Budget',
                'requirement' => 'No ceiling set',
                'evidence' => $product->price(),
                'result' => 'pass',
                'detail' => 'No budget limit was given, so price is informational only.',
            ];
        }

        $budget = Money::format($requirements->budgetCents, $requirements->currency);
        $within = $product->currency === $requirements->currency
            && $product->price_cents <= $requirements->budgetCents;

        if ($within) {
            $difference = Money::format($requirements->budgetCents - $product->price_cents, $requirements->currency);
            $detail = $product->currency === $requirements->currency
                ? "{$difference} under your {$budget} limit."
                : 'Price is within budget once converted.';
        } else {
            $difference = Money::format($product->price_cents - $requirements->budgetCents, $requirements->currency);
            $detail = "{$difference} over your {$budget} limit.";
        }

        return [
            'key' => 'budget',
            'label' => 'Budget',
            'requirement' => '≤ '.$budget,
            'evidence' => $product->price(),
            'result' => $within ? 'pass' : 'fail',
            'detail' => $product->currency === $requirements->currency
                ? $detail
                : "Listed in {$product->currency}, your budget is in {$requirements->currency}.",
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stockCheck(Product $product): array
    {
        $available = $product->stock > 0;

        return [
            'key' => 'stock',
            'label' => 'Availability',
            'requirement' => 'In stock',
            'evidence' => $available ? $product->stock.' units' : 'Out of stock',
            'result' => $available ? 'pass' : 'fail',
            'detail' => $available
                ? "{$product->stock} units recorded as available."
                : 'Out of stock, so it cannot be purchased today.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function currencyCheck(Product $product, Requirements $requirements): array
    {
        $matches = $product->currency === $requirements->currency;

        return [
            'key' => 'currency',
            'label' => 'Currency',
            'requirement' => $requirements->currency,
            'evidence' => $product->currency,
            'result' => $matches ? 'pass' : 'fail',
            'detail' => $matches
                ? "Priced in {$product->currency}, matching your budget."
                : "Priced in {$product->currency}, but your budget is in {$requirements->currency}.",
        ];
    }

    /**
     * @return array<string, string>
     */
    private function featureCheck(Product $product, string $feature): array
    {
        $label = Requirements::FEATURES[$feature] ?? $feature;
        $state = $product->hasFeature($feature);

        return [
            'key' => 'feature:'.$feature,
            'label' => $label,
            'requirement' => 'Required',
            'evidence' => match ($state) {
                true => 'Recorded',
                false => 'Absent',
                default => 'No data',
            },
            'result' => match ($state) {
                true => 'pass',
                false => 'fail',
                default => 'unverified',
            },
            'detail' => match ($state) {
                true => "The catalog records {$label} for this model.",
                false => "The catalog records this model without {$label}.",
                default => "{$label} is not recorded in the catalog, so it cannot be verified.",
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function specCheck(Product $product, string $key, float $minimum): array
    {
        $meta = Requirements::MIN_SPECS[$key] ?? ['label' => $key, 'unit' => ''];
        $actual = $product->spec($key);
        $minimumLabel = Requirements::withUnit($this->number($minimum), $meta['unit']);
        $requirement = '≥ '.$minimumLabel;

        if (! is_numeric($actual)) {
            return [
                'key' => 'spec:'.$key,
                'label' => $meta['label'],
                'requirement' => $requirement,
                'evidence' => 'No data',
                'result' => 'unverified',
                'detail' => "{$meta['label']} is not recorded in the catalog; the requirement cannot be confirmed.",
            ];
        }

        $actual = (float) $actual;
        $pass = $actual >= $minimum;
        $evidence = Requirements::withUnit($this->number($actual), $meta['unit']);

        return [
            'key' => 'spec:'.$key,
            'label' => $meta['label'],
            'requirement' => $requirement,
            'evidence' => $evidence,
            'result' => $pass ? 'pass' : 'fail',
            'detail' => $pass
                ? "{$evidence} recorded, meeting your {$minimumLabel} minimum."
                : "{$evidence} recorded, below your {$minimumLabel} minimum.",
        ];
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
