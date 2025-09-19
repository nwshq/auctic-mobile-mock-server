<?php

namespace MockServer\TestScenarios\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MockServer\TestScenarios\Services\RemoveListingTestTracker;
use MockServer\TestScenarios\Services\TestSessionService;
use Illuminate\Support\Facades\Log;

class RemoveListingTestAnalysisController
{
    public function __construct(
        private RemoveListingTestTracker $tracker,
        private TestSessionService $sessionService
    ) {}

    /**
     * Get analysis for the remove listing test
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

        // Verify this is a remove-listing-test scenario
        $session = $this->sessionService->getSession($sessionId);

        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
                'session_id' => $sessionId,
                'message' => 'The test session may have expired or does not exist'
            ], 404);
        }

        if ($session['scenario'] !== 'remove-listing-test') {
            return response()->json([
                'error' => 'Invalid scenario',
                'current_scenario' => $session['scenario'],
                'required_scenario' => 'remove-listing-test',
                'message' => 'This endpoint is only available for remove-listing-test scenario'
            ], 403);
        }

        // Get analysis from tracker
        $analysis = $this->tracker->getAnalysis($sessionId);

        if (!$analysis) {
            return response()->json([
                'error' => 'No tracking data available',
                'session_id' => $sessionId,
                'message' => 'No listing removals have been tracked for this session'
            ], 404);
        }

        // Log the analysis request
        Log::info('[REMOVE-LISTING-TEST-ANALYSIS] Analysis requested', [
            'session_id' => $sessionId,
            'unique_listings_removed' => $analysis['removal_summary']['unique_listings_removed'],
            'unique_media_removed' => $analysis['removal_summary']['unique_media_removed'],
            'matches_pattern' => $analysis['removal_summary']['matches_expected_pattern'],
            'test_success' => $analysis['test_result']['success']
        ]);

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'summary' => [
                'total_listings_removed' => $analysis['removal_summary']['unique_listings_removed'],
                'total_media_removed' => $analysis['removal_summary']['unique_media_removed'],
                'matches_expected_pattern' => $analysis['removal_summary']['matches_expected_pattern'],
                'expected_pattern' => $analysis['removal_summary']['expected_pattern'],
                'avg_media_per_listing' => $analysis['removal_summary']['avg_media_per_listing'],
                'test_passed' => $analysis['test_result']['success']
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

        // Verify this is a remove-listing-test scenario
        $session = $this->sessionService->getSession($sessionId);

        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
                'session_id' => $sessionId
            ], 404);
        }

        if ($session['scenario'] !== 'remove-listing-test') {
            return response()->json([
                'error' => 'Invalid scenario',
                'current_scenario' => $session['scenario'],
                'required_scenario' => 'remove-listing-test'
            ], 403);
        }

        // Clear the tracking data
        $this->tracker->clearSession($sessionId);

        // Re-initialize the session
        $this->tracker->initializeSession($sessionId);

        Log::info('[REMOVE-LISTING-TEST-ANALYSIS] Tracking data cleared and re-initialized', [
            'session_id' => $sessionId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tracking data cleared and re-initialized',
            'session_id' => $sessionId
        ]);
    }

    /**
     * Get detailed removal timeline
     */
    public function getTimeline(Request $request): JsonResponse
    {
        // Extract session ID from request
        $sessionId = $this->extractSessionId($request);

        if (!$sessionId) {
            return response()->json([
                'error' => 'No test session ID provided',
                'message' => 'Please provide X-Test-Session-ID header or test_session_id query parameter'
            ], 400);
        }

        // Get analysis from tracker
        $analysis = $this->tracker->getAnalysis($sessionId);

        if (!$analysis) {
            return response()->json([
                'error' => 'No tracking data available',
                'session_id' => $sessionId,
                'message' => 'No listing removals have been tracked for this session'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'timeline' => $analysis['timeline'],
            'media_by_listing' => $analysis['media_by_listing']
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