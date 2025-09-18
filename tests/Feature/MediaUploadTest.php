<?php

namespace Tests\Feature;

use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    /**
     * Test that request-upload endpoint returns 422 when media size is 0
     */
    public function test_request_upload_returns_422_when_size_is_zero()
    {
        $response = $this->postJson('/mobile-api/v1/catalog/request-upload', [
            'media' => [
                [
                    'identifier' => 'test-123',
                    'filename' => 'test.jpg',
                    'content_type' => 'image/jpeg',
                    'size' => 0,  // Invalid size - should be at least 1
                ]
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['media.0.size']);
    }

    /**
     * Test that request-upload endpoint accepts valid media with size > 0
     */
    public function test_request_upload_accepts_valid_media_with_positive_size()
    {
        $response = $this->postJson('/mobile-api/v1/catalog/request-upload', [
            'media' => [
                [
                    'identifier' => 'test-123',
                    'filename' => 'test.jpg',
                    'content_type' => 'image/jpeg',
                    'size' => 1024,  // Valid size
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'identifier',
                        'storage_key',
                        'upload_url',
                        'expires_at'
                    ]
                ]
            ]);
    }

    /**
     * Test that request-upload endpoint returns 422 when media size is negative
     */
    public function test_request_upload_returns_422_when_size_is_negative()
    {
        $response = $this->postJson('/mobile-api/v1/catalog/request-upload', [
            'media' => [
                [
                    'identifier' => 'test-123',
                    'filename' => 'test.jpg',
                    'content_type' => 'image/jpeg',
                    'size' => -100,  // Invalid negative size
                ]
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['media.0.size']);
    }

    /**
     * Test that request-upload endpoint returns 422 for multiple invalid sizes
     */
    public function test_request_upload_returns_422_for_multiple_invalid_sizes()
    {
        $response = $this->postJson('/mobile-api/v1/catalog/request-upload', [
            'media' => [
                [
                    'identifier' => 'test-123',
                    'filename' => 'test1.jpg',
                    'content_type' => 'image/jpeg',
                    'size' => 0,  // Invalid
                ],
                [
                    'identifier' => 'test-456',
                    'filename' => 'test2.jpg',
                    'content_type' => 'image/jpeg',
                    'size' => 1024,  // Valid
                ],
                [
                    'identifier' => 'test-789',
                    'filename' => 'test3.jpg',
                    'content_type' => 'image/jpeg',
                    'size' => -1,  // Invalid
                ]
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['media.0.size', 'media.2.size']);
    }
}