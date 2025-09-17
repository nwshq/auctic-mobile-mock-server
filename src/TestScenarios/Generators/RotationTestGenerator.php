<?php

namespace MockServer\TestScenarios\Generators;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;

class RotationTestGenerator implements ResponseGeneratorInterface
{
    /**
     * Generate response for rotation testing
     * This generator tracks media changes during device rotation
     */
    public function generate(array $parameters, array $session = []): array
    {
        // Apply delay if specified
        if (isset($parameters['delay']) && $parameters['delay'] > 0) {
            sleep($parameters['delay']);
        }

        // Return a response that indicates this is a rotation test
        return [
            'passthrough' => true,
            'delay_applied' => isset($parameters['delay']) ? $parameters['delay'] : 0,
            'logging_enabled' => isset($parameters['enable_logging']) ? $parameters['enable_logging'] : false,
            'track_changes' => true,
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
        return 'rotation-test';
    }

    /**
     * Get generator description
     */
    public function getDescription(): string
    {
        return 'Rotation testing generator that tracks media changes during device rotation';
    }
}