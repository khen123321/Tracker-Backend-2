<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://192.168.100.6:5173',
        'https://192.168.100.6:5173',
        'http://192.168.25.190:5173',  
        'https://192.168.25.190:5173',
        'http://localhost:3000', 
        'https://climbstracker.vercel.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];