<?php

namespace Tests\Feature\TestScenarios;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use MockServer\TestScenarios\Services\TestSessionService;
use MockServer\TestScenarios\Services\RotationTestTracker;

class RotationTestAnalysisTest extends TestCase
{
    private TestSessionService $sessionService;
    private RotationTestTracker $tracker;
    private string $testSessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionService = app(TestSessionService::class);
        $this->tracker = app(RotationTestTracker::class);

        // Create a test session with rotation-test scenario
        $sessionData = $this->sessionService->createSession('rotation-test', [
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
        $response = $this->getJson('/api/test-scenarios/rotation-test/analysis');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'No test session ID provided',
                'message' => 'Please provide X-Test-Session-ID header or test_session_id query parameter'
            ]);
    }

    public function test_analysis_endpoint_validates_session_exists()
    {
        $response = $this->getJson('/api/test-scenarios/rotation-test/analysis?test_session_id=non_existent_session');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Session not found',
                'session_id' => 'non_existent_session'
            ]);
    }

    public function test_analysis_endpoint_requires_rotation_test_scenario()
    {
        // Create a session with a different scenario
        $defaultSession = $this->sessionService->createSession('default', ['test' => true]);
        $defaultSessionId = $defaultSession['session_id'];

        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$defaultSessionId}");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid scenario',
                'current_scenario' => 'default',
                'required_scenario' => 'rotation-test'
            ]);

        // Clean up
        $this->sessionService->destroySession($defaultSessionId);
    }

    public function test_analysis_endpoint_returns_empty_data_when_no_tracking()
    {
        // Initialize tracker for the session
        $this->tracker->initializeSession($this->testSessionId);

        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'session_id',
                    'started_at',
                    'analysis_at',
                    'media_changes' => [
                        'total_changes',
                        'total_added',
                        'total_removed',
                        'unique_added',
                        'unique_removed',
                        'added_identifiers',
                        'removed_identifiers',
                        'matches_expected_pattern',
                        'expected_pattern'
                    ],
                    'rotation_events' => [
                        'total_events',
                        'events'
                    ],
                    'timeline'
                ],
                'summary'
            ])
            ->assertJsonPath('data.media_changes.total_added', 0)
            ->assertJsonPath('data.media_changes.total_removed', 0);
    }

    public function test_analysis_tracks_expected_pattern_one_added_one_removed()
    {
        // Initialize tracker and add media changes
        $this->tracker->initializeSession($this->testSessionId);

        // Track 1 added and 1 removed (expected pattern)
        $this->tracker->trackMediaChanges($this->testSessionId, [
            'added' => [
                ['identifier' => 'new_media_001']
            ],
            'removed' => [
                ['identifier' => 'old_media_001']
            ]
        ]);

        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.media_changes.total_added', 1)
            ->assertJsonPath('data.media_changes.total_removed', 1)
            ->assertJsonPath('data.media_changes.unique_added', 1)
            ->assertJsonPath('data.media_changes.unique_removed', 1)
            ->assertJsonPath('data.media_changes.matches_expected_pattern', true)
            ->assertJsonPath('summary.matches_expected_pattern', true)
            ->assertJsonPath('summary.expected_pattern', '1 new media and 1 removed');
    }

    public function test_analysis_detects_pattern_mismatch()
    {
        // Initialize tracker and add media changes
        $this->tracker->initializeSession($this->testSessionId);

        // Track multiple added and removed (not matching expected pattern)
        $this->tracker->trackMediaChanges($this->testSessionId, [
            'added' => [
                ['identifier' => 'new_media_001'],
                ['identifier' => 'new_media_002']
            ],
            'removed' => [
                ['identifier' => 'old_media_001'],
                ['identifier' => 'old_media_002'],
                ['identifier' => 'old_media_003']
            ]
        ]);

        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.media_changes.total_added', 2)
            ->assertJsonPath('data.media_changes.total_removed', 3)
            ->assertJsonPath('data.media_changes.unique_added', 2)
            ->assertJsonPath('data.media_changes.unique_removed', 3)
            ->assertJsonPath('data.media_changes.matches_expected_pattern', false)
            ->assertJsonPath('summary.matches_expected_pattern', false);
    }

    public function test_analysis_tracks_rotation_events()
    {
        // Initialize tracker
        $this->tracker->initializeSession($this->testSessionId);

        // Track rotation events
        $this->tracker->trackRotationEvent($this->testSessionId, [
            'orientation' => 'portrait',
            'media_state' => []
        ]);

        $this->tracker->trackRotationEvent($this->testSessionId, [
            'orientation' => 'landscape',
            'media_state' => []
        ]);

        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.rotation_events.total_events', 2)
            ->assertJsonPath('summary.rotation_events_count', 2);

        $events = $response->json('data.rotation_events.events');
        $this->assertEquals('portrait', $events[0]['orientation']);
        $this->assertEquals('landscape', $events[1]['orientation']);
    }

    public function test_analysis_endpoint_with_header_session_id()
    {
        // Initialize tracker
        $this->tracker->initializeSession($this->testSessionId);

        $response = $this->getJson('/api/test-scenarios/rotation-test/analysis', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.session_id', $this->testSessionId);
    }

    public function test_clear_tracking_endpoint()
    {
        // Initialize tracker and add some data
        $this->tracker->initializeSession($this->testSessionId);
        $this->tracker->trackMediaChanges($this->testSessionId, [
            'added' => [['identifier' => 'test_media']],
            'removed' => []
        ]);

        // Verify data exists
        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");
        $response->assertJsonPath('data.media_changes.total_added', 1);

        // Clear tracking
        $response = $this->postJson("/api/test-scenarios/rotation-test/clear?test_session_id={$this->testSessionId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tracking data cleared and re-initialized',
                'session_id' => $this->testSessionId
            ]);

        // Verify data is cleared
        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");
        $response->assertJsonPath('data.media_changes.total_added', 0);
    }

    public function test_clear_tracking_validates_scenario()
    {
        // Create a session with default scenario
        $defaultSession = $this->sessionService->createSession('default', ['test' => true]);
        $defaultSessionId = $defaultSession['session_id'];

        $response = $this->postJson("/api/test-scenarios/rotation-test/clear?test_session_id={$defaultSessionId}");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid scenario',
                'current_scenario' => 'default',
                'required_scenario' => 'rotation-test'
            ]);

        // Clean up
        $this->sessionService->destroySession($defaultSessionId);
    }

    public function test_complete_timeline_tracking()
    {
        // Initialize tracker
        $this->tracker->initializeSession($this->testSessionId);

        // First change: add a media
        $this->tracker->trackMediaChanges($this->testSessionId, [
            'added' => [['identifier' => 'media_001']],
            'removed' => []
        ]);

        // Second change: remove a media
        $this->tracker->trackMediaChanges($this->testSessionId, [
            'added' => [],
            'removed' => [['identifier' => 'media_002']]
        ]);

        // Third change: add and remove simultaneously
        $this->tracker->trackMediaChanges($this->testSessionId, [
            'added' => [['identifier' => 'media_003']],
            'removed' => [['identifier' => 'media_004']]
        ]);

        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");

        $response->assertStatus(200);

        $timeline = $response->json('data.timeline');
        $this->assertCount(3, $timeline['media_changes']);
        $this->assertCount(2, $timeline['added_media']);
        $this->assertCount(2, $timeline['removed_media']);

        // Verify the timeline entries have the expected structure
        $this->assertEquals(1, $timeline['media_changes'][0]['added_count']);
        $this->assertEquals(0, $timeline['media_changes'][0]['removed_count']);
        $this->assertEquals(0, $timeline['media_changes'][1]['added_count']);
        $this->assertEquals(1, $timeline['media_changes'][1]['removed_count']);
        $this->assertEquals(1, $timeline['media_changes'][2]['added_count']);
        $this->assertEquals(1, $timeline['media_changes'][2]['removed_count']);
    }

    public function test_real_rotation_scenario()
    {
        // Initialize tracker
        $this->tracker->initializeSession($this->testSessionId);

        // Simulate a real rotation scenario:
        // 1. User takes a photo in portrait
        $this->tracker->trackRotationEvent($this->testSessionId, [
            'orientation' => 'portrait',
            'media_state' => ['media_001']
        ]);

        // 2. User rotates to landscape
        $this->tracker->trackRotationEvent($this->testSessionId, [
            'orientation' => 'landscape',
            'media_state' => ['media_001']
        ]);

        // 3. During rotation, one media is added and one is removed
        $this->tracker->trackMediaChanges($this->testSessionId, [
            'added' => [['identifier' => 'media_002']],
            'removed' => [['identifier' => 'media_001']]
        ]);

        // 4. User rotates back to portrait
        $this->tracker->trackRotationEvent($this->testSessionId, [
            'orientation' => 'portrait',
            'media_state' => ['media_002']
        ]);

        $response = $this->getJson("/api/test-scenarios/rotation-test/analysis?test_session_id={$this->testSessionId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.media_changes.matches_expected_pattern', true)
            ->assertJsonPath('data.rotation_events.total_events', 3)
            ->assertJsonPath('summary.total_unique_added', 1)
            ->assertJsonPath('summary.total_unique_removed', 1)
            ->assertJsonPath('summary.rotation_events_count', 3);
    }
}