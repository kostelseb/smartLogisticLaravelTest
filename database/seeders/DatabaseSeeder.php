<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\ProviderFailureMode;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSubscribers();
        $this->seedDemoBatch();
    }

    private function seedSubscribers(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Subscriber::query()->updateOrCreate(
                ['id' => $i],
                [
                    'name' => "Subscriber {$i}",
                    'email' => "subscriber{$i}@example.test",
                    'phone' => '+790000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'provider_failure_mode' => match ($i) {
                        4 => ProviderFailureMode::PERMANENT,
                        5 => ProviderFailureMode::TEMPORARY_ONCE,
                        default => ProviderFailureMode::NONE,
                    },
                ]
            );
        }
    }

    private function seedDemoBatch(): void
    {
        $batch = NotificationBatch::query()->find('019eb82a-1605-7392-ba67-7287397244fa')
            ?? new NotificationBatch();

        $batch->forceFill([
            'id' => '019eb82a-1605-7392-ba67-7287397244fa',
            'idempotency_key' => 'seeded-transactional-batch',
            'channel' => NotificationChannel::SMS,
            'priority' => NotificationPriority::TRANSACTIONAL,
            'message' => 'Seeded transactional notification',
            'queued_count' => 1,
            'sent_count' => 1,
            'delivered_count' => 1,
            'dropped_count' => 0,
        ])->save();

        $notification = Notification::query()->find('019eb82a-1605-7392-ba67-7287397244fb')
            ?? new Notification();

        $notification->forceFill([
            'id' => '019eb82a-1605-7392-ba67-7287397244fb',
            'batch_id' => $batch->id,
            'subscriber_id' => 1,
            'channel' => NotificationChannel::SMS,
            'priority' => NotificationPriority::TRANSACTIONAL,
            'status' => NotificationStatus::DELIVERED,
            'deduplication_key' => "{$batch->id}:1",
            'sent_at' => now(),
            'delivered_at' => now(),
        ])->save();

        NotificationAttempt::query()->updateOrCreate(
            [
                'notification_id' => $notification->id,
                'attempt' => 1,
            ],
            [
                'provider' => 'fake-sms',
                'status' => NotificationStatus::DELIVERED,
                'error' => null,
            ]
        );
    }
}
