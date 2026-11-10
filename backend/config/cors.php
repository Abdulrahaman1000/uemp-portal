<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    // Comma separated list, e.g. http://localhost:8080,https://uemp.vercel.app
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:8080'))))),
    // Optional regex patterns, e.g. #^https://uemp-.*\.vercel\.app$# for Vercel previews.
    'allowed_origins_patterns' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))))),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 600,
    // Bearer tokens only: no cookies, so credentials stay off.
    'supports_credentials' => false,
];
