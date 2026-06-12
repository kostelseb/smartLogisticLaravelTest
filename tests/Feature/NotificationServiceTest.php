<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\ProviderFailureMode;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use App\Models\Subscriber;
use App\Services\Kafka\FakeMessagePublisher;
use App\Services\Kafka\MessagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSubscribers();
    }

    public function test_priority_ordering(): void
    {
        $this->postJson('/api/notification-batches', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Critical first',
            'subscriber_ids' => [1],
        ], ['Idempotency-Key' => 'critical-first'])->assertCreated();

        $this->postJson('/api/notification-batches', [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'Marketing campaign',
            'subscriber_ids' => [2, 3, 4, 5, 6, 7, 8, 9],
        ], ['Idempotency-Key' => 'marketing-middle'])->assertCreated();

        $this->postJson('/api/notification-batches', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Critical last',
            'subscriber_ids' => [10],
        ], ['Idempotency-Key' => 'critical-last'])->assertCreated();

        $publisher = app(MessagePublisher::class);
        $this->assertInstanceOf(FakeMessagePublisher::class, $publisher);
        $this->artisan('notifications:drain-local')->assertSuccessful();

        $criticalAttemptIds = NotificationAttempt::query()
            ->whereHas('notification', fn ($query) => $query->whereIn('subscriber_id', [1, 10]))
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $firstMarketingAttemptId = NotificationAttempt::query()
            ->whereHas('notification', fn ($query) => $query->whereBetween('subscriber_id', [2, 9]))
            ->orderBy('id')
            ->value('id');

        $this->assertCount(2, $criticalAttemptIds);
        $this->assertLessThan($firstMarketingAttemptId, max($criticalAttemptIds));
        $this->assertDatabaseCount('notification_attempts', 10);
    }

    public function test_permanent_failure_drops_notification(): void
    {
        $response = $this->postJson('/api/notification-batches', [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'Failure scenario',
            'subscriber_ids' => [4],
        ], ['Idempotency-Key' => 'permanent-failure'])->assertCreated();

        $notificationId = $response->json('notifications.0.id');

        $this->artisan('notifications:drain-local')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'status' => NotificationStatus::DROPPED->value,
        ]);
        $this->assertDatabaseHas('notification_attempts', [
            'notification_id' => $notificationId,
            'provider' => 'fake-email',
            'status' => NotificationStatus::DROPPED->value,
            'error' => 'Provider rejected recipient address',
        ]);
    }

    public function test_idempotency_prevents_duplicates(): void
    {
        $payload = [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'One time code',
            'subscriber_ids' => [1, 2],
        ];

        $first = $this->postJson('/api/notification-batches', $payload, ['Idempotency-Key' => 'same-key'])->assertCreated();
        $second = $this->postJson('/api/notification-batches', $payload, ['Idempotency-Key' => 'same-key'])->assertCreated();

        $publisher = app(MessagePublisher::class);

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertCount(2, $publisher->messages);
    }

    public function test_temporary_failure_is_retried(): void
    {
        $response = $this->postJson('/api/notification-batches', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Retry scenario',
            'subscriber_ids' => [5],
        ], ['Idempotency-Key' => 'temporary-failure'])->assertCreated();

        $notificationId = $response->json('notifications.0.id');

        app(\App\Services\Notifications\NotificationDeliveryService::class)->deliver($notificationId);
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'status' => NotificationStatus::SENT->value,
        ]);

        app(\App\Services\Notifications\NotificationDeliveryService::class)->deliver($notificationId);
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'status' => NotificationStatus::DELIVERED->value,
        ]);
        $this->assertSame(2, Notification::query()->findOrFail($notificationId)->attempts()->count());
    }

    public function test_subscriber_get_history(): void
    {
        $this->postJson('/api/notification-batches', [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'History scenario',
            'subscriber_ids' => [1],
        ], ['Idempotency-Key' => 'history'])->assertCreated();

        $this->artisan('notifications:drain-local')->assertSuccessful();

        $this->getJson('/api/subscribers/1/notifications')
            ->assertOk()
            ->assertJsonPath('subscriber.id', 1)
            ->assertJsonPath('notifications.0.status', NotificationStatus::DELIVERED->value);
    }

    public function test_missing_idempotency_key(): void
    {
        $this->post('/api/notification-batches', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Missing header',
            'subscriber_ids' => [1],
        ])->assertStatus(422)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('errors.Idempotency-Key.0', 'Idempotency-Key header is required');
    }

    public function test_missing_subscriber(): void
    {
        $this->get('/api/subscribers/999/notifications')
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    private function seedSubscribers(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Subscriber::query()->create([
                'id' => $i,
                'name' => "Subscriber {$i}",
                'email' => "subscriber{$i}@example.test",
                'phone' => '+790000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'provider_failure_mode' => match ($i) {
                    4 => ProviderFailureMode::PERMANENT,
                    5 => ProviderFailureMode::TEMPORARY_ONCE,
                    default => ProviderFailureMode::NONE,
                },
            ]);
        }
    }
}
