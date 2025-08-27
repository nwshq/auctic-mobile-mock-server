<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MockJWTtokenControllerTest extends TestCase
{
    #[Test]
    public function it_returns_mobile_deep_link_with_correct_structure()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mobileDeepLink',
                'qrCode',
                'accessToken',
                'jwt',
                'config' => [
                    'apiConfig' => [
                        'rootUrl',
                        'apiUrl'
                    ],
                    'pusherConfig' => [
                        'apiKey',
                        'authEndpoint',
                        'cluster',
                        'useTls'
                    ],
                    'loggingEnabled',
                    'debugLoggingEnabled',
                    'useQualityToGenerateSaleOrder',
                    'qualitySalesOrderRanges' => [
                        'excellent',
                        'good',
                        'average',
                        'poor'
                    ]
                ]
            ]);
    }

    #[Test]
    public function it_returns_valid_mobile_deep_link_format()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertStringStartsWith('auctic-mobile://auth/verify?token=', $data['mobileDeepLink']);
    }

    #[Test]
    public function it_returns_valid_jwt_token_format()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // JWT should have 3 parts separated by dots
        $jwtParts = explode('.', $data['jwt']);
        $this->assertCount(3, $jwtParts);
        
        // Each part should be base64url encoded (URL-safe base64)
        foreach ($jwtParts as $part) {
            // Convert base64url to base64
            $base64 = strtr($part, '-_', '+/');
            $base64 = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
            $this->assertNotFalse(base64_decode($base64, true));
        }
    }

    #[Test]
    public function it_returns_mock_personal_access_token()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertStringStartsWith('mock_pat_', $data['accessToken']);
        $this->assertEquals(49, strlen($data['accessToken'])); // mock_pat_ (9 chars) + 40 random chars
    }

    #[Test]
    public function it_returns_qr_code_as_base64_data_url()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertStringStartsWith('data:image/png;base64,', $data['qrCode']);
    }

    #[Test]
    public function it_includes_jwt_token_in_mobile_deep_link()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Extract token from deep link
        $deepLinkUrl = parse_url($data['mobileDeepLink']);
        parse_str($deepLinkUrl['query'] ?? '', $queryParams);
        
        $this->assertArrayHasKey('token', $queryParams);
        // The token in the deep link should be URL encoded version of JWT
        $this->assertEquals($data['jwt'], urldecode($queryParams['token']));
    }

    #[Test]
    public function it_returns_consistent_access_token_in_jwt_payload()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Decode JWT payload (middle part - base64url encoded)
        $jwtParts = explode('.', $data['jwt']);
        $base64 = strtr($jwtParts[1], '-_', '+/');
        $base64 = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
        $payload = json_decode(base64_decode($base64), true);
        
        $this->assertArrayHasKey('encrypted_payload', $payload);
        
        // For testing with OpenSSL encryption, we'll decrypt the payload
        $encryptedData = base64_decode($payload['encrypted_payload']);
        
        // Extract IV (first 16 bytes) and encrypted content
        $iv = substr($encryptedData, 0, 16);
        $encrypted = substr($encryptedData, 16);
        
        // Get the decryption key from config (it's base64 encoded)
        $key = base64_decode(config('jwt.encryption_key'));
        
        // Decrypt the payload
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        $decryptedPayload = json_decode($decrypted, true);
        
        $this->assertEquals($data['accessToken'], $decryptedPayload['accessToken']);
    }

    #[Test]
    public function it_returns_valid_jwt_expiration_time()
    {
        $response = $this->getJson('/mobile/profile');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Decode JWT payload (base64url encoded)
        $jwtParts = explode('.', $data['jwt']);
        $base64 = strtr($jwtParts[1], '-_', '+/');
        $base64 = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
        $payload = json_decode(base64_decode($base64), true);
        
        $this->assertArrayHasKey('exp', $payload);
        $this->assertGreaterThan(time(), $payload['exp']);
        // Should expire in configured time (default 60 minutes = 3600 seconds)
        $expirationMinutes = config('jwt.expires_at', 60);
        $this->assertLessThanOrEqual(time() + ($expirationMinutes * 60) + 100, $payload['exp']);
    }
}