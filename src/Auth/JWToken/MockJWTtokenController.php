<?php

namespace MockServer\Auth\JWToken;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MockJWTtokenController
{
    private const MOBILE_DEEP_LINK_PREFIX = 'auctic-mobile://auth/verify';

    public function __invoke(Request $request)
    {
        $pat = $this->createMockPersonalAccessToken();
        $jwt = $this->createMockJwt($pat);
        $mobileDeepLink = $this->generateMobileDeepLink($jwt);
        $qrCode = $this->generateMockQrCode($mobileDeepLink);

        return response()->json([
            'qrCode' => $qrCode,
            'mobileDeepLink' => $mobileDeepLink,
            'accessToken' => $pat,
            'jwt' => $jwt,
            'config' => $this->getMockMobileConfig()
        ]);
    }

    private function createMockPersonalAccessToken(): string
    {
        return 'mock_pat_' . Str::random(40);
    }

    private function getMockMobileConfig(): array
    {
        return [
            'apiConfig' => [
                'rootUrl' => config('app.url', 'http://localhost:8000'),
                'apiUrl' => config('app.url', 'http://localhost:8000') . '/mobile-api/v1',
            ],
            'pusherConfig' => [
                'apiKey' => config('broadcasting.connections.pusher.key'),
                'authEndpoint' => url('broadcasting/auth'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTls' => config('broadcasting.connections.pusher.options.useTLS'),
            ],
            'loggingEnabled' => config('app.debug'),
            'debugLoggingEnabled' => config('app.debug'),
            'useQualityToGenerateSaleOrder' => true,
            'qualitySalesOrderRanges' => [
                'excellent' => 1000,
                'good' => 2000,
                'average' => 3000,
                'poor' => 4000,
            ],
        ];
    }

    private function createMockJwt(string $pat): string
    {
        $header = base64_encode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));

        $payload = base64_encode(json_encode([
            'iss' => config('app.url', 'http://localhost:8000'),
            'aud' => 'auctic-mobile-app',
            'exp' => time() + 3600,
            'sub' => 'mock_user_' . rand(1, 1000),
            'encrypted_payload' => $this->encryptMockPayload([
                'config' => $this->getMockMobileConfig(),
                'accessToken' => $pat,
            ])
        ]));

        $signature = base64_encode('mock_signature_' . Str::random(20));

        return $header . '.' . $payload . '.' . $signature;
    }

    private function encryptMockPayload(array $payload): string
    {
        // Mock encryption key - in production this would come from config
        $key = base64_decode('bW9ja19lbmNyeXB0aW9uX2tleV9mb3JfdGVzdGluZw==');
        $iv = random_bytes(16);
        $data = json_encode($payload);
        
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Combine IV and encrypted data
        $combined = $iv . $encrypted;
        
        // Base64 encode the result
        return base64_encode($combined);
    }

    private function generateMockQrCode(string $data): string
    {
        $mockQrData = base64_encode('MOCK_QR_CODE_FOR: ' . $data);
        return 'data:image/png;base64,' . $mockQrData;
    }

    private function generateMobileDeepLink(string $jwt): string
    {
        return sprintf(
            '%s?token=%s',
            self::MOBILE_DEEP_LINK_PREFIX,
            urlencode($jwt)
        );
    }
}