<?php

namespace MockServer\TestScenarios\Services;

use Illuminate\Support\Facades\File;

class ScenarioService
{
    private array $scenarios = [];
    private string $scenarioPath;

    public function __construct()
    {
        $this->scenarioPath = config('test-scenarios.config_path', base_path('config/test-scenarios'));
        $this->loadScenarios();
    }

    /**
     * Load all scenario configurations
     */
    private function loadScenarios(): void
    {
        if (!File::exists($this->scenarioPath)) {
            File::makeDirectory($this->scenarioPath, 0755, true);
        }

        $files = File::files($this->scenarioPath);
        
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $scenarios = require $file->getPathname();
                $this->scenarios = array_merge($this->scenarios, $scenarios);
            }
        }
    }

    /**
     * Get a specific scenario configuration
     * Always returns default scenario
     */
    public function getScenario(string $scenarioName): ?array
    {
        // Always return default scenario regardless of input
        return $this->scenarios['default'] ?? [
            'name' => 'Default Scenario',
            'description' => 'Default mock server responses',
            'responses' => []
        ];
    }

    /**
     * Get all available scenarios
     * Returns only the default scenario
     */
    public function getAllScenarios(): array
    {
        return [[
            'name' => 'default',
            'display_name' => 'Default Scenario',
            'description' => 'Default mock server responses',
            'endpoints' => []
        ]];
    }

    /**
     * Get response configuration for a specific endpoint and scenario
     */
    public function getResponseConfig(string $scenario, string $endpoint): ?array
    {
        $scenarioConfig = $this->getScenario($scenario);
        
        if (!$scenarioConfig) {
            return null;
        }

        // Check for exact endpoint match
        if (isset($scenarioConfig['responses'][$endpoint])) {
            return $scenarioConfig['responses'][$endpoint];
        }

        // Check for wildcard match
        if (isset($scenarioConfig['responses']['*'])) {
            return $scenarioConfig['responses']['*'];
        }

        return null;
    }

    /**
     * Check if a scenario exists
     * Always returns true for compatibility
     */
    public function scenarioExists(string $scenario): bool
    {
        // Always return true since we only use default scenario
        return true;
    }

    /**
     * Get scenario metadata
     * Always returns default scenario metadata
     */
    public function getScenarioMetadata(string $scenario): array
    {
        $config = $this->getScenario($scenario);
        
        if (!$config) {
            return [];
        }

        return [
            'name' => $config['name'] ?? $scenario,
            'description' => $config['description'] ?? '',
            'created_at' => $config['created_at'] ?? null,
            'updated_at' => $config['updated_at'] ?? null,
            'author' => $config['author'] ?? null,
            'tags' => $config['tags'] ?? []
        ];
    }

    /**
     * Reload scenarios (useful for development)
     */
    public function reloadScenarios(): void
    {
        $this->scenarios = [];
        $this->loadScenarios();
    }
}