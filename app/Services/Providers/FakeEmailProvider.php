<?php

namespace App\Services\Providers;

use App\Models\Notification;

class FakeEmailProvider implements EmailProviderInterface
{
    public function __construct(private readonly FakeGatewayDecider $decider)
    {
    }

    public function send(Notification $notification): ProviderResult
    {
        return $this->decider->decide($notification);
    }
}
