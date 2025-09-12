<?php

namespace MockServer\TestScenarios\Strategies;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface ScenarioStrategyInterface
{
    /**
     * Process the request before it reaches the controller
     * 
     * @param Request $request The incoming request
     * @param array $config The scenario configuration for this endpoint
     * @param array $session The test session data
     * @return array Processing result with instructions for middleware
     */
    public function processRequest(Request $request, array $config, array $session): array;
    
    /**
     * Process the response after it returns from the controller
     * 
     * @param Response $response The response from the controller
     * @param array $config The scenario configuration for this endpoint
     * @param array $session The test session data
     * @return Response The modified response
     */
    public function processResponse(Response $response, array $config, array $session): Response;
    
    /**
     * Check if this strategy should completely override the controller response
     * 
     * @param array $config The scenario configuration
     * @return bool
     */
    public function shouldOverrideResponse(array $config): bool;
    
    /**
     * Generate a complete response without calling the controller
     * 
     * @param Request $request The incoming request
     * @param array $config The scenario configuration
     * @param array $session The test session data
     * @return Response|null The generated response or null to continue to controller
     */
    public function generateResponse(Request $request, array $config, array $session): ?Response;
}