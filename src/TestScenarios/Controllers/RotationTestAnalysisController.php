<?php

namespace MockServer\TestScenarios\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MockServer\TestScenarios\Services\RotationTestTracker;
use MockServer\TestScenarios\Services\TestSessionService;
use Illuminate\Support\Facades\Log;

class RotationTestAnalysisController
{
    public function __construct(
        private RotationTestTracker $tracker,
        private TestSessionService $sessionService
    ) {}

    /**
     * Get analysis for the rotation test
     */
    public function getAnalysis(Request $request): JsonResponse
    {
        // Extract session ID from request
        $sessionId = $this->extractSessionId($request);

        if (!$sessionId) {
            return response()->json([
                'error' => 'No test session ID provided',
                'message' => 'Please provide X-Test-Session-ID header or test_session_id query parameter'
            ], 400);
        }

        // Verify this is a rotation-test scenario
        $session = $this->sessionService->getSession($sessionId);

        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
                'session_id' => $sessionId,
                'message' => 'The test session may have expired or does not exist'
            ], 404);
        }

        if ($session['scenario'] !== 'rotation-test') {
            return response()->json([
                'error' => 'Invalid scenario',
                'current_scenario' => $session['scenario'],
                'required_scenario' => 'rotation-test',
                'message' => 'This endpoint is only available for rotation-test scenario'
            ], 403);
        }

        // Get analysis from tracker
        $analysis = $this->tracker->getAnalysis($sessionId);

        if (!$analysis) {
            return response()->json([
                'error' => 'No tracking data available',
                'session_id' => $sessionId,
                'message' => 'No media changes have been tracked for this session'
            ], 404);
        }

        // Log the analysis request
        Log::info('[ROTATION-TEST-ANALYSIS] Analysis requested', [
            'session_id' => $sessionId,
            'unique_added' => $analysis['media_changes']['unique_added'],
            'unique_removed' => $analysis['media_changes']['unique_removed'],
            'matches_pattern' => $analysis['media_changes']['matches_expected_pattern']
        ]);

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'summary' => [
                'total_unique_added' => $analysis['media_changes']['unique_added'],
                'total_unique_removed' => $analysis['media_changes']['unique_removed'],
                'matches_expected_pattern' => $analysis['media_changes']['matches_expected_pattern'],
                'expected_pattern' => $analysis['media_changes']['expected_pattern'],
                'rotation_events_count' => $analysis['rotation_events']['total_events']
            ]
        ]);
    }

    /**
     * Clear tracking data for a session
     */
    public function clearTracking(Request $request): JsonResponse
    {
        // Extract session ID from request
        $sessionId = $this->extractSessionId($request);

        if (!$sessionId) {
            return response()->json([
                'error' => 'No test session ID provided',
                'message' => 'Please provide X-Test-Session-ID header or test_session_id query parameter'
            ], 400);
        }

        // Verify this is a rotation-test scenario
        $session = $this->sessionService->getSession($sessionId);

        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
                'session_id' => $sessionId
            ], 404);
        }

        if ($session['scenario'] !== 'rotation-test') {
            return response()->json([
                'error' => 'Invalid scenario',
                'current_scenario' => $session['scenario'],
                'required_scenario' => 'rotation-test'
            ], 403);
        }

        // Clear the tracking data
        $this->tracker->clearSession($sessionId);

        // Re-initialize the session
        $this->tracker->initializeSession($sessionId);

        Log::info('[ROTATION-TEST-ANALYSIS] Tracking data cleared and re-initialized', [
            'session_id' => $sessionId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tracking data cleared and re-initialized',
            'session_id' => $sessionId
        ]);
    }

    /**
     * Extract session ID from multiple sources
     */
    private function extractSessionId(Request $request): ?string
    {
        // Priority: Header > Query Parameter
        if ($request->hasHeader('X-Test-Session-ID')) {
            return $request->header('X-Test-Session-ID');
        }

        if ($request->has('test_session_id')) {
            return $request->query('test_session_id');
        }

        return null;
    }
}