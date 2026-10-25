<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $type
 * @property string|null $provider_event_id
 * @property string|null $status
 * @property array<string, mixed>|null $payload
 * @property Carbon $created_at
 */
class PaymentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'type', 'provider_event_id', 'status', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
