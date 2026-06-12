<?php

namespace App\Services\Providers;

use App\Models\Notification;

interface SmsProviderInterface
{
    public function send(Notification $notification): ProviderResult;
}
