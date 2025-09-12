<?php

namespace MockServer\TestScenarios\Generators;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;

class CameraPerformanceGenerator implements ResponseGeneratorInterface
{
    /**
     * Generate response for camera performance testing
     * This generator handles delays, logging, and response modifications
     */
    public function generate(array $parameters, array $session = []): array
    {
        // Apply delay if specified
        if (isset($parameters['delay']) && $parameters['delay'] > 0) {
            sleep($parameters['delay']);
        }
        
        // Note: Since we're using the Strategy Pattern now, this generator
        // is mainly kept for backward compatibility. The actual logic is
        // handled by CameraPerformanceStrategy.
        
        // Return a response that indicates this is a camera performance test
        return [
            'passthrough' => true,
            'delay_applied' => isset($parameters['delay']) ? $parameters['delay'] : 0,
            'logging_enabled' => isset($parameters['enable_logging']) ? $parameters['enable_logging'] : false,
            'modifications' => [
                'last_modified' => $parameters['fixed_last_modified'] ?? null
            ]
        ];
    }
    
    /**
     * Get generator name
     */
    public function getName(): string
    {
        return 'camera-performance';
    }
    
    /**
     * Get generator description
     */
    public function getDescription(): string
    {
        return 'Camera performance testing generator with delays and logging';
    }
}