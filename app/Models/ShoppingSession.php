<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $raw_intent
 * @property array<string, mixed> $requirements
 * @property string $status
 * @property string|null $ai_provider
 * @property string|null $ai_model
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShoppingSession extends Model
{
    protected $fillable = [
        'uuid', 'raw_intent', 'requirements', 'status', 'ai_provider', 'ai_model',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            $session->uuid ??= (string) Str::uuid();
        });
    }

    /** @return HasOne<Recommendation, $this> */
    public function recommendation(): HasOne
    {
        return $this->hasOne(Recommendation::class);
    }

    /** @return HasMany<PurchaseApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseApproval::class);
    }

    /** @return HasMany<AuditEvent, $this> */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
