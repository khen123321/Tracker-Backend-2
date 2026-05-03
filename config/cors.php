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

    // ✨ Cleaned up: Explicitly allows your API routes (including /api/server-time), Sanctum, and Storage ✨
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    // 🚨 THIS WAS THE FIX: No more '*' wildcard! We explicitly list your safe domains.
    'allowed_origins' => [
        'http://localhost:5173', 
        'http://localhost:3000', 
        'https://climbstracker.vercel.app'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // ✨ REQUIRED for Sanctum authentication and secure requests ✨
    'supports_credentials' => true,

];