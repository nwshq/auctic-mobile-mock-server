<?php

namespace MockServer\TestScenarios\Generators;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;
use Carbon\Carbon;
use Faker\Factory as Faker;

class SingleEventGenerator implements ResponseGeneratorInterface
{
    private $faker;
    
    public function __construct()
    {
        $this->faker = Faker::create();
    }
    
    public function generate(array $parameters, array $session = []): array
    {
        $eventCount = $parameters['event_count'] ?? 1;
        $listingCount = $parameters['listing_count'] ?? 5;
        $status = $parameters['status'] ?? 'active';
        
        $events = [];
        $listings = [];
        
        for ($i = 0; $i < $eventCount; $i++) {
            $event = $this->generateEvent($i + 1, $status);
            $events[] = $event;
            
            // Generate listings for this event
            for ($j = 0; $j < $listingCount; $j++) {
                $listings[] = $this->generateListing($event['id'], $j + 1);
            }
        }
        
        return [
            'events' => $events,
            'listings' => $listings,
            'sellers' => $this->generateSellers(3),
            'categories' => $this->getDefaultCategories(),
            'qualities' => $this->getDefaultQualities(),
            'last_modified' => Carbon::now()->toIso8601String(),
            'incremental_id' => \Illuminate\Support\Str::uuid()->toString()
        ];
    }
    
    public function getName(): string
    {
        return 'SingleEventGenerator';
    }
    
    public function getDescription(): string
    {
        return 'Generates catalog with configurable number of events and listings';
    }
    
    private function generateEvent(int $id, string $status): array
    {
        $eventDate = Carbon::now()->addDays($this->faker->numberBetween(7, 90));
        
        return [
            'id' => $id,
            'external_id' => 'EVT-' . str_pad($id, 6, '0', STR_PAD_LEFT),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'venue' => [
                'id' => $this->faker->numberBetween(1, 10),
                'name' => $this->faker->company() . ' Arena',
                'city' => $this->faker->city(),
                'state' => $this->faker->stateAbbr(),
                'country' => 'US',
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
            ],
            'date' => $eventDate->toDateString(),
            'time' => $eventDate->format('H:i:s'),
            'datetime' => $eventDate->toIso8601String(),
            'status' => $status,
            'category_id' => $this->faker->numberBetween(1, 4),
            'image_url' => $this->faker->imageUrl(800, 600, 'events'),
            'thumbnail_url' => $this->faker->imageUrl(200, 200, 'events'),
            'created_at' => Carbon::now()->subDays($this->faker->numberBetween(1, 30))->toIso8601String(),
            'updated_at' => Carbon::now()->toIso8601String(),
        ];
    }
    
    private function generateListing(int $eventId, int $id): array
    {
        $basePrice = $this->faker->randomFloat(2, 50, 500);
        
        return [
            'id' => $id,
            'event_id' => $eventId,
            'seller_id' => $this->faker->numberBetween(1, 3),
            'section' => 'Section ' . $this->faker->numberBetween(100, 400),
            'row' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']),
            'seats' => implode(',', range(1, $this->faker->numberBetween(1, 4))),
            'quantity' => $this->faker->numberBetween(1, 4),
            'price' => $basePrice,
            'fees' => round($basePrice * 0.15, 2),
            'total_price' => round($basePrice * 1.15, 2),
            'quality_id' => $this->faker->numberBetween(1, 3),
            'delivery_method' => $this->faker->randomElement(['electronic', 'instant', 'will_call']),
            'split_type' => $this->faker->randomElement(['any', 'pairs', 'all']),
            'status' => 'available',
            'notes' => $this->faker->optional(0.3)->sentence(),
            'created_at' => Carbon::now()->subDays($this->faker->numberBetween(1, 10))->toIso8601String(),
            'updated_at' => Carbon::now()->toIso8601String(),
        ];
    }
    
    private function generateSellers(int $count): array
    {
        $sellers = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $sellers[] = [
                'id' => $i,
                'name' => $this->faker->company(),
                'rating' => $this->faker->randomFloat(1, 3.5, 5.0),
                'total_sales' => $this->faker->numberBetween(100, 10000),
                'response_time' => $this->faker->numberBetween(1, 24),
                'verified' => $this->faker->boolean(80),
            ];
        }
        
        return $sellers;
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