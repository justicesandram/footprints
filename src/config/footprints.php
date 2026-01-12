<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Footprints
    |--------------------------------------------------------------------------
    |
    | Master switch to enable or disable the logging globally.
    |
    */
    'enabled' => env('FOOTPRINTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Service Identification
    |--------------------------------------------------------------------------
    | The name of this application as it should for unique identification.
    */
    'service_name' => env('FOOTPRINT_SERVICE_NAME', env('APP_NAME', 'laravel-app')),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | The queue connection and name to use for the background logging job.
    |
    */
    'queue' => [
        'connection' => env('FOOTPRINTS_QUEUE_CONNECTION', 'database'),
        'queue' => env('FOOTPRINTS_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Channels & Order
    |--------------------------------------------------------------------------
    |
    | Specify which channels to log to and in what order.
    | Available drivers: 'file', 'database', 'kafka', 'elasticsearch'
    |
    */
    'channels' => env("FOOTPRINTS_CHANNELS", "file"),

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Masking
    |--------------------------------------------------------------------------
    |
    | specific fields in the request body to hide.
    |
    */
    'mask_fields' => env("FOOTPRINTS_HIDDEN_FIELDS",
        "password,password_confirmation,new_pin,pin,credit_card,api_key"),

    /*
    |--------------------------------------------------------------------------
    | Channel Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'file' => [
            'path' => env('FOOTPRINTS_FILE_PATH', storage_path('logs/footprints.log')),
            'min_free_space_mb' => env('FOOTPRINTS_FILE_MIN_FREE_SPACE_MB', 100),
        ],

        'database' => [
            'table_name' => env('FOOTPRINTS_TABLE_NAME', 'footprints'),
            'connection' => env('DB_CONNECTION', 'mysql'),
        ],

        'kafka' => [
            'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
            'topic' => env('KAFKA_TOPIC', 'app_footprints'),
            'client_id' => env('KAFKA_CLIENT_ID', 'laravel_logger'),
            'timeout_ms' => env('KAFKA_TIMEOUT_MS', 1000),
            'sasl_mechanism' => env('KAFKA_SASL_MECHANISM'),
            'security_protocol' => env('KAFKA_SECURITY_PROTOCOL'),
            'sasl_username' => env('KAFKA_SASL_USERNAME'),
            'sasl_password' => env('KAFKA_SASL_PASSWORD'),
            // Message key: null (no key), string (field name from footprint), or callable
            // Set to null to disable message keys, or a field name like 'request_id'
            // For callable functions, set directly in config file (not via env):
            // 'message_key' => function($footprint) { return $footprint['request_id']; }
            'message_key' => env('KAFKA_MESSAGE_KEY', null),
        ],

        'elasticsearch' => [
            'hosts' => explode(',', env('ELASTICSEARCH_HOSTS', 'localhost:9200')),
            'index' => env('ELASTICSEARCH_INDEX', 'footprints_logs'),
            // Authentication: username/password or API key (use one or the other)
            'username' => env('ELASTICSEARCH_USERNAME', null),
            'password' => env('ELASTICSEARCH_PASSWORD', null),
            'api_key' => env('ELASTICSEARCH_API_KEY', null),
            // Operation type: 'index' (default, allows updates) or 'create' (for datastreams, fails if exists)
            'operation_type' => env('ELASTICSEARCH_OPERATION_TYPE', 'index'),
            // Document ID: string (field name from footprint, default: 'request_id') or callable
            // Set a field name like 'request_id' to use that field's value as document ID
            // For callable functions, set directly in config file (not via env):
            // 'document_id_field' => function($footprint) { return $footprint['request_id']; }
            'document_id_field' => env('ELASTICSEARCH_DOCUMENT_ID_FIELD', 'request_id'),
        ],
    ],
];
