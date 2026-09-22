<?php

namespace App\Support;

final class Money
{
    public static function format(int $amountMinor, string $currency = 'NGN'): string
    {
        $sign = $amountMinor < 0 ? '-' : '';
        $amountMinor = abs($amountMinor);
        $major = intdiv($amountMinor, 100);
        $minor = str_pad((string) ($amountMinor % 100), 2, '0', STR_PAD_LEFT);
        $formatted = number_format($major).'.'.$minor;

        return $currency === 'NGN' ? $sign.'₦'.$formatted : $sign.$currency.' '.$formatted;
    }
}
