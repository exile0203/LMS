<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Product;
use App\Models\AppNotification;
use App\Models\AppNotificationPreference;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\MailMessage;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptHistory;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SupportChatMessage;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'section',
        'course',
        'student_id_no',
        'avatar_path',
    ];

    protected $appends = [
        'avatar',
    ];

    public function products(){
        return $this->hasMany(Product::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'created_by');
    }

    public function chatGroups(): HasMany
    {
        return $this->hasMany(ChatGroup::class, 'created_by');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function chatMessageReads(): HasMany
    {
        return $this->hasMany(ChatMessageRead::class);
    }

    public function mailMessages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(AppNotificationPreference::class);
    }

    public function supportChatMessages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'created_by');
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function quizAttemptHistories(): HasMany
    {
        return $this->hasMany(QuizAttemptHistory::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'created_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'student_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'avatar_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public static function generateUniqueStudentIdNo(): string
    {
        $prefix = (string) now()->year;

        do {
            $candidate = $prefix.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::query()->where('student_id_no', $candidate)->exists());

        return $candidate;
    }

    public function getAvatarAttribute(): ?string
    {
        $avatarPath = $this->avatar_path;
        if (! $avatarPath) {
            return null;
        }

        $baseUrl = "/users/{$this->id}/avatar";
        $version = $this->updated_at?->timestamp;

        return $version ? "{$baseUrl}?v={$version}" : $baseUrl;
    }
}
