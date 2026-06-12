<?php

namespace Tests\Integration;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Kafka\FakeMessagePublisher;
use App\Services\Kafka\MessagePublisher;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_batch_can_be_loaded_by_uuid_for_postman_scenario(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->getJson('/api/notification-batches/019eb82a-1605-7392-ba67-7287397244fa')
            ->assertOk()
            ->assertJsonPath('id', '019eb82a-1605-7392-ba67-7287397244fa')
            ->assertJsonPath('counters.delivered', 1)
            ->assertJsonPath('notifications.0.id', '019eb82a-1605-7392-ba67-7287397244fb')
            ->assertJsonPath('notifications.0.status', NotificationStatus::DELIVERED->value);
    }

    public function test_subscriber_history_returns_seeded_and_new_notifications(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/notification-batches', [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'New subscriber history item',
            'recipient_ids' => [1],
        ], ['Idempotency-Key' => 'integration-history-001'])->assertCreated();

        $this->artisan('notifications:drain-local')->assertSuccessful();

        $this->getJson('/api/subscribers/1/notifications')
            ->assertOk()
            ->assertJsonPath('subscriber.id', 1)
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.status', NotificationStatus::DELIVERED->value);
    }

    public function test_end_to_end_api_batch_is_published_delivered_and_persisted(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->postJson('/api/notification-batches', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Integration delivery',
            'recipient_ids' => [2],
        ], ['Idempotency-Key' => 'integration-delivery-001'])->assertCreated();

        $publisher = app(MessagePublisher::class);
        $this->assertInstanceOf(FakeMessagePublisher::class, $publisher);
        $this->assertSame('notifications.transactional', $publisher->messages[0]['topic']);

        $this->artisan('notifications:drain-local')->assertSuccessful();

        $notificationId = $response->json('notifications.0.id');
        $notification = Notification::query()->with('attempts')->findOrFail($notificationId);

        $this->assertSame(NotificationStatus::DELIVERED, $notification->status);
        $this->assertSame('fake-sms', $notification->attempts->first()->provider);
    }
}
