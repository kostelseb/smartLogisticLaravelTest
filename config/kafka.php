<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
    'securityProtocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),

    'sasl' => [
        'mechanisms' => env('KAFKA_MECHANISMS', 'PLAINTEXT'),
        'username' => env('KAFKA_USERNAME'),
        'password' => env('KAFKA_PASSWORD'),
    ],

    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'notification-service'),
    'consumer_timeout_ms' => (int) env('KAFKA_CONSUMER_DEFAULT_TIMEOUT', 2000),
    'offset_reset' => env('KAFKA_OFFSET_RESET', 'earliest'),
    'auto_commit' => filter_var(env('KAFKA_AUTO_COMMIT', true), FILTER_VALIDATE_BOOL),
    'sleep_on_error' => (int) env('KAFKA_ERROR_SLEEP', 5),
    'partition' => (int) env('KAFKA_PARTITION', 0),
    'compression' => env('KAFKA_COMPRESSION_TYPE', 'snappy'),
    'debug' => filter_var(env('KAFKA_DEBUG', false), FILTER_VALIDATE_BOOL),

    'flush_retry_sleep_in_ms' => 100,
    'flush_retries' => 10,
    'flush_timeout_in_ms' => 1000,

    'cache_driver' => env('KAFKA_CACHE_DRIVER', env('CACHE_STORE', 'database')),
    'message_id_key' => env('MESSAGE_ID_KEY', 'laravel-kafka::message-id'),
];
