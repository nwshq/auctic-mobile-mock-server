<?php

namespace Tests\Feature\TestScenarios;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CameraPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear any existing test sessions
        Cache::flush();
    }

    /**
     * Test camera performance scenario is available
     */
    public function test_camera_performance_scenario_is_available()
    {
        $response = $this->getJson('/api/test-scenarios/available');

        $response->assertStatus(200);
        
        $scenarios = $response->json('scenarios');
        $scenarioNames = array_column($scenarios, 'name');
        
        $this->assertContains('camera-performance-test', $scenarioNames);
    }

    /**
     * Test activating camera performance scenario
     */
    public function test_can_activate_camera_performance_scenario()
    {
        $response = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'camera-performance-test',
            'metadata' => [
                'test_name' => 'camera_test',
                'test_suite' => 'performance_tests'
            ]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'scenario' => 'camera-performance-test',
                'is_generic' => false
            ]);
        
        $this->assertStringStartsWith('maestro_session_', $response->json('session_id'));
    }

    /**
     * Test that camera performance scenario applies delay
     */
    public function test_camera_performance_applies_delay_to_changes_endpoint()
    {
        // Activate camera performance scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'camera-performance-test'
        ]);
        $sessionId = $activateResponse->json('session_id');
        
        // Measure time for request with delay
        $startTime = microtime(true);
        
        $response = $this->postJson('/mobile-api/v1/catalog/changes', [
            'changes' => [
                ['field' => 'test', 'value' => 'data']
            ]
        ], [
            'Authorization' => 'Bearer mock_pat_test123',
            'X-Test-Session-ID' => $sessionId
        ]);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should take at least 5 seconds due to delay
        $this->assertGreaterThanOrEqual(5, $duration);
        
        // Request should still succeed
        $response->assertStatus(200);
    }

    /**
     * Test that camera performance scenario adds logging
     */
    public function test_camera_performance_adds_logging()
    {
        // Mock the Log facade to capture log calls
        // First mock the channel call for test_scenarios
        Log::shouldReceive('channel')
            ->with('test_scenarios')
            ->andReturnSelf();
        
        // Mock the info method on the test_scenarios channel
        Log::shouldReceive('info')
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Processing test scenario request');
            })
            ->once();
        
        // Mock the actual logging from CameraPerformanceStrategy
        Log::shouldReceive('info')
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[UPLOAD-REQUEST-IN]') ||
                       str_contains($message, '[CHANGES-REQUEST]') ||
                       str_contains($message, '[S3-UPLOAD]');
            })
            ->once(); 
        
        // Activate camera performance scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'camera-performance-test'
        ]);
        $sessionId = $activateResponse->json('session_id');
        
        // Make a request that should trigger logging
        $this->postJson('/mobile-api/v1/catalog/request-upload', [
            'media' => [
                [
                    'identifier' => 'test-123',
                    'filename' => 'test.jpg',
                    'content_type' => 'image/jpeg',
                    'size' => 1024
                ]
            ]
        ], [
            'Authorization' => 'Bearer mock_pat_test123',
            'X-Test-Session-ID' => $sessionId
        ]);
    }

    /**
     * Test that camera performance scenario fixes last_modified timestamp
     */
    public function test_camera_performance_fixes_last_modified_timestamp()
    {
        // Activate camera performance scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'camera-performance-test'
        ]);
        $sessionId = $activateResponse->json('session_id');
        
        // Get catalog hydrate response
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_test123',
            'X-Test-Session-ID' => $sessionId
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'last_modified' => '2025-08-27 20:24:35'
            ]);
    }

    /**
     * Test that default scenario doesn't apply delays
     */
    public function test_default_scenario_no_delay()
    {
        // Activate default scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'default'
        ]);
        $sessionId = $activateResponse->json('session_id');
        
        // Measure time for request without delay
        $startTime = microtime(true);
        
        $response = $this->postJson('/mobile-api/v1/catalog/changes', [
            'changes' => [
                ['field' => 'test', 'value' => 'data']
            ]
        ], [
            'Authorization' => 'Bearer mock_pat_test123',
            'X-Test-Session-ID' => $sessionId
        ]);
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should be fast (less than 2 seconds)
        $this->assertLessThan(2, $duration);
        
        // Request should still succeed
        $response->assertStatus(200);
    }

    /**
     * Test switching from default to camera performance scenario
     */
    public function test_can_switch_to_camera_performance_scenario()
    {
        // Start with default scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'default'
        ]);
        $sessionId = $activateResponse->json('session_id');
        
        // Switch to camera performance
        $switchResponse = $this->postJson('/api/test-scenarios/switch', 
            ['scenario' => 'camera-performance-test'],
            ['X-Test-Session-ID' => $sessionId]
        );
        
        $switchResponse->assertStatus(200)
            ->assertJson([
                'scenario' => 'camera-performance-test',
                'message' => 'Scenario switched successfully'
            ]);
        
        // Verify the switch by checking current scenario
        $currentResponse = $this->getJson('/api/test-scenarios/current', [
            'X-Test-Session-ID' => $sessionId
        ]);
        
        $currentResponse->assertJson([
            'scenario' => 'camera-performance-test'
        ]);
    }
}