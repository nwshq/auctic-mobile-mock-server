<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Scenarios Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the test scenario system for Maestro integration tests.
    |
    */

    /**
     * Enable or disable the test scenario system
     */
    'enabled' => env('TEST_SCENARIOS_ENABLED', true),

    /**
     * Cache driver for storing test sessions
     * Options: 'redis', 'memcached', 'database', 'file', 'array'
     */
    'cache_driver' => env('TEST_SESSION_CACHE_DRIVER', 'redis'),

    /**
     * Session time-to-live in seconds (default: 2 hours)
     */
    'session_ttl' => env('TEST_SESSION_TTL', 7200),

    /**
     * Path to scenario configuration files
     */
    'config_path' => env('TEST_SCENARIO_CONFIG_PATH', base_path('config/test-scenarios')),

    /**
     * Enable debug endpoints
     */
    'debug_enabled' => env('TEST_SCENARIOS_DEBUG', true),

    /**
     * Enable metrics endpoints
     */
    'metrics_enabled' => env('TEST_SCENARIOS_METRICS', true),

    /**
     * Add debug headers to responses
     */
    'add_debug_headers' => env('TEST_SCENARIOS_DEBUG_HEADERS', true),

    /**
     * Logging configuration
     */
    'logging' => [
        'enabled' => env('TEST_SCENARIOS_LOGGING', true),
        'channel' => env('TEST_SCENARIOS_LOG_CHANNEL', 'test_scenarios'),
    ],

    /**
     * Rate limiting for control endpoints
     */
    'rate_limiting' => [
        'enabled' => env('TEST_SCENARIOS_RATE_LIMIT', true),
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],

    /**
     * Default response generators
     */
    'generators' => [
        'empty_catalog' => \MockServer\TestScenarios\Generators\EmptyCatalogGenerator::class,
        'single_event' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
    ],

    /**
     * Middleware groups to apply test scenarios to
     */
    'middleware_groups' => [
        'api',
        'mobile-api'
    ],

    /**
     * Excluded routes (will not be affected by test scenarios)
     */
    'excluded_routes' => [
        'test-scenarios/*',
        'health',
        'status'
    ],

    /**
     * Maximum number of concurrent test sessions
     */
    'max_concurrent_sessions' => env('TEST_SCENARIOS_MAX_SESSIONS', 100),

    /**
     * Auto-cleanup old sessions
     */
    'auto_cleanup' => [
        'enabled' => env('TEST_SCENARIOS_AUTO_CLEANUP', true),
        'older_than_hours' => 24,
    ],
];