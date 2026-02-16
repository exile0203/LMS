<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\ChatGroup;
use App\Models\MailMessage;
use App\Models\Quiz;
use App\Models\SupportChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarController extends Controller
{
    public function notifications(Request $request): JsonResponse
    {
        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AppNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'text' => $notification->body ?? '',
                'time' => $notification->created_at?->diffForHumans() ?? '',
                'isRead' => $notification->is_read,
                'link' => $notification->link,
                'type' => $notification->type,
            ])
            ->values();

        $unreadCount = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'preferences' => AppNotification::preferencesForUser((int) $request->user()->id),
        ]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => AppNotification::preferencesForUser((int) $request->user()->id),
        ]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mail' => ['sometimes', 'boolean'],
            'group_chat' => ['sometimes', 'boolean'],
            'quiz' => ['sometimes', 'boolean'],
            'attendance' => ['sometimes', 'boolean'],
            'support' => ['sometimes', 'boolean'],
            'general' => ['sometimes', 'boolean'],
        ]);

        $preferences = AppNotification::setPreferencesForUser(
            userId: (int) $request->user()->id,
            input: $validated,
        );

        return response()->json([
            'ok' => true,
            'preferences' => $preferences,
        ]);
    }

    public function supportMessages(Request $request): JsonResponse
    {
        $messages = SupportChatMessage::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (SupportChatMessage $message) => [
                'id' => $message->id,
                'senderType' => $message->sender_type,
                'message' => $message->message,
                'time' => $message->created_at?->format('h:i A') ?? '',
            ])
            ->values();

        if ($messages->isEmpty()) {
            $welcome = SupportChatMessage::create([
                'user_id' => $request->user()->id,
                'sender_type' => 'system',
                'message' => 'Welcome to support. How can we help you today?',
            ]);

            $messages = collect([[
                'id' => $welcome->id,
                'senderType' => $welcome->sender_type,
                'message' => $welcome->message,
                'time' => $welcome->created_at?->format('h:i A') ?? '',
            ]]);
        }

        return response()->json(['messages' => $messages]);
    }

    public function sendSupportMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        SupportChatMessage::create([
            'user_id' => $request->user()->id,
            'sender_type' => 'user',
            'message' => $validated['message'],
        ]);

        SupportChatMessage::create([
            'user_id' => $request->user()->id,
            'sender_type' => 'system',
            'message' => 'Thanks. We received your message and will respond shortly.',
        ]);

        return $this->supportMessages($request);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $user = $request->user();
        $isTeacher = $this->isTeacher($user);
        $results = collect();

        $mailResults = MailMessage::query()
            ->where('user_id', $user->id)
            ->where(function ($builder) use ($query) {
                $builder->where('subject', 'like', "%{$query}%")
                    ->orWhere('snippet', 'like', "%{$query}%")
                    ->orWhere('sender_name', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($mail) => [
                'type' => 'Mail',
                'title' => $mail->subject,
                'subtitle' => $mail->sender_name,
                'link' => '/mail',
            ]);
        $results = $results->concat($mailResults);

        $quizQuery = Quiz::query()
            ->where(function ($builder) use ($query) {
                $builder->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->latest();

        if (! $isTeacher) {
            if (! empty($user?->section) && ! empty($user?->course)) {
                $quizQuery
                    ->where('section', $user->section)
                    ->where('course', $user->course);
            } else {
                $quizQuery->whereRaw('1 = 0');
            }
        }

        $quizResults = $quizQuery
            ->limit(5)
            ->get()
            ->map(fn ($quiz) => [
                'type' => 'Quiz',
                'title' => $quiz->title,
                'subtitle' => $quiz->section.' · '.$quiz->course,
                'link' => '/quiz',
            ]);
        $results = $results->concat($quizResults);

        $groupQuery = ChatGroup::query()
            ->where('name', 'like', "%{$query}%")
            ->latest();

        if (! $isTeacher) {
            if (! empty($user?->section) && ! empty($user?->course)) {
                $groupQuery
                    ->where('section', $user->section)
                    ->where('course', $user->course);
            } else {
                $groupQuery->whereRaw('1 = 0');
            }
        }

        $groupResults = $groupQuery
            ->limit(5)
            ->get()
            ->map(fn ($group) => [
                'type' => 'Group Chat',
                'title' => $group->name,
                'subtitle' => $group->section.' · '.$group->course,
                'link' => '/groupchat',
            ]);
        $results = $results->concat($groupResults);

        return response()->json([
            'results' => $results->take(12)->values(),
        ]);
    }

    private function isTeacher($user): bool
    {
        if (! $user) {
            return false;
        }

        $role = strtolower((string) ($user->role ?? $user->user_type ?? $user->type ?? 'student'));
        return $role === 'teacher';
    }
}
