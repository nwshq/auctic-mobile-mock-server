<?php

return [
    'auth_failure' => [
        'name' => 'Authentication Failure',
        'description' => 'Simulates authentication failures',
        'responses' => [
            '*' => [
                'type' => 'error',
                'status_code' => 401,
                'data' => [
                    'error' => 'Unauthorized',
                    'message' => 'Invalid authentication token',
                    'code' => 'AUTH_001'
                ]
            ]
        ]
    ],
    
    'server_error' => [
        'name' => 'Server Error',
        'description' => 'Simulates 500 internal server errors',
        'responses' => [
            '*' => [
                'type' => 'error',
                'status_code' => 500,
                'data' => [
                    'error' => 'Internal Server Error',
                    'message' => 'An unexpected error occurred',
                    'code' => 'SRV_001'
                ]
            ]
        ]
    ],
    
    'rate_limit' => [
        'name' => 'Rate Limiting',
        'description' => 'Simulates rate limit exceeded responses',
        'responses' => [
            '*' => [
                'type' => 'error',
                'status_code' => 429,
                'data' => [
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => 60
                ],
                'headers' => [
                    'Retry-After' => '60'
                ]
            ]
        ]
    ],
    
    'network_timeout' => [
        'name' => 'Network Timeout',
        'description' => 'Simulates network timeouts with delays',
        'responses' => [
            '*' => [
                'type' => 'error',
                'delay_ms' => 30000,
                'status_code' => 504,
                'data' => [
                    'error' => 'Gateway Timeout',
                    'message' => 'The server did not respond in time'
                ]
            ]
        ]
    ],
    
    'maintenance_mode' => [
        'name' => 'Maintenance Mode',
        'description' => 'Simulates service maintenance',
        'responses' => [
            '*' => [
                'type' => 'error',
                'status_code' => 503,
                'data' => [
                    'error' => 'Service Unavailable',
                    'message' => 'The service is currently under maintenance. Please try again later.',
                    'maintenance_until' => '{{timestamp}}'
                ],
                'headers' => [
                    'Retry-After' => '3600'
                ]
            ]
        ]
    ],
    
    'validation_error' => [
        'name' => 'Validation Errors',
        'description' => 'Simulates request validation errors',
        'responses' => [
            '*' => [
                'type' => 'error',
                'status_code' => 422,
                'data' => [
                    'error' => 'Validation Error',
                    'message' => 'The given data was invalid',
                    'errors' => [
                        'field_name' => [
                            'The field_name field is required.'
                        ]
                    ]
                ]
            ]
        ]
    ],
    
    'not_found' => [
        'name' => 'Resource Not Found',
        'description' => 'Simulates 404 not found responses',
        'responses' => [
            '*' => [
                'type' => 'error',
                'status_code' => 404,
                'data' => [
                    'error' => 'Not Found',
                    'message' => 'The requested resource was not found'
                ]
            ]
        ]
    ]
];