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
 * @property int $purchase_approval_id
 * @property string $provider
 * @property string $environment
 * @property string|null $paypal_order_id
 * @property string $status
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $capture_id
 * @property string|null $payer_id
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Order extends Model
{
    protected $fillable = [
        'uuid', 'purchase_approval_id', 'provider', 'environment', 'paypal_order_id',
        'status', 'amount_cents', 'currency', 'capture_id', 'payer_id',
        'failure_reason', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<PurchaseApproval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(PurchaseApproval::class, 'purchase_approval_id');
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function amountLabel(): string
    {
        return Money::format($this->amount_cents, $this->currency);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
