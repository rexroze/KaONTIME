<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        // Set FRONTEND_URL per environment; the rest cover production, local dev,
        // and the Capacitor Android/iOS WebView origins.
        env('FRONTEND_URL'),
        'https://vueactivity-kaontime.vercel.app',
        'http://localhost',
        'http://127.0.0.1:9000',
        'capacitor://localhost',
        'https://localhost',
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
