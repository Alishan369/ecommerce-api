<?php

namespace App\Support;

final class IndianPhone
{
    /** Validation regex for a bare 10-digit Indian mobile number. */
    public const PATTERN = '/^[6-9][0-9]{9}$/';

    /** Accepts "+91 98765 43210" / "098765-43210" and returns the bare 10 digits. */
    public static function normalize(?string $value): string
    {
        $phone = preg_replace('/\D+/', '', (string) $value);

        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            return substr($phone, 2);
        }

        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            return substr($phone, 1);
        }

        return $phone;
    }
}
