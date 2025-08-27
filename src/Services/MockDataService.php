<?php

namespace MockServer\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MockDataService
{
    private array $mockData;
    private string $lastModified;
    
    public function __construct()
    {
        $this->initializeMockData();
    }
    
    /**
     * Initialize mock data
     */
    private function initializeMockData(): void
    {
        // Check if data exists in cache
        $cachedData = Cache::get('mock_catalog_data');
        
        if ($cachedData) {
            $this->mockData = $cachedData['data'];
            $this->lastModified = $cachedData['last_modified'];
        } else {
            $this->generateFreshData();
        }
    }
    
    /**
     * Generate fresh mock data
     */
    private function generateFreshData(): void
    {
        $events = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $eventId = (string) $i;
            $listings = [];
            
            // Generate listings for this event
            $listingCount = rand(3, 8);
            for ($j = 1; $j <= $listingCount; $j++) {
                $listingId = (string) ($i * 1000 + $j);
                $listingMedia = [];
                
                // Generate media for this listing
                $mediaCount = rand(0, 3);
                for ($k = 1; $k <= $mediaCount; $k++) {
                    $listingMedia[] = $this->generateMedia(
                        (string) ($listingId * 100 + $k),
                        'listing',
                        $listingId
                    );
                }
                
                $listings[] = [
                    'id' => $listingId,
                    'event_id' => $eventId,
                    'title' => $this->getRandomProductName(),
                    'description' => $this->getRandomDescription(),
                    'starting_price' => rand(100, 5000),
                    'current_price' => rand(100, 10000),
                    'reserve_price' => rand(1000, 20000),
                    'status' => $this->randomFrom(['pending', 'approved', 'active', 'sold', 'passed', 'withdrawn']),
                    'category' => $this->getRandomCategory(),
                    'condition' => $this->randomFrom(['new', 'like_new', 'good', 'fair', 'poor']),
                    'metadata' => [
                        'lot_number' => 'LOT-' . rand(100, 999),
                        'estimate_low' => rand(100, 5000),
                        'estimate_high' => rand(5000, 50000),
                    ],
                    'created_at' => Carbon::now()->subDays(rand(30, 365))->toIso8601String(),
                    'updated_at' => Carbon::now()->subDays(rand(1, 29))->toIso8601String(),
                    'deleted_at' => null,
                    'media' => $listingMedia,
                    'media_count' => count($listingMedia)
                ];
            }
            
            // Generate event media
            $eventMedia = [];
            $eventMediaCount = rand(1, 2);
            for ($k = 1; $k <= $eventMediaCount; $k++) {
                $eventMedia[] = $this->generateMedia(
                    (string) ($eventId * 10000 + $k),
                    'event',
                    $eventId
                );
            }
            
            $events[] = [
                'id' => $eventId,
                'name' => $this->getRandomEventName(),
                'description' => $this->getRandomDescription(),
                'event_date' => Carbon::now()->addDays(rand(1, 60))->toIso8601String(),
                'location' => $this->getRandomLocation(),
                'status' => $this->randomFrom(['draft', 'published', 'active', 'completed', 'cancelled']),
                'metadata' => [
                    'venue' => $this->getRandomVenue(),
                    'start_date' => Carbon::now()->addDays(rand(1, 60))->toIso8601String(),
                    'end_date' => Carbon::now()->addDays(rand(61, 90))->toIso8601String(),
                    'preview_start_date' => Carbon::now()->addDays(rand(1, 30))->toIso8601String(),
                    'preview_end_date' => Carbon::now()->addDays(rand(31, 59))->toIso8601String(),
                    'terms_and_conditions' => $this->getRandomTerms(),
                    'buyer_premium' => rand(10, 25),
                    'currency' => 'USD',
                    'timezone' => 'America/New_York'
                ],
                'created_at' => Carbon::now()->subDays(rand(60, 365))->toIso8601String(),
                'updated_at' => Carbon::now()->subDays(rand(1, 59))->toIso8601String(),
                'deleted_at' => null,
                'listings' => $listings,
                'media' => $eventMedia,
                'listings_count' => count($listings),
                'media_count' => count($eventMedia)
            ];
        }
        
