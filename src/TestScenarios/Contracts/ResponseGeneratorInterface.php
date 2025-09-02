<?php

namespace MockServer\TestScenarios\Contracts;

interface ResponseGeneratorInterface
{
    /**
     * Generate response data based on parameters
     *
     * @param array $parameters Configuration parameters for the generator
     * @param array $session Current test session data
     * @return array Generated response data
     */
    public function generate(array $parameters, array $session = []): array;
    
    /**
     * Get generator name
     *
     * @return string
     */
    public function getName(): string;
    
    /**
     * Get generator description
     *
     * @return string
     */
    public function getDescription(): string;
}