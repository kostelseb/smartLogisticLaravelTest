<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case TRANSACTIONAL = 'transactional';
    case MARKETING = 'marketing';

    public function topic(): string
    {
        return match ($this) {
            self::TRANSACTIONAL => config('notifications.kafka.topics.transactional'),
            self::MARKETING => config('notifications.kafka.topics.marketing'),
        };
    }
}
