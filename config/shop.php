<?php

return [

    /*
    | Shipping is charged per order and calculated on the server — the cart and
    | checkout both read these values, so the storefront can never under-charge.
    */
    'free_shipping_threshold' => (float) env('SHOP_FREE_SHIPPING_THRESHOLD', 499),
    'shipping_fee' => (float) env('SHOP_SHIPPING_FEE', 49),

    // Products at or below this stock level show up in the admin dashboard's low-stock list.
    'low_stock_threshold' => (int) env('SHOP_LOW_STOCK_THRESHOLD', 10),

    // Seeded admin account (database/seeders/AdminUserSeeder.php). Change the password after first login.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Store Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'Admin@12345'),
    ],

    // The password above ships in the README, so a production store refuses it.
    'default_admin_password' => 'Admin@12345',

    // Where links in emails (order pages, password reset) point — the storefront, not the API.
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')), '/'),

];
