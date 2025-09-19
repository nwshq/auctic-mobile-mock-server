<?php

return [
    'default' => [
        'name' => 'Default Scenario',
        'description' => 'Default mock server responses',
        'responses' => [
            // All endpoints will use the normal mock data from controllers
            // No specific overrides needed for default scenario
        ]
    ],
    
    'camera-performance-test' => [
        'name' => 'Camera Performance Test',
        'description' => 'Adds delays and logging for camera performance testing',
        'responses' => [
            'catalog.changes' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\CameraPerformanceGenerator::class,
                'parameters' => [
                    'delay' => 5, // 5 second delay
                    'enable_logging' => true
                ]
            ],
            'catalog.request-upload' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\CameraPerformanceGenerator::class,
                'parameters' => [
                    'delay' => 5, // 5 second delay
                    'enable_logging' => true
                ]
            ],
            'mock-s3.upload' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\CameraPerformanceGenerator::class,
                'parameters' => [
                    'delay' => 5, // 5 second delay
                    'enable_logging' => true
                ]
            ],
            'catalog.hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\CameraPerformanceGenerator::class,
                'parameters' => [
                    'fixed_last_modified' => '2025-08-27 20:24:35'
                ]
            ]
        ]
    ],

    'rotation-test' => [
        'name' => 'Rotation Test',
        'description' => 'Tracks media changes during device rotation (1 added, 1 removed)',
        'responses' => [
            'catalog.changes' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RotationTestGenerator::class,
                'parameters' => [
                    'enable_logging' => true,
                    'track_changes' => true
                ]
            ],
            'catalog.request-upload' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RotationTestGenerator::class,
                'parameters' => [
                    'enable_logging' => true
                ]
            ],
            'mock-s3.upload' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RotationTestGenerator::class,
                'parameters' => [
                    'enable_logging' => true
                ]
            ],
            'catalog.hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RotationTestGenerator::class,
                'parameters' => [
                    'fixed_last_modified' => '2025-08-27 20:24:35'
                ]
            ]
        ]
    ],

    'remove-listing-test' => [
        'name' => 'Remove Listing Test',
        'description' => 'Tracks listing and media removals during listing deletion',
        'responses' => [
            'catalog.changes' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RemoveListingTestGenerator::class,
                'parameters' => [
                    'enable_logging' => true,
                    'track_changes' => true
                ]
            ],
            'catalog.request-upload' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RemoveListingTestGenerator::class,
                'parameters' => [
                    'enable_logging' => true
                ]
            ],
            'mock-s3.upload' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RemoveListingTestGenerator::class,
                'parameters' => [
                    'enable_logging' => true
                ]
            ],
            'catalog.hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\RemoveListingTestGenerator::class,
                'parameters' => [
                    'fixed_last_modified' => '2025-08-27 20:24:35'
                ]
            ]
        ]
    ]
];