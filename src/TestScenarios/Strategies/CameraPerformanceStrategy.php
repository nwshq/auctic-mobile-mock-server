<?php

namespace MockServer\TestScenarios\Strategies;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MockServer\TestScenarios\Services\CameraPerformanceTracker;

class CameraPerformanceStrategy implements ScenarioStrategyInterface
{
    public function __construct(
        private CameraPerformanceTracker $tracker
    ) {}
    /**
     * Process the request before it reaches the controller
     * Applies delays and logging for camera performance testing
     */
    public function processRequest(Request $request, array $config, array $session): array
    {
        $parameters = $config['parameters'] ?? [];
        $sessionId = $session['session_id'] ?? null;
        
        // Initialize tracker session if not exists
        if ($sessionId && !$this->tracker->sessionExists($sessionId)) {
            $this->tracker->initializeSession($sessionId);
        }
        
        // Apply delay if specified
        if (isset($parameters['delay']) && $parameters['delay'] > 0) {
            sleep($parameters['delay']);
        }
        
        // Log request if enabled
        if (isset($parameters['enable_logging']) && $parameters['enable_logging']) {
            $this->logRequest($request, $sessionId);
        }
        
        return [
            'continue' => true,
            'delay_applied' => $parameters['delay'] ?? 0,
            'logging_enabled' => $parameters['enable_logging'] ?? false
        ];
    }
    
    /**
     * Process the response after it returns from the controller
     * Applies modifications like fixed timestamps
     */
    public function processResponse(Response $response, array $config, array $session): Response
    {
        $parameters = $config['parameters'] ?? [];
        
        // Apply fixed last_modified if specified
        if (isset($parameters['fixed_last_modified'])) {
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            if ($data && is_array($data)) {
                $data['last_modified'] = $parameters['fixed_last_modified'];
                return response()->json($data, $response->getStatusCode());
            }
        }
        
        return $response;
    }
    
    /**
     * Check if this strategy should completely override the controller response
     * Camera performance strategy never overrides, it only modifies
     */
    public function shouldOverrideResponse(array $config): bool
    {
        return false;
    }
    
    /**
     * Generate a complete response without calling the controller
     * Camera performance strategy doesn't generate responses
     */
    public function generateResponse(Request $request, array $config, array $session): ?Response
    {
        return null;
    }
    
    /**
     * Log request details for debugging
     */
    private function logRequest(Request $request, ?string $sessionId): void
    {
        $requestId = Str::random(8);
        $endpoint = $request->route() ? $request->route()->getName() : $request->path();
        
        // Different logging based on endpoint
        if (str_contains($endpoint, 'changes')) {
            $this->logChangesRequest($request, $requestId, $sessionId);
        } elseif (str_contains($endpoint, 'request-upload')) {
            $this->logUploadRequest($request, $requestId, $sessionId);
        } elseif (str_contains($endpoint, 's3-upload') || str_contains($endpoint, 'mock-s3')) {
            $this->logS3Upload($request, $requestId);
        }
    }
    
    /**
     * Log changes request
     */
    private function logChangesRequest(Request $request, string $requestId, ?string $sessionId): void
    {
        $requestData = $request->all();
        
        Log::info('[CHANGES-REQUEST] Full request received', [
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'full_request_data' => $requestData
        ]);
        
        // Track in performance tracker
        if ($sessionId && isset($requestData['changes'])) {
            $this->tracker->trackChangesRequest($sessionId, $requestData['changes']);
        }
    }
    
    /**
     * Log upload request
     */
    private function logUploadRequest(Request $request, string $requestId, ?string $sessionId): void
    {
        $requestData = $request->all();
        $mediaCount = isset($requestData['media']) ? count($requestData['media']) : 0;
        $mediaItems = array_map(function($item) {
            return [
                'identifier' => $item['identifier'] ?? null,
                'filename' => $item['filename'] ?? null,
                'content_type' => $item['content_type'] ?? null,
                'size' => $item['size'] ?? null
            ];
        }, $requestData['media'] ?? []);
        
        Log::info('[UPLOAD-REQUEST-IN] Upload request received', [
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'media_count' => $mediaCount,
            'media_items' => $mediaItems
        ]);
        
        // Track in performance tracker
        if ($sessionId && !empty($mediaItems)) {
            $this->tracker->trackUploadRequest($sessionId, $mediaItems);
        }
    }
    
    /**
     * Log S3 upload
     */
    private function logS3Upload(Request $request, string $requestId): void
    {
        $uploadId = $request->route('uploadId');
        
        Log::info('[S3-UPLOAD] Upload received', [
            'upload_id' => $uploadId,
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'has_file' => $request->hasFile('file')
        ]);
    }
}