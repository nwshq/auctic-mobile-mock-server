<?php

namespace Tests\Feature\TestScenarios;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use MockServer\TestScenarios\Services\TestSessionService;
use MockServer\TestScenarios\Services\RemoveListingTestTracker;

class RemoveListingTestAnalysisTest extends TestCase
{
    private TestSessionService $sessionService;
    private RemoveListingTestTracker $tracker;
    private string $testSessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionService = app(TestSessionService::class);
        $this->tracker = app(RemoveListingTestTracker::class);

        // Create a test session with remove-listing-test scenario
        $sessionData = $this->sessionService->createSession('remove-listing-test', [
            'test' => true
        ]);
        $this->testSessionId = $sessionData['session_id'];
    }

    protected function tearDown(): void
    {
        // Clean up test session
        $this->sessionService->destroySession($this->testSessionId);
        $this->tracker->clearSession($this->testSessionId);

        parent::tearDown();
    }

    public function test_analysis_endpoint_requires_session_id()
    {
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'No test session ID provided',
                'message' => 'Please provide X-Test-Session-ID header or test_session_id query parameter'
            ]);
    }

    public function test_analysis_endpoint_validates_session_exists()
    {
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis', [
            'X-Test-Session-ID' => 'non-existent-session'
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Session not found',
                'session_id' => 'non-existent-session',
                'message' => 'The test session may have expired or does not exist'
            ]);
    }

    public function test_analysis_endpoint_validates_correct_scenario()
    {
        // Create a session with a different scenario
        $wrongSession = $this->sessionService->createSession('default', [
            'test' => true
        ]);

        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis', [
            'X-Test-Session-ID' => $wrongSession['session_id']
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid scenario',
                'current_scenario' => 'default',
                'required_scenario' => 'remove-listing-test'
            ]);

        // Clean up
        $this->sessionService->destroySession($wrongSession['session_id']);
    }

    public function test_analysis_returns_no_data_when_no_tracking_exists()
    {
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'No tracking data available',
                'session_id' => $this->testSessionId,
                'message' => 'No listing removals have been tracked for this session'
            ]);
    }

    public function test_tracking_single_listing_removal()
    {
        // Initialize the tracker session
        $this->tracker->initializeSession($this->testSessionId);

        // Track a single listing removal with media
        $this->tracker->trackRemovalChanges($this->testSessionId, [
            'removed_listings' => [
                [
                    'identifier' => 'listing-001',
                    'title' => 'Test Listing',
                    'status' => 'active'
                ]
            ],
            'removed_media' => [
                ['identifier' => 'media-001', 'listing_id' => 'listing-001'],
                ['identifier' => 'media-002', 'listing_id' => 'listing-001'],
                ['identifier' => 'media-003', 'listing_id' => 'listing-001']
            ]
        ]);

        // Get analysis
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_listings_removed' => 1,
                    'total_media_removed' => 3,
                    'matches_expected_pattern' => true,
                    'expected_pattern' => '1 listing removed with all associated media',
                    'test_passed' => true
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['removal_summary']['unique_listings_removed']);
        $this->assertEquals(3, $data['removal_summary']['unique_media_removed']);
        $this->assertTrue($data['test_result']['success']);
        $this->assertStringContainsString('Test passed', $data['test_result']['message']);
    }

    public function test_tracking_multiple_listing_removals()
    {
        // Initialize the tracker session
        $this->tracker->initializeSession($this->testSessionId);

        // Track multiple listing removals
        $this->tracker->trackRemovalChanges($this->testSessionId, [
            'removed_listings' => [
                ['identifier' => 'listing-001', 'title' => 'First Listing'],
                ['identifier' => 'listing-002', 'title' => 'Second Listing']
            ],
            'removed_media' => [
                ['identifier' => 'media-001', 'listing_id' => 'listing-001'],
                ['identifier' => 'media-002', 'listing_id' => 'listing-002']
            ]
        ]);

        // Get analysis
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(2, $data['removal_summary']['unique_listings_removed']);
        $this->assertEquals(2, $data['removal_summary']['unique_media_removed']);
        $this->assertFalse($data['test_result']['success']); // Should fail - expects 1 listing
        $this->assertStringContainsString('Test failed', $data['test_result']['message']);
    }

    public function test_timeline_endpoint_returns_detailed_history()
    {
        // Initialize the tracker session
        $this->tracker->initializeSession($this->testSessionId);

        // Track removals at different times
        $this->tracker->trackRemovalChanges($this->testSessionId, [
            'removed_listings' => [
                ['identifier' => 'listing-001', 'title' => 'Test Listing']
            ],
            'removed_media' => [
                ['identifier' => 'media-001', 'listing_id' => 'listing-001']
            ]
        ]);

        sleep(1); // Add delay to see different timestamps

        $this->tracker->trackRemovalChanges($this->testSessionId, [
            'removed_media' => [
                ['identifier' => 'media-002', 'listing_id' => 'listing-001']
            ]
        ]);

        // Get timeline
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/timeline', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'timeline' => [
                    'removal_changes',
                    'removed_listings',
                    'removed_media'
                ],
                'media_by_listing'
            ]);

        $timeline = $response->json('timeline');
        $this->assertCount(2, $timeline['removal_changes']);
        $this->assertCount(1, $timeline['removed_listings']);
        $this->assertCount(2, $timeline['removed_media']);
    }

    public function test_clear_tracking_endpoint()
    {
        // Initialize and add some tracking data
        $this->tracker->initializeSession($this->testSessionId);
        $this->tracker->trackRemovalChanges($this->testSessionId, [
            'removed_listings' => [
                ['identifier' => 'listing-001']
            ]
        ]);

        // Verify data exists
        $analysis = $this->tracker->getAnalysis($this->testSessionId);
        $this->assertNotNull($analysis);

        // Clear tracking
        $response = $this->postJson('/api/test-scenarios/remove-listing-test/clear', [], [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tracking data cleared and re-initialized',
                'session_id' => $this->testSessionId
            ]);

        // Verify data was cleared and re-initialized
        $newAnalysis = $this->tracker->getAnalysis($this->testSessionId);
        $this->assertNotNull($newAnalysis);
        $this->assertEquals(0, $newAnalysis['removal_summary']['total_listings_removed']);
        $this->assertEquals(0, $newAnalysis['removal_summary']['total_media_removed']);
    }

    public function test_media_grouping_by_listing()
    {
        // Initialize the tracker session
        $this->tracker->initializeSession($this->testSessionId);

        // Track removals with media from different listings
        $this->tracker->trackRemovalChanges($this->testSessionId, [
            'removed_listings' => [
                ['identifier' => 'listing-001'],
                ['identifier' => 'listing-002']
            ],
            'removed_media' => [
                ['identifier' => 'media-001', 'listing_id' => 'listing-001'],
                ['identifier' => 'media-002', 'listing_id' => 'listing-001'],
                ['identifier' => 'media-003', 'listing_id' => 'listing-002'],
                ['identifier' => 'media-004', 'listing_id' => 'listing-002'],
                ['identifier' => 'media-005', 'listing_id' => 'listing-002']
            ]
        ]);

        // Get analysis
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(200);

        $mediaByListing = $response->json('data.media_by_listing');
        $this->assertCount(2, $mediaByListing['listing-001']);
        $this->assertCount(3, $mediaByListing['listing-002']);

        $avgMedia = $response->json('summary.avg_media_per_listing');
        $this->assertEquals(2.5, $avgMedia);
    }

    public function test_removal_event_tracking()
    {
        // Initialize the tracker session
        $this->tracker->initializeSession($this->testSessionId);

        // Track a removal event
        $this->tracker->trackRemovalEvent($this->testSessionId, [
            'action' => 'swipe_to_delete',
            'target_listing' => 'listing-001',
            'associated_media_count' => 3,
            'context' => [
                'user_action' => 'manual_deletion',
                'screen' => 'listing_detail'
            ]
        ]);

        // Track removal changes
        $this->tracker->trackRemovalChanges($this->testSessionId, [
            'removed_listings' => [
                ['identifier' => 'listing-001']
            ],
            'removed_media' => [
                ['identifier' => 'media-001', 'listing_id' => 'listing-001'],
                ['identifier' => 'media-002', 'listing_id' => 'listing-001'],
                ['identifier' => 'media-003', 'listing_id' => 'listing-001']
            ]
        ]);

        // Get analysis
        $response = $this->getJson('/api/test-scenarios/remove-listing-test/analysis', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(200);

        $events = $response->json('data.removal_events.events');
        $this->assertCount(1, $events);
        $this->assertEquals('swipe_to_delete', $events[0]['action']);
        $this->assertEquals('listing-001', $events[0]['target_listing']);
        $this->assertEquals(3, $events[0]['associated_media_count']);
    }
}