<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'upload',
        'documents/*',
    ],

    'allowed_methods' => [
        '*',
    ],

    'allowed_origins' => [
        'https://asal-final.vercel.app',
        'https://asal-frontend-coral.vercel.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        '*',
    ],

    'exposed_headers' => [
        'Content-Disposition',
        'Content-Length',
        'Content-Type',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];
