<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAttempt extends Model
{
    protected $fillable = [
        'notification_id',
        'attempt',
        'provider',
        'status',
        'error',
    ];

    protected $casts = [
        'status' => NotificationStatus::class,
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
