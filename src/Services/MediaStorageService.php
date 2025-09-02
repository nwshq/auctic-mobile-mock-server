<?php

namespace MockServer\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    private const CACHE_TTL = 3600; // 1 hour
    
    /**
     * Store upload metadata
     */
    public function storeUploadMetadata(string $storageKey, array $metadata): void
    {
        Cache::put("upload_metadata:{$storageKey}", $metadata, self::CACHE_TTL);
        Cache::put("upload_id:{$metadata['upload_id']}", $storageKey, self::CACHE_TTL);
    }
    
    /**
     * Get upload metadata by storage key
     */
    public function getUploadMetadata(string $storageKey): ?array
    {
        return Cache::get("upload_metadata:{$storageKey}");
    }
    
    /**
     * Get storage key by upload ID
     */
    public function getStorageKeyByUploadId(string $uploadId): ?string
    {
        return Cache::get("upload_id:{$uploadId}");
    }
    
    /**
     * Store file data
     */
    public function storeFileData(string $storageKey, array $fileData): void
    {
        Cache::put("file_data:{$storageKey}", $fileData, self::CACHE_TTL);
    }
    
    /**
     * Get file data
     */
    public function getFileData(string $storageKey): ?array
    {
        return Cache::get("file_data:{$storageKey}");
    }
    
    /**
     * Associate media with listing
     */
    public function associateMediaWithListing(string $listingId, string $collection, string $storageKey, array $fileData): void
    {
        $key = "listing_media:{$listingId}:{$collection}";
        $existingMedia = Cache::get($key, []);
        
        $existingMedia[] = array_merge($fileData, [
            'id' => count($existingMedia) + 1,
            'storage_key' => $storageKey,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        ]);
        
        Cache::put($key, $existingMedia, self::CACHE_TTL);
    }
    
    /**
     * Get listing media
     */
    public function getListingMedia(string $listingId, string $collection): array
    {
        return Cache::get("listing_media:{$listingId}:{$collection}", []);
    }
}