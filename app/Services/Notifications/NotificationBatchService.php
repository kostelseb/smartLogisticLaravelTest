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

        $existingSubscribers = Subscriber::query()
            ->whereIn('id', $data->subscriberIds)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();

        $missingSubscribers = array_values(array_diff($data->subscriberIds, $existingSubscribers));
        if ($missingSubscribers !== []) {
            throw ValidationException::withMessages([
                'subscriber_ids' => ['Unknown subscribers: '.implode(', ', $missingSubscribers)],
            ]);
        }

        return DB::transaction(function () use ($data): NotificationBatch {
            $batch = NotificationBatch::query()->create([
                'idempotency_key' => $data->idempotencyKey,
                'channel' => $data->channel,
                'priority' => $data->priority,
                'message' => $data->message,
                'queued_count' => count($data->subscriberIds),
            ]);

            foreach ($data->subscriberIds as $subscriberId) {
                $notification = Notification::query()->create([
                    'batch_id' => $batch->id,
                    'subscriber_id' => $subscriberId,
                    'channel' => $data->channel,
                    'priority' => $data->priority,
                    'status' => NotificationStatus::QUEUED,
                    'deduplication_key' => "{$batch->id}:{$subscriberId}",
                ]);

                DB::afterCommit(function () use ($data, $batch, $notification): void {
                    $this->publisher->publish($data->priority->getTopic(), $notification->id, [
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
