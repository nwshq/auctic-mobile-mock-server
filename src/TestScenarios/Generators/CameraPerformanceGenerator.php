<?php

namespace MockServer\TestScenarios\Generators;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CameraPerformanceGenerator implements ResponseGeneratorInterface
{
    /**
     * Generate response for camera performance testing
     * This generator handles delays, logging, and response modifications
     */
    public function generate(array $parameters, $request = null, $originalResponse = null): array
    {
        // Apply delay if specified
        if (isset($parameters['delay']) && $parameters['delay'] > 0) {
            sleep($parameters['delay']);
        }
        
        // Handle logging if enabled
        if (isset($parameters['enable_logging']) && $parameters['enable_logging'] && $request) {
            $this->logRequest($request);
        }
        
        // Handle fixed last_modified for catalog.hydrate
        if (isset($parameters['fixed_last_modified']) && $originalResponse) {
            if (is_array($originalResponse)) {
                $originalResponse['last_modified'] = $parameters['fixed_last_modified'];
            }
        }
        
        // Return the original response (modified if needed)
        // The middleware will handle passing through to the controller
        return [
            'passthrough' => true,
            'delay_applied' => isset($parameters['delay']) ? $parameters['delay'] : 0,
            'logging_enabled' => isset($parameters['enable_logging']) ? $parameters['enable_logging'] : false,
            'modifications' => [
                'last_modified' => $parameters['fixed_last_modified'] ?? null
            ]
        ];
    }
    
    /**
     * Log request details for debugging
     */
    private function logRequest($request): void
    {
        $requestId = Str::random(8);
        $endpoint = $request->route()->getName() ?? $request->path();
        
        // Different logging based on endpoint
        if (str_contains($endpoint, 'changes')) {
            Log::info('[CHANGES-REQUEST] Full request received', [
                'request_id' => $requestId,
                'timestamp' => now()->toIso8601String(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'full_request_data' => $request->all()
            ]);
        } elseif (str_contains($endpoint, 'request-upload')) {
            $requestData = $request->all();
            $mediaCount = isset($requestData['media']) ? count($requestData['media']) : 0;
            
            Log::info('[UPLOAD-REQUEST-IN] Upload request received', [
                'request_id' => $requestId,
                'timestamp' => now()->toIso8601String(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'media_count' => $mediaCount,
                'media_items' => array_map(function($item) {
                    return [
                        'identifier' => $item['identifier'] ?? null,
                        'filename' => $item['filename'] ?? null,
                        'content_type' => $item['content_type'] ?? null,
                        'size' => $item['size'] ?? null
                    ];
                }, $requestData['media'] ?? [])
            ]);
        } elseif (str_contains($endpoint, 's3-upload') || str_contains($endpoint, 'mock-s3')) {
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
}