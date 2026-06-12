<?php

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\ProviderFailureMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('provider_failure_mode')->default(ProviderFailureMode::NONE->value);
            $table->timestamps();
        });

        Schema::create('notification_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique();
            $table->string('channel')->default(NotificationChannel::EMAIL->value);
            $table->string('priority')->default(NotificationPriority::MARKETING->value);
            $table->text('message');
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('dropped_count')->default(0);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('notification_batches')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default(NotificationChannel::EMAIL->value);
            $table->string('priority')->default(NotificationPriority::MARKETING->value);
            $table->string('status')->default(NotificationStatus::QUEUED->value);
            $table->string('deduplication_key')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->timestamps();

            $table->index(['subscriber_id', 'created_at']);
            $table->index(['priority', 'status']);
        });

        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('notification_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt');
            $table->string('provider');
            $table->string('status');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_batches');
        Schema::dropIfExists('subscribers');
    }
};
