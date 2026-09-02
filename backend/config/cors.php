<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Cookie-based Sanctum authentication requires an explicit origin; `*`
    // cannot be used together with credentials in browsers. PUBLIC_WEB_URL is
    // the marketing site (may be a comma-separated list) and only calls the
    // unauthenticated /public/* endpoints.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        array_merge(
            [env('FRONTEND_URL', 'http://localhost:5173')],
            explode(',', (string) env('PUBLIC_WEB_URL', '')),
        ),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
