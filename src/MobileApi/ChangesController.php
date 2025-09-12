<?php

namespace MockServer\MobileApi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChangesController
{
    /**
     * Submit changes from mobile app
     * Processes create/update/delete operations for events, listings, and media
     */
    public function submitChanges(Request $request)
    {
        // Add 5 second delay
        sleep(5);
        
        // Log incoming request for debugging duplicates
        $requestId = Str::random(8);
        $requestData = $request->all();
        
        Log::info('[CHANGES-REQUEST] Full request received', [
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'full_request_data' => $requestData
        ]);
        
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // Check permissions
        if (!$this->hasPermission($request, ['mobile-api.catalog', 'mobile-api.catalog.edit'])) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }
        
        $changes = $request->input('changes', []);
        
        // Validate request structure
        if (empty($changes)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'changes' => ['The changes field is required.']
                ]
            ], 422);
        }
        
        $response = [
            'data' => [
                'events' => [],
                'listings' => [],
                'media' => []
            ]
        ];
        
        // Process events changes
        if (isset($changes['events'])) {
            foreach ($changes['events'] as $eventChange) {
                $result = $this->processEventChange($eventChange);
                $response['data']['events'][] = $result;
            }
        }
        
        // Process listings changes
        if (isset($changes['listings'])) {
            foreach ($changes['listings'] as $listingChange) {
                $result = $this->processListingChange($listingChange);
                $response['data']['listings'][] = $result;
            }
        }
        
        // Process media changes
        if (isset($changes['media'])) {
            foreach ($changes['media'] as $mediaChange) {
                $result = $this->processMediaChange($mediaChange);
                $response['data']['media'][] = $result;
            }
        }
        
        return response()->json($response);
    }
    
    /**
     * Process event change (create/update/delete)
     */
    private function processEventChange(array $change): array
    {
        $action = $change['action'] ?? null;
        $tempId = $change['temp_id'] ?? null;
        $id = $change['id'] ?? null;
        
        switch ($action) {
            case 'create':
                // Generate a new ID for created events
                $newId = $this->generateId('event');
                
                // Store the event data in cache for later retrieval
                if (isset($change['attributes'])) {
                    Cache::put("event_{$newId}", $change['attributes'], now()->addHours(24));
                }
                
                return [
                    'temp_id' => $tempId,
                    'id' => $newId,
                    'status' => 'success',
                    'errors' => []
                ];
                
            case 'update':
                // Store the updated data in cache
                if (isset($change['attributes']) && $id) {
                    $existingData = Cache::get("event_{$id}", []);
                    $updatedData = array_merge($existingData, $change['attributes']);
                    Cache::put("event_{$id}", $updatedData, now()->addHours(24));
                }
                
                return [
                    'id' => $id,
                    'status' => 'success',
                    'errors' => []
                ];
                
            case 'delete':
                // Remove from cache
                if ($id) {
                    Cache::forget("event_{$id}");
                }
                
                return [
                    'id' => $id,
                    'status' => 'success',
                    'errors' => []
                ];
                
            default:
                return [
                    'temp_id' => $tempId,
                    'id' => $id,
                    'status' => 'error',
                    'errors' => ['Invalid action: ' . $action]
                ];
        }
    }
    
    /**
     * Process listing change (create/update/delete)
     */
    private function processListingChange(array $change): array
    {
        $action = $change['action'] ?? null;
        $tempId = $change['temp_id'] ?? null;
        $id = $change['id'] ?? null;
        
        switch ($action) {
            case 'create':
                // Generate a new ID for created listings
                $newId = $this->generateId('listing');
                
                // Store the listing data in cache
                if (isset($change['attributes'])) {
                    Cache::put("listing_{$newId}", $change['attributes'], now()->addHours(24));
                }
                
                return [
                    'temp_id' => $tempId,
                    'id' => $newId,
                    'status' => 'success',
                    'errors' => []
                ];
                
            case 'update':
                // Validate last_modified if provided
                if (isset($change['last_modified'])) {
                    // In a real system, you'd check if the listing has been modified since
                    // For mock, we'll just accept it
                }
                
                // Store the updated data in cache
                if (isset($change['attributes']) && $id) {
                    $existingData = Cache::get("listing_{$id}", []);
                    $updatedData = array_merge($existingData, $change['attributes']);
                    Cache::put("listing_{$id}", $updatedData, now()->addHours(24));
                }
                
                return [
                    'id' => $id,
                    'status' => 'success',
                    'errors' => []
                ];
                
            case 'delete':
                // Remove from cache
                if ($id) {
                    Cache::forget("listing_{$id}");
                }
                
                return [
                    'id' => $id,
                    'status' => 'success',
                    'errors' => []
                ];
                
            default:
                return [
                    'temp_id' => $tempId,
                    'id' => $id,
                    'status' => 'error',
                    'errors' => ['Invalid action: ' . $action]
                ];
        }
    }
    
    /**
     * Process media change (create/update/delete)
     */
    private function processMediaChange(array $change): array
    {
        $action = $change['action'] ?? null;
        $tempId = $change['temp_id'] ?? null;
        $id = $change['id'] ?? null;
        $tempStorageKey = $change['temp_storage_key'] ?? null;
        
        switch ($action) {
            case 'create':
                // Generate a new ID for created media
                $newId = $this->generateId('media');
                
                // Store the media data in cache
                if (isset($change['attributes'])) {
                    $attributes = $change['attributes'];
                    
                    // If temp_storage_key is provided, link it to the uploaded file
                    if ($tempStorageKey) {
                        $uploadedFile = Cache::get("upload_{$tempStorageKey}");
                        if ($uploadedFile) {
                            $attributes['url'] = $uploadedFile['url'] ?? "https://bucket.s3.amazonaws.com/media/{$newId}/{$attributes['file_name']}";
                            $attributes['thumbnail_url'] = $uploadedFile['thumbnail_url'] ?? "https://bucket.s3.amazonaws.com/media/{$newId}/conversions/thumb-{$attributes['file_name']}";
                        }
                    }
                    
                    Cache::put("media_{$newId}", $attributes, now()->addHours(24));
                }
                
                return [
                    'temp_id' => $tempId,
                    'id' => $newId,
                    'status' => 'success',
                    'errors' => []
                ];
                
            case 'update':
                // Store the updated data in cache
                if (isset($change['attributes']) && $id) {
                    $existingData = Cache::get("media_{$id}", []);
                    $updatedData = array_merge($existingData, $change['attributes']);
                    Cache::put("media_{$id}", $updatedData, now()->addHours(24));
                }
                
                return [
                    'id' => $id,
                    'status' => 'success',
                    'errors' => []
                ];
                
            case 'delete':
                // Remove from cache
                if ($id) {
                    Cache::forget("media_{$id}");
                }
                
                return [
                    'id' => $id,
                    'status' => 'success',
                    'errors' => []
                ];
                
            default:
                return [
                    'temp_id' => $tempId,
                    'id' => $id,
                    'status' => 'error',
                    'errors' => ['Invalid action: ' . $action]
                ];
        }
    }
    
    /**
     * Generate a unique ID for a given entity type
     */
    private function generateId(string $type): int
    {
        // Get the last ID from cache or start from a base value
        $lastIdKey = "last_{$type}_id";
        $lastId = Cache::get($lastIdKey, 1000);
        
        // Increment and store
        $newId = $lastId + 1;
        Cache::put($lastIdKey, $newId, now()->addDays(30));
        
        return $newId;
    }
    
    /**
     * Check if request is authenticated
     */
    private function isAuthenticated(Request $request): bool
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader) {
            return false;
        }
        
        $token = str_replace('Bearer ', '', $authHeader);
        
        // Accept any token that starts with mock_pat_ or mock-token
        return str_starts_with($token, 'mock_pat_') || str_starts_with($token, 'mock-token');
    }
    
    /**
     * Check if user has required permissions
     */
    private function hasPermission(Request $request, array $permissions): bool
    {
        // In mock server, we assume authenticated users have all permissions
        return $this->isAuthenticated($request);
    }
}