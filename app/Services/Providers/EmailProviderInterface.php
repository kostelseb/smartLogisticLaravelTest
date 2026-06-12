<?php

namespace App\Services\Providers;

use App\Models\Notification;

interface EmailProviderInterface
{
    public function send(Notification $notification): ProviderResult;
}
