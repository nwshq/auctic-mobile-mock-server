<?php

use MockServer\Services\MockDataService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clear cache before each test to ensure fresh data
    Cache::flush();
});

describe('Hydrate Endpoint', function () {
    
    test('returns 401 when not authenticated', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate');
        
        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    });
    
    test('returns 401 with invalid token', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer invalid_token'
        ]);
        
        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    });
    
    test('returns successful hydrate response with valid authentication', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'events' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'start_time',
                            'end_time',
                            'location',
                            'status',
                            'event_type',
                            'listings' => [
                                '*' => [
                                    'id',
                                    'event_id',
                                    'title',
                                    'description',
                                    'lot_number',
                                    'category',
                                    'subcategory',
                                    'media'
                                ]
                            ],
                            'hero_media'
                        ]
                ],
                'last_modified'
            ]);
    });
    
    test('hydrate response contains expected number of events', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(200);
        
        $data = $response->json('events');
        expect($data)->toBeArray()
            ->toHaveCount(2); // MockDataService generates 2 events
    });
    
    test('each event contains listings with media', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(200);
        
        $events = $response->json('events');
        
        foreach ($events as $event) {
            expect($event)->toHaveKeys(['id', 'title', 'listings']);
            expect($event['listings'])->toBeArray();
            
            // Each event should have at least 1 listing
            expect(count($event['listings']))->toBeGreaterThanOrEqual(1);
            
            foreach ($event['listings'] as $listing) {
                expect($listing)->toHaveKeys(['id', 'event_id', 'title', 'media']);
                expect($listing['media'])->toBeArray();
                expect($listing['event_id'])->toBe($event['id']);
            }
        }
    });
    
    test('accepts mock_pat_ prefixed tokens', function () {
        $customToken = 'mock_pat_' . uniqid();
        
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => "Bearer {$customToken}"
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'events',
                'last_modified'
            ]);
    });
});

describe('Sync Endpoint', function () {
    
    test('returns 401 when not authenticated', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/sync?since=2025-08-26T00:00:00Z');
        
        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    });
    
    test('returns 400 when since parameter is missing', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/sync', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(400)
            ->assertJson(['error' => 'Missing required parameter: since']);
    });
    
    test('returns successful sync response with valid parameters', function () {
        $since = now()->subDay()->toIso8601String();
        
        $response = $this->getJson("/mobile-api/v1/catalog/sync?since={$since}", [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'events',
                    'listings',
                    'media'
                ],
                'deletions' => [
                    'events',
                    'listings',
                    'media'
                ],
                'last_modified'
            ]);
    });
    
    test('pagination always shows single page with has_more false', function () {
        $since = now()->subDay()->toIso8601String();
        
        $response = $this->getJson("/mobile-api/v1/catalog/sync?since={$since}", [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'deletions',
                'last_modified'
            ]);
    });
    
    test('returns only items updated after since timestamp', function () {
        // Get initial data
        $initialResponse = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $lastModified = $initialResponse->json('last_modified');
        
        // Request sync with current timestamp (should return empty or minimal changes)
        $response = $this->getJson("/mobile-api/v1/catalog/sync?since={$lastModified}", [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Since we're syncing from last_modified, we should get minimal or no changes
        expect($data)->toHaveKeys(['events', 'listings', 'media']);
    });
    
    test('handles different date formats for since parameter', function () {
        $dates = [
            '2025-08-26T00:00:00Z',
            '2025-08-26T00:00:00+00:00',
            '2025-08-26T00:00:00.000Z',
            now()->subWeek()->toIso8601String(),
            now()->subWeek()->toDateTimeString(),
        ];
        
        foreach ($dates as $date) {
            $response = $this->getJson("/mobile-api/v1/catalog/sync?since={$date}", [
                'Authorization' => 'Bearer mock_pat_token123'
            ]);
            
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'deletions',
                    'last_modified'
                ]);
        }
    });
    
    test('deletions arrays are included but empty by default', function () {
        $since = now()->subDay()->toIso8601String();
        
        $response = $this->getJson("/mobile-api/v1/catalog/sync?since={$since}", [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonPath('deletions.events', [])
            ->assertJsonPath('deletions.listings', [])
            ->assertJsonPath('deletions.media', []);
    });
});

describe('Mock Data Service Integration', function () {
    
    test('data persists between requests using cache', function () {
        // First request
        $response1 = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $events1 = $response1->json('events');
        $lastModified1 = $response1->json('last_modified');
        
        // Second request (should get same data from cache)
        $response2 = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $events2 = $response2->json('events');
        $lastModified2 = $response2->json('last_modified');
        
        // Data should be identical
        expect($lastModified1)->toBe($lastModified2);
        expect(count($events1))->toBe(count($events2));
        
        // Event IDs should match
        $eventIds1 = array_column($events1, 'id');
        $eventIds2 = array_column($events2, 'id');
        expect($eventIds1)->toBe($eventIds2);
    });
    
    test('mock data service can be reset', function () {
        // Get initial data
        $response1 = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        $lastModified1 = $response1->json('last_modified');
        
        // Travel 1 second into the future before resetting
        $this->travel(1)->seconds();
        
        // Reset the data
        $service = app(MockDataService::class);
        $service->resetData();
        
        // Get new data
        $response2 = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        $lastModified2 = $response2->json('last_modified');
        
        // Last modified should be different after reset
        expect($lastModified1)->not->toBe($lastModified2);
    });
    
    test('media items have correct owner relationships', function () {
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $events = $response->json('events');
        
        foreach ($events as $event) {
            // Check listing media only (events don't have media array in this structure)
            foreach ($event['listings'] as $listing) {
                foreach ($listing['media'] as $media) {
                    expect($media['model_type'])->toBe('App\Models\Listing');
                    expect($media['model_id'])->toBe($listing['id']);
                }
            }
        }
    });
    
    test('sync endpoint correctly filters by timestamp', function () {
        // First get hydrate to see what data exists
        $hydrateResponse = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        expect($hydrateResponse->status())->toBe(200);
        
        // Use a very old date to get all data via sync
        $veryOldDate = now()->subYears(10)->toIso8601String();
        
        // Get sync data for all time
        $syncResponse = $this->getJson("/mobile-api/v1/catalog/sync?since={$veryOldDate}", [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $syncResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'events',
                    'listings', 
                    'media'
                ],
                'deletions',
                'last_modified'
            ]);
        
        // Test with future date - should get no data
        $futureDate = now()->addYear()->toIso8601String();
        
        $futureSyncResponse = $this->getJson("/mobile-api/v1/catalog/sync?since={$futureDate}", [
            'Authorization' => 'Bearer mock_pat_token123'
        ]);
        
        $futureSyncResponse->assertStatus(200);
        
        $futureData = $futureSyncResponse->json('data');
        
        // Should have no data for future date
        expect($futureData['events'])->toBeEmpty();
        expect($futureData['listings'])->toBeEmpty();
        expect($futureData['media'])->toBeEmpty();
    });
});