<?php

namespace Tests\Feature\TestScenarios;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use MockServer\TestScenarios\Services\TestSessionService;
use MockServer\TestScenarios\Services\CameraPerformanceTracker;

class CameraPerformanceAnalysisTest extends TestCase
{
    private TestSessionService $sessionService;
    private CameraPerformanceTracker $tracker;
    private string $testSessionId;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sessionService = app(TestSessionService::class);
        $this->tracker = app(CameraPerformanceTracker::class);
        
        // Create a test session with camera-performance-test scenario
        $sessionData = $this->sessionService->createSession('camera-performance-test', [
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
        $response = $this->getJson('/api/test-scenarios/camera-performance/analysis');
        
        $response->assertStatus(400)
            ->assertJson([
                'error' => 'No test session ID provided',
                'message' => 'Please provide X-Test-Session-ID header or test_session_id query parameter'
            ]);
    }
    
    public function test_analysis_endpoint_validates_session_exists()
    {
        $response = $this->getJson('/api/test-scenarios/camera-performance/analysis?test_session_id=non_existent_session');
        
        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Session not found',
                'session_id' => 'non_existent_session'
            ]);
    }
    
    public function test_analysis_endpoint_requires_camera_performance_scenario()
    {
        // Create a session with a different scenario
        $defaultSession = $this->sessionService->createSession('default', ['test' => true]);
        $defaultSessionId = $defaultSession['session_id'];
        
        $response = $this->getJson("/api/test-scenarios/camera-performance/analysis?test_session_id={$defaultSessionId}");
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid scenario',
                'current_scenario' => 'default',
                'required_scenario' => 'camera-performance-test'
            ]);
        
        // Clean up
        $this->sessionService->destroySession($defaultSessionId);
    }
    
    public function test_analysis_endpoint_returns_empty_data_when_no_tracking()
    {
        // Initialize tracker for the session
        $this->tracker->initializeSession($this->testSessionId);
        
        $response = $this->getJson("/api/test-scenarios/camera-performance/analysis?test_session_id={$this->testSessionId}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'session_id',
                    'started_at',
                    'analysis_at',
                    'upload_requests' => [
                        'total_requests',
                        'total_media_items',
                        'unique_media_items',
                        'duplicate_items',
                        'unique_identifiers',
                        'duplicates'
                    ],
                    'changes_requests' => [
                        'total_requests',
                        'total_media_items',
                        'unique_media_items',
                        'unique_identifiers'
                    ],
                    'timeline'
                ],
                'summary'
            ])
            ->assertJsonPath('data.upload_requests.total_media_items', 0)
            ->assertJsonPath('data.changes_requests.total_media_items', 0);
    }
    
    public function test_analysis_endpoint_tracks_upload_requests()
    {
        // Initialize tracker and add some upload data
        $this->tracker->initializeSession($this->testSessionId);
        
        // Simulate upload requests
        $mediaItems1 = [
            ['identifier' => 'upload_1', 'filename' => 'image1.jpg', 'content_type' => 'image/jpeg', 'size' => 100000],
            ['identifier' => 'upload_2', 'filename' => 'image2.jpg', 'content_type' => 'image/jpeg', 'size' => 200000],
        ];
        
        $mediaItems2 = [
            ['identifier' => 'upload_3', 'filename' => 'image3.jpg', 'content_type' => 'image/jpeg', 'size' => 150000],
            ['identifier' => 'upload_2', 'filename' => 'image2.jpg', 'content_type' => 'image/jpeg', 'size' => 200000], // Duplicate
        ];
        
        $this->tracker->trackUploadRequest($this->testSessionId, $mediaItems1);
        $this->tracker->trackUploadRequest($this->testSessionId, $mediaItems2);
        
        $response = $this->getJson("/api/test-scenarios/camera-performance/analysis?test_session_id={$this->testSessionId}");
        
        $response->assertStatus(200)
            ->assertJsonPath('data.upload_requests.total_requests', 2)
            ->assertJsonPath('data.upload_requests.total_media_items', 4)
            ->assertJsonPath('data.upload_requests.unique_media_items', 3)
            ->assertJsonPath('data.upload_requests.duplicate_items', 1)
            ->assertJsonPath('summary.total_unique_uploads', 3)
            ->assertJsonPath('summary.total_duplicate_uploads', 1)
            ->assertJsonPath('summary.has_duplicates', true)
            ->assertJsonPath('summary.duplicate_upload_identifiers.upload_2', 2);
    }
    
    public function test_analysis_endpoint_tracks_changes_requests()
    {
        // Initialize tracker and add some changes data
        $this->tracker->initializeSession($this->testSessionId);
        
        // Simulate changes requests
        $changes1 = [
            'media' => [
                ['temp_id' => 'temp_1', 'action' => 'create'],
                ['temp_id' => 'temp_2', 'action' => 'create'],
            ]
        ];
        
        $changes2 = [
            'media' => [
                ['temp_id' => 'temp_3', 'action' => 'create'],
            ]
        ];
        
        $this->tracker->trackChangesRequest($this->testSessionId, $changes1);
        $this->tracker->trackChangesRequest($this->testSessionId, $changes2);
        
        $response = $this->getJson("/api/test-scenarios/camera-performance/analysis?test_session_id={$this->testSessionId}");
        
        $response->assertStatus(200)
            ->assertJsonPath('data.changes_requests.total_requests', 2)
            ->assertJsonPath('data.changes_requests.total_media_items', 3)
            ->assertJsonPath('data.changes_requests.unique_media_items', 3)
            ->assertJsonPath('summary.total_unique_changes', 3);
    }
    
    public function test_analysis_endpoint_with_header_session_id()
    {
        // Initialize tracker
        $this->tracker->initializeSession($this->testSessionId);
        
        $response = $this->getJson('/api/test-scenarios/camera-performance/analysis', [
            'X-Test-Session-ID' => $this->testSessionId
        ]);
        
        $response->assertStatus(200)
            ->assertJsonPath('data.session_id', $this->testSessionId);
    }
    
    public function test_clear_tracking_endpoint()
    {
        // Initialize tracker and add some data
        $this->tracker->initializeSession($this->testSessionId);
        $this->tracker->trackUploadRequest($this->testSessionId, [
            ['identifier' => 'test_upload', 'filename' => 'test.jpg']
        ]);
        
        // Verify data exists
        $response = $this->getJson("/api/test-scenarios/camera-performance/analysis?test_session_id={$this->testSessionId}");
        $response->assertJsonPath('data.upload_requests.total_media_items', 1);
        
        // Clear tracking
        $response = $this->postJson("/api/test-scenarios/camera-performance/clear?test_session_id={$this->testSessionId}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tracking data cleared and re-initialized',
                'session_id' => $this->testSessionId
            ]);
        
        // Verify data is cleared
        $response = $this->getJson("/api/test-scenarios/camera-performance/analysis?test_session_id={$this->testSessionId}");
        $response->assertJsonPath('data.upload_requests.total_media_items', 0);
    }
    
    public function test_clear_tracking_validates_scenario()
    {
        // Create a session with default scenario
        $defaultSession = $this->sessionService->createSession('default', ['test' => true]);
        $defaultSessionId = $defaultSession['session_id'];
        
        $response = $this->postJson("/api/test-scenarios/camera-performance/clear?test_session_id={$defaultSessionId}");
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid scenario',
                'current_scenario' => 'default',
                'required_scenario' => 'camera-performance-test'
            ]);
        
        // Clean up
        $this->sessionService->destroySession($defaultSessionId);
    }
    
    public function test_real_world_scenario_with_25_unique_uploads()
    {
        // Initialize tracker
        $this->tracker->initializeSession($this->testSessionId);
        
        // Simulate 25 unique upload requests (like in the real scenario)
        for ($i = 1; $i <= 25; $i++) {
            $this->tracker->trackUploadRequest($this->testSessionId, [
                [
                    'identifier' => "unique_id_{$i}",
                    'filename' => "media_{$i}.jpg",
                    'content_type' => 'image/jpeg',
                    'size' => 200000 + $i
                ]
            ]);
        }
        
        // Add some duplicate uploads (to simulate the issue)
        $this->tracker->trackUploadRequest($this->testSessionId, [
            ['identifier' => 'unique_id_5', 'filename' => 'media_5.jpg', 'content_type' => 'image/jpeg', 'size' => 200005],
            ['identifier' => 'unique_id_10', 'filename' => 'media_10.jpg', 'content_type' => 'image/jpeg', 'size' => 200010],
            ['identifier' => 'unique_id_15', 'filename' => 'media_15.jpg', 'content_type' => 'image/jpeg', 'size' => 200015],
        ]);
        
        $response = $this->getJson("/api/test-scenarios/camera-performance/analysis?test_session_id={$this->testSessionId}");
        
        $response->assertStatus(200)
            ->assertJsonPath('data.upload_requests.total_requests', 26) // 25 unique + 1 with duplicates
            ->assertJsonPath('data.upload_requests.total_media_items', 28) // 25 + 3 duplicates
            ->assertJsonPath('data.upload_requests.unique_media_items', 25) // Only 25 unique
            ->assertJsonPath('data.upload_requests.duplicate_items', 3)
            ->assertJsonPath('summary.total_unique_uploads', 25)
            ->assertJsonPath('summary.total_duplicate_uploads', 3)
            ->assertJsonPath('summary.has_duplicates', true)
            ->assertJsonPath('summary.duplicate_upload_identifiers.unique_id_5', 2)
            ->assertJsonPath('summary.duplicate_upload_identifiers.unique_id_10', 2)
            ->assertJsonPath('summary.duplicate_upload_identifiers.unique_id_15', 2);
        
        // Verify the summary shows exactly what we expect
        $data = $response->json('data');
        $this->assertCount(25, $data['upload_requests']['unique_identifiers']);
    }
}