<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Comma-separated, e.g. CORS_ALLOWED_ORIGINS=https://shop.example.com. When unset: '*' locally
    // (the API uses Bearer tokens, not cookies) and only the storefront (FRONTEND_URL) in production.
    'allowed_origins' => array_map(
        fn (string $origin) => rtrim(trim($origin), '/'),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_ENV') === 'production' ? env('FRONTEND_URL', '*') : '*')),
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Lets the admin panel read the report filename on CSV downloads.
    'exposed_headers' => ['Content-Disposition'],

    // Browsers cache the preflight for a day instead of sending an OPTIONS request before every call.
    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => false,

];
