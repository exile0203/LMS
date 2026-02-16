<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'description',
        'section',
        'course',
        'quiz_set',
        'max_attempts',
        'score_policy',
    ];

    protected $casts = [
        'max_attempts' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class)->latest('submitted_at');
    }

    public function attemptHistories(): HasMany
    {
        return $this->hasMany(QuizAttemptHistory::class)->latest('submitted_at');
    }
}
