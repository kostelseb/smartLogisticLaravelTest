<?php

namespace App\Console\Commands;

use App\Enums\NotificationPriority;
use App\Services\Notifications\NotificationDeliveryService;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class RunNotificationWorker extends Command
{
    protected $signature = 'notifications:consume {priority : transactional or marketing} {--max-time=0}';

    protected $description = 'Consume notification messages from a Kafka topic.';

    public function handle(NotificationDeliveryService $deliveryService): int
    {
        if (! extension_loaded('rdkafka')) {
            $this->error('The rdkafka PHP extension is not installed. Run this command inside Docker, or use notifications:drain-local for local OSPanel checks.');

            return self::FAILURE;
        }

        $priority = NotificationPriority::from($this->argument('priority'));
        $topic = $priority->getTopic();

        Kafka::consumer([$topic], config('kafka.consumer_group_id'))
            ->withHandler(function (ConsumerMessage $kafkaMessage) use ($deliveryService): void {
                $payload = $kafkaMessage->getBody();
                $deliveryService->deliver($payload['notification_id']);
            })
            ->withMaxTime((int) $this->option('max-time'))
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
