<?php

return [
    // В Laravel конфиги - обычные PHP-массивы в config/*.php; это ближайший аналог Symfony YAML config.
    'kafka' => [
        'fake' => (bool) env('KAFKA_FAKE', false),
        'topics' => [
            'transactional' => env('KAFKA_TOPIC_TRANSACTIONAL', 'notifications.transactional'),
            'marketing' => env('KAFKA_TOPIC_MARKETING', 'notifications.marketing'),
        ],
    ],

    'retry' => [
        'max_attempts' => (int) env('NOTIFICATION_MAX_ATTEMPTS', 3),
    ],
];
