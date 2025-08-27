<?php

namespace Tests\Feature\MobileApi;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    #[Test]
    public function it_returns_user_data_with_correct_structure()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer mock_pat_token123'
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'permissions' => [
                    'mobile-api.catalog',
                    'mobile-api.catalog.view',
                    'mobile-api.catalog.edit',
                    'mobile-api.catalog.media.create'
                ],
                'features',
                'user' => [
                    'id',
                    'name',
                    'email_verified_at',
                    'default_buyer_display_name',
                    'current_team_id',
                    'owned_team_count',
                    'initials'
                ]
            ]);
    }

    #[Test]
    public function it_returns_correct_permissions_structure()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer mock_pat_token123'
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertArrayHasKey('permissions', $data);
        $this->assertIsBool($data['permissions']['mobile-api.catalog']);
        $this->assertIsBool($data['permissions']['mobile-api.catalog.view']);
        $this->assertIsBool($data['permissions']['mobile-api.catalog.edit']);
        $this->assertIsBool($data['permissions']['mobile-api.catalog.media.create']);
    }

    #[Test]
    public function it_returns_features_array()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer mock_pat_token123'
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertArrayHasKey('features', $data);
        $this->assertIsArray($data['features']);
        $this->assertContains('mobile-api.v1.catalog', $data['features']);
    }

    #[Test]
    public function it_returns_user_with_all_required_fields()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer mock_pat_token123'
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertArrayHasKey('user', $data);
        $user = $data['user'];
        
        $this->assertIsInt($user['id']);
        $this->assertIsString($user['name']);
        $this->assertNotEmpty($user['name']);
        $this->assertIsString($user['initials']);
        $this->assertEquals(2, strlen($user['initials']));
        $this->assertIsInt($user['owned_team_count']);
    }

    #[Test]
    public function it_returns_401_without_authorization_header()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json'
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_returns_401_with_invalid_bearer_token()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer invalid'
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_returns_valid_email_verified_at_timestamp()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer mock_pat_token123'
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Check if email_verified_at is a valid timestamp format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $data['user']['email_verified_at']
        );
    }

    #[Test]
    public function it_accepts_any_valid_mock_pat_token()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer mock_pat_' . str_repeat('a', 40)
        ])->getJson('/mobile-api/v1/user');

        $response->assertStatus(200);
    }
}