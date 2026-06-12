<?php

namespace App\Console\Commands;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Notifications\NotificationDeliveryService;
use Illuminate\Console\Command;

class DeliverQueuedNotificationsCommand extends Command
{
    protected $signature = 'notifications:drain-local';

    protected $description = 'Deliver queued notifications from DB, transactional first. Useful for local tests without Kafka.';

    public function handle(NotificationDeliveryService $deliveryService): int
    {
        foreach ([NotificationPriority::TRANSACTIONAL, NotificationPriority::MARKETING] as $priority) {
            Notification::query()
                ->where('priority', $priority->value)
                ->whereIn('status', [NotificationStatus::QUEUED->value, NotificationStatus::SENT->value])
                ->orderBy('created_at')
                ->each(fn (Notification $notification) => $deliveryService->deliver($notification->id));
        }

        return self::SUCCESS;
    }
}
