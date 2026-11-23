<?php

namespace App\Support;

class Mobile
{
    /** Normalise an Indian mobile number to 10 digits, or null if invalid. */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    public static function mask(string $mobile): string
    {
        return substr($mobile, 0, 2).str_repeat('X', max(0, strlen($mobile) - 4)).substr($mobile, -2);
    }
}
