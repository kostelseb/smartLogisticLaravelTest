<?php

namespace App\Console\Commands;

use App\Enums\NotificationPriority;
use App\Services\Notifications\NotificationDeliveryService;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class ConsumeNotificationTopicCommand extends Command
{
    protected $signature = 'notifications:consume {priority : transactional or marketing} {--max-time=0}';

    protected $description = 'Consume notification messages from a Kafka topic.';

    public function handle(NotificationDeliveryService $deliveryService): int
    {
        $priority = NotificationPriority::from($this->argument('priority'));
        $topic = $priority->topic();

        // Для критичных сообщений запускается отдельный consumer: он читает свой topic независимо от marketing.
        Kafka::consumer([$topic], config('kafka.consumer_group_id'))
            ->withHandler(function (ConsumerMessage $message) use ($deliveryService): void {
                $body = $message->getBody();
                $deliveryService->deliver($body['notification_id']);
            })
            ->withMaxTime((int) $this->option('max-time'))
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
