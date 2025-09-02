<?php

namespace MockServer\TestScenarios\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use MockServer\TestScenarios\Services\TestSessionService;
use MockServer\TestScenarios\Services\ScenarioService;
use App\Http\Controllers\Controller;

class TestScenarioController extends Controller
{
    public function __construct(
        private TestSessionService $sessionService,
        private ScenarioService $scenarioService
    ) {}

    /**
     * POST /test-scenarios/activate
     * Activate a test scenario and create a new session
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'scenario' => 'required|string',
            'metadata' => 'array',
            'metadata.test_name' => 'string',
            'metadata.test_suite' => 'string',
            'metadata.maestro_flow' => 'string'
        ]);

        $scenario = $request->input('scenario');
        $isGeneric = false;
        
        // Check if scenario exists, if not use generic scenario
        if (!$this->scenarioService->scenarioExists($scenario)) {
            // Use a generic scenario instead of returning error
            $scenario = 'generic_scenario';
            $isGeneric = true;
            
            // Ensure generic scenario exists, create if needed
            if (!$this->scenarioService->scenarioExists($scenario)) {
                // Fall back to 'default' if generic_scenario doesn't exist
                $scenario = 'default';
            }
        }

        // Create new session
        $session = $this->sessionService->createSession(
            $scenario,
            array_merge(
                $request->input('metadata', []),
                ['requested_scenario' => $request->input('scenario')]
            )
        );

        return response()->json([
            'session_id' => $session['session_id'],
            'scenario' => $session['scenario'],
            'is_generic' => $isGeneric,
            'requested_scenario' => $request->input('scenario'),
            'expires_at' => $session['expires_at']
        ], 201);
    }

    /**
     * GET /test-scenarios/current
     * Get the current active scenario for a session
     */
    public function current(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Test-Session-ID');
        
        if (!$sessionId) {
            return response()->json([
                'error' => 'TSE001',
                'message' => 'Invalid session ID: Session ID header required'
            ], 400);
        }

        $session = $this->sessionService->getSession($sessionId);
        
        if (!$session) {
            return response()->json([
                'error' => 'TSE002',
                'message' => 'Session expired or not found'
            ], 404);
        }

        return response()->json([
            'session_id' => $session['session_id'],
            'scenario' => $session['scenario'],
            'active' => true,
            'request_count' => $session['state']['request_count'] ?? 0,
            'expires_at' => $session['expires_at']
        ]);
    }

    /**
     * POST /test-scenarios/switch
     * Switch to a different scenario mid-test
     */
    public function switch(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Test-Session-ID');
        
        if (!$sessionId) {
            return response()->json([
                'error' => 'TSE001',
                'message' => 'Invalid session ID: Session ID header required'
            ], 400);
        }

        $request->validate([
            'scenario' => 'required|string'
        ]);

        $newScenario = $request->input('scenario');
        
        // Check if scenario exists
        if (!$this->scenarioService->scenarioExists($newScenario)) {
            return response()->json([
                'error' => 'TSE003',
                'message' => 'Unknown scenario: ' . $newScenario
            ], 400);
        }

        $session = $this->sessionService->switchScenario($sessionId, $newScenario);
        
        if (!$session) {
            return response()->json([
                'error' => 'TSE002',
                'message' => 'Session expired or not found'
            ], 404);
        }

        return response()->json([
            'session_id' => $session['session_id'],
            'scenario' => $session['scenario'],
            'message' => 'Scenario switched successfully'
        ]);
    }

    /**
     * POST /test-scenarios/reset
     * Reset or destroy a test session
     */
    public function reset(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Test-Session-ID');
        
        if (!$sessionId) {
            return response()->json([
                'error' => 'TSE001',
                'message' => 'Invalid session ID: Session ID header required'
            ], 400);
        }

        $destroyed = $this->sessionService->destroySession($sessionId);
        
        if (!$destroyed) {
            return response()->json([
                'error' => 'TSE002',
                'message' => 'Session not found or already destroyed'
            ], 404);
        }

        return response()->json([
            'message' => 'Session destroyed successfully'
        ]);
    }

    /**
     * GET /test-scenarios/available
     * List all available test scenarios
     */
    public function available(): JsonResponse
    {
        $scenarios = $this->scenarioService->getAllScenarios();
        
        return response()->json([
            'scenarios' => $scenarios
        ]);
    }

    /**
     * GET /test-scenarios/debug/{session_id}
     * Get detailed session information for debugging
     */
    public function debug(string $sessionId): JsonResponse
    {
        if (!config('test-scenarios.debug_enabled', false)) {
            return response()->json([
                'error' => 'Debug endpoint disabled'
            ], 403);
        }

        $session = $this->sessionService->getSession($sessionId);
        
        if (!$session) {
            return response()->json([
                'error' => 'TSE002',
                'message' => 'Session not found'
            ], 404);
        }

        $scenarioMetadata = $this->scenarioService->getScenarioMetadata($session['scenario']);
        
        return response()->json([
            'session' => $session,
            'scenario_metadata' => $scenarioMetadata
        ]);
    }

    /**
     * GET /test-scenarios/metrics
     * Get performance metrics for test scenarios
     */
    public function metrics(): JsonResponse
    {
        if (!config('test-scenarios.metrics_enabled', false)) {
            return response()->json([
                'error' => 'Metrics endpoint disabled'
            ], 403);
        }

        $sessions = $this->sessionService->getAllSessions();
        
        $metrics = [
            'active_sessions' => count($sessions),
            'scenarios_in_use' => array_unique(array_column($sessions, 'scenario')),
            'total_requests' => array_sum(array_column(array_column($sessions, 'state'), 'request_count')),
            'sessions' => array_map(function($session) {
                return [
                    'session_id' => $session['session_id'],
                    'scenario' => $session['scenario'],
                    'request_count' => $session['state']['request_count'] ?? 0,
                    'created_at' => $session['created_at'],
                    'expires_at' => $session['expires_at']
                ];
            }, $sessions)
        ];
        
        return response()->json($metrics);
    }
}