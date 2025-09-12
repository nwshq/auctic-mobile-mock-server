<?php

namespace MockServer\TestScenarios\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use MockServer\TestScenarios\Services\CameraPerformanceTracker;
use MockServer\TestScenarios\Services\TestSessionService;
use Illuminate\Support\Facades\Log;

class CameraPerformanceAnalysisController
{
    public function __construct(
        private CameraPerformanceTracker $tracker,
        private TestSessionService $sessionService
    ) {}
    
    /**
     * Get analysis for camera performance test
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
        
        // Verify this is a camera-performance-test scenario
        $session = $this->sessionService->getSession($sessionId);
        
        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
                'session_id' => $sessionId,
                'message' => 'The test session may have expired or does not exist'
            ], 404);
        }
        
        if ($session['scenario'] !== 'camera-performance-test') {
            return response()->json([
                'error' => 'Invalid scenario',
                'current_scenario' => $session['scenario'],
                'required_scenario' => 'camera-performance-test',
                'message' => 'This endpoint is only available for camera-performance-test scenario'
            ], 403);
        }
        
        // Get analysis from tracker
        $analysis = $this->tracker->getAnalysis($sessionId);
        
        if (!$analysis) {
            return response()->json([
                'error' => 'No tracking data available',
                'session_id' => $sessionId,
                'message' => 'No upload or changes requests have been tracked for this session'
            ], 404);
        }
        
        // Log the analysis request
        Log::info('[CAMERA-PERFORMANCE-ANALYSIS] Analysis requested', [
            'session_id' => $sessionId,
            'unique_uploads' => $analysis['upload_requests']['unique_media_items'],
            'total_uploads' => $analysis['upload_requests']['total_media_items'],
            'unique_changes' => $analysis['changes_requests']['unique_media_items'],
            'total_changes' => $analysis['changes_requests']['total_media_items']
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $analysis,
            'summary' => [
                'total_unique_uploads' => $analysis['upload_requests']['unique_media_items'],
                'total_upload_requests' => $analysis['upload_requests']['total_requests'],
                'total_duplicate_uploads' => $analysis['upload_requests']['duplicate_items'],
                'duplicate_upload_identifiers' => $analysis['upload_requests']['duplicates'],
                'total_unique_changes' => $analysis['changes_requests']['unique_media_items'],
                'total_changes_requests' => $analysis['changes_requests']['total_requests'],
                'has_duplicates' => $analysis['upload_requests']['duplicate_items'] > 0
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
        
        // Verify this is a camera-performance-test scenario
        $session = $this->sessionService->getSession($sessionId);
        
        if (!$session) {
            return response()->json([
                'error' => 'Session not found',
                'session_id' => $sessionId
            ], 404);
        }
        
        if ($session['scenario'] !== 'camera-performance-test') {
            return response()->json([
                'error' => 'Invalid scenario',
                'current_scenario' => $session['scenario'],
                'required_scenario' => 'camera-performance-test'
            ], 403);
        }
        
        // Clear the tracking data
        $this->tracker->clearSession($sessionId);
        
        // Re-initialize the session
        $this->tracker->initializeSession($sessionId);
        
        Log::info('[CAMERA-PERFORMANCE-ANALYSIS] Tracking data cleared and re-initialized', [
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