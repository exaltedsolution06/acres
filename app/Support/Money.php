<?php

namespace App\Support;

class Money
{
    /** Indian price wording: ₹1.25 Cr, ₹70 Lakh, ₹45,000. */
    public static function inr(?int $amount): string
    {
        if ($amount === null) {
            return 'Price on request';
        }

        if ($amount >= 10000000) {
            return '₹'.rtrim(rtrim(number_format($amount / 10000000, 2), '0'), '.').' Cr';
        }

        if ($amount >= 100000) {
            return '₹'.rtrim(rtrim(number_format($amount / 100000, 2), '0'), '.').' Lakh';
        }

        return '₹'.number_format($amount);
    }

    /** Exact amount from paise: ₹1,180.00 */
    public static function paise(int $paise): string
    {
        return '₹'.number_format($paise / 100, 2);
    }
}
