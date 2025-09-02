<?php

namespace MockServer\TestScenarios\Services;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;
use Illuminate\Support\Facades\Log;

class ResponseGeneratorService
{
    private array $generators = [];

    /**
     * Register a response generator
     */
    public function registerGenerator(string $name, ResponseGeneratorInterface $generator): void
    {
        $this->generators[$name] = $generator;
    }

    /**
     * Generate response using specified generator
     */
    public function generate(string $generatorName, array $parameters, array $session = []): array
    {
        // Check if it's a class name
        if (class_exists($generatorName)) {
            $generator = app($generatorName);
            
            if (!$generator instanceof ResponseGeneratorInterface) {
                throw new \InvalidArgumentException(
                    "Generator class {$generatorName} must implement ResponseGeneratorInterface"
                );
            }
            
            return $generator->generate($parameters, $session);
        }
        
        // Check registered generators
        if (!isset($this->generators[$generatorName])) {
            throw new \InvalidArgumentException("Generator '{$generatorName}' not found");
        }
        
        return $this->generators[$generatorName]->generate($parameters, $session);
    }

    /**
     * Check if generator exists
     */
    public function hasGenerator(string $name): bool
    {
        return isset($this->generators[$name]) || class_exists($name);
    }

    /**
     * Get all registered generators
     */
    public function getGenerators(): array
    {
        return array_keys($this->generators);
    }
}