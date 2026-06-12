<?php

namespace App\Services\Kafka;

class FakeMessagePublisher implements MessagePublisher
{
    /** @var array<int, array{topic: string, key: string, payload: array}> */
    public array $messages = [];

    public function publish(string $topic, string $key, array $payload): void
    {
        $this->messages[] = compact('topic', 'key', 'payload');
    }

    public function count(): int
    {
        return count($this->messages);
    }
}
