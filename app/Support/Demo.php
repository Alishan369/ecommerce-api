<?php

namespace App\Support;

/** Public demo mode helpers (config/shop.php → demo). */
final class Demo
{
    public static function enabled(): bool
    {
        return (bool) config('shop.demo.enabled');
    }

    /**
     * The shared logins shown on the sign-in page in demo mode.
     *
     * @return array<int, array{role: string, label: string, email: string, password: string}>
     */
    public static function accounts(): array
    {
        return [
            ['role' => 'admin', 'label' => 'Store admin', 'email' => strtolower(config('shop.admin.email')), 'password' => config('shop.admin.password')],
            ['role' => 'customer', 'label' => 'Demo customer', 'email' => strtolower(config('shop.demo.customer.email')), 'password' => config('shop.demo.customer.password')],
        ];
    }

    public static function isDemoAccount(?string $email): bool
    {
        return $email !== null && in_array(strtolower(trim($email)), array_column(self::accounts(), 'email'), true);
    }
}
