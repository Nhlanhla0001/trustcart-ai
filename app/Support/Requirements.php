<?php

namespace App\Support;

/**
 * A structured buying requirement. This is the only shape the AI is allowed to
 * hand to the rules engine: no free text, no product claims, no prices.
 */
final class Requirements
{
    public const CATEGORIES = ['headphones', 'laptops', 'monitors', 'keyboards', 'accessories'];

    /** Canonical feature keys and the human label used in the interface. */
    public const FEATURES = [
        'noise_cancellation' => 'Active noise cancellation',
        'wireless' => 'Wireless',
        'bluetooth' => 'Bluetooth',
        'usb_c' => 'USB-C',
        'mechanical' => 'Mechanical switches',
        'hot_swappable' => 'Hot-swappable switches',
        'backlit' => 'Backlit keys',
        'four_k' => '4K resolution',
        'high_refresh' => 'High refresh rate',
        'webcam' => 'Built-in webcam',
        'thunderbolt' => 'Thunderbolt',
        'lightweight' => 'Lightweight',
    ];

    /** Numeric minimums, all of which must be met at or above the value. */
    public const MIN_SPECS = [
        'battery_hours' => ['label' => 'Battery life', 'unit' => 'h'],
        'refresh_rate_hz' => ['label' => 'Refresh rate', 'unit' => 'Hz'],
        'screen_size_inches' => ['label' => 'Screen size', 'unit' => '"'],
        'ram_gb' => ['label' => 'Memory', 'unit' => 'GB'],
        'storage_gb' => ['label' => 'Storage', 'unit' => 'GB'],
        'weight_kg' => ['label' => 'Weight', 'unit' => 'kg'],
    ];

    public const SYNONYMS = [
        'anc' => 'noise_cancellation',
        'noise cancelling' => 'noise_cancellation',
        'noise cancellation' => 'noise_cancellation',
        'active noise cancellation' => 'noise_cancellation',
        'noise-cancelling' => 'noise_cancellation',
        'noise-cancellation' => 'noise_cancellation',
        'cordless' => 'wireless',
        'bt' => 'bluetooth',
        'usb c' => 'usb_c',
        'usb-c' => 'usb_c',
        'hot swap' => 'hot_swappable',
        'hot-swap' => 'hot_swappable',
        'hot swappable' => 'hot_swappable',
        '4k' => 'four_k',
        'uhd' => 'four_k',
        'high refresh rate' => 'high_refresh',
        '120hz' => 'high_refresh',
        '144hz' => 'high_refresh',
        'web cam' => 'webcam',
    ];

    /**
     * @param  list<string>  $requiredFeatures
     * @param  array<string, float|int>  $minSpecs
     * @param  list<string>  $preferences
     */
    public function __construct(
        public readonly string $category,
        public readonly ?int $budgetCents,
        public readonly string $currency,
        public readonly array $requiredFeatures = [],
        public readonly array $minSpecs = [],
        public readonly array $preferences = [],
        public readonly string $summary = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $features = [];
        foreach ((array) ($data['required_features'] ?? []) as $feature) {
            if (! is_string($feature)) {
                continue;
            }

            $canonical = self::canonicalFeature($feature);
            if ($canonical !== null) {
                $features[$canonical] = true;
            }
        }

        $specs = [];
        foreach ((array) ($data['min_specs'] ?? []) as $key => $value) {
            if (array_key_exists($key, self::MIN_SPECS) && is_numeric($value)) {
                $specs[$key] = (float) $value;
            }
        }

        $currency = strtoupper((string) ($data['currency'] ?? 'USD'));

        return new self(
            category: in_array($data['category'] ?? null, self::CATEGORIES, true) ? $data['category'] : 'headphones',
            budgetCents: isset($data['budget_cents']) && is_numeric($data['budget_cents'])
                ? max(0, (int) $data['budget_cents'])
                : null,
            currency: in_array($currency, ['USD', 'EUR', 'GBP', 'ZAR'], true) ? $currency : 'USD',
            requiredFeatures: array_keys($features),
            minSpecs: $specs,
            preferences: array_values(array_filter((array) ($data['preferences'] ?? []), 'is_string')),
            summary: (string) ($data['summary'] ?? ''),
        );
    }

    public static function canonicalFeature(string $feature): ?string
    {
        $key = strtolower(trim($feature));
        $key = str_replace(['_', '-'], '', $key);

        foreach (self::FEATURES as $canonical => $label) {
            if (str_replace('_', '', $canonical) === $key || strtolower($label) === strtolower(trim($feature))) {
                return $canonical;
            }
        }

        foreach (self::SYNONYMS as $synonym => $canonical) {
            if (str_replace(['_', '-'], '', $synonym) === $key) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'budget_cents' => $this->budgetCents,
            'currency' => $this->currency,
            'required_features' => $this->requiredFeatures,
            'min_specs' => $this->minSpecs,
            'preferences' => $this->preferences,
            'summary' => $this->summary,
        ];
    }

    public function budgetLabel(): ?string
    {
        return $this->budgetCents === null ? null : Money::format($this->budgetCents, $this->currency);
    }

    /** @return list<array{key: string, label: string}> */
    public function featureLabels(): array
    {
        return array_map(
            fn (string $feature) => ['key' => $feature, 'label' => self::FEATURES[$feature] ?? $feature],
            $this->requiredFeatures,
        );
    }

    /** @return list<array{key: string, label: string, value: string}> */
    public function minSpecLabels(): array
    {
        $labels = [];

        foreach ($this->minSpecs as $key => $value) {
            $meta = self::MIN_SPECS[$key] ?? ['label' => $key, 'unit' => ''];
            $labels[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => self::withUnit(
                    rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'),
                    $meta['unit'],
                ),
            ];
        }

        return $labels;
    }

    public function categoryLabel(): string
    {
        return ucfirst($this->category);
    }

    public static function withUnit(string $number, string $unit): string
    {
        return $unit === '"' ? $number.'"' : trim($number.' '.$unit);
    }
}
