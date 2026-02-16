<?php

namespace App\Models;

use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AppNotification extends Model
{
    public const TYPE_TO_CHANNEL = [
        'mail' => 'mail',
        'groupchat' => 'group_chat',
        'quiz' => 'quiz',
        'attendance_alert' => 'attendance',
        'attendance_alert_summary' => 'attendance',
        'support' => 'support',
    ];

    public const DEFAULT_PREFERENCES = [
        'mail' => true,
        'group_chat' => true,
        'quiz' => true,
        'attendance' => true,
        'support' => true,
        'general' => true,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'link',
        'is_read',
        'meta',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function createForUserIfEnabled(int $userId, array $attributes): ?self
    {
        $type = (string) ($attributes['type'] ?? 'general');
        if (! self::isEnabledForUser($userId, $type)) {
            return null;
        }

        return self::create(array_merge($attributes, [
            'user_id' => $userId,
        ]));
    }

    public static function isEnabledForUser(int $userId, string $type): bool
    {
        $preferences = self::preferencesForUser($userId);
        $channel = self::TYPE_TO_CHANNEL[$type] ?? 'general';

        return (bool) ($preferences[$channel] ?? true);
    }

    public static function preferencesForUser(int $userId): array
    {
        if (! self::hasPreferencesTable()) {
            return self::DEFAULT_PREFERENCES;
        }

        $row = AppNotificationPreference::query()
            ->where('user_id', $userId)
            ->first();

        if (! $row) {
            return self::DEFAULT_PREFERENCES;
        }

        return [
            'mail' => (bool) $row->mail,
            'group_chat' => (bool) $row->group_chat,
            'quiz' => (bool) $row->quiz,
            'attendance' => (bool) $row->attendance,
            'support' => (bool) $row->support,
            'general' => (bool) $row->general,
        ];
    }

    public static function setPreferencesForUser(int $userId, array $input): array
    {
        $next = self::preferencesForUser($userId);
        foreach (array_keys($next) as $key) {
            if (array_key_exists($key, $input)) {
                $next[$key] = (bool) $input[$key];
            }
        }

        if (! self::hasPreferencesTable()) {
            return $next;
        }

        AppNotificationPreference::query()->updateOrCreate(
            ['user_id' => $userId],
            $next,
        );

        return $next;
    }

    private static function hasPreferencesTable(): bool
    {
        try {
            return Schema::hasTable('app_notification_preferences');
        } catch (QueryException) {
            return false;
        }
    }
}
