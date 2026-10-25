<?php

namespace App\Services;

use App\Services\Ai\AiClient;
use App\Support\Requirements;
use Illuminate\Support\Str;

/**
 * Turns natural language into Requirements. The model is asked first; if it is
 * missing, slow, or returns anything that does not survive validation, a
 * deterministic parser produces the same shape. The rest of the system cannot
 * tell the difference, which is the point.
 */
class RequirementExtractor
{
    public function __construct(private readonly AiClient $ai) {}

    /**
     * @return array{requirements: Requirements, source: string, provider: string|null, model: string|null, error: string|null}
     */
    public function extract(string $intent, ?string $fallbackCategory = null, ?int $fallbackBudgetCents = null): array
    {
        $error = null;
        $source = 'fallback';

        $parsed = null;
        if ($this->ai->isConfigured()) {
            $candidate = $this->ai->extractRequirements($intent);
            $parsed = $candidate ? $this->normalise($candidate) : null;
            $error = $parsed === null ? ($this->ai->lastError() ?? 'invalid_requirement_payload') : null;
        } else {
            $error = $this->ai->lastError() ?? 'provider_not_configured';
        }

        if ($parsed === null) {
            $parsed = $this->fallback($intent);
        } else {
            $source = 'ai';
        }

        if (! in_array($parsed['category'] ?? null, Requirements::CATEGORIES, true)) {
            $parsed['category'] = $fallbackCategory ?? 'headphones';
        }

        if (empty($parsed['budget_cents']) && $fallbackBudgetCents !== null) {
            $parsed['budget_cents'] = $fallbackBudgetCents;
        }

        if (blank($parsed['summary'] ?? null)) {
            $parsed['summary'] = $this->summarise($parsed);
        }

        return [
            'requirements' => Requirements::fromArray($parsed),
            'source' => $source,
            'provider' => $source === 'ai' ? $this->ai->usedProvider() : null,
            'model' => $source === 'ai' ? $this->ai->model() : null,
            'error' => $error,
        ];
    }

