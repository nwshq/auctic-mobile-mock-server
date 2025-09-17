<?php

namespace MockServer\TestScenarios\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RotationTestTracker
{
    private const CACHE_PREFIX = 'rotation_test_tracker:';
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
            'media_changes' => [],
            'added_media' => [],
            'removed_media' => [],
            'rotation_events' => []
        ], self::TTL);

        Log::info('[ROTATION-TEST-TRACKER] Session initialized', [
            'session_id' => $sessionId,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Track media changes (additions and removals)
     */
    public function trackMediaChanges(string $sessionId, array $changes): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        $data = Cache::get($cacheKey);

        if (!$data) {
            Log::warning('[ROTATION-TEST-TRACKER] Session not found for changes tracking', [
                'session_id' => $sessionId
            ]);
            return;
        }

        // Track added media
        if (isset($changes['added'])) {
            foreach ($changes['added'] as $item) {
                $data['added_media'][] = [
                    'identifier' => $item['identifier'] ?? $item['temp_id'] ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'type' => 'added'
                ];
            }
        }

        // Track removed media
        if (isset($changes['removed'])) {
            foreach ($changes['removed'] as $item) {
                $data['removed_media'][] = [
                    'identifier' => $item['identifier'] ?? $item['temp_id'] ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'type' => 'removed'
                ];
            }
        }

        // Track the full change event
        $data['media_changes'][] = [
            'timestamp' => now()->toIso8601String(),
            'added_count' => count($changes['added'] ?? []),
            'removed_count' => count($changes['removed'] ?? []),
            'added_identifiers' => array_column($changes['added'] ?? [], 'identifier'),
            'removed_identifiers' => array_column($changes['removed'] ?? [], 'identifier')
        ];

        Cache::put($cacheKey, $data, self::TTL);

        Log::info('[ROTATION-TEST-TRACKER] Media changes tracked', [
            'session_id' => $sessionId,
            'added' => count($changes['added'] ?? []),
            'removed' => count($changes['removed'] ?? []),
            'total_added' => count($data['added_media']),
            'total_removed' => count($data['removed_media'])
        ]);
    }

    /**
     * Track a rotation event
     */
    public function trackRotationEvent(string $sessionId, array $eventData): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        $data = Cache::get($cacheKey);

        if (!$data) {
            Log::warning('[ROTATION-TEST-TRACKER] Session not found for rotation tracking', [
                'session_id' => $sessionId
            ]);
            return;
        }

        $data['rotation_events'][] = [
            'timestamp' => now()->toIso8601String(),
            'orientation' => $eventData['orientation'] ?? 'unknown',
            'media_state' => $eventData['media_state'] ?? []
        ];

        Cache::put($cacheKey, $data, self::TTL);

        Log::info('[ROTATION-TEST-TRACKER] Rotation event tracked', [
            'session_id' => $sessionId,
            'orientation' => $eventData['orientation'] ?? 'unknown',
            'event_count' => count($data['rotation_events'])
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

        // Analyze unique additions and removals
        $uniqueAdded = array_unique(array_filter(array_column($data['added_media'], 'identifier')));
        $uniqueRemoved = array_unique(array_filter(array_column($data['removed_media'], 'identifier')));

        // Check for expected pattern: 1 new media, 1 removed
        $expectedPattern = (count($uniqueAdded) == 1 && count($uniqueRemoved) == 1);

        return [
            'session_id' => $sessionId,
            'started_at' => $data['started_at'],
            'analysis_at' => now()->toIso8601String(),
            'media_changes' => [
                'total_changes' => count($data['media_changes']),
                'total_added' => count($data['added_media']),
                'total_removed' => count($data['removed_media']),
                'unique_added' => count($uniqueAdded),
                'unique_removed' => count($uniqueRemoved),
                'added_identifiers' => array_values($uniqueAdded),
                'removed_identifiers' => array_values($uniqueRemoved),
                'matches_expected_pattern' => $expectedPattern,
                'expected_pattern' => '1 new media and 1 removed'
            ],
            'rotation_events' => [
                'total_events' => count($data['rotation_events']),
                'events' => $data['rotation_events']
            ],
            'timeline' => [
                'media_changes' => $data['media_changes'],
                'added_media' => $data['added_media'],
                'removed_media' => $data['removed_media']
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

        Log::info('[ROTATION-TEST-TRACKER] Session cleared', [
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