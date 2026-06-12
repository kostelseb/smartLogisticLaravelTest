<?php

namespace App\Services\Providers;

use App\Enums\ProviderFailureMode;
use App\Models\Notification;

class FakeGatewayDecider
{
    public function decide(Notification $notification): ProviderResult
    {
        $subscriber = $notification->subscriber;

        return match ($subscriber->provider_failure_mode) {
            ProviderFailureMode::PERMANENT => ProviderResult::permanentFailure('Provider rejected recipient address'),
            ProviderFailureMode::TEMPORARY_ONCE => $notification->attempts()->count() === 0
                ? ProviderResult::temporaryFailure('Provider is temporarily unavailable')
                : ProviderResult::delivered(),
            ProviderFailureMode::NONE => ProviderResult::delivered(),
        };
    }
}
