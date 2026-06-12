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

    public function handle(NotificationDeliveryService $deliveryService): int
    {
        if (! extension_loaded('rdkafka')) {
            $this->error('The rdkafka PHP extension is not installed.');

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
