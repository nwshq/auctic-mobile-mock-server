<?php

namespace MockServer\TestScenarios\Strategies;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use MockServer\TestScenarios\Services\RotationTestTracker;

class RotationTestStrategy implements ScenarioStrategyInterface
{
    public function __construct(
        private RotationTestTracker $tracker
    ) {}

    /**
     * Process the request before it reaches the controller
     * Tracks media changes during rotation testing
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
     * Rotation test strategy never overrides, it only tracks
     */
    public function shouldOverrideResponse(array $config): bool
    {
        return false;
    }

    /**
     * Generate a complete response without calling the controller
     * Rotation test strategy doesn't generate responses
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

        Log::info('[ROTATION-TEST] Request received', [
            'session_id' => $sessionId,
            'endpoint' => $endpoint,
            'timestamp' => now()->toIso8601String(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);
    }

    /**
     * Track changes for rotation test analysis
     */
    private function trackChanges(Request $request, string $sessionId): void
    {
        $requestData = $request->all();

        if (!isset($requestData['changes'])) {
            return;
        }

        $changes = $requestData['changes'];
        $mediaItems = $changes['media'] ?? [];

        // Analyze media changes to detect additions and removals
        $added = [];
        $removed = [];

        foreach ($mediaItems as $item) {
            // Items with temp_id and no existing id are new additions
            if (isset($item['temp_id']) && !isset($item['id'])) {
                $added[] = ['identifier' => $item['temp_id']];
            }
            // Items marked as deleted are removals
            if (isset($item['deleted']) && $item['deleted'] === true) {
                $removed[] = ['identifier' => $item['id'] ?? $item['temp_id'] ?? null];
            }
        }

        // Track the changes
        if (!empty($added) || !empty($removed)) {
            $this->tracker->trackMediaChanges($sessionId, [
                'added' => $added,
                'removed' => $removed
            ]);

            Log::info('[ROTATION-TEST] Media changes tracked', [
                'session_id' => $sessionId,
                'added_count' => count($added),
                'removed_count' => count($removed),
                'timestamp' => now()->toIso8601String()
            ]);
        }
    }
}