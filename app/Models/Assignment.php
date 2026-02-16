<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'description',
        'section',
        'course',
        'due_at',
        'allow_file',
        'allow_link',
        'is_closed',
        'closed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'allow_file' => 'boolean',
        'allow_link' => 'boolean',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class)->latest('submitted_at');
    }
}
