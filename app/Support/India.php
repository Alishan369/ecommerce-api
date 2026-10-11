<?php

namespace App\Support;

/** Indian mobile numbers and PIN codes: validation patterns, messages and input clean-up. */
final class India
{
    /** A bare 10-digit mobile number. */
    public const PHONE_PATTERN = '/^[6-9][0-9]{9}$/';

    public const PHONE_MESSAGE = 'Enter a valid 10-digit Indian mobile number.';

    public const PINCODE_PATTERN = '/^[1-9][0-9]{5}$/';

    public const PINCODE_MESSAGE = 'Enter a valid 6-digit PIN code.';

    /** "+91 98765 43210" / "098765-43210" → "9876543210". Input without digits is returned as-is for the rules to reject. */
    public static function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return match (true) {
            $digits === '' => $value,
            strlen($digits) === 12 && str_starts_with($digits, '91') => substr($digits, 2),
            strlen($digits) === 11 && str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };
    }

    /** "400 050" → "400050". */
    public static function pincode(string $value): string
    {
        return preg_replace('/\s+/', '', $value);
    }
}
