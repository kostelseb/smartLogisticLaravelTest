<?php

namespace App\Services\Providers;

use App\Enums\ProviderFailureMode;
use App\Models\Notification;

class FakeProviderBehavior
{
    public function simulateDelivery(Notification $notification): ProviderResult
    {
        $subscriber = $notification->subscriber;

        return match ($subscriber->provider_failure_mode) {
            ProviderFailureMode::PERMANENT => ProviderResult::permanentError('Provider rejected recipient address'),
            ProviderFailureMode::TEMPORARY_ONCE => $notification->attempts()->count() === 0
                ? ProviderResult::temporaryError('Provider is temporarily unavailable')
                : ProviderResult::success(),
            ProviderFailureMode::NONE => ProviderResult::success(),
        };
    }
}
