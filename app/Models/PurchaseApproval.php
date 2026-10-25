<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $shopping_session_id
 * @property int $recommendation_id
 * @property int $product_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $total_cents
 * @property string $currency
 * @property string $status
 * @property Carbon|null $authorized_at
 * @property Carbon $expires_at
 * @property string|null $invalidated_reason
 * @property string|null $authorized_ip
 * @property string|null $authorized_user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PurchaseApproval extends Model
{
    protected $fillable = [
        'uuid', 'shopping_session_id', 'recommendation_id', 'product_id', 'quantity',
        'unit_price_cents', 'total_cents', 'currency', 'status', 'authorized_at',
        'expires_at', 'invalidated_reason', 'authorized_ip', 'authorized_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $approval) {
            $approval->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ShoppingSession, $this> */
    public function shoppingSession(): BelongsTo
    {
        return $this->belongsTo(ShoppingSession::class);
    }

    /** @return BelongsTo<Recommendation, $this> */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized' && ! $this->isExpired();
    }

    public function isActionable(): bool
    {
        return in_array($this->status, ['pending', 'authorized'], true) && ! $this->isExpired();
    }

    public function totalLabel(): string
    {
        return Money::format($this->total_cents, $this->currency);
    }
}
