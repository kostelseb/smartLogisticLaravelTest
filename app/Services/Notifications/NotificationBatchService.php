<?php

namespace App\Services\Notifications;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use App\Services\Kafka\MessagePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class NotificationBatchService
{
    public function __construct(private MessagePublisher $publisher)
    {
    }

    public function create(CreateNotificationBatchData $data): NotificationBatch
    {
        if ($existing = NotificationBatch::query()->where('idempotency_key', $data->idempotencyKey)->first()) {
            return $existing->load('notifications');
        }

        $existingRecipients = Subscriber::query()
            ->whereIn('id', $data->recipientIds)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();

        $missingRecipients = array_values(array_diff($data->recipientIds, $existingRecipients));
        if ($missingRecipients !== []) {
            throw ValidationException::withMessages([
                'recipient_ids' => ['Unknown subscribers: '.implode(', ', $missingRecipients)],
            ]);
        }

        return DB::transaction(function () use ($data): NotificationBatch {
            $batch = NotificationBatch::query()->create([
                'idempotency_key' => $data->idempotencyKey,
                'channel' => $data->channel,
                'priority' => $data->priority,
                'message' => $data->message,
                'queued_count' => count($data->recipientIds),
            ]);

            foreach ($data->recipientIds as $recipientId) {
                $notification = Notification::query()->create([
                    'batch_id' => $batch->id,
                    'subscriber_id' => $recipientId,
                    'channel' => $data->channel,
                    'priority' => $data->priority,
                    'status' => NotificationStatus::QUEUED,
                    'deduplication_key' => "{$batch->id}:{$recipientId}",
                ]);

                // Publish only after commit so the Kafka consumer can see the notification row in DB.
                DB::afterCommit(function () use ($data, $batch, $notification): void {
                    $this->publisher->publish($data->priority->topic(), $notification->id, [
                        'notification_id' => $notification->id,
                        'batch_id' => $batch->id,
                        'priority' => $data->priority->value,
                    ]);
                });
            }

            return $batch->load('notifications');
        });
    }
}
