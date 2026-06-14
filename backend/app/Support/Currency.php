<?php

namespace App\Support;

class Currency
{
    public static function code(): string
    {
        return strtoupper((string) config('currency.code', 'USD'));
    }

    public static function symbol(): string
    {
        return (string) config('currency.symbol', '$');
    }

    public static function format(float|int|null $amount, ?string $currency = null): string
    {
        if ($amount === null) {
            return '—';
        }

        $code = strtoupper($currency ?: self::code());
        $formatted = number_format((float) $amount, 2, '.', ',');

        if ($code === self::code()) {
            return self::symbol().$formatted;
        }

        return $code.' '.$formatted;
    }
}
