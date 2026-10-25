<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sku
 * @property string $slug
 * @property string $name
 * @property string $brand
 * @property string $category
 * @property string $tagline
 * @property string $description
 * @property int $price_cents
 * @property string $currency
 * @property int $stock
 * @property string $merchant_name
 * @property bool $merchant_verified
 * @property string $illustration
 * @property array<string, mixed> $specs
 * @property array<int, string>|null $data_gaps
 * @property string $status
 * @property Carbon $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Product extends Model
{
    protected $fillable = [
        'sku', 'slug', 'name', 'brand', 'category', 'tagline', 'description',
        'price_cents', 'currency', 'stock', 'merchant_name', 'merchant_verified',
        'illustration', 'specs', 'data_gaps', 'status', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock' => 'integer',
            'merchant_verified' => 'boolean',
            'specs' => 'array',
            'data_gaps' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function price(): string
    {
        return Money::format($this->price_cents, $this->currency);
    }

    public function spec(string $key): mixed
    {
        return $this->specs[$key] ?? null;
    }

    /**
     * Tri-state feature evidence: true, false, or null when the catalog does
     * not carry enough information. Missing evidence is never a pass.
     */
    public function hasFeature(string $feature): ?bool
    {
        $features = $this->specs['features'] ?? null;

        if (! is_array($features)) {
            return null;
        }

        if (in_array($feature, $features, true)) {
            return true;
        }

        $explicit = $this->specs['feature_flags'][$feature] ?? null;

        if ($explicit === false) {
            return false;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'tagline' => $this->tagline,
            'price_cents' => $this->price_cents,
            'price' => $this->price(),
            'currency' => $this->currency,
            'stock' => $this->stock,
            'merchant' => $this->merchant_name,
            'merchant_verified' => $this->merchant_verified,
            'illustration' => $this->illustration,
            'specs' => $this->specs,
            'data_gaps' => $this->data_gaps ?? [],
            'last_synced_at' => $this->last_synced_at->toIso8601String(),
        ];
    }
}
