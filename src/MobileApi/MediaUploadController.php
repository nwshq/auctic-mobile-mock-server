<?php

namespace MockServer\MobileApi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MockServer\Services\MediaStorageService;

class MediaUploadController
{
    private MediaStorageService $mediaStorageService;
    
    public function __construct(MediaStorageService $mediaStorageService)
    {
        $this->mediaStorageService = $mediaStorageService;
    }
    
    /**
     * Step 1: Request upload URL for media
     * Mobile app calls this to get presigned upload URLs
     */
    public function requestUpload(Request $request)
    {
        $request->validate([
            'media' => 'required|array',
            'media.*.identifier' => 'required|string',
            'media.*.filename' => 'required|string',
            'media.*.content_type' => 'required|string',
            'media.*.size' => 'required|integer',
        ]);
        
        $uploadRequests = [];
        
        foreach ($request->input('media') as $item) {
            $storageKey = 'temp-' . Str::uuid();
            $uploadId = Str::uuid();
            
            // Store metadata using MediaStorageService for persistence
            $this->mediaStorageService->storeUploadMetadata($storageKey, [
                'identifier' => $item['identifier'],
                'filename' => $item['filename'],
                'content_type' => $item['content_type'],
                'size' => $item['size'],
                'upload_id' => $uploadId,
            ]);
            
            $baseUrl = env('APP_URL', 'http://localhost:8000');
            $expiresAt = now()->addHour()->toIso8601String();
            
            $uploadRequests[] = [
                'identifier' => $item['identifier'],
                'storage_key' => $storageKey,
                'upload_url' => "{$baseUrl}/mock-s3-upload/{$uploadId}",
                'expires_at' => $expiresAt,
            ];
        }
        
        return response()->json(['data' => $uploadRequests]);
    }
    
    /**
     * Mock S3 upload endpoint
     * This simulates S3's PUT endpoint for file uploads
     */
    public function mockS3Upload(Request $request, string $uploadId)
    {
        // Find the metadata by upload ID using MediaStorageService
        $storageKey = $this->mediaStorageService->getStorageKeyByUploadId($uploadId);
        
        if (!$storageKey) {
            return response()->json(['error' => 'Invalid upload ID'], 404);
        }
        
        $metadata = $this->mediaStorageService->getUploadMetadata($storageKey);
        
        if (!$metadata) {
            return response()->json(['error' => 'Upload metadata not found'], 404);
        }
        
        // Store file data using MediaStorageService
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = Storage::disk('local')->putFileAs(
                'mock-uploads',
                $file,
                $uploadId . '_' . $metadata['filename']
            );
            
            $this->mediaStorageService->storeFileData($storageKey, [
                'path' => $path,
                'filename' => $metadata['filename'],
                'mime_type' => $metadata['content_type'],
                'size' => $file->getSize(),
            ]);
        } else {
            // Handle raw body upload (like S3 does)
            $content = $request->getContent();
            $path = 'mock-uploads/' . $uploadId . '_' . $metadata['filename'];
            Storage::disk('local')->put($path, $content);
            
            $this->mediaStorageService->storeFileData($storageKey, [
                'path' => $path,
                'filename' => $metadata['filename'],
                'mime_type' => $metadata['content_type'],
                'size' => strlen($content),
            ]);
        }
        
        // Return S3-like response
        return response()->json([
            'ETag' => '"' . md5($uploadId) . '"',
            'Location' => env('APP_URL') . '/storage/' . $path,
            'Key' => $uploadId,
            'Bucket' => 'mock-bucket',
        ], 200);
    }
    
    /**
     * Step 2: Associate uploaded media with listing
     * Called after successful S3 upload to link media to listing
     */
    public function associateMedia(Request $request, string $listingId)
    {
        $request->validate([
            'media' => 'required|array',
            'media.*.storage_key' => 'required|string',
            'collection' => 'required|string',
        ]);
        
        $collection = $request->input('collection');
        
        foreach ($request->input('media') as $media) {
            $fileData = $this->mediaStorageService->getFileData($media['storage_key']);
            
            if ($fileData) {
                // Store association using MediaStorageService
                $this->mediaStorageService->associateMediaWithListing(
                    $listingId,
                    $collection,
                    $media['storage_key'],
                    $fileData
                );
            }
        }
        
        return response()->json(['message' => 'Media collection saved']);
    }
    
    /**
     * Get media for a specific listing and collection
     */
    public function getListingMedia(Request $request, string $listingId, string $collection)
    {
        $mediaItems = $this->mediaStorageService->getListingMedia($listingId, $collection);
        $baseUrl = env('MEDIA_BASE_URL', 'https://bucket.s3.amazonaws.com');
        
        $response = [];
        
        foreach ($mediaItems as $index => $item) {
            $filename = $item['filename'];
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            
            $response[] = [
                'id' => $item['id'],
                'name' => $filename,
                'file_name' => $filename,
                'mime_type' => $item['mime_type'],
                'size' => $item['size'],
                'url' => "{$baseUrl}/{$listingId}/{$filename}",
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
                'custom_properties' => [],
                'generated_conversions' => [],
                'uuid' => $item['uuid'],
                'preview_url' => "{$baseUrl}/{$item['id']}/conversions/{$nameWithoutExt}-preview.jpg",
                'thumbnail_url' => "{$baseUrl}/{$item['id']}/conversions/{$nameWithoutExt}-thumb.jpg",
                'original_url' => "{$baseUrl}/{$listingId}/{$filename}",
                'xl_url' => "{$baseUrl}/{$item['id']}/conversions/{$nameWithoutExt}-xl.jpg",
                'order' => $index,
                'extension' => $extension,
            ];
        }
        
        return response()->json($response);
    }
}