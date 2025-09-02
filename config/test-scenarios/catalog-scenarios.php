<?php

return [
    'empty_catalog' => [
        'name' => 'Empty Catalog State',
        'description' => 'Returns empty events and listings',
        'responses' => [
            'catalog.hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\EmptyCatalogGenerator::class,
                'parameters' => [
                    'include_defaults' => true
                ]
            ],
            'catalog.sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\EmptyCatalogGenerator::class,
                'parameters' => [
                    'include_defaults' => true
                ]
            ],
            'mobile-api/v1/catalog/hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\EmptyCatalogGenerator::class,
                'parameters' => [
                    'include_defaults' => true
                ]
            ],
            'mobile-api/v1/catalog/sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\EmptyCatalogGenerator::class,
                'parameters' => [
                    'include_defaults' => true
                ]
            ]
        ]
    ],
    
    'single_event_with_listings' => [
        'name' => 'Single Event with Listings',
        'description' => 'One active event with multiple listings',
        'responses' => [
            'catalog.hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 1,
                    'listing_count' => 5,
                    'status' => 'active'
                ]
            ],
            'catalog.sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 1,
                    'listing_count' => 5,
                    'status' => 'active'
                ]
            ],
            'mobile-api/v1/catalog/hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 1,
                    'listing_count' => 5,
                    'status' => 'active'
                ]
            ],
            'mobile-api/v1/catalog/sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 1,
                    'listing_count' => 5,
                    'status' => 'active'
                ]
            ]
        ]
    ],
    
    'multiple_events' => [
        'name' => 'Multiple Events',
        'description' => 'Multiple events with various listing counts',
        'responses' => [
            'catalog.hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 5,
                    'listing_count' => 3,
                    'status' => 'active'
                ]
            ],
            'catalog.sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 5,
                    'listing_count' => 3,
                    'status' => 'active'
                ]
            ],
            'mobile-api/v1/catalog/hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 5,
                    'listing_count' => 3,
                    'status' => 'active'
                ]
            ],
            'mobile-api/v1/catalog/sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 5,
                    'listing_count' => 3,
                    'status' => 'active'
                ]
            ]
        ]
    ],
    
    'sold_out_events' => [
        'name' => 'Sold Out Events',
        'description' => 'Events with no available listings',
        'responses' => [
            'catalog.hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 3,
                    'listing_count' => 0,
                    'status' => 'active'
                ]
            ],
            'catalog.sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 3,
                    'listing_count' => 0,
                    'status' => 'active'
                ]
            ],
            'mobile-api/v1/catalog/hydrate' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 3,
                    'listing_count' => 0,
                    'status' => 'active'
                ]
            ],
            'mobile-api/v1/catalog/sync' => [
                'type' => 'dynamic',
                'generator' => \MockServer\TestScenarios\Generators\SingleEventGenerator::class,
                'parameters' => [
                    'event_count' => 3,
                    'listing_count' => 0,
                    'status' => 'active'
                ]
            ]
        ]
    ]
];