<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_group_id',
        'reply_to_message_id',
        'sender_id',
        'kind',
        'body',
        'file_name',
        'file_size',
        'reactions',
        'edited_at',
        'deleted_at',
        'deleted_by',
        'is_pinned',
        'pinned_at',
        'pinned_by',
        'published_at',
    ];

    protected $casts = [
        'reactions' => 'array',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ChatMessageRead::class);
    }
}
