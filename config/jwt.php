<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing JWT configuration settings used by the
    | application for encoding and decoding JSON Web Tokens.
    |
    */

    'key' => env('JWT_KEY', base64_encode('mock-jwt-key-for-testing-purposes-32-characters-long')),
    'audience' => env('JWT_AUDIENCE', 'com.auctic.mobile'),
    'expires_at' => env('JWT_EXPIRES_AT', 60), // minutes
    'encryption_key' => env('JWT_ENCRYPTION_KEY', base64_encode('mock-encryption-key-32-characters-long-for-test')),
];