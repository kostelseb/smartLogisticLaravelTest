<?php

namespace App\Models;

use App\Enums\ProviderFailureMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'provider_failure_mode',
    ];

    protected $casts = [
        'provider_failure_mode' => ProviderFailureMode::class,
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
