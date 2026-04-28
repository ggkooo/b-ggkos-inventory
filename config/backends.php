<?php

return [
    'servers' => array_values(array_filter([
        env('BACKEND_URL_1'),
        env('BACKEND_URL_2'),
        env('BACKEND_URL_3'),
        env('BACKEND_URL_4'),
        env('BACKEND_URL_5'),
    ])),

    'timeout' => (int) env('BACKEND_TIMEOUT', 10),

    'connect_timeout' => (int) env('BACKEND_CONNECT_TIMEOUT', 3),

    'retries' => (int) env('BACKEND_RETRIES', 2),

    'retry_sleep_ms' => (int) env('BACKEND_RETRY_SLEEP_MS', 150),

    'max_total_duration_ms' => (int) env('BACKEND_MAX_TOTAL_DURATION_MS', 25000),

    'cache_store' => env('APP_ENV') === 'testing'
        ? 'array'
        : env('BACKEND_CACHE_STORE', 'file'),

    'server_role' => env('BACKEND_SERVER_ROLE', 'gateway'),

    'auth_rate_limit_per_minute' => (int) env(
        'BACKEND_AUTH_RATE_LIMIT_PER_MINUTE',
        env('APP_ENV') === 'production' ? 30 : 300,
    ),

    'write_rate_limit_per_minute' => (int) env(
        'BACKEND_WRITE_RATE_LIMIT_PER_MINUTE',
        env('APP_ENV') === 'production' ? 60 : 600,
    ),

    'api_key' => env('BACKEND_API_KEY'),

    'forward_client_api_key' => (bool) env('BACKEND_FORWARD_CLIENT_API_KEY', false),

    'health_path' => env('BACKEND_HEALTH_PATH', '/up'),

    'health_method' => env('BACKEND_HEALTH_METHOD', 'GET'),

    'health_report_cache_ttl_seconds' => (int) env('BACKEND_HEALTH_REPORT_CACHE_TTL_SECONDS', 2),

    'health_report_cache_key' => env('BACKEND_HEALTH_REPORT_CACHE_KEY', 'backends:health:report'),

    'telemetry_enabled' => (bool) env('BACKEND_TELEMETRY_ENABLED', true),

    'telemetry_sample_rate_percentage' => (int) env('BACKEND_TELEMETRY_SAMPLE_RATE_PERCENTAGE', 100),

    'round_robin_cache_key' => env('BACKEND_ROUND_ROBIN_CACHE_KEY', 'backends:round-robin:index'),
];
