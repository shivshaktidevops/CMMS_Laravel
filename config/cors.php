<?php

$origins = array_values(array_filter(array_map('trim', explode(',', (string) env('ALLOWED_ORIGINS', '')))));

return [
    'paths' => ['api/*', 'uploads/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins ?: ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
