<?php

namespace App\Services\Kafka;

use Junges\Kafka\Facades\Kafka;

class LaravelKafkaMessagePublisher implements MessagePublisher
{
    public function publish(string $topic, string $key, array $payload): void
    {
        Kafka::publish()
            ->onTopic($topic)
            ->withKafkaKey($key)
            ->withBody($payload)
            ->send();
    }
}