    /**
     * Accept only keys and types we control, and only values that exist in the
     * catalog vocabulary. Anything else is discarded rather than repaired.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalise(array $raw): array
    {
        $category = is_string($raw['category'] ?? null) ? strtolower(trim($raw['category'])) : '';
        $category = rtrim($category, 's');
        $category = match ($category) {
            'headphone', 'earphone', 'audio' => 'headphones',
            'laptop', 'notebook' => 'laptops',
            'monitor', 'display' => 'monitors',
            'keyboard' => 'keyboards',
            'accessory', 'mouse', 'accessories' => 'accessories',
            'unknown' => null,
            default => $category,
        };

        $features = [];
        foreach ((array) ($raw['required_features'] ?? []) as $feature) {
            if (! is_string($feature)) {
                continue;
            }

            $canonical = Requirements::canonicalFeature($feature);
            if ($canonical !== null) {
                $features[] = $canonical;
            }
        }

        $minSpecs = [];
        foreach ((array) ($raw['min_specs'] ?? []) as $key => $value) {
            if (is_string($key) && array_key_exists($key, Requirements::MIN_SPECS) && is_numeric($value) && $value >= 0) {
                $minSpecs[$key] = (float) $value;
            }
        }

        $budget = $raw['budget_cents'] ?? null;
        if (is_string($budget) && is_numeric($budget)) {
            $budget = (int) $budget;
        }
        if (! is_int($budget) || $budget <= 0 || $budget > 100_000_000) {
            $budget = null;
        }

        $currency = strtoupper((string) ($raw['currency'] ?? 'USD'));
        if (! in_array($currency, ['USD', 'EUR', 'GBP', 'ZAR'], true)) {
            $currency = 'USD';
        }

        if ($category === '' || ! in_array($category, Requirements::CATEGORIES, true)) {
            $category = null;
        }

        return [
            'category' => $category,
            'budget_cents' => $budget,
            'currency' => $currency,
            'required_features' => array_values(array_unique(array_slice($features, 0, 6))),
            'min_specs' => array_slice($minSpecs, 0, 6, true),
            'preferences' => array_values(array_filter(
                array_slice((array) ($raw['preferences'] ?? []), 0, 5),
                'is_string',
            )),
            'summary' => is_string($raw['summary'] ?? null) ? mb_substr(trim($raw['summary']), 0, 240) : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fallback(string $intent): array
    {
        $text = mb_strtolower($intent);
        $category = $this->detectCategory($text);
        $features = [];

        $keywords = [
            'noise_cancellation' => ['noise cancel', 'noise-cancel', 'noise cancelling', 'anc', 'active noise'],
            'wireless' => ['wireless', 'cordless'],
            'bluetooth' => ['bluetooth'],
            'usb_c' => ['usb-c', 'usb c'],
            'mechanical' => ['mechanical'],
            'hot_swappable' => ['hot-swap', 'hot swap', 'hot swappable'],
            'backlit' => ['backlit', 'backlight', 'back-lit'],
            'four_k' => ['4k', 'uhd'],
            'high_refresh' => ['high refresh', '120hz', '144hz', '165hz'],
            'webcam' => ['webcam', 'web cam'],
            'thunderbolt' => ['thunderbolt'],
            'lightweight' => ['lightweight', 'light weight'],
        ];

        foreach ($keywords as $feature => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    $features[] = $feature;
                    break;
                }
            }
        }

        $minSpecs = [];
        if (preg_match('/(\d{1,3}(?:\.\d)?)\s*(?:hours|hour|hrs|hr|h)\b/', $text, $m)
            && (str_contains($text, 'battery') || str_contains($text, 'playtime') || str_contains($text, 'charge') || $category === 'headphones')) {
            $minSpecs['battery_hours'] = (float) $m[1];
        }
        if (preg_match('/(\d{2,3})\s*hz\b/', $text, $m)) {
            $minSpecs['refresh_rate_hz'] = (float) $m[1];
        }
        if (preg_match('/(\d{1,2})\s*gb\s*(?:of\s*)?(?:ram|memory)/', $text, $m)) {
            $minSpecs['ram_gb'] = (float) $m[1];
        }
        if (preg_match('/(\d{2,4})\s*gb\s*(?:ssd|storage)/', $text, $m)) {
            $minSpecs['storage_gb'] = (float) $m[1];
        }
        if (preg_match('/(\d{2}(?:\.\d)?)\s*(?:inch|inches|in\b|")/', $text, $m)) {
            $minSpecs['screen_size_inches'] = (float) $m[1];
        }

        $currency = 'USD';
        if (str_contains($intent, '£')) {
            $currency = 'GBP';
        } elseif (str_contains($intent, '€')) {
            $currency = 'EUR';
        } elseif (preg_match('/\bR\s?\d/', $intent)) {
            $currency = 'ZAR';
        }

        $budget = $this->detectBudget($intent);

        return [
            'category' => $category,
            'budget_cents' => $budget,
            'currency' => $currency,
            'required_features' => array_values(array_unique(array_slice($features, 0, 6))),
            'min_specs' => array_slice($minSpecs, 0, 6, true),
            'preferences' => [],
            'summary' => '',
        ];
    }

    private function detectCategory(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'headphone'), str_contains($text, 'earphone'), str_contains($text, 'earbud') => 'headphones',
            str_contains($text, 'laptop'), str_contains($text, 'notebook') => 'laptops',
            str_contains($text, 'monitor'), str_contains($text, 'display') => 'monitors',
            str_contains($text, 'keyboard') => 'keyboards',
            str_contains($text, 'mouse'), str_contains($text, 'accessory'), str_contains($text, 'accessories') => 'accessories',
            default => null,
        };
    }

    private function detectBudget(string $intent): ?int
    {
        $patterns = [
            '/(?:under|below|less than|up to|max(?:imum)?(?: of)?|budget(?: of| is)?|no more than|within)\s*[$€£]?\s*R?\s*(\d[\d,]*(?:\.\d{1,2})?)/i',
            '/[$€£]\s*(\d[\d,]*(?:\.\d{1,2})?)/',
            '/(\d[\d,]*(?:\.\d{1,2})?)\s*(?:dollars|usd|euros|eur|pounds|gbp|rand|zar)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $intent, $m)) {
                $value = (float) str_replace(',', '', $m[1]);
                if ($value > 0 && $value <= 1_000_000) {
                    return (int) round($value * 100);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function summarise(array $parsed): string
    {
        $parts = [];
        if (! empty($parsed['budget_cents'])) {
            $parts[] = 'under $'.number_format($parsed['budget_cents'] / 100, 2);
        }
        if (! empty($parsed['required_features'])) {
            $labels = array_map(
                fn ($feature) => Str::lcfirst(Requirements::FEATURES[$feature] ?? $feature),
                $parsed['required_features'],
            );
            $parts[] = 'with '.implode(' and ', array_slice($labels, 0, 2));
        }

        $category = ucfirst((string) ($parsed['category'] ?? 'products'));

        return $parts === [] ? $category : $category.' '.implode(' ', $parts);
    }
}
