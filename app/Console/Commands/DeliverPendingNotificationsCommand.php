<?php

namespace App\Console\Commands;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Notifications\NotificationDeliveryService;
use Illuminate\Console\Command;

class DeliverPendingNotificationsCommand extends Command
{
    protected $signature = 'notifications:drain-local';

    protected $description = 'Deliver queued notifications from DB, transactional first. Useful for local tests without Kafka.';

    public function handle(NotificationDeliveryService $deliveryService): int
    {
        $this->drainByPriority(NotificationPriority::TRANSACTIONAL, $deliveryService);
        $this->drainByPriority(NotificationPriority::MARKETING, $deliveryService);

        return self::SUCCESS;
    }

    private function drainByPriority(NotificationPriority $priority, NotificationDeliveryService $deliveryService): void
    {
        $pendingStatuses = [
            NotificationStatus::QUEUED->value,
            NotificationStatus::SENT->value,
        ];

        Notification::query()
            ->where('priority', $priority->value)
            ->whereIn('status', $pendingStatuses)
            ->orderBy('created_at')
            ->each(fn (Notification $notification) => $deliveryService->deliver($notification->id));
    }
}
