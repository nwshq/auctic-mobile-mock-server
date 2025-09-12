<?php

namespace MockServer\TestScenarios\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CameraPerformanceTracker
{
    private const CACHE_PREFIX = 'camera_performance_tracker:';
    private const TTL = 7200; // 2 hours
    
    /**
     * Initialize tracking for a new session
     */
    public function initializeSession(string $sessionId): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        
        Cache::put($cacheKey, [
            'session_id' => $sessionId,
            'started_at' => now()->toIso8601String(),
            'upload_identifiers' => [],
            'changes_identifiers' => [],
            'upload_requests' => [],
            'changes_requests' => []
        ], self::TTL);
        
        Log::info('[CAMERA-PERFORMANCE-TRACKER] Session initialized', [
            'session_id' => $sessionId,
            'timestamp' => now()->toIso8601String()
        ]);
    }
    
    /**
     * Track an upload request
     */
    public function trackUploadRequest(string $sessionId, array $mediaItems): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        $data = Cache::get($cacheKey);
        
        if (!$data) {
            Log::warning('[CAMERA-PERFORMANCE-TRACKER] Session not found for upload tracking', [
                'session_id' => $sessionId
            ]);
            return;
        }
        
        // Track individual identifiers
        foreach ($mediaItems as $item) {
            if (isset($item['identifier'])) {
                $data['upload_identifiers'][] = $item['identifier'];
            }
        }
        
        // Track the full request
        $data['upload_requests'][] = [
            'timestamp' => now()->toIso8601String(),
            'media_count' => count($mediaItems),
            'identifiers' => array_column($mediaItems, 'identifier')
        ];
        
        Cache::put($cacheKey, $data, self::TTL);
        
        Log::info('[CAMERA-PERFORMANCE-TRACKER] Upload request tracked', [
            'session_id' => $sessionId,
            'media_count' => count($mediaItems),
            'total_uploads' => count($data['upload_identifiers']),
            'unique_uploads' => count(array_unique($data['upload_identifiers']))
        ]);
    }
    
    /**
     * Track a changes request
     */
    public function trackChangesRequest(string $sessionId, array $changes): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        $data = Cache::get($cacheKey);
        
        if (!$data) {
            Log::warning('[CAMERA-PERFORMANCE-TRACKER] Session not found for changes tracking', [
                'session_id' => $sessionId
            ]);
            return;
        }
        
        // Extract media identifiers from changes
        $mediaItems = $changes['media'] ?? [];
        foreach ($mediaItems as $item) {
            if (isset($item['temp_id'])) {
                $data['changes_identifiers'][] = $item['temp_id'];
            }
        }
        
        // Track the full request
        $data['changes_requests'][] = [
            'timestamp' => now()->toIso8601String(),
            'media_count' => count($mediaItems),
            'identifiers' => array_column($mediaItems, 'temp_id')
        ];
        
        Cache::put($cacheKey, $data, self::TTL);
        
        Log::info('[CAMERA-PERFORMANCE-TRACKER] Changes request tracked', [
            'session_id' => $sessionId,
            'media_count' => count($mediaItems),
            'total_changes' => count($data['changes_identifiers']),
            'unique_changes' => count(array_unique($data['changes_identifiers']))
        ]);
    }
    
    /**
     * Get analysis for a session
     */
    public function getAnalysis(string $sessionId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        $data = Cache::get($cacheKey);
        
        if (!$data) {
            return null;
        }
        
        $uniqueUploadIds = array_unique($data['upload_identifiers']);
        $uniqueChangesIds = array_unique($data['changes_identifiers']);
        
        // Calculate duplicates - items that appear more than once
        $uploadCounts = array_count_values($data['upload_identifiers']);
        $duplicateUploads = array_filter($uploadCounts, function($count) {
            return $count > 1;
        });
        
        return [
            'session_id' => $sessionId,
            'started_at' => $data['started_at'],
            'analysis_at' => now()->toIso8601String(),
            'upload_requests' => [
                'total_requests' => count($data['upload_requests']),
                'total_media_items' => count($data['upload_identifiers']),
                'unique_media_items' => count($uniqueUploadIds),
                'duplicate_items' => count($data['upload_identifiers']) - count($uniqueUploadIds),
                'unique_identifiers' => array_values($uniqueUploadIds),
                'duplicates' => $duplicateUploads
            ],
            'changes_requests' => [
                'total_requests' => count($data['changes_requests']),
                'total_media_items' => count($data['changes_identifiers']),
                'unique_media_items' => count($uniqueChangesIds),
                'unique_identifiers' => array_values($uniqueChangesIds)
            ],
            'timeline' => [
                'upload_requests' => $data['upload_requests'],
                'changes_requests' => $data['changes_requests']
            ]
        ];
    }
    
    /**
     * Clear session data
     */
    public function clearSession(string $sessionId): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        Cache::forget($cacheKey);
        
        Log::info('[CAMERA-PERFORMANCE-TRACKER] Session cleared', [
            'session_id' => $sessionId,
            'timestamp' => now()->toIso8601String()
        ]);
    }
    
    /**
     * Check if session exists
     */
    public function sessionExists(string $sessionId): bool
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        return Cache::has($cacheKey);
    }
}