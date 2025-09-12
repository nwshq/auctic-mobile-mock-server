<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Scenarios Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the test scenarios system used
    | for automated testing with Maestro and other testing frameworks.
    |
    */

    'enabled' => env('TEST_SCENARIOS_ENABLED', true),

    'session' => [
        'ttl' => env('TEST_SESSION_TTL', 7200),
        'cache_driver' => env('TEST_SESSION_CACHE_DRIVER', 'redis'),
        'max_sessions' => env('TEST_SCENARIOS_MAX_SESSIONS', 100),
        'auto_cleanup' => env('TEST_SCENARIOS_AUTO_CLEANUP', true),
    ],

    'config_path' => env('TEST_SCENARIO_CONFIG_PATH', 'config/test-scenarios'),

    'debug' => [
        'enabled' => env('TEST_SCENARIOS_DEBUG', true),
        'headers' => env('TEST_SCENARIOS_DEBUG_HEADERS', true),
        'logging' => env('TEST_SCENARIOS_LOGGING', true),
        'log_channel' => env('TEST_SCENARIOS_LOG_CHANNEL', 'test_scenarios'),
    ],

    'metrics' => [
        'enabled' => env('TEST_SCENARIOS_METRICS', true),
    ],

    'rate_limit' => [
        'enabled' => env('TEST_SCENARIOS_RATE_LIMIT', true),
    ],
];