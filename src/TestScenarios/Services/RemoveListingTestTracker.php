<?php

namespace MockServer\TestScenarios\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RemoveListingTestTracker
{
    private const CACHE_PREFIX = 'remove_listing_test_tracker:';
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
            'removal_changes' => [],
            'removed_listings' => [],
            'removed_media' => [],
            'removal_events' => []
        ], self::TTL);

        Log::info('[REMOVE-LISTING-TEST-TRACKER] Session initialized', [
            'session_id' => $sessionId,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Track removal changes (listings and associated media)
     */
    public function trackRemovalChanges(string $sessionId, array $changes): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        $data = Cache::get($cacheKey);

        if (!$data) {
            Log::warning('[REMOVE-LISTING-TEST-TRACKER] Session not found for changes tracking', [
                'session_id' => $sessionId
            ]);
            return;
        }

        // Track removed listings
        if (isset($changes['removed_listings'])) {
            foreach ($changes['removed_listings'] as $listing) {
                $data['removed_listings'][] = [
                    'identifier' => $listing['identifier'],
                    'title' => $listing['title'] ?? null,
                    'status' => $listing['status'] ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'type' => 'listing_removed'
                ];
            }
        }

        // Track removed media
        if (isset($changes['removed_media'])) {
            foreach ($changes['removed_media'] as $media) {
                $data['removed_media'][] = [
                    'identifier' => $media['identifier'],
                    'listing_id' => $media['listing_id'] ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'type' => 'media_removed'
                ];
            }
        }

        // Track the full removal event
        $data['removal_changes'][] = [
            'timestamp' => now()->toIso8601String(),
            'removed_listings_count' => count($changes['removed_listings'] ?? []),
            'removed_media_count' => count($changes['removed_media'] ?? []),
            'removed_listing_ids' => array_column($changes['removed_listings'] ?? [], 'identifier'),
            'removed_media_ids' => array_column($changes['removed_media'] ?? [], 'identifier')
        ];

        Cache::put($cacheKey, $data, self::TTL);

        Log::info('[REMOVE-LISTING-TEST-TRACKER] Removal changes tracked', [
            'session_id' => $sessionId,
            'removed_listings' => count($changes['removed_listings'] ?? []),
            'removed_media' => count($changes['removed_media'] ?? []),
            'total_removed_listings' => count($data['removed_listings']),
            'total_removed_media' => count($data['removed_media'])
        ]);
    }

    /**
     * Track a removal event (e.g., user action that triggers removal)
     */
    public function trackRemovalEvent(string $sessionId, array $eventData): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        $data = Cache::get($cacheKey);

        if (!$data) {
            Log::warning('[REMOVE-LISTING-TEST-TRACKER] Session not found for removal tracking', [
                'session_id' => $sessionId
            ]);
            return;
        }

        $data['removal_events'][] = [
            'timestamp' => now()->toIso8601String(),
            'action' => $eventData['action'] ?? 'unknown',
            'target_listing' => $eventData['target_listing'] ?? null,
            'associated_media_count' => $eventData['associated_media_count'] ?? 0,
            'context' => $eventData['context'] ?? []
        ];

        Cache::put($cacheKey, $data, self::TTL);

        Log::info('[REMOVE-LISTING-TEST-TRACKER] Removal event tracked', [
            'session_id' => $sessionId,
            'action' => $eventData['action'] ?? 'unknown',
            'event_count' => count($data['removal_events'])
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

        // Analyze unique removals
        $uniqueListings = array_unique(array_filter(array_column($data['removed_listings'], 'identifier')));
        $uniqueMedia = array_unique(array_filter(array_column($data['removed_media'], 'identifier')));

        // Group media by listing
        $mediaByListing = [];
        foreach ($data['removed_media'] as $media) {
            $listingId = $media['listing_id'] ?? 'unknown';
            if (!isset($mediaByListing[$listingId])) {
                $mediaByListing[$listingId] = [];
            }
            $mediaByListing[$listingId][] = $media['identifier'];
        }

        // Check for expected pattern: 1 listing removed with all its media
        $expectedPattern = count($uniqueListings) == 1;

        // Calculate average media per listing
        $avgMediaPerListing = count($uniqueListings) > 0
            ? count($uniqueMedia) / count($uniqueListings)
            : 0;

        return [
            'session_id' => $sessionId,
            'started_at' => $data['started_at'],
            'analysis_at' => now()->toIso8601String(),
            'removal_summary' => [
                'total_removal_events' => count($data['removal_changes']),
                'total_listings_removed' => count($data['removed_listings']),
                'total_media_removed' => count($data['removed_media']),
                'unique_listings_removed' => count($uniqueListings),
                'unique_media_removed' => count($uniqueMedia),
                'listings_removed' => array_values($uniqueListings),
                'media_removed' => array_values($uniqueMedia),
                'matches_expected_pattern' => $expectedPattern,
                'expected_pattern' => '1 listing removed with all associated media',
                'avg_media_per_listing' => round($avgMediaPerListing, 2)
            ],
            'media_by_listing' => $mediaByListing,
            'removal_events' => [
                'total_events' => count($data['removal_events']),
                'events' => $data['removal_events']
            ],
            'timeline' => [
                'removal_changes' => $data['removal_changes'],
                'removed_listings' => $data['removed_listings'],
                'removed_media' => $data['removed_media']
            ],
            'test_result' => [
                'success' => $expectedPattern,
                'message' => $expectedPattern
                    ? 'Test passed: One listing successfully removed with all associated media'
                    : sprintf('Test failed: Expected 1 listing removal, found %d', count($uniqueListings)),
                'details' => [
                    'listings_removed' => count($uniqueListings),
                    'media_items_removed' => count($uniqueMedia),
                    'expected_listings' => 1
                ]
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

        Log::info('[REMOVE-LISTING-TEST-TRACKER] Session cleared', [
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