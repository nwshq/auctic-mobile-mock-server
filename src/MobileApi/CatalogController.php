<?php

namespace MockServer\MobileApi;

use Illuminate\Http\Request;
use MockServer\Services\MockDataService;

class CatalogController
{
    private MockDataService $mockDataService;
    
    public function __construct(MockDataService $mockDataService)
    {
        $this->mockDataService = $mockDataService;
    }
    
    /**
     * Full data hydration endpoint
     * Returns complete snapshot of events, listings, and media
     */
    public function hydrate(Request $request)
    {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Check permissions
        if (!$this->hasPermission($request, ['mobile-api.catalog', 'mobile-api.catalog.view'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        $data = $this->mockDataService->getAllData();
        
        $response = [
            'data' => [
                'events' => $data['events'],
                'last_modified' => $data['last_modified']
            ]
        ];
        
        return response()->json($response);
    }
    
    /**
     * Incremental sync endpoint
     * Returns changes since specified timestamp
     */
    public function sync(Request $request)
    {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Check permissions
        if (!$this->hasPermission($request, ['mobile-api.catalog', 'mobile-api.catalog.view'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        $since = $request->query('since');
        
        if (!$since) {
            return response()->json([
                'error' => 'Missing required parameter: since'
            ], 400);
        }
        
        $changes = $this->mockDataService->getChangesSince($since);
        
        $totalRecords = collect($changes['data'])->flatten(1)->count() + 
                       collect($changes['deletions'])->flatten(1)->count();
        
        $response = [
            'data' => $changes['data'],
            'deletions' => $changes['deletions'],
            'last_modified' => $changes['last_modified'],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 1,
                'total_records' => $totalRecords,
                'per_page' => 1000,
                'has_more' => false
            ]
        ];
        
        return response()->json($response);
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
        
        // Accept any token that starts with mock_pat_
        return str_starts_with($token, 'mock_pat_');
    }
    
    /**
     * Check if user has required permissions
     */
    private function hasPermission(Request $request, array $permissions): bool
    {
        // In mock server, we assume authenticated users have all permissions
        // In real implementation, you'd check actual user permissions
        return $this->isAuthenticated($request);
    }
}