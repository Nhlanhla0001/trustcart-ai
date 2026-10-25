<?php

namespace App\Services;

use App\Models\ShoppingSession;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function log(string $type, ?ShoppingSession $session = null, array $data = [], ?object $subject = null): void
    {
        DB::table('audit_events')->insert([
            'shopping_session_id' => $session?->id,
            'type' => $type,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject->id ?? null,
            'data' => json_encode($this->redact($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        $sensitive = ['client_secret', 'authorization', 'access_token', 'card', 'cvv', 'email_address'];

        array_walk_recursive($data, function (&$value, $key) use ($sensitive) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $value = '[redacted]';
            }
        });

        return $data;
    }
}
