<?php

namespace MockServer\TestScenarios\Strategies;

use InvalidArgumentException;

class ScenarioStrategyFactory
{
    /**
     * Map of scenario names to their strategy classes
     */
    private static array $strategies = [
        'default' => DefaultScenarioStrategy::class,
        'camera-performance-test' => CameraPerformanceStrategy::class,
        'rotation-test' => RotationTestStrategy::class,
        'remove-listing-test' => RemoveListingTestStrategy::class,
    ];
    
    /**
     * Cache of instantiated strategies
     */
    private static array $instances = [];
    
    /**
     * Get the strategy for a given scenario
     * 
     * @param string $scenario The scenario name
     * @return ScenarioStrategyInterface
     * @throws InvalidArgumentException If scenario strategy not found
     */
    public static function getStrategy(string $scenario): ScenarioStrategyInterface
    {
        // Check if we have a cached instance
        if (isset(self::$instances[$scenario])) {
            return self::$instances[$scenario];
        }
        
        // Get the strategy class
        $strategyClass = self::$strategies[$scenario] ?? self::$strategies['default'];
        
        // Validate the class exists and implements the interface
        if (!class_exists($strategyClass)) {
            throw new InvalidArgumentException("Strategy class {$strategyClass} not found");
        }
        
        if (!is_subclass_of($strategyClass, ScenarioStrategyInterface::class)) {
            throw new InvalidArgumentException("Strategy class {$strategyClass} must implement ScenarioStrategyInterface");
        }
        
        // Create and cache the instance using Laravel's container for dependency injection
        self::$instances[$scenario] = app($strategyClass);
        
        return self::$instances[$scenario];
    }
    
    /**
     * Register a custom strategy
     * 
     * @param string $scenario The scenario name
     * @param string $strategyClass The strategy class name
     */
    public static function registerStrategy(string $scenario, string $strategyClass): void
    {
        if (!class_exists($strategyClass)) {
            throw new InvalidArgumentException("Strategy class {$strategyClass} not found");
        }
        
        if (!is_subclass_of($strategyClass, ScenarioStrategyInterface::class)) {
            throw new InvalidArgumentException("Strategy class {$strategyClass} must implement ScenarioStrategyInterface");
        }
        
        self::$strategies[$scenario] = $strategyClass;
        
        // Clear cached instance if exists
        unset(self::$instances[$scenario]);
    }
    
    /**
     * Check if a scenario has a registered strategy
     * 
     * @param string $scenario
     * @return bool
     */
    public static function hasStrategy(string $scenario): bool
    {
        return isset(self::$strategies[$scenario]);
    }
    
    /**
     * Get all registered scenarios
     * 
     * @return array
     */
    public static function getRegisteredScenarios(): array
    {
        return array_keys(self::$strategies);
    }
    
    /**
     * Clear all cached instances
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }
}