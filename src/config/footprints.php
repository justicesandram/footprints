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
            'path' => storage_path('logs/footprints.log'),
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
        ],

        'elasticsearch' => [
            'hosts' => explode(',', env('ELASTICSEARCH_HOSTS', 'localhost:9200')),
            'index' => env('ELASTICSEARCH_INDEX', 'footprints_logs'),
        ],
    ],
];
