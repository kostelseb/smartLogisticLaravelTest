<?php

namespace App\Services\Providers;

use App\Models\Notification;

class FakeSmsProvider implements SmsProviderInterface
{
    public function __construct(private readonly FakeProviderBehavior $decider)
    {
    }

    public function send(Notification $notification): ProviderResult
    {
        return $this->decider->simulateDelivery($notification);
    }
}
