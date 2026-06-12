<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;

final readonly class CreateNotificationBatchData
{
    /**
     * @param array<int, int> $subscriberIds
     */
    public function __construct(
        public string $idempotencyKey,
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $message,
        public array $subscriberIds
        ,
    ) {
    }
}
