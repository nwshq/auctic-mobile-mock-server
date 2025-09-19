<?php

namespace MockServer\TestScenarios\Generators;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;

class RemoveListingTestGenerator implements ResponseGeneratorInterface
{
    /**
     * Generate response for remove listing testing
     * This generator tracks listing and media removals during listing deletion
     */
    public function generate(array $parameters, array $session = []): array
    {
        // Apply delay if specified
        if (isset($parameters['delay']) && $parameters['delay'] > 0) {
            sleep($parameters['delay']);
        }

        // Return a response that indicates this is a remove listing test
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
        return 'remove-listing-test';
    }

    /**
     * Get generator description
     */
    public function getDescription(): string
    {
        return 'Remove listing testing generator that tracks listing and media removals during listing deletion';
    }
}