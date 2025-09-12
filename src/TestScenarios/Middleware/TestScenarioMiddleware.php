<?php

namespace MockServer\TestScenarios\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use MockServer\TestScenarios\Services\TestSessionService;
use MockServer\TestScenarios\Services\ScenarioService;
use MockServer\TestScenarios\Services\ResponseGeneratorService;
use MockServer\TestScenarios\Strategies\ScenarioStrategyFactory;
use Illuminate\Support\Facades\Log;

class TestScenarioMiddleware
{
    public function __construct(
        private TestSessionService $sessionService,
        private ScenarioService $scenarioService,
        private ResponseGeneratorService $generatorService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('scenarios.enabled', false)) {
            return $next($request);
        }

        // Skip middleware for test-scenarios routes themselves
        if (str_starts_with($request->path(), 'api/test-scenarios')) {
            return $next($request);
        }

        // Extract session ID from request
        $sessionId = $this->extractSessionId($request);
        
        if (!$sessionId) {
            return $next($request); // Normal flow if no test session
        }

        // Load active scenario from session
        $session = $this->sessionService->getSession($sessionId);

        if (!$session) {
            Log::warning('Test scenario session not found or expired', ['session_id' => $sessionId]);
            return $next($request);
        }

        // Increment request count
        $this->sessionService->incrementRequestCount($sessionId);

        // Get scenario configuration
        $scenario = $session['scenario'];
        $scenarioConfig = $this->scenarioService->getScenario($scenario);
        
        if (!$scenarioConfig) {
            Log::error('Test scenario configuration not found', ['scenario' => $scenario]);
            return $next($request);
        }

        // Attach scenario context to request
        $request->attributes->set('test_scenario', $scenario);
        $request->attributes->set('test_session_id', $sessionId);
        $request->attributes->set('test_session', $session);

        // Get endpoint from route
        $endpoint = $this->getEndpointIdentifier($request);
        
        // Get the strategy for this scenario
        try {
            $strategy = ScenarioStrategyFactory::getStrategy($scenario);
        } catch (\Exception $e) {
            Log::error('Failed to get scenario strategy', [
                'scenario' => $scenario,
                'error' => $e->getMessage()
            ]);
            // Fall back to normal processing
            return $next($request);
        }
        
        // Get response configuration for this endpoint
        $responseConfig = $this->scenarioService->getResponseConfig($scenario, $endpoint);
        
        if (!$responseConfig) {
            // No configuration for this endpoint, use normal flow
            return $next($request);
        }
        
        // Log the test scenario activity
        Log::channel('test_scenarios')->info('Processing test scenario request', [
            'session_id' => $sessionId,
            'scenario' => $scenario,
            'endpoint' => $endpoint,
            'request_count' => $session['state']['request_count'] ?? 0,
            'strategy' => get_class($strategy)
        ]);
        
        // Let the strategy process the request
        $requestResult = $strategy->processRequest($request, $responseConfig, $session);
        
        // Check if strategy wants to override the response completely
        if ($strategy->shouldOverrideResponse($responseConfig)) {
            $generatedResponse = $strategy->generateResponse($request, $responseConfig, $session);
            if ($generatedResponse) {
                return $generatedResponse;
            }
        }
        
        // Continue to controller
        $response = $next($request);
        
        // Let the strategy process the response
        $response = $strategy->processResponse($response, $responseConfig, $session);
        
