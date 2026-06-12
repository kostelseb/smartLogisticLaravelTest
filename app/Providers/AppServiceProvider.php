<?php

namespace App\Providers;

use App\Services\Kafka\FakeMessagePublisher;
use App\Services\Kafka\LaravelKafkaMessagePublisher;
use App\Services\Kafka\MessagePublisher;
use App\Services\Providers\EmailProviderInterface;
use App\Services\Providers\FakeEmailProvider;
use App\Services\Providers\FakeSmsProvider;
use App\Services\Providers\SmsProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // В Symfony это обычно services.yaml/autowire; в Laravel биндинги интерфейсов живут в ServiceProvider.
        $this->app->singleton(FakeMessagePublisher::class);
        $this->app->singleton(MessagePublisher::class, function ($app): MessagePublisher {
            if ($app->environment('testing') || config('notifications.kafka.fake', false) || ! extension_loaded('rdkafka')) {
                return $app->make(FakeMessagePublisher::class);
            }

            return $app->make(LaravelKafkaMessagePublisher::class);
        });

        $this->app->bind(SmsProviderInterface::class, FakeSmsProvider::class);
        $this->app->bind(EmailProviderInterface::class, FakeEmailProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
