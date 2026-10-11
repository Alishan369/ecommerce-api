<?php

namespace App\Support;

final class Money
{
    /** "₹1,499", with paise only when there are any ("₹1,499.50"). */
    public static function format(float $amount): string
    {
        return '₹'.number_format(round($amount, 2), fmod($amount, 1.0) == 0.0 ? 0 : 2);
    }
}
