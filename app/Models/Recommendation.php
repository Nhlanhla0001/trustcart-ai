<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shopping_session_id
 * @property int|null $product_id
 * @property string $verdict
 * @property int $score
 * @property string|null $narrative
 * @property string $narrative_source
 * @property array<int, array<string, string>> $checks
 * @property array<int, array<string, mixed>> $candidates
 * @property array<string, mixed>|null $engine
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Recommendation extends Model
{
    protected $fillable = [
        'shopping_session_id', 'product_id', 'verdict', 'score', 'narrative',
        'narrative_source', 'checks', 'candidates', 'engine',
    ];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'candidates' => 'array',
            'engine' => 'array',
            'score' => 'integer',
        ];
    }

    /** @return BelongsTo<ShoppingSession, $this> */
    public function shoppingSession(): BelongsTo
    {
        return $this->belongsTo(ShoppingSession::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isRecommended(): bool
    {
        return $this->verdict === 'recommended';
    }
}
