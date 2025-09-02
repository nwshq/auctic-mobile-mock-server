<?php

namespace MockServer\TestScenarios\Generators;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;
use Carbon\Carbon;

class EmptyCatalogGenerator implements ResponseGeneratorInterface
{
    public function generate(array $parameters, array $session = []): array
    {
        $includeDefaults = $parameters['include_defaults'] ?? true;
        
        $response = [
            'events' => [],
            'listings' => [],
            'sellers' => [],
            'last_modified' => Carbon::now()->toIso8601String(),
            'incremental_id' => \Illuminate\Support\Str::uuid()->toString()
        ];
        
        if ($includeDefaults) {
            $response['categories'] = $this->getDefaultCategories();
            $response['qualities'] = $this->getDefaultQualities();
        } else {
            $response['categories'] = [];
            $response['qualities'] = [];
        }
        
        return $response;
    }
    
    public function getName(): string
    {
        return 'EmptyCatalogGenerator';
    }
    
    public function getDescription(): string
    {
        return 'Generates an empty catalog response with no events or listings';
    }
    
    private function getDefaultCategories(): array
    {
        return [
            ['id' => 1, 'name' => 'Sports', 'slug' => 'sports'],
            ['id' => 2, 'name' => 'Music', 'slug' => 'music'],
            ['id' => 3, 'name' => 'Theater', 'slug' => 'theater'],
            ['id' => 4, 'name' => 'Comedy', 'slug' => 'comedy'],
        ];
    }
    
    private function getDefaultQualities(): array
    {
        return [
            ['id' => 1, 'name' => 'Standard', 'code' => 'STD'],
            ['id' => 2, 'name' => 'Premium', 'code' => 'PRM'],
            ['id' => 3, 'name' => 'VIP', 'code' => 'VIP'],
        ];
    }
}