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
        // Add 5 second delay
        sleep(5);
        
        // Log incoming upload request for debugging duplicates
        $requestId = Str::random(8);
        $requestData = $request->all();
        $mediaCount = isset($requestData['media']) ? count($requestData['media']) : 0;
        
        \Illuminate\Support\Facades\Log::info('[UPLOAD-REQUEST-IN] Upload request received', [
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'media_count' => $mediaCount,
            'media_items' => array_map(function($item) {
                return [
                    'identifier' => $item['identifier'] ?? null,
                    'filename' => $item['filename'] ?? null,
                    'content_type' => $item['content_type'] ?? null,
                    'size' => $item['size'] ?? null
                ];
            }, $requestData['media'] ?? [])
        ]);
        
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
     * Supports both multipart/form-data and raw binary uploads
     */
    public function mockS3Upload(Request $request, string $uploadId)
    {
        // Add 5 second delay
        sleep(5);
        
        \Illuminate\Support\Facades\Log::info('[S3-UPLOAD] Upload received', [
            'upload_id' => $uploadId,
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'has_file' => $request->hasFile('file')
        ]);
        
        // Find the metadata by upload ID using MediaStorageService
        $storageKey = $this->mediaStorageService->getStorageKeyByUploadId($uploadId);
        
        if (!$storageKey) {
            return response()->json(['error' => 'Invalid upload ID'], 404);
        }
        
        $metadata = $this->mediaStorageService->getUploadMetadata($storageKey);
        
        if (!$metadata) {
            return response()->json(['error' => 'Upload metadata not found'], 404);
        }
        
        // Create mock-uploads directory if it doesn't exist
        if (!Storage::disk('local')->exists('mock-uploads')) {
            Storage::disk('local')->makeDirectory('mock-uploads');
        }
        
        $path = 'mock-uploads/' . $uploadId . '_' . $metadata['filename'];
        $content = '';
        $size = 0;
        
        // Store file data using MediaStorageService
        if ($request->hasFile('file')) {
            // Handle multipart/form-data upload
            $file = $request->file('file');
            Storage::disk('local')->putFileAs(
                'mock-uploads',
                $file,
                $uploadId . '_' . $metadata['filename']
            );
            $size = $file->getSize();
            $content = file_get_contents($file->getRealPath());
        } else {
            // Handle raw binary upload (like S3 does)
            $content = $request->getContent();
            Storage::disk('local')->put($path, $content);
            $size = strlen($content);
        }
        
        $this->mediaStorageService->storeFileData($storageKey, [
            'path' => $path,
            'filename' => $metadata['filename'],
            'mime_type' => $metadata['content_type'],
            'size' => $size,
        ]);
        
        // Return a simple 200 OK response like S3 does
        // S3 returns minimal response for PUT operations
        return response('', 200)
            ->header('ETag', '"' . md5($content) . '"')
            ->header('x-amz-request-id', $uploadId)
            ->header('x-amz-id-2', base64_encode($uploadId));
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