        return $response;
    }

    /**
     * Extract session ID from multiple sources
     */
    private function extractSessionId(Request $request): ?string
    {
        // Priority order: Header > Query Parameter > Cookie
        
        // Check header
        if ($request->hasHeader('X-Test-Session-ID')) {
            return $request->header('X-Test-Session-ID');
        }
        
        // Check query parameter
        if ($request->has('test_session_id')) {
            return $request->query('test_session_id');
        }
        
        // Check cookie (for web-based testing)
        if ($request->hasCookie('test_session_id')) {
            return $request->cookie('test_session_id');
        }
        
        return null;
    }

    /**
     * Get endpoint identifier from request
     */
    private function getEndpointIdentifier(Request $request): string
    {
        $route = $request->route();
        
        if ($route) {
            // Use route name if available
            if ($route->getName()) {
                return $route->getName();
            }
            
            // Otherwise use action name
            $action = $route->getActionName();
            if ($action && $action !== 'Closure') {
                return $action;
            }
        }
        
        // Fallback to path
        return $request->path();
    }

    /**
     * Check if we should completely override the response
     */
    private function shouldOverrideResponse(array $responseConfig): bool
    {
        $type = $responseConfig['type'] ?? 'normal';
        
        return in_array($type, ['static', 'dynamic', 'error', 'custom']);
    }

    /**
     * Generate a scenario-specific response
     */
    private function generateScenarioResponse(Request $request, array $responseConfig, array $session): Response
    {
        $type = $responseConfig['type'] ?? 'normal';
        
        switch ($type) {
            case 'static':
                return $this->generateStaticResponse($responseConfig);
                
            case 'dynamic':
                return $this->generateDynamicResponse($responseConfig, $session);
                
            case 'error':
                return $this->generateErrorResponse($responseConfig);
                
            case 'custom':
                return $this->generateCustomResponse($responseConfig, $request, $session);
                
            default:
                return response()->json(['error' => 'Invalid response type'], 500);
        }
    }

    /**
     * Generate static response
     */
    private function generateStaticResponse(array $config): Response
    {
        $data = $config['data'] ?? [];
        $statusCode = $config['status_code'] ?? 200;
        $headers = $config['headers'] ?? [];
        
        // Process variable substitutions
        $data = $this->processVariableSubstitutions($data);
        
        return response()->json($data, $statusCode, $headers);
    }

    /**
     * Generate dynamic response using generator
     */
    private function generateDynamicResponse(array $config, array $session): Response
    {
        $generatorClass = $config['generator'] ?? null;
        $parameters = $config['parameters'] ?? [];
        $statusCode = $config['status_code'] ?? 200;
        $headers = $config['headers'] ?? [];
        
        if (!$generatorClass) {
            return response()->json(['error' => 'Generator not specified'], 500);
        }
        
        try {
            $data = $this->generatorService->generate($generatorClass, $parameters, $session);
            return response()->json($data, $statusCode, $headers);
        } catch (\Exception $e) {
            Log::error('Failed to generate dynamic response', [
                'generator' => $generatorClass,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Response generation failed'], 500);
        }
    }

    /**
     * Generate error response
     */
    private function generateErrorResponse(array $config): Response
    {
        $statusCode = $config['status_code'] ?? 500;
        $data = $config['data'] ?? ['error' => 'Test scenario error'];
        $headers = $config['headers'] ?? [];
        
        return response()->json($data, $statusCode, $headers);
    }

    /**
     * Generate custom response using closure
     */
    private function generateCustomResponse(array $config, Request $request, array $session): Response
    {
        $handler = $config['handler'] ?? null;
        
        if (!$handler || !is_callable($handler)) {
            return response()->json(['error' => 'Custom handler not found'], 500);
        }
        
        try {
            return $handler($request, $session, $config);
        } catch (\Exception $e) {
            Log::error('Custom response handler failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Custom response failed'], 500);
        }
    }

    /**
     * Apply response variations to an existing response
     */
    private function applyResponseVariations(Response $response, array $config, array $session): Response
    {
        // Apply delay if configured
        if (isset($config['delay_ms']) && $config['delay_ms'] > 0) {
            usleep($config['delay_ms'] * 1000);
        }
        
        // Apply response modifications
        if (isset($config['modify'])) {
            $response = $this->modifyResponse($response, $config['modify'], $session);
        }
        
        // Add test headers for debugging
        if (config('scenarios.debug.headers', false)) {
            $response->headers->set('X-Test-Scenario', $session['scenario']);
            $response->headers->set('X-Test-Session-ID', $session['session_id']);
        }
        
        return $response;
    }

    /**
     * Modify response data
     */
    private function modifyResponse(Response $response, array $modifications, array $session): Response
    {
        $content = json_decode($response->getContent(), true);
        
        if (!$content) {
            return $response;
        }
        
        foreach ($modifications as $path => $value) {
            $content = $this->setNestedValue($content, $path, $value);
        }
        
        $response->setContent(json_encode($content));
        
        return $response;
    }

    /**
     * Process variable substitutions in data
     */
    private function processVariableSubstitutions($data)
    {
        if (is_string($data)) {
            return $this->substituteVariables($data);
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->processVariableSubstitutions($value);
            }
        }
        
        return $data;
    }

    /**
     * Substitute variables in string
     */
    private function substituteVariables(string $text): string
    {
        $variables = [
            '{{timestamp}}' => now()->toIso8601String(),
            '{{date}}' => now()->toDateString(),
            '{{time}}' => now()->toTimeString(),
            '{{uuid}}' => (string) \Illuminate\Support\Str::uuid(),
            '{{random_int}}' => rand(1, 1000000),
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $text);
    }

    /**
     * Set nested value in array using dot notation
     */
    private function setNestedValue(array $array, string $path, $value): array
    {
        $keys = explode('.', $path);
        $current = &$array;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
        
        return $array;
    }
}