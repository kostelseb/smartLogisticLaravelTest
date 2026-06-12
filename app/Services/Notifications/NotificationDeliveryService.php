<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Providers\EmailProviderInterface;
use App\Services\Providers\ProviderResult;
use App\Services\Providers\SmsProviderInterface;
use Illuminate\Support\Facades\DB;

class NotificationDeliveryService
{
    public function __construct(
        private readonly SmsProviderInterface $smsProvider,
        private readonly EmailProviderInterface $emailProvider,
    ) {
    }

    public function deliver(string $notificationId): Notification
    {
        return DB::transaction(function () use ($notificationId): Notification {
            $notification = Notification::query()
                ->with(['subscriber', 'attempts', 'batch'])
                ->lockForUpdate()
                ->findOrFail($notificationId);

            if (in_array($notification->status, [NotificationStatus::DELIVERED, NotificationStatus::DROPPED], true)) {
                return $notification;
            }

            $providerName = $notification->channel === NotificationChannel::SMS ? 'fake-sms' : 'fake-email';
            $result = $this->attemptDelivery($notification);
            $attempt = $notification->attempts()->count() + 1;

            $notification->attempts()->create([
                'attempt' => $attempt,
                'provider' => $providerName,
                'status' => $result->success ? NotificationStatus::DELIVERED : NotificationStatus::DROPPED,
                'error' => $result->error,
            ]);

            $notification->forceFill([
                'status' => NotificationStatus::SENT,
                'sent_at' => $notification->sent_at ?? now(),
            ])->save();

            if ($result->success) {
                $notification->forceFill([
                    'status' => NotificationStatus::DELIVERED,
                    'delivered_at' => now(),
                ])->save();
            } elseif (! $result->retryable || $attempt >= config('notifications.retry.max_attempts')) {
                $notification->forceFill([
                    'status' => NotificationStatus::DROPPED,
                    'dropped_at' => now(),
                ])->save();
            }

            $this->refreshBatchCounters($notification);

            return $notification->refresh()->load(['subscriber', 'attempts', 'batch']);
        });
    }

    private function attemptDelivery(Notification $notification): ProviderResult
    {
        return match ($notification->channel) {
            NotificationChannel::SMS => $this->smsProvider->send($notification),
            NotificationChannel::EMAIL => $this->emailProvider->send($notification),
        };
    }

    private function refreshBatchCounters(Notification $notification): void
    {
        $batch = $notification->batch;
        $counts = $batch->notifications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $batch->forceFill([
            'queued_count' => (int) ($counts[NotificationStatus::QUEUED->value] ?? 0),
            'sent_count' => (int) ($counts[NotificationStatus::SENT->value] ?? 0),
            'delivered_count' => (int) ($counts[NotificationStatus::DELIVERED->value] ?? 0),
            'dropped_count' => (int) ($counts[NotificationStatus::DROPPED->value] ?? 0),
        ])->save();
    }
}
