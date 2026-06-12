<?php

namespace App\Presentation\Controllers\Api;

use App\Models\Subscriber;
use App\Presentation\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SubscriberNotificationController extends Controller
{
    public function index(Subscriber $subscriber): JsonResponse
    {
        $notifications = $subscriber->notifications()
            ->with(['batch', 'attempts'])
            ->latest()
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'batch_id' => $notification->batch_id,
                'channel' => $notification->channel->value,
                'priority' => $notification->priority->value,
                'message' => $notification->batch->message,
                'status' => $notification->status->value,
                'attempts' => $notification->attempts->map(fn ($attempt): array => [
                    'attempt' => $attempt->attempt,
                    'provider' => $attempt->provider,
                    'status' => $attempt->status->value,
                    'error' => $attempt->error,
                    'created_at' => $attempt->created_at?->toISOString(),
                ])->values(),
                'created_at' => $notification->created_at?->toISOString(),
            ]);

        return response()->json([
            'subscriber' => [
                'id' => $subscriber->id,
                'name' => $subscriber->name,
                'email' => $subscriber->email,
                'phone' => $subscriber->phone,
            ],
            'notifications' => $notifications,
        ]);
    }
}
