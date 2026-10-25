<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $shopping_session_id
 * @property string $type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $data
 * @property Carbon $created_at
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['shopping_session_id', 'type', 'subject_type', 'subject_id', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ShoppingSession, $this> */
    public function shoppingSession(): BelongsTo
    {
        return $this->belongsTo(ShoppingSession::class);
    }
}
