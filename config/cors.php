<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'], // Add '*' just to be safe in dev
    'allowed_methods' => ['*'],
    
    // THIS IS THE MOST IMPORTANT LINE:
    'allowed_origins' => ['*'], 

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false, // Keep this false since you use Bearer tokens
];
