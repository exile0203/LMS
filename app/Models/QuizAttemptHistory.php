<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptHistory extends Model
{
    protected $fillable = [
        'quiz_id',
        'user_id',
        'attempt_no',
        'answers',
        'score',
        'total_items',
        'submitted_at',
        'is_overridden',
        'overridden_by',
        'override_note',
    ];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'is_overridden' => 'boolean',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
