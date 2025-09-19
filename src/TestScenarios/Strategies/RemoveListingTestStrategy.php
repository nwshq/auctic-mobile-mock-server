<?php

namespace MockServer\TestScenarios\Strategies;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use MockServer\TestScenarios\Services\RemoveListingTestTracker;

class RemoveListingTestStrategy implements ScenarioStrategyInterface
{
    public function __construct(
        private RemoveListingTestTracker $tracker
    ) {}

    /**
     * Process the request before it reaches the controller
     * Tracks listing removals during remove listing testing
     */
    public function processRequest(Request $request, array $config, array $session): array
    {
        $parameters = $config['parameters'] ?? [];
        $sessionId = $session['session_id'] ?? null;

        // Initialize tracker session if not exists
        if ($sessionId && !$this->tracker->sessionExists($sessionId)) {
            $this->tracker->initializeSession($sessionId);
        }

        // Log request if enabled
        if (isset($parameters['enable_logging']) && $parameters['enable_logging']) {
            $this->logRequest($request, $sessionId);
        }

        // Track changes if this is a changes request
        if ($sessionId && str_contains($request->path(), 'changes')) {
            $this->trackChanges($request, $sessionId);
        }

        return [
            'continue' => true,
            'tracking_enabled' => $parameters['track_changes'] ?? false,
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
     * Remove listing test strategy never overrides, it only tracks
     */
    public function shouldOverrideResponse(array $config): bool
    {
        return false;
    }

    /**
     * Generate a complete response without calling the controller
     * Remove listing test strategy doesn't generate responses
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
        $endpoint = $request->route() ? $request->route()->getName() : $request->path();

        Log::info('[REMOVE-LISTING-TEST] Request received', [
            'session_id' => $sessionId,
            'endpoint' => $endpoint,
            'timestamp' => now()->toIso8601String(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);
    }

    /**
     * Track changes for remove listing test analysis
     */
    private function trackChanges(Request $request, string $sessionId): void
    {
        $requestData = $request->all();

        if (!isset($requestData['changes'])) {
            return;
        }

        $changes = $requestData['changes'];

        // Track listing removals
        $listings = $changes['listings'] ?? [];
        $media = $changes['media'] ?? [];

        Log::info('[REMOVE-LISTING-TEST] Received changes request', [
            'session_id' => $sessionId,
            'listings_count' => count($listings),
            'media_count' => count($media),
            'raw_listings' => $listings,
            'raw_media' => $media
        ]);

        $removedListings = [];
        $removedMedia = [];

        // Track listing removals
        foreach ($listings as $listing) {
            $action = $listing['action'] ?? null;

            if ($action === 'delete' || $action === 'remove') {
                $removedListings[] = [
                    'identifier' => $listing['id'] ?? $listing['temp_id'] ?? null,
                    'title' => $listing['title'] ?? null,
                    'status' => $listing['status'] ?? null
                ];
            }
        }

        // Track associated media removals
        foreach ($media as $item) {
            $action = $item['action'] ?? null;

            if ($action === 'delete' || $action === 'remove') {
                $removedMedia[] = [
                    'identifier' => $item['id'] ?? $item['temp_id'] ?? null,
                    'listing_id' => $item['listing_id'] ?? null
                ];
            }
        }

        // Track the changes
        if (!empty($removedListings) || !empty($removedMedia)) {
            $this->tracker->trackRemovalChanges($sessionId, [
                'removed_listings' => $removedListings,
                'removed_media' => $removedMedia
            ]);

            Log::info('[REMOVE-LISTING-TEST] Removal changes tracked', [
                'session_id' => $sessionId,
                'removed_listings_count' => count($removedListings),
                'removed_media_count' => count($removedMedia),
                'timestamp' => now()->toIso8601String()
            ]);
        }
    }
}