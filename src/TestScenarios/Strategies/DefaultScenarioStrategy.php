<?php

namespace MockServer\TestScenarios\Strategies;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultScenarioStrategy implements ScenarioStrategyInterface
{
    /**
     * Process the request before it reaches the controller
     * Default strategy does no pre-processing
     */
    public function processRequest(Request $request, array $config, array $session): array
    {
        return [
            'continue' => true,
            'modifications' => []
        ];
    }
    
    /**
     * Process the response after it returns from the controller
     * Default strategy does no post-processing
     */
    public function processResponse(Response $response, array $config, array $session): Response
    {
        return $response;
    }
    
    /**
     * Check if this strategy should completely override the controller response
     * Default strategy never overrides
     */
    public function shouldOverrideResponse(array $config): bool
    {
        return false;
    }
    
    /**
     * Generate a complete response without calling the controller
     * Default strategy never generates responses
     */
    public function generateResponse(Request $request, array $config, array $session): ?Response
    {
        return null;
    }
}