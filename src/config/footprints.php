<?php

return [
    'enabled' => env('FOOTPRINTS_ENABLED', true),
    'app_name' => env('FOOTPRINTS_APPLICATION_NAME', env('APP_NAME', 'unnamed-laravel-app')),
    'queue' => [
        'connection' => env('FOOTPRINTS_QUEUE_CONNECTION', 'database'),
        'queue' => env('FOOTPRINTS_QUEUE_NAME', 'default'),
    ],
    'mask_fields' => env(
        "FOOTPRINTS_HIDDEN_FIELDS",
        "password,password_confirmation,new_pin,pin,credit_card,api_key,token"
    ),
    "table_name" => env('FOOTPRINTS_TABLE_NAME', 'application_footprints'),
    'kafka' => [
        'brokers' => env('FOOTPRINTS_KAFKA_BROKERS'),
        'topic' => env('FOOTPRINTS_KAFKA_TOPIC', 'application_footprints'),
        'client_id' => env('FOOTPRINTS_KAFKA_CLIENT_ID', 'laravel_logger'),
        'timeout_ms' => env('FOOTPRINTS_KAFKA_TIMEOUT_MS', 1000),
        'sasl_mechanism' => env('FOOTPRINTS_KAFKA_SASL_MECHANISM'),
        'security_protocol' => env('FOOTPRINTS_KAFKA_SECURITY_PROTOCOL'),
        'sasl_username' => env('FOOTPRINTS_KAFKA_SASL_USERNAME'),
        'sasl_password' => env('FOOTPRINTS_KAFKA_SASL_PASSWORD'),
        // e.g., 'message_key_func' => function($footprint) { return $footprint['request_id']; }
        'message_key_func' => env('FOOTPRINTS_KAFKA_MESSAGE_KEY_FUNC', 'TNM\Footprints\Utils\getDefaultEventKey')(...),
    ]
];