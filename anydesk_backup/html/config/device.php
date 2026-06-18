<?php

return [
    'java_api' => [
        'base_url' => env('JAVA_API_BASE_URL', 'http://localhost:8080'),
        'timeout' => env('JAVA_API_TIMEOUT', 30),
        'retry_attempts' => env('JAVA_API_RETRY_ATTEMPTS', 3),
    ],
    
    'tcp_server' => [
        'host' => env('TCP_SERVER_HOST', 'localhost'),
        'port' => env('TCP_SERVER_PORT', 18887),
    ],
    
    'qr_code' => [
        'validation_enabled' => env('QR_VALIDATION_ENABLED', true),
        'max_usage_limit' => env('QR_MAX_USAGE_LIMIT', 1),
    ],
    
    'device_commands' => [
        'open_door' => '5000',
        'close_door' => '5001',
        'trigger_alarm' => '5E00',
        'clear_cards' => '4202',
        'get_status' => '4100',
    ],
];