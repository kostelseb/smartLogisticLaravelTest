<?php

namespace App\Services\Kafka;

interface MessagePublisher
{
    public function publish(string $topic, string $key, array $payload): void;
}