        $this->mockData = [
            'events' => $events,
            'deletedEvents' => [],
            'deletedListings' => [],
            'deletedMedia' => []
        ];
        
        $this->lastModified = Carbon::now()->toIso8601String();
        
        // Cache the data for 1 hour
        Cache::put('mock_catalog_data', [
            'data' => $this->mockData,
            'last_modified' => $this->lastModified
        ], 3600);
    }
    
    /**
     * Generate media entity
     */
    private function generateMedia(string $id, string $ownerType, string $ownerId): array
    {
        $mediaType = $this->randomFrom(['image', 'video', 'document', 'audio']);
        
        return [
            'id' => $id,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'filename' => Str::random(20) . $this->getFileExtension($mediaType),
            'original_filename' => $this->getRandomFileName($mediaType),
            'mime_type' => $this->getMimeType($mediaType),
            'file_size' => rand(100000, 10000000),
            'media_type' => $mediaType,
            'url' => $this->getMediaUrl($mediaType),
            'thumbnail_url' => $mediaType === 'image' ? $this->getRandomImageUrl() : null,
            'sort_order' => rand(1, 10),
            'alt_text' => $this->getRandomAltText(),
            'metadata' => $this->getMediaMetadata($mediaType),
            'upload_status' => 'completed',
            'created_at' => Carbon::now()->subDays(rand(30, 365))->toIso8601String(),
            'updated_at' => Carbon::now()->subDays(rand(1, 29))->toIso8601String(),
            'deleted_at' => null
        ];
    }
    
    /**
     * Get all data for hydrate endpoint
     */
    public function getAllData(): array
    {
        return [
            'events' => $this->mockData['events'],
            'last_modified' => $this->lastModified
        ];
    }
    
    /**
     * Get changes since specific timestamp
     */
    public function getChangesSince(string $since): array
    {
        // Handle various date formats
        try {
            // Remove any extra spaces that might have been introduced
            $since = trim($since);
            // If the string contains a space that's not part of a timezone indicator, it might be malformed
            $since = preg_replace('/\s+/', ' ', $since);
            
            $sinceDate = Carbon::parse($since);
        } catch (\Exception $e) {
            // Fallback to creating from format if parse fails
            $sinceDate = Carbon::now()->subDay();
        }
        $changes = [
            'data' => [
                'events' => [],
                'listings' => [],
                'media' => []
            ],
            'deletions' => [
                'events' => $this->mockData['deletedEvents'],
                'listings' => $this->mockData['deletedListings'],
                'media' => $this->mockData['deletedMedia']
            ],
            'last_modified' => $this->lastModified
        ];
        
        // Find changed events
        foreach ($this->mockData['events'] as $event) {
            $eventUpdated = Carbon::parse($event['updated_at']);
            if ($eventUpdated->isAfter($sinceDate)) {
                $changes['data']['events'][] = $event;
            }
            
            // Find changed listings
            foreach ($event['listings'] as $listing) {
                $listingUpdated = Carbon::parse($listing['updated_at']);
                if ($listingUpdated->isAfter($sinceDate)) {
                    $changes['data']['listings'][] = $listing;
                }
                
                // Find changed media for listings
                foreach ($listing['media'] as $media) {
                    $mediaUpdated = Carbon::parse($media['updated_at']);
                    if ($mediaUpdated->isAfter($sinceDate)) {
                        $changes['data']['media'][] = $media;
                    }
                }
            }
            
            // Find changed media for events
            foreach ($event['media'] as $media) {
                $mediaUpdated = Carbon::parse($media['updated_at']);
                if ($mediaUpdated->isAfter($sinceDate)) {
                    $changes['data']['media'][] = $media;
                }
            }
        }
        
        return $changes;
    }
    
    /**
     * Reset mock data
     */
    public function resetData(): void
    {
        Cache::forget('mock_catalog_data');
        // Add a small delay to ensure timestamp is different
        usleep(1000);
        $this->generateFreshData();
    }
    
    // Helper methods
    
    private function randomFrom(array $items): string
    {
        return $items[array_rand($items)];
    }
    
    private function getRandomProductName(): string
    {
        $adjectives = ['Vintage', 'Modern', 'Classic', 'Rare', 'Limited Edition', 'Antique', 'Contemporary'];
        $items = ['Watch', 'Painting', 'Sculpture', 'Vase', 'Jewelry', 'Book', 'Coin', 'Stamp', 'Furniture'];
        return $this->randomFrom($adjectives) . ' ' . $this->randomFrom($items);
    }
    
    private function getRandomEventName(): string
    {
        $types = ['Fine Art', 'Jewelry', 'Collectibles', 'Antiques', 'Modern Art', 'Estate', 'Luxury'];
        $suffixes = ['Auction', 'Sale', 'Event', 'Collection'];
        return $this->randomFrom($types) . ' ' . $this->randomFrom($suffixes);
    }
    
    private function getRandomDescription(): string
    {
        $descriptions = [
            'A remarkable piece from a private collection.',
            'Excellent condition with original documentation.',
            'Rare find with authenticated provenance.',
            'Museum-quality piece with detailed history.',
            'Exceptional example of the artist\'s work.',
            'Well-preserved item with minor wear consistent with age.',
            'Important piece from the estate collection.'
        ];
        return $this->randomFrom($descriptions);
    }
    
    private function getRandomLocation(): string
    {
        $cities = ['New York', 'London', 'Paris', 'Tokyo', 'Hong Kong', 'Geneva', 'Los Angeles'];
        $states = ['NY', 'UK', 'FR', 'JP', 'HK', 'CH', 'CA'];
        $index = array_rand($cities);
        return $cities[$index] . ', ' . $states[$index];
    }
    
    private function getRandomVenue(): string
    {
        $venues = ['Christie\'s', 'Sotheby\'s', 'Phillips', 'Bonhams', 'Heritage Auctions'];
        return $this->randomFrom($venues) . ' Auction House';
    }
    
    private function getRandomCategory(): string
    {
        $categories = ['Fine Art', 'Jewelry', 'Watches', 'Wine', 'Books', 'Coins', 'Stamps', 'Collectibles'];
        return $this->randomFrom($categories);
    }
    
    private function getRandomTerms(): string
    {
        return 'Standard auction terms and conditions apply. All sales are final. Buyer\'s premium applies to all lots.';
    }
    
    private function getFileExtension(string $mediaType): string
    {
        $extensions = [
            'image' => '.jpg',
            'video' => '.mp4',
            'document' => '.pdf',
            'audio' => '.mp3'
        ];
        return $extensions[$mediaType] ?? '.bin';
    }
    
    private function getRandomFileName(string $mediaType): string
    {
        $prefixes = [
            'image' => 'IMG_',
            'video' => 'VID_',
            'document' => 'DOC_',
            'audio' => 'AUD_'
        ];
        return ($prefixes[$mediaType] ?? 'FILE_') . rand(1000, 9999) . $this->getFileExtension($mediaType);
    }
    
    private function getMimeType(string $mediaType): string
    {
        $mimeTypes = [
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'document' => 'application/pdf',
            'audio' => 'audio/mpeg'
        ];
        return $mimeTypes[$mediaType] ?? 'application/octet-stream';
    }
    
    private function getMediaUrl(string $mediaType): string
    {
        if ($mediaType === 'image') {
            return $this->getRandomImageUrl();
        }
        return 'https://example.com/media/' . Str::random(20) . $this->getFileExtension($mediaType);
    }
    
    private function getRandomImageUrl(): string
    {
        $width = rand(800, 1920);
        $height = rand(600, 1080);
        return "https://picsum.photos/{$width}/{$height}?random=" . rand(1, 10000);
    }
    
    private function getRandomAltText(): string
    {
        $texts = [
            'Product image',
            'Detail view',
            'Front view',
            'Side view',
            'Close-up',
            'Overview'
        ];
        return $this->randomFrom($texts);
    }
    
    private function getMediaMetadata(string $mediaType): array
    {
        $metadata = [];
        
        if ($mediaType === 'image') {
            $metadata['width'] = rand(800, 4000);
            $metadata['height'] = rand(600, 3000);
        } elseif ($mediaType === 'video') {
            $metadata['duration'] = rand(10, 300);
            $metadata['width'] = 1920;
            $metadata['height'] = 1080;
        } elseif ($mediaType === 'audio') {
            $metadata['duration'] = rand(30, 600);
        }
        
        return $metadata;
    }
}