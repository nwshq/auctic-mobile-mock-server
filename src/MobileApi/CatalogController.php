<?php

namespace MockServer\MobileApi;

use Illuminate\Http\Request;
use MockServer\Services\MockDataService;

class CatalogController
{
    private MockDataService $mockDataService;
    
    public function __construct(MockDataService $mockDataService)
    {
        $this->mockDataService = $mockDataService;
    }
    
    /**
     * Full data hydration endpoint
     * Returns complete snapshot of events, listings, and media
     */
    public function hydrate(Request $request)
    {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Check permissions
        if (!$this->hasPermission($request, ['mobile-api.catalog', 'mobile-api.catalog.view'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        $data = $this->mockDataService->getAllData();
        
        $response = [
            'events' => $data['events'],
            'categories' => $this->getCategories(),
            'sellers' => [
                [
                    'id' => 0,
                    'name' => 'Onsite User',
                    'email' => 'onsite@auctic.com',
                    'phone' => '8023792276',
                    'city' => 'A',
                    'state_code' => 'CA',
                    'postal_code' => '12345',
                    'country' => 'US',
                    'extra_attributes' => []
                ],
                [
                    'id' => 1,
                    'name' => 'System',
                    'email' => 'system@auctic.com',
                    'phone' => null,
                    'city' => null,
                    'state_code' => null,
                    'postal_code' => null,
                    'country' => null,
                    'extra_attributes' => []
                ]
            ],
            'qualities' => [
                '1' => 'Excellent',
                '2' => 'Good',
                '3' => 'Average',
                '4' => 'Poor'
            ],
            'dictionary' => [
                'terms' => [
                    'listings_title_noun' => 'Item',
                    'lot_number' => 'Lot #',
                    'listing' => 'Lot',
                    'listings' => 'Lots',
                    'event' => 'Sale',
                    'events' => 'Sales',
                    'item' => 'Item',
                    'items' => 'Items',
                    'passed' => 'Not Sold',
                    'tag_number' => 'Tag #',
                    'estimate' => 'Target No.',
                    'consignments' => 'Consignments'
                ]
            ],
            'fallback_image_url' => 'https://placehold.co/600x400/orange/white',
            'last_modified' => $data['last_modified'] ?? now()->format('Y-m-d H:i:s')
        ];
        
        return response()->json($response);
    }
    
    /**
     * Incremental sync endpoint
     * Returns changes since specified timestamp
     */
    public function sync(Request $request)
    {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Check permissions
        if (!$this->hasPermission($request, ['mobile-api.catalog', 'mobile-api.catalog.view'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        $since = $request->query('since');
        
        if (!$since) {
            return response()->json([
                'error' => 'Missing required parameter: since'
            ], 400);
        }
        
        $response = [
            'data' => [
                'events' => [],
                'listings' => [],
                'media' => [],
                'sellers' => [],
                'qualities' => [],
                'categories' => []
            ],
            'deletions' => [
                'events' => [],
                'listings' => [],
                'media' => [],
                'sellers' => [],
                'qualities' => [],
                'categories' => []
            ],
            'last_modified' => now()->format('Y-m-d H:i:s')
        ];
        
        return response()->json($response);
    }
    
    /**
     * Check if request is authenticated
     */
    private function isAuthenticated(Request $request): bool
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader) {
            return false;
        }
        
        $token = str_replace('Bearer ', '', $authHeader);
        
        // Accept any token that starts with mock_pat_
        return str_starts_with($token, 'mock_pat_');
    }
    
    /**
     * Check if user has required permissions
     */
    private function hasPermission(Request $request, array $permissions): bool
    {
        // In mock server, we assume authenticated users have all permissions
        // In real implementation, you'd check actual user permissions
        return $this->isAuthenticated($request);
    }
    
    /**
     * Get categories list
     */
    private function getCategories(): array
    {
        return [
            ['name' => 'Aerial Equipment', 'slug' => 'aerial_equipment', 'subcategories' => [
                ['name' => 'Boom Lift', 'slug' => 'boom_lift'],
                ['name' => 'Electric Boom Lift', 'slug' => 'electric_boom_lift'],
                ['name' => 'Electric Scissor Lift', 'slug' => 'electric_scissor_lift'],
                ['name' => 'Man Lift', 'slug' => 'man_lift'],
                ['name' => 'Rough Terrain Scissor Lift', 'slug' => 'rough_terrain_scissor_lift']
            ]],
            ['name' => 'Aggregate Equipment', 'slug' => 'aggregate_equipment', 'subcategories' => [
                ['name' => 'Box Screener', 'slug' => 'box_screener'],
                ['name' => 'Cone Crusher', 'slug' => 'cone_crusher'],
                ['name' => 'Impact Crusher', 'slug' => 'impact_crusher'],
                ['name' => 'Jaw Crusher', 'slug' => 'jaw_crusher'],
                ['name' => 'Radial Stacking Conveyor', 'slug' => 'radial_stacking_conveyor'],
                ['name' => 'Stacking Conveyor', 'slug' => 'stacking_conveyor'],
                ['name' => 'Three Product Screener', 'slug' => 'three_product_screener'],
                ['name' => 'Trommel Screener', 'slug' => 'trommel_screener']
            ]],
            ['name' => 'Agricultural Equipment', 'slug' => 'agricultural_equipment', 'subcategories' => [
                ['name' => 'Tractor', 'slug' => 'tractor'],
                ['name' => 'Tractor Attachment', 'slug' => 'tractor_attachment']
            ]],
            ['name' => 'Large Miscellaneous', 'slug' => 'large_miscellaneous', 'subcategories' => []],
            ['name' => 'Cars and Light Duty Trucks', 'slug' => 'cars_and_light_duty_trucks', 'subcategories' => [
                ['name' => 'Automobile', 'slug' => 'automobile'],
                ['name' => 'Dump Truck (up to 3500 class)', 'slug' => 'dump_truck'],
                ['name' => 'Flatbed Truck', 'slug' => 'flatbed_truck'],
                ['name' => 'Pickup (up to 3500 class) ', 'slug' => 'pickup'],
                ['name' => 'Sport Utility Vehicle', 'slug' => 'sport_utility_vehicle'],
                ['name' => 'Utility Truck (up to 3500 class)', 'slug' => 'utility_truck']
            ]],
            ['name' => 'Excavators', 'slug' => 'excavators', 'subcategories' => [
                ['name' => 'Hydraulic Excavator', 'slug' => 'hydraulic_excavator'],
                ['name' => 'Mini Excavator (up to 12,000 lb.)', 'slug' => 'mini_excavator'],
                ['name' => 'Rubber Tire Excavator', 'slug' => 'rubber_tire_excavator']
            ]],
            ['name' => 'Forklifts', 'slug' => 'forklifts', 'subcategories' => [
                ['name' => 'Forklift', 'slug' => 'forklift'],
                ['name' => 'Rough Terrain Forklift', 'slug' => 'rough_terrain_forklift'],
                ['name' => 'Telescopic Forklift', 'slug' => 'telescopic_forklift']
            ]],
            ['name' => 'Skid Steers', 'slug' => 'skid_steers', 'subcategories' => [
                ['name' => 'Skidsteer', 'slug' => 'skidsteer'],
                ['name' => 'Track Skidsteer', 'slug' => 'track_skidsteer']
            ]],
            ['name' => 'Wheel Loaders', 'slug' => 'wheel_loaders', 'subcategories' => [
                ['name' => 'Rubber Tire Wheel Loader', 'slug' => 'rubber_tire_wheel_loader']
            ]],
            ['name' => 'Uncategorized', 'slug' => 'uncategorized', 'subcategories' => [
                ['name' => 'Uncategorized', 'slug' => 'uncategorized']
            ]]
        ];
    }
}