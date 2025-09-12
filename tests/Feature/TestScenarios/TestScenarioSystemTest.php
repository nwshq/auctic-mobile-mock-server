<?php

namespace Tests\Feature\TestScenarios;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class TestScenarioSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear any existing test sessions
        Cache::flush();
    }

    /**
     * Test listing available scenarios
     */
    public function test_can_list_available_scenarios()
    {
        $response = $this->getJson('/api/test-scenarios/available');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'scenarios' => [
                    '*' => [
                        'name',
                        'display_name',
                        'description',
                        'endpoints'
                    ]
                ]
            ]);

        $scenarios = $response->json('scenarios');
        
        // Should only contain default scenario
        $this->assertCount(1, $scenarios);
        $this->assertEquals('default', $scenarios[0]['name']);
        $this->assertEquals('Default Scenario', $scenarios[0]['display_name']);
    }

    /**
     * Test activating a test scenario
     */
    public function test_can_activate_test_scenario()
    {
        $response = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'default',
            'metadata' => [
                'test_name' => 'test_default',
                'test_suite' => 'catalog_tests'
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'session_id',
                'scenario',
                'expires_at'
            ]);

        // Always returns default scenario
        $this->assertEquals('default', $response->json('scenario'));
        $this->assertStringStartsWith('maestro_session_', $response->json('session_id'));
    }

    /**
     * Test any scenario request uses default
     */
    public function test_any_scenario_request_uses_default()
    {
        $response = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'non_existent_scenario'
        ]);

        // Should succeed with default scenario
        $response->assertStatus(201)
            ->assertJson([
                'scenario' => 'default',
                'is_generic' => true,
                'requested_scenario' => 'non_existent_scenario'
            ]);
    }

    /**
     * Test getting current scenario status
     */
    public function test_can_get_current_scenario_status()
    {
        // First activate a scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'test'
        ]);
        $sessionId = $activateResponse->json('session_id');

        // Get current status
        $response = $this->getJson('/api/test-scenarios/current', [
            'X-Test-Session-ID' => $sessionId
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'session_id' => $sessionId,
                'scenario' => 'default',
                'active' => true,
                'request_count' => 0
            ]);
    }

    /**
     * Test switching scenarios (always stays on default)
     */
    public function test_switching_scenarios_stays_on_default()
    {
        // Activate initial scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'any'
        ]);
        $sessionId = $activateResponse->json('session_id');

        // Try to switch to different scenario
        $response = $this->postJson('/api/test-scenarios/switch', 
            ['scenario' => 'another_scenario'],
            ['X-Test-Session-ID' => $sessionId]
        );

        $response->assertStatus(200)
            ->assertJson([
                'session_id' => $sessionId,
                'scenario' => 'default',
                'message' => 'Scenario switched successfully'
            ]);
    }

    /**
     * Test resetting a session
     */
    public function test_can_reset_session()
    {
        // Activate a scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'default'
        ]);
        $sessionId = $activateResponse->json('session_id');

        // Reset the session
        $response = $this->postJson('/api/test-scenarios/reset', [], [
            'X-Test-Session-ID' => $sessionId
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Session destroyed successfully'
            ]);

        // Verify session is gone
        $currentResponse = $this->getJson('/api/test-scenarios/current', [
            'X-Test-Session-ID' => $sessionId
        ]);

        $currentResponse->assertStatus(404);
    }

    /**
     * Test scenario persists across multiple requests
     */
    public function test_scenario_persists_across_requests()
    {
        // Activate scenario
        $activateResponse = $this->postJson('/api/test-scenarios/activate', [
            'scenario' => 'default'
        ]);
        $sessionId = $activateResponse->json('session_id');

        // Make multiple requests
        for ($i = 0; $i < 3; $i++) {
            $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
                'Authorization' => 'Bearer mock_pat_test123',
                'X-Test-Session-ID' => $sessionId
            ]);

            $response->assertStatus(200);
        }

        // Verify session still exists
        $statusResponse = $this->getJson('/api/test-scenarios/current', [
            'X-Test-Session-ID' => $sessionId
        ]);

        $this->assertEquals('default', $statusResponse->json('scenario'));
    }

    /**
     * Test request without session ID uses normal flow
     */
    public function test_request_without_session_uses_normal_flow()
    {
        // Call API without test session
        $response = $this->getJson('/mobile-api/v1/catalog/hydrate', [
            'Authorization' => 'Bearer mock_pat_test123'
        ]);

        $response->assertStatus(200);
        
        // Should return data with expected structure
        $response->assertJsonStructure([
            'categories',
            'qualities',
            'sellers'
        ]);
    }

    /**
     * Test invalid session ID returns 404
     */
    public function test_invalid_session_id_returns_404()
    {
        $response = $this->getJson('/api/test-scenarios/current', [
            'X-Test-Session-ID' => 'invalid_session_id'
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'TSE002',
                'message' => 'Session expired or not found'
            ]);
    }
}