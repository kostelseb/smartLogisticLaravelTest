<?php

namespace App\Presentation\Controllers\Api;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Models\NotificationBatch;
use App\Presentation\Controllers\Controller;
use App\Services\Notifications\CreateNotificationBatchData;
use App\Services\Notifications\NotificationBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationBatchController extends Controller
{
    public function store(Request $request, NotificationBatchService $service): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['Idempotency-Key header is required.'],
            ]);
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'priority' => ['required', Rule::enum(NotificationPriority::class)],
            'message' => ['required', 'string', 'max:1000'],
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', 'distinct'],
        ]);

        $batch = $service->create(new CreateNotificationBatchData(
            idempotencyKey: $idempotencyKey,
            channel: NotificationChannel::from($validated['channel']),
            priority: NotificationPriority::from($validated['priority']),
            message: $validated['message'],
            recipientIds: array_map('intval', $validated['recipient_ids']),
        ));

        return response()->json($this->presentBatch($batch), 201);
    }

    public function show(NotificationBatch $notificationBatch): JsonResponse
    {
        return response()->json($this->presentBatch($notificationBatch->load('notifications.subscriber')));
    }

    private function presentBatch(NotificationBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'channel' => $batch->channel->value,
            'priority' => $batch->priority->value,
            'message' => $batch->message,
            'counters' => [
                'queued' => $batch->queued_count,
                'sent' => $batch->sent_count,
                'delivered' => $batch->delivered_count,
                'dropped' => $batch->dropped_count,
            ],
            'notifications' => $batch->notifications->map(fn ($notification): array => [
                'id' => $notification->id,
                'subscriber_id' => $notification->subscriber_id,
                'status' => $notification->status->value,
                'created_at' => $notification->created_at?->toISOString(),
            ])->values(),
        ];
    }
}
