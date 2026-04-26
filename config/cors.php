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
    */

    // ✨ Ensured 'storage/*' is explicitly declared just in case ✨
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*', '*'],

    'allowed_methods' => ['*'],

    // ✨ REMOVED the '*' fallback. Browsers block images if '*' is mixed with credentials! ✨
    'allowed_origins' => [
        'http://localhost:5173',
        'http://192.168.25.190:5173',
        'https://192.168.25.190:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // ✨ FLIPPED TO TRUE: Crucial for Sanctum auth and secure image fetching ✨
    'supports_credentials' => true,
];