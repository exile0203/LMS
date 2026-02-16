<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'mail',
        'group_chat',
        'quiz',
        'attendance',
        'support',
        'general',
    ];

    protected $casts = [
        'mail' => 'boolean',
        'group_chat' => 'boolean',
        'quiz' => 'boolean',
        'attendance' => 'boolean',
        'support' => 'boolean',
        'general' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
