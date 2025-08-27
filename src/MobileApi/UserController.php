<?php

namespace MockServer\MobileApi;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserController
{
    public function __invoke(Request $request)
    {
        // Simple mock authorization - accept any token starting with "mock_pat_"
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        $token = str_replace('Bearer ', '', $authHeader);
        
        // Accept any token that starts with mock_pat_ or is exactly mock_pat_token123
        if (!str_starts_with($token, 'mock_pat_') && $token !== 'mock_pat_token123') {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Generate random but consistent user data based on token
        $userId = crc32($token) % 10000 + 1;
        $names = [
            'Ivan Novak', 'Maria Garcia', 'John Smith', 'Anna Johnson', 
            'Peter Williams', 'Sofia Martinez', 'Michael Brown', 'Emma Davis',
            'Lucas Anderson', 'Olivia Wilson'
        ];
        
        $nameIndex = $userId % count($names);
        $name = $names[$nameIndex];
        $initials = $this->getInitials($name);
        
        return response()->json([
            'permissions' => [
                'mobile-api.catalog' => true,
                'mobile-api.catalog.view' => true,
                'mobile-api.catalog.edit' => true,
                'mobile-api.catalog.media.create' => true
            ],
            'features' => [
                'mobile-api.v1.catalog'
            ],
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email_verified_at' => Carbon::now()->subDays(rand(1, 365))->format('Y-m-d\TH:i:s.u\Z'),
                'default_buyer_display_name' => null,
                'current_team_id' => null,
                'owned_team_count' => rand(1, 5),
                'initials' => $initials
            ]
        ]);
    }
    
    private function getInitials(string $name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        
        foreach ($words as $word) {
            if (strlen($word) > 0) {
                $initials .= strtoupper($word[0]);
            }
        }
        
        return substr($initials, 0, 2);
    }
}