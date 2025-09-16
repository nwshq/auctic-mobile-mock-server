<?php

namespace MockServer\MobileApi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MockServer\Services\MediaStorageService;

class MediaUploadController
{    
    public function __construct(private MediaStorageService $mediaStorageService, private string $baseUrl, private string $baseS3Url)
    {
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
            
            $expiresAt = now()->addHour()->toIso8601String();
            
            $uploadRequests[] = [
                'identifier' => $item['identifier'],
                'storage_key' => $storageKey,
                'upload_url' => "{$this->baseUrl}/mock-s3-upload/{$uploadId}",
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
                'url' => "{$this->baseS3Url}/{$listingId}/{$filename}",
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
                'custom_properties' => [],
                'generated_conversions' => [],
                'uuid' => $item['uuid'],
                'preview_url' => "{$this->baseS3Url}/{$item['id']}/conversions/{$nameWithoutExt}-preview.jpg",
                'thumbnail_url' => "{$this->baseS3Url}/{$item['id']}/conversions/{$nameWithoutExt}-thumb.jpg",
                'original_url' => "{$this->baseS3Url}/{$listingId}/{$filename}",
                'xl_url' => "{$this->baseS3Url}/{$item['id']}/conversions/{$nameWithoutExt}-xl.jpg",
                'order' => $index,
                'extension' => $extension,
            ];
        }
        
        return response()->json($response);
    }
}