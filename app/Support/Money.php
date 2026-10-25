<?php

namespace App\Support;

final class Money
{
    private const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'ZAR' => 'R',
    ];

    public static function format(int $cents, string $currency = 'USD'): string
    {
        return (self::SYMBOLS[$currency] ?? $currency.' ').number_format($cents / 100, 2);
    }

    public static function formatWithCode(int $cents, string $currency = 'USD'): string
    {
        return self::format($cents, $currency).' '.$currency;
    }

    public static function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function fromDecimal(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
