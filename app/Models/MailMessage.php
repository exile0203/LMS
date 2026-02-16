<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailMessage extends Model
{
    protected $fillable = [
        'user_id',
        'sender_id',
        'sender_name',
        'sender_email',
        'subject',
        'snippet',
        'body',
        'folder',
        'unread',
        'starred',
    ];

    protected $casts = [
        'unread' => 'boolean',
        'starred' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
