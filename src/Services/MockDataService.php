<?php

namespace MockServer\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MockDataService
{
    private array $mockData;
    private string $lastModified;
    
    public function __construct()
    {
        $this->initializeMockData();
    }
    
    /**
     * Initialize mock data
     */
    private function initializeMockData(): void
    {
        // Check if data exists in cache
        $cachedData = Cache::get('mock_catalog_data');
        
        if ($cachedData) {
            $this->mockData = $cachedData['data'];
            $this->lastModified = $cachedData['last_modified'];
        } else {
            $this->generateFreshData();
        }
    }
    
    /**
     * Generate fresh mock data using real structure from hydrate-example.json
     */
    private function generateFreshData(): void
    {
        $events = [
            [
                'id' => 21,
                'status' => 'active',
                'event_type' => 'live_auction',
                'title' => 'MIRA Dissolution Authority - Live Sale!',
                'description' => '<p>Test</p>',
                'start_time' => '2024-12-13T15:10:00+00:00',
                'end_time' => '2025-12-13T15:10:00+00:00',
                'hero_media' => [
                    'id' => 68481,
                    'model_type' => 'App\\Models\\Event',
                    'model_id' => 21,
                    'order' => 1,
                    'file_name' => 'Logo-ct-mira.jpg',
                    'url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/68481/Logo-ct-mira.jpg',
                    'thumbnail_url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/68481/conversions/Logo-ct-mira-thumb.jpg',
                    'updated_at' => '2025-04-07 18:56:58',
                    'content_type' => 'image/jpeg',
                    'collection' => 'images',
                    'is_video' => false
                ],
                'listings' => [
                    [
                        'id' => 10490,
                        'event_id' => 21,
                        'user_id' => 1,
                        'is_approved' => true,
                        'lot_number' => 1,
                        'inventory_number' => null,
                        'title' => 'QUANTITY OF ROLLER STANDS',
                        'notes' => null,
                        'description' => null,
                        'category' => 'large_miscellaneous',
                        'subcategory' => null,
                        'sale_order' => 1,
                        'requires_title' => false,
                        'media' => [
                            [
                                'id' => 72053,
                                'model_type' => 'App\\Models\\Listing',
                                'model_id' => 10490,
                                'order' => 1,
                                'file_name' => 'media_ce23ff83-862d-4bfe-acee-57de51b706cf.jpg',
                                'url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/72053/media_ce23ff83-862d-4bfe-acee-57de51b706cf.jpg',
                                'thumbnail_url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/72053/conversions/media_ce23ff83-862d-4bfe-acee-57de51b706cf-thumb.jpg',
                                'updated_at' => '2025-08-03 23:31:08',
                                'content_type' => 'image/jpeg',
                                'collection' => 'images',
                                'is_video' => false
                            ],
                            [
                                'id' => 72054,
                                'model_type' => 'App\\Models\\Listing',
                                'model_id' => 10490,
                                'order' => 2,
                                'file_name' => 'media_fb21c2d1-bf41-4bd3-a362-2ef5001b3387.jpg',
                                'url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/72054/media_fb21c2d1-bf41-4bd3-a362-2ef5001b3387.jpg',
                                'thumbnail_url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/72054/conversions/media_fb21c2d1-bf41-4bd3-a362-2ef5001b3387-thumb.jpg',
                                'updated_at' => '2025-08-04 01:01:15',
                                'content_type' => 'image/jpeg',
                                'collection' => 'images',
                                'is_video' => false
                            ]
                        ],
                        'quality' => null,
                        'estimate' => null
                    ],
                    [
                        'id' => 10491,
                        'event_id' => 21,
                        'user_id' => 1,
                        'is_approved' => true,
                        'lot_number' => 2,
                        'inventory_number' => null,
                        'title' => 'VESTIL PORTABLE GANTRY CRANE',
                        'notes' => null,
                        'description' => '<p>4000# CAPACITY, C/W TROLLEY, CHAIN HOIST</p>',
                        'category' => 'large_miscellaneous',
                        'subcategory' => null,
                        'sale_order' => 2,
                        'requires_title' => false,
                        'media' => [
                            [
                                'id' => 72055,
                                'model_type' => 'App\\Models\\Listing',
                                'model_id' => 10491,
                                'order' => 1,
                                'file_name' => 'gantry_crane_01.jpg',
                                'url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/72055/gantry_crane_01.jpg',
                                'thumbnail_url' => 'https://vapor-salesco-qa.s3.us-east-1.amazonaws.com/72055/conversions/gantry_crane_01-thumb.jpg',
                                'updated_at' => '2025-08-04 01:10:15',
                                'content_type' => 'image/jpeg',
                                'collection' => 'images',
                                'is_video' => false
                            ]
                        ],
                        'quality' => null,
                        'estimate' => null
                    ],
                    [
                        'id' => 10492,
                        'event_id' => 21,
                        'user_id' => 1,
                        'is_approved' => true,
                        'lot_number' => 3,
                        'inventory_number' => null,
                        'title' => '2020 CATERPILLAR 315 HYDRAULIC EXCAVATOR',
                        'notes' => 'Hour Meter: 4,523',
                        'description' => '<p>Equipped with: Quick Coupler, 48" Bucket, A/C Cab, Aux Hydraulics</p>',
                        'category' => 'excavators',
                        'subcategory' => 'hydraulic_excavator',
                        'sale_order' => 3,
                        'requires_title' => false,
                        'media' => [],
                        'quality' => '2',
                        'estimate' => '150000'
                    ]
                ],
                'location' => [
                    'description' => null,
                    'address' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                    'country_code' => 'US'
                ]
            ],
            [
                'id' => 22,
                'status' => 'active',
                'event_type' => 'online_auction',
                'title' => 'Heavy Equipment & Truck Auction',
                'description' => '<p>Monthly online auction featuring construction equipment, trucks, and more.</p>',
                'start_time' => '2025-01-15T14:00:00+00:00',
                'end_time' => '2025-01-18T22:00:00+00:00',
                'hero_media' => null,
                'listings' => [
                    [
                        'id' => 10493,
                        'event_id' => 22,
                        'user_id' => 1,
                        'is_approved' => true,
                        'lot_number' => 1,
                        'inventory_number' => 'INV-2025-001',
                        'title' => '2019 JOHN DEERE 544L WHEEL LOADER',
                        'notes' => 'Hour Meter: 2,845',
                        'description' => '<p>4WD, GP Bucket, Ride Control, Auto Greasing System</p>',
                        'category' => 'wheel_loaders',
                        'subcategory' => 'rubber_tire_wheel_loader',
                        'sale_order' => 1,
                        'requires_title' => false,
                        'media' => [],
                        'quality' => '1',
                        'estimate' => '225000'
                    ]
                ],
                'location' => [
                    'description' => 'Online Auction - Equipment located across multiple sites',
                    'address' => '123 Auction Way',
                    'city' => 'Dallas',
                    'state' => 'TX',
                    'postal_code' => '75001',
                    'country_code' => 'US'
                ]
            ]
        ];
        
        $this->mockData = [
            'events' => $events,
            'deletedEvents' => [],
            'deletedListings' => [],
            'deletedMedia' => []
        ];
        
        $this->lastModified = Carbon::now()->format('Y-m-d H:i:s');
        
        // Cache the data for 1 hour
        Cache::put('mock_catalog_data', [
            'data' => $this->mockData,
            'last_modified' => $this->lastModified
        ], 3600);
    }
    
    /**
     * Get all data for hydrate endpoint
     */
    public function getAllData(): array
    {
        return [
            'events' => $this->mockData['events'],
            'last_modified' => $this->lastModified
        ];
    }
    
    /**
     * Get changes since specific timestamp
     */
    public function getChangesSince(string $since): array
    {
        // For the mock, we'll return empty arrays as specified
        return [
            'data' => [
                'events' => [],
                'listings' => [],
                'media' => []
            ],
            'deletions' => [
                'events' => [],
                'listings' => [],
                'media' => []
            ],
            'last_modified' => $this->lastModified
        ];
    }
    
    /**
     * Reset mock data
     */
    public function resetData(): void
    {
        Cache::forget('mock_catalog_data');
        // Add a small delay to ensure timestamp is different
        usleep(1000);
        $this->generateFreshData();
    }
}