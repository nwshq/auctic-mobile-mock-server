<?php

namespace MockServer\TestScenarios\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestSessionService
{
    const SESSION_PREFIX = 'maestro_session_';
    const DEFAULT_TTL = 7200; // 2 hours in seconds

    /**
     * Create a new test session
     */
    public function createSession(string $scenario, array $metadata = []): array
    {
        $sessionId = self::SESSION_PREFIX . Str::uuid();
        $expiresAt = Carbon::now()->addSeconds(self::DEFAULT_TTL);
        
        $sessionData = [
            'session_id' => $sessionId,
            'scenario' => $scenario,
            'created_at' => Carbon::now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'metadata' => $metadata,
            'state' => [
                'request_count' => 0,
                'last_request_at' => null,
                'custom_data' => []
            ]
        ];

        Cache::put($this->getCacheKey($sessionId), $sessionData, $expiresAt);
        
        return $sessionData;
    }

    /**
     * Get an active session
     */
    public function getSession(string $sessionId): ?array
    {
        $sessionData = Cache::get($this->getCacheKey($sessionId));
        
        if (!$sessionData) {
            return null;
        }

        // Check if session has expired
        if (Carbon::parse($sessionData['expires_at'])->isPast()) {
            $this->destroySession($sessionId);
            return null;
        }

        return $sessionData;
    }

    /**
     * Update session scenario
     */
    public function switchScenario(string $sessionId, string $newScenario): ?array
    {
        $session = $this->getSession($sessionId);
        
        if (!$session) {
            return null;
        }

        $session['scenario'] = $newScenario;
        $session['state']['last_request_at'] = Carbon::now()->toIso8601String();
        
        Cache::put(
            $this->getCacheKey($sessionId), 
            $session, 
            Carbon::parse($session['expires_at'])
        );
        
        return $session;
    }

    /**
     * Increment request count for a session
     */
    public function incrementRequestCount(string $sessionId): void
    {
        $session = $this->getSession($sessionId);
        
        if ($session) {
            $session['state']['request_count']++;
            $session['state']['last_request_at'] = Carbon::now()->toIso8601String();
            
            Cache::put(
                $this->getCacheKey($sessionId), 
                $session, 
                Carbon::parse($session['expires_at'])
            );
        }
    }

    /**
     * Update custom session data
     */
    public function updateSessionData(string $sessionId, array $customData): ?array
    {
        $session = $this->getSession($sessionId);
        
        if (!$session) {
            return null;
        }

        $session['state']['custom_data'] = array_merge(
            $session['state']['custom_data'] ?? [],
            $customData
        );
        
        Cache::put(
            $this->getCacheKey($sessionId), 
            $session, 
            Carbon::parse($session['expires_at'])
        );
        
        return $session;
    }

    /**
     * Destroy a session
     */
    public function destroySession(string $sessionId): bool
    {
        return Cache::forget($this->getCacheKey($sessionId));
    }

    /**
     * Get all active sessions (for debugging)
     */
    public function getAllSessions(): array
    {
        $sessions = [];
        $keys = Cache::get('test_session_keys', []);
        
        foreach ($keys as $key) {
            $session = Cache::get($key);
            if ($session && !Carbon::parse($session['expires_at'])->isPast()) {
                $sessions[] = $session;
            }
        }
        
        return $sessions;
    }

    /**
     * Extract session ID from request
     */
    public function extractSessionId(\Illuminate\Http\Request $request): ?string
    {
        // Check header first
        if ($request->hasHeader('X-Test-Session-ID')) {
            return $request->header('X-Test-Session-ID');
        }
        
        // Check query parameter
        if ($request->has('test_session_id')) {
            return $request->query('test_session_id');
        }
        
        return null;
    }

    /**
     * Get cache key for session
     */
    private function getCacheKey(string $sessionId): string
    {
        return 'test_session:' . $sessionId;
    }
}