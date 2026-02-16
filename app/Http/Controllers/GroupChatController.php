<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\ChatMessageReport;
use App\Models\ChatMessageRead;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class GroupChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isTeacher = $this->isTeacher($user);
        $tableMissing = ! $this->hasChatTables();

        if ($tableMissing) {
            return Inertia::render('SideBarPages/GroupChatPage', [
                'groups' => [],
                'sectionOptions' => ['Section 1', 'Section 2', 'Section 3'],
                'courseOptions' => ['Mathematics', 'Science', 'English'],
            ]);
        }

        $groupQuery = ChatGroup::query()
            ->with([
                'creator:id,name',
                'messages' => fn ($query) => $this->applyVisibleMessagesScope($query)
                    ->with([
                        'sender:id,name,role,avatar_path,updated_at',
                        'replyTo:id,sender_id,kind,body,file_name,file_size',
                        'replyTo.sender:id,name,role,avatar_path,updated_at',
                        'reads.user:id,name,avatar_path,updated_at',
                    ])
                    ->orderByRaw('COALESCE(published_at, created_at) asc')
                    ->orderBy('id'),
            ])
            ->latest();

        if (! $isTeacher) {
            if ($user?->section && $user?->course) {
                $groupQuery
                    ->where('section', $user->section)
                    ->where('course', $user->course);
            } else {
                $groupQuery->whereRaw('1 = 0');
            }
        }

        $muteSettings = $this->resolveMuteSettings((int) ($user?->id ?? 0));

        $groups = $groupQuery
            ->get()
            ->map(fn (ChatGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'section' => $group->section,
                'course' => $group->course,
                'createdBy' => $group->creator?->name ?? 'Teacher',
                'isMuted' => (bool) ($muteSettings[$group->id]['isMuted'] ?? false),
                'mutedUntilAt' => $muteSettings[$group->id]['mutedUntilAt'] ?? null,
                'messages' => $group->messages->map(fn (ChatMessage $message) => $this->mapMessage($message, $user?->id, $isTeacher))->values(),
            ])
            ->values();

        return Inertia::render('SideBarPages/GroupChatPage', [
            'groups' => $groups,
            'sectionOptions' => ['Section 1', 'Section 2', 'Section 3'],
            'courseOptions' => ['Mathematics', 'Science', 'English'],
        ]);
    }

    public function storeGroup(Request $request)
    {
        if (! $this->hasChatTables()) {
            return back()->with('error', 'Group chat backend is not ready. Start your database and run migrations.');
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can create group chats.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
        ]);

        ChatGroup::create([
            'created_by' => $user->id,
            'name' => $validated['name'],
            'section' => $validated['section'],
            'course' => $validated['course'],
        ]);

        if (Schema::hasTable('app_notifications')) {
            AppNotification::createForUserIfEnabled($user->id, [
                'type' => 'groupchat',
                'title' => 'Group Chat Created',
                'body' => "You created {$validated['name']} for {$validated['section']} · {$validated['course']}.",
                'link' => '/groupchat',
            ]);
        }

        return back()->with('success', 'Group chat created successfully.');
    }

    public function toggleGroupMute(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['error' => 'Group chat backend is not ready.'], 503);
        }
        if (! $this->hasGroupSettingsTable()) {
            return response()->json(['error' => 'Group settings table is missing. Run migrations.'], 503);
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to update this group.');
        $validated = $request->validate([
            'duration' => ['nullable', 'string', 'in:off,1h,8h,24h,forever'],
        ]);
        $duration = $validated['duration'] ?? null;
        $now = now();

        $row = DB::table('chat_group_user_settings')
            ->where('chat_group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        $currentIsMuted = $this->isActiveMutedRow($row);
        $nextIsMuted = false;
        $mutedUntil = null;

        if ($duration === null) {
            $nextIsMuted = ! $currentIsMuted;
            $mutedUntil = null;
        } elseif ($duration === 'off') {
            $nextIsMuted = false;
        } elseif ($duration === 'forever') {
            $nextIsMuted = true;
            $mutedUntil = null;
        } else {
            $hours = (int) str_replace('h', '', $duration);
            $nextIsMuted = $hours > 0;
            $mutedUntil = $nextIsMuted ? $now->copy()->addHours($hours) : null;
        }

        if (! $row) {
            DB::table('chat_group_user_settings')->insert([
                'chat_group_id' => $group->id,
                'user_id' => $user->id,
                'muted_at' => $nextIsMuted ? $now : null,
                'muted_until' => $nextIsMuted ? $mutedUntil : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('chat_group_user_settings')
                ->where('id', $row->id)
                ->update([
                    'muted_at' => $nextIsMuted ? $now : null,
                    'muted_until' => $nextIsMuted ? $mutedUntil : null,
                    'updated_at' => $now,
                ]);
        }

        return response()->json([
            'ok' => true,
            'isMuted' => $nextIsMuted,
            'mutedUntilAt' => $nextIsMuted && $mutedUntil ? $mutedUntil->toIso8601String() : null,
            'groupId' => (int) $group->id,
        ]);
    }

    public function storeMessage(Request $request, ChatGroup $group)
    {
        if (! $this->hasChatTables()) {
            return back()->with('error', 'Group chat backend is not ready. Start your database and run migrations.');
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to message this group.');

        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:text,quiz,file,image,gif,sticker,emoji,link'],
            'body' => ['nullable', 'string'],
            'fileName' => ['nullable', 'string', 'max:255'],
            'fileSize' => ['nullable', 'string', 'max:50'],
            'file' => ['nullable', 'file', 'max:10240'],
            'scheduledFor' => ['nullable', 'date'],
            'replyToMessageId' => ['nullable', 'integer', 'exists:chat_messages,id'],
        ]);

        $contentValidator = Validator::make($validated, [
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validated['kind'] === 'quiz' && ! $this->isTeacher($user)) {
            $contentValidator->errors()->add('kind', 'Only teachers can share quizzes in group chat.');
        }

        $isTeacher = $this->isTeacher($user);
        $scheduledForInput = $validated['scheduledFor'] ?? null;
        $publishAt = now();
        $isScheduled = false;

        if (! empty($scheduledForInput)) {
            if (! $isTeacher) {
                $contentValidator->errors()->add('scheduledFor', 'Only teachers can schedule messages.');
            } else {
                $parsedSchedule = Carbon::parse((string) $scheduledForInput);
                if ($parsedSchedule->lte(now()->addSeconds(10))) {
                    $contentValidator->errors()->add('scheduledFor', 'Schedule time must be in the future.');
                } else {
                    $publishAt = $parsedSchedule;
                    $isScheduled = true;
                }
            }
        }

        if ($validated['kind'] === 'link' && ! filter_var((string) ($validated['body'] ?? ''), FILTER_VALIDATE_URL)) {
            $contentValidator->errors()->add('body', 'Please provide a valid URL.');
        }

        if (in_array($validated['kind'], ['text', 'quiz', 'emoji'], true) && blank($validated['body'] ?? null)) {
            $contentValidator->errors()->add('body', 'Message body is required.');
        }

        if (in_array($validated['kind'], ['gif', 'sticker'], true)) {
            $url = (string) ($validated['body'] ?? '');
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                $contentValidator->errors()->add('body', 'GIF/Sticker must be a valid URL.');
            }
        }

        if ($request->hasFile('file') && ! in_array($validated['kind'], ['file', 'image'], true)) {
            $contentValidator->errors()->add('file', 'Files are allowed only for file/image message types.');
        }

        if ($validated['kind'] === 'image') {
            if (! $request->hasFile('file')) {
                $contentValidator->errors()->add('file', 'Image upload is required.');
            } else {
                $image = $request->file('file');
                $mime = strtolower((string) $image?->getMimeType());
                if (! str_starts_with($mime, 'image/')) {
                    $contentValidator->errors()->add('file', 'Only image files are allowed for image messages.');
                }
                if (($image?->getSize() ?? 0) > 5 * 1024 * 1024) {
                    $contentValidator->errors()->add('file', 'Image size must be 5MB or below.');
                }
            }
        }

        if ($validated['kind'] === 'file' && $request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower((string) $file?->getClientOriginalExtension());
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'csv'];

            if (! in_array($extension, $allowed, true)) {
                $contentValidator->errors()->add('file', 'Unsupported file type.');
            }
        }

        if ($contentValidator->fails()) {
            $firstError = $contentValidator->errors()->first();
            if ($request->expectsJson()) {
                return response()->json(['error' => $firstError], 422);
            }

            return back()->with('error', $firstError);
        }

        $body = $validated['body'] ?? null;
        $fileName = $validated['fileName'] ?? null;
        $fileSize = $validated['fileSize'] ?? null;
        $replyToMessageId = $validated['replyToMessageId'] ?? null;

        if ($replyToMessageId) {
            $replyTarget = ChatMessage::query()->find($replyToMessageId);
            if (! $replyTarget || $replyTarget->chat_group_id !== $group->id) {
                return $request->expectsJson()
                    ? response()->json(['error' => 'Reply target is invalid for this group.'], 422)
                    : back()->with('error', 'Reply target is invalid for this group.');
            }
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('chat-uploads', 'public');
            $body = $path;
            $fileName ??= $file->getClientOriginalName();
            $fileSize ??= $this->formatBytes($file->getSize());
        }

        if (! $body) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Message body is required.'], 422)
                : back()->with('error', 'Message body is required.');
        }

        $message = $group->messages()->create([
            'sender_id' => $user->id,
            'reply_to_message_id' => $replyToMessageId,
            'kind' => $validated['kind'],
            'body' => $body,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'published_at' => $publishAt,
        ]);

        Cache::forget($this->typingCachePrefix($group->id).$user->id);
        $this->touchPresence($group->id, (int) $user->id);

        if (! $isScheduled && Schema::hasTable('app_notifications')) {
            $recipients = User::query()
                ->where('id', '!=', $user->id)
                ->where(function ($query) use ($group) {
                    $query->where('role', 'teacher')
                        ->orWhere(function ($studentQuery) use ($group) {
                            $studentQuery
                                ->where('section', $group->section)
                                ->where('course', $group->course);
                        });
                })
                ->get(['id']);

            foreach ($recipients as $recipient) {
                AppNotification::createForUserIfEnabled($recipient->id, [
                    'type' => 'groupchat',
                    'title' => 'New Group Message',
                    'body' => "{$user->name} sent a message in {$group->name}.",
                    'link' => '/groupchat',
                ]);
            }
        }

        if ($request->expectsJson()) {
            if ($isScheduled) {
                return response()->json([
                    'scheduled' => true,
                    'scheduledFor' => $publishAt->toIso8601String(),
                    'message' => null,
                ]);
            }

            return response()->json([
                'scheduled' => false,
                'message' => $this->mapMessage($message->load([
                    'sender:id,name,role,avatar_path,updated_at',
                    'replyTo:id,sender_id,kind,body,file_name,file_size',
                    'replyTo.sender:id,name,role,avatar_path,updated_at',
                ]), $user->id, $this->isTeacher($user)),
            ]);
        }

        return back();
    }

    public function messages(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['messages' => []], 503);
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to read this group.');

        $messages = $group->messages()
            ->with([
                'sender:id,name,role,avatar_path,updated_at',
                'replyTo:id,sender_id,kind,body,file_name,file_size',
                'replyTo.sender:id,name,role,avatar_path,updated_at',
                'reads.user:id,name,avatar_path,updated_at',
            ])
            ->tap(fn ($query) => $this->applyVisibleMessagesScope($query))
            ->orderByRaw('COALESCE(published_at, created_at) asc')
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $message) => $this->mapMessage($message, $user?->id, $this->isTeacher($user)))
            ->values();

        return response()->json([
            'messages' => $messages,
            'activeUsers' => $this->resolveActiveUsers($group, (int) $user->id),
            'presenceUsers' => $this->resolvePresenceUsers($group, (int) $user->id),
        ]);
    }

    public function stream(Request $request, ChatGroup $group): HttpResponse
    {
        if (! $this->hasChatTables()) {
            abort(503, 'Group chat backend is not ready.');
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to read this group.');

        $userId = (int) ($user?->id ?? 0);
        $viewerIsTeacher = $this->isTeacher($user);

        return response()->stream(function () use ($group, $userId, $viewerIsTeacher) {
            @set_time_limit(0);

            $startedAt = time();
            $maxDurationSeconds = 25;
            $lastHash = null;

            while (! connection_aborted() && (time() - $startedAt) < $maxDurationSeconds) {
                $messages = $group->messages()
                    ->with([
                        'sender:id,name,role,avatar_path,updated_at',
                        'replyTo:id,sender_id,kind,body,file_name,file_size',
                        'replyTo.sender:id,name,role,avatar_path,updated_at',
                        'reads.user:id,name,avatar_path,updated_at',
                    ])
                    ->tap(fn ($query) => $this->applyVisibleMessagesScope($query))
                    ->orderByRaw('COALESCE(published_at, created_at) asc')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ChatMessage $message) => $this->mapMessage($message, $userId, $viewerIsTeacher))
                    ->values()
                    ->all();

                $typingUsers = $this->resolveTypingUsers($group, $userId);
                $payload = [
                    'messages' => $messages,
                    'typingUsers' => $typingUsers,
                    'activeUsers' => $this->resolveActiveUsers($group, $userId),
                    'presenceUsers' => $this->resolvePresenceUsers($group, $userId),
                ];

                $hash = md5(json_encode($payload));
                if ($hash !== $lastHash) {
                    echo "event: snapshot\n";
                    echo 'data: '.json_encode($payload)."\n\n";
                    $lastHash = $hash;
                    @ob_flush();
                    @flush();
                }

                usleep(800000);
            }

            echo "event: close\n";
            echo 'data: {"ok":true}'."\n\n";
            @ob_flush();
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function typingStatus(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['typingUsers' => []], 503);
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to read this group.');

        $typingUsers = $this->resolveTypingUsers($group, (int) $user->id);

        return response()->json(['typingUsers' => array_values($typingUsers)]);
    }

    public function setTyping(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['ok' => false], 503);
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to update typing status.');

        $validated = $request->validate([
            'isTyping' => ['required', 'boolean'],
        ]);

        $key = $this->typingCachePrefix($group->id).$user->id;
        if ($validated['isTyping']) {
            Cache::put($key, true, now()->addSeconds(8));
            $this->touchPresence($group->id, (int) $user->id);
        } else {
            Cache::forget($key);
        }

        return response()->json(['ok' => true]);
    }

    public function setPresence(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['ok' => false], 503);
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to update presence status.');
        $this->touchPresence($group->id, (int) $user->id);

        return response()->json(['ok' => true]);
    }

    public function presenceStatus(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['activeUsers' => []], 503);
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to read presence status.');

        return response()->json([
            'activeUsers' => $this->resolveActiveUsers($group, (int) $user->id),
            'presenceUsers' => $this->resolvePresenceUsers($group, (int) $user->id),
        ]);
    }

    public function markSeen(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['error' => 'Group chat backend is not ready.'], 503);
        }

        $user = $request->user();
        abort_unless($this->canAccessGroup($user, $group), 403, 'Not allowed to update seen status.');

        $validated = $request->validate([
            'lastMessageId' => ['nullable', 'integer'],
        ]);

        $lastMessageId = (int) ($validated['lastMessageId'] ?? 0);
        if ($lastMessageId <= 0) {
            $lastMessageId = (int) ($group->messages()
                ->tap(fn ($query) => $this->applyVisibleMessagesScope($query))
                ->max('id') ?? 0);
        }

        if ($lastMessageId <= 0) {
            return response()->json(['ok' => true, 'marked' => 0]);
        }

        $messageIds = ChatMessage::query()
            ->where('chat_group_id', $group->id)
            ->where('id', '<=', $lastMessageId)
            ->where('sender_id', '!=', $user->id)
            ->tap(fn ($query) => $this->applyVisibleMessagesScope($query))
            ->pluck('id');

        if ($messageIds->isEmpty()) {
            return response()->json(['ok' => true, 'marked' => 0]);
        }

        $existing = ChatMessageRead::query()
            ->where('user_id', $user->id)
            ->whereIn('chat_message_id', $messageIds)
            ->pluck('chat_message_id')
            ->all();

        $existingLookup = array_fill_keys(array_map('intval', $existing), true);
        $now = now();
        $rows = [];
        foreach ($messageIds as $messageId) {
            $messageId = (int) $messageId;
            if (isset($existingLookup[$messageId])) {
                continue;
            }
            $rows[] = [
                'chat_message_id' => $messageId,
                'user_id' => $user->id,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ChatMessageRead::query()->insert($rows);
        }

        return response()->json(['ok' => true, 'marked' => count($rows)]);
    }

    public function reactToMessage(Request $request, ChatMessage $message): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['error' => 'Group chat backend is not ready.'], 503);
        }

        $group = $message->group;
        abort_unless($group && $this->canAccessGroup($request->user(), $group), 403, 'Not allowed to react to this message.');
        if ($message->deleted_at) {
            return response()->json(['error' => 'Cannot react to a deleted message.'], 422);
        }

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $emoji = trim($validated['emoji']);
        if ($emoji === '') {
            return response()->json(['error' => 'Reaction emoji is required.'], 422);
        }

        $reactions = $this->normalizeReactions($message->reactions);
        $userId = (int) $request->user()->id;
        $userIds = $reactions[$emoji] ?? [];

        if (in_array($userId, $userIds, true)) {
            $userIds = array_values(array_filter($userIds, fn ($id) => (int) $id !== $userId));
        } else {
            $userIds[] = $userId;
        }

        if ($userIds === []) {
            unset($reactions[$emoji]);
        } else {
            $reactions[$emoji] = array_values(array_unique(array_map('intval', $userIds)));
        }

        $message->update(['reactions' => $reactions]);
        $message->refresh();

        return response()->json([
            'message' => $this->mapMessage($message->load([
                'sender:id,name,role,avatar_path,updated_at',
                'replyTo:id,sender_id,kind,body,file_name,file_size',
                'replyTo.sender:id,name,role,avatar_path,updated_at',
                'reads.user:id,name,avatar_path,updated_at',
            ]), $userId, $this->isTeacher($request->user())),
        ]);
    }

    public function updateMessage(Request $request, ChatMessage $message): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['error' => 'Group chat backend is not ready.'], 503);
        }

        $group = $message->group;
        $user = $request->user();
        abort_unless($group && $this->canAccessGroup($user, $group), 403, 'Not allowed to edit this message.');
        abort_unless((int) $message->sender_id === (int) $user->id, 403, 'Only sender can edit this message.');

        if ($message->deleted_at) {
            return response()->json(['error' => 'Deleted messages cannot be edited.'], 422);
        }

        if (! in_array($message->kind, ['text', 'quiz', 'emoji', 'link'], true)) {
            return response()->json(['error' => 'This message type cannot be edited.'], 422);
        }

        if ($message->kind === 'quiz' && ! $this->isTeacher($user)) {
            return response()->json(['error' => 'Only teachers can edit shared quiz messages.'], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $newBody = trim((string) $validated['body']);
        if ($newBody === '') {
            return response()->json(['error' => 'Message cannot be empty.'], 422);
        }

        if ($message->kind === 'link' && ! filter_var($newBody, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Please provide a valid URL.'], 422);
        }

        $message->update([
            'body' => $newBody,
            'edited_at' => now(),
        ]);
        $message->refresh();

        return response()->json([
            'message' => $this->mapMessage($message->load([
                'sender:id,name,role,avatar_path,updated_at',
                'replyTo:id,sender_id,kind,body,file_name,file_size',
                'replyTo.sender:id,name,role,avatar_path,updated_at',
                'reads.user:id,name,avatar_path,updated_at',
            ]), (int) $user->id, $this->isTeacher($user)),
        ]);
    }

    public function deleteMessage(Request $request, ChatMessage $message): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['error' => 'Group chat backend is not ready.'], 503);
        }

        $group = $message->group;
        $user = $request->user();
        abort_unless($group && $this->canAccessGroup($user, $group), 403, 'Not allowed to delete this message.');

        $isSender = (int) $message->sender_id === (int) $user->id;
        $isTeacher = $this->isTeacher($user);
        abort_unless($isSender || $isTeacher, 403, 'Not allowed to delete this message.');

        if (! $message->deleted_at) {
            $message->update([
                'kind' => 'text',
                'body' => 'This message was removed.',
                'file_name' => null,
                'file_size' => null,
                'reactions' => [],
                'is_pinned' => false,
                'pinned_at' => null,
                'pinned_by' => null,
                'deleted_at' => now(),
                'deleted_by' => $user->id,
            ]);
            $message->refresh();
        }

        return response()->json([
            'message' => $this->mapMessage($message->load([
                'sender:id,name,role,avatar_path,updated_at',
                'replyTo:id,sender_id,kind,body,file_name,file_size',
                'replyTo.sender:id,name,role,avatar_path,updated_at',
                'reads.user:id,name,avatar_path,updated_at',
            ]), (int) $user->id, $this->isTeacher($user)),
        ]);
    }

    public function pinMessage(Request $request, ChatMessage $message): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['error' => 'Group chat backend is not ready.'], 503);
        }

        $group = $message->group;
        $user = $request->user();
        abort_unless($group && $this->canAccessGroup($user, $group), 403, 'Not allowed to pin this message.');

        $isSender = (int) $message->sender_id === (int) $user->id;
        $isTeacher = $this->isTeacher($user);
        abort_unless($isSender || $isTeacher, 403, 'Not allowed to pin this message.');

        if ($message->deleted_at) {
            return response()->json(['error' => 'Deleted messages cannot be pinned.'], 422);
        }

        $nextPinned = ! (bool) $message->is_pinned;
        $message->update([
            'is_pinned' => $nextPinned,
            'pinned_at' => $nextPinned ? now() : null,
            'pinned_by' => $nextPinned ? $user->id : null,
        ]);
        $message->refresh();

        return response()->json([
            'message' => $this->mapMessage($message->load([
                'sender:id,name,role,avatar_path,updated_at',
                'replyTo:id,sender_id,kind,body,file_name,file_size',
                'replyTo.sender:id,name,role,avatar_path,updated_at',
                'reads.user:id,name,avatar_path,updated_at',
            ]), (int) $user->id, $isTeacher),
        ]);
    }

    public function reportMessage(Request $request, ChatMessage $message): JsonResponse
    {
        if (! $this->hasChatTables()) {
            return response()->json(['error' => 'Group chat backend is not ready.'], 503);
        }

        if (! $this->hasMessageReportsTable()) {
            return response()->json(['error' => 'Message report table is missing. Run migrations.'], 503);
        }

        $group = $message->group;
        $user = $request->user();
        abort_unless($group && $this->canAccessGroup($user, $group), 403, 'Not allowed to report this message.');

        if ((int) $message->sender_id === (int) $user->id) {
            return response()->json(['error' => 'You cannot report your own message.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = trim((string) ($validated['reason'] ?? ''));
        $reason = $reason !== '' ? $reason : null;

        $report = ChatMessageReport::query()->firstOrCreate(
            [
                'chat_message_id' => (int) $message->id,
                'reported_by' => (int) $user->id,
            ],
            [
                'chat_group_id' => (int) $group->id,
                'reported_user_id' => (int) $message->sender_id,
                'reason' => $reason,
                'status' => 'open',
            ],
        );

        if (Schema::hasTable('app_notifications')) {
            $teachers = User::query()
                ->where('role', 'teacher')
                ->where('id', '!=', $user->id)
                ->pluck('id');

            $summary = "{$user->name} reported a message in {$group->name}.";
            if ($reason) {
                $summary .= " Reason: {$reason}";
            }

            foreach ($teachers as $teacherId) {
                AppNotification::createForUserIfEnabled((int) $teacherId, [
                    'type' => 'groupchat',
                    'title' => 'Message Reported',
                    'body' => $summary,
                    'link' => '/groupchat',
                    'meta' => [
                        'groupId' => (int) $group->id,
                        'messageId' => (int) $message->id,
                        'reportedBy' => (int) $user->id,
                        'reportedUserId' => (int) $message->sender_id,
                    ],
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $report->wasRecentlyCreated
                ? 'Message reported. Teachers have been notified.'
                : 'You already reported this message.',
        ]);
    }

    public function messageMedia(Request $request, ChatMessage $message): HttpResponse
    {
        if (! $this->hasChatTables()) {
            abort(404);
        }

        $group = $message->group;
        abort_unless($group && $this->canAccessGroup($request->user(), $group), 403);

        $path = $this->extractStoragePath($message);
        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path, $message->file_name ?? basename($path));
    }

    private function isTeacher($user): bool
    {
        if (! $user) {
            return false;
        }

        $role = strtolower((string) ($user->role ?? $user->user_type ?? $user->type ?? 'student'));
        return $role === 'teacher';
    }

    private function canAccessGroup($user, ChatGroup $group): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isTeacher($user)) {
            return true;
        }

        return ! empty($user->section)
            && ! empty($user->course)
            && $user->section === $group->section
            && $user->course === $group->course;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    private function resolveMessageBody(ChatMessage $message): string
    {
        $path = $this->extractStoragePath($message);
        if ($path) {
            return route('groupchat.messages.media', ['message' => $message->id]);
        }

        return (string) ($message->body ?? '');
    }

    private function mapMessage(ChatMessage $message, ?int $viewerId = null, bool $viewerIsTeacher = false): array
    {
        $reactions = $this->normalizeReactions($message->reactions);
        $reactionSummary = collect($reactions)
            ->map(fn ($userIds, $emoji) => [
                'emoji' => (string) $emoji,
                'count' => count($userIds),
                'reacted' => $viewerId ? in_array((int) $viewerId, $userIds, true) : false,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $seenUsers = [];
        if ($message->relationLoaded('reads')) {
            $seenUsers = $message->reads
                ->filter(fn ($read) => (int) $read->user_id !== (int) $message->sender_id)
                ->filter(fn ($read) => ! is_null($read->user))
                ->map(fn ($read) => [
                    'id' => (int) $read->user_id,
                    'name' => (string) ($read->user?->name ?? 'User'),
                    'avatar' => $read->user?->avatar,
                ])
                ->unique('id')
                ->values()
                ->all();
        }
        $seenBy = collect($seenUsers)->pluck('name')->all();

        $replyTo = null;
        if ($message->relationLoaded('replyTo') && $message->replyTo) {
            $replyToBody = $message->replyTo->kind === 'file'
                ? ($message->replyTo->file_name ?? 'File')
                : ($message->replyTo->kind === 'image'
                    ? '[Image]'
                    : $this->resolveMessageBody($message->replyTo));

            $replyTo = [
                'id' => $message->replyTo->id,
                'senderName' => $message->replyTo->sender?->name ?? 'User',
                'senderAvatar' => $message->replyTo->sender?->avatar,
                'body' => mb_substr((string) $replyToBody, 0, 120),
                'kind' => $message->replyTo->kind,
            ];
        }

        $isDeleted = ! is_null($message->deleted_at);
        $isSender = $viewerId ? (int) $viewerId === (int) $message->sender_id : false;
        $messageTimestamp = $this->resolveMessageTimestamp($message);
        $isScheduled = ! is_null($message->published_at) && $message->published_at->gt($message->created_at);

        return [
            'id' => $message->id,
            'senderId' => (int) $message->sender_id,
            'senderName' => $message->sender?->name ?? 'User',
            'senderAvatar' => $message->sender?->avatar,
            'senderRole' => strtolower((string) ($message->sender?->role ?? 'student')),
            'replyToMessageId' => $message->reply_to_message_id ? (int) $message->reply_to_message_id : null,
            'kind' => $message->kind,
            'body' => $this->resolveMessageBody($message),
            'createdAt' => $messageTimestamp?->format('h:i A') ?? '',
            'createdAtIso' => $messageTimestamp?->toIso8601String(),
            'isScheduled' => $isScheduled,
            'scheduledFor' => $message->published_at?->toIso8601String(),
            'fileName' => $message->file_name,
            'fileSize' => $message->file_size,
            'replyTo' => $replyTo,
            'reactions' => $reactionSummary,
            'seenUsers' => $seenUsers,
            'seenBy' => $seenBy,
            'seenCount' => count($seenBy),
            'isDeleted' => $isDeleted,
            'isEdited' => ! is_null($message->edited_at),
            'canEdit' => $isSender && ! $isDeleted && in_array($message->kind, ['text', 'quiz', 'emoji', 'link'], true),
            'canDelete' => ! $isDeleted && ($isSender || $viewerIsTeacher),
            'canPin' => ! $isDeleted && ($isSender || $viewerIsTeacher),
            'isPinned' => (bool) $message->is_pinned,
        ];
    }

    private function normalizeReactions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $emoji => $userIds) {
            if (! is_string($emoji) || $emoji === '' || ! is_array($userIds)) {
                continue;
            }

            $normalized[$emoji] = array_values(array_unique(array_map('intval', $userIds)));
        }

        return $normalized;
    }

    private function extractStoragePath(ChatMessage $message): ?string
    {
        if (! in_array($message->kind, ['file', 'image'], true)) {
            return null;
        }

        $body = trim((string) ($message->body ?? ''));
        if ($body === '') {
            return null;
        }

        if (str_starts_with($body, 'chat-uploads/')) {
            return $body;
        }

        if (str_starts_with($body, '/storage/chat-uploads/')) {
            return ltrim(substr($body, strlen('/storage/')), '/');
        }

        $parsedPath = parse_url($body, PHP_URL_PATH);
        if (is_string($parsedPath) && str_contains($parsedPath, '/storage/chat-uploads/')) {
            $normalized = ltrim($parsedPath, '/');
            if (str_starts_with($normalized, 'storage/')) {
                return substr($normalized, strlen('storage/'));
            }
        }

        return null;
    }

    private function hasChatTables(): bool
    {
        try {
            return Schema::hasTable('chat_groups')
                && Schema::hasTable('chat_messages')
                && Schema::hasTable('chat_message_reads')
                && Schema::hasColumn('chat_messages', 'reply_to_message_id')
                && Schema::hasColumn('chat_messages', 'reactions')
                && Schema::hasColumn('chat_messages', 'edited_at')
                && Schema::hasColumn('chat_messages', 'deleted_at')
                && Schema::hasColumn('chat_messages', 'deleted_by')
                && Schema::hasColumn('chat_messages', 'is_pinned')
                && Schema::hasColumn('chat_messages', 'pinned_at')
                && Schema::hasColumn('chat_messages', 'pinned_by')
                && Schema::hasColumn('chat_messages', 'published_at');
        } catch (QueryException) {
            return false;
        }
    }

    private function hasMessageReportsTable(): bool
    {
        try {
            return Schema::hasTable('chat_message_reports')
                && Schema::hasColumn('chat_message_reports', 'chat_message_id')
                && Schema::hasColumn('chat_message_reports', 'reported_by')
                && Schema::hasColumn('chat_message_reports', 'status');
        } catch (QueryException) {
            return false;
        }
    }

    private function applyVisibleMessagesScope(Builder|Relation $query): Builder|Relation
    {
        if (! $this->hasPublishedAtColumn()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $builder->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    private function hasPublishedAtColumn(): bool
    {
        try {
            return Schema::hasColumn('chat_messages', 'published_at');
        } catch (QueryException) {
            return false;
        }
    }

    private function resolveMessageTimestamp(ChatMessage $message): CarbonInterface|null
    {
        if ($this->hasPublishedAtColumn() && ! is_null($message->published_at)) {
            return $message->published_at;
        }

        return $message->created_at;
    }

    private function hasGroupSettingsTable(): bool
    {
        try {
            return Schema::hasTable('chat_group_user_settings')
                && Schema::hasColumn('chat_group_user_settings', 'chat_group_id')
                && Schema::hasColumn('chat_group_user_settings', 'user_id')
                && Schema::hasColumn('chat_group_user_settings', 'muted_at')
                && Schema::hasColumn('chat_group_user_settings', 'muted_until');
        } catch (QueryException) {
            return false;
        }
    }

    private function resolveMuteSettings(int $userId): array
    {
        if ($userId <= 0 || ! $this->hasGroupSettingsTable()) {
            return [];
        }

        $rows = DB::table('chat_group_user_settings')
            ->where('user_id', $userId)
            ->whereNotNull('muted_at')
            ->get(['id', 'chat_group_id', 'muted_until']);

        $settings = [];
        $expiredRowIds = [];
        $nowTimestamp = now()->timestamp;

        foreach ($rows as $row) {
            $groupId = (int) $row->chat_group_id;
            $untilTimestamp = null;
            if (! is_null($row->muted_until)) {
                $parsed = strtotime((string) $row->muted_until);
                $untilTimestamp = $parsed === false ? null : $parsed;
            }

            $isMuted = is_null($untilTimestamp) || $untilTimestamp > $nowTimestamp;
            if (! $isMuted) {
                $expiredRowIds[] = (int) $row->id;
            }

            $settings[$groupId] = [
                'isMuted' => $isMuted,
                'mutedUntilAt' => $untilTimestamp ? date(DATE_ATOM, $untilTimestamp) : null,
            ];
        }

        if ($expiredRowIds !== []) {
            DB::table('chat_group_user_settings')
                ->whereIn('id', $expiredRowIds)
                ->update([
                    'muted_at' => null,
                    'muted_until' => null,
                    'updated_at' => now(),
                ]);
        }

        return $settings;
    }

    private function isActiveMutedRow(object|null $row): bool
    {
        if (! $row || is_null($row->muted_at)) {
            return false;
        }

        if (is_null($row->muted_until)) {
            return true;
        }

        $until = strtotime((string) $row->muted_until);
        return $until !== false && $until > now()->timestamp;
    }

    private function typingCachePrefix(int $groupId): string
    {
        return "groupchat:typing:{$groupId}:";
    }

    private function presenceCachePrefix(int $groupId): string
    {
        return "groupchat:presence:{$groupId}:";
    }

    private function presenceLastSeenCachePrefix(int $groupId): string
    {
        return "groupchat:presence:last-seen:{$groupId}:";
    }

    private function touchPresence(int $groupId, int $userId): void
    {
        $timestamp = now()->timestamp;
        Cache::put($this->presenceCachePrefix($groupId).$userId, true, now()->addSeconds(70));
        Cache::put($this->presenceLastSeenCachePrefix($groupId).$userId, $timestamp, now()->addDays(7));
    }

    private function resolveTypingUsers(ChatGroup $group, int $viewerId): array
    {
        $typingUsers = [];
        $prefix = $this->typingCachePrefix($group->id);

        $participants = User::query()
            ->select(['id', 'name', 'role', 'section', 'course', 'avatar_path', 'updated_at'])
            ->where(function ($query) use ($group) {
                $query->where('role', 'teacher')
                    ->orWhere(function ($studentQuery) use ($group) {
                        $studentQuery
                            ->where('section', $group->section)
                            ->where('course', $group->course);
                    });
            })
            ->cursor();

        foreach ($participants as $candidate) {
            if ((int) $candidate->id === $viewerId) {
                continue;
            }

            if (Cache::get($prefix.$candidate->id)) {
                $typingUsers[] = $candidate->name;
            }
        }

        return array_values($typingUsers);
    }

    private function resolveActiveUsers(ChatGroup $group, int $viewerId): array
    {
        return array_values(array_map(
            fn (array $user) => (string) $user['name'],
            array_filter(
                $this->resolvePresenceUsers($group, $viewerId),
                fn (array $user) => (bool) ($user['isOnline'] ?? false),
            ),
        ));
    }

    private function resolvePresenceUsers(ChatGroup $group, int $viewerId): array
    {
        $onlinePrefix = $this->presenceCachePrefix($group->id);
        $lastSeenPrefix = $this->presenceLastSeenCachePrefix($group->id);
        $presenceUsers = [];

        $participants = User::query()
            ->select(['id', 'name', 'role', 'section', 'course', 'avatar_path', 'updated_at'])
            ->where(function ($query) use ($group) {
                $query->where('role', 'teacher')
                    ->orWhere(function ($studentQuery) use ($group) {
                        $studentQuery
                            ->where('section', $group->section)
                            ->where('course', $group->course);
                    });
            })
            ->cursor();

        foreach ($participants as $candidate) {
            if ((int) $candidate->id === $viewerId) {
                continue;
            }

            $isOnline = (bool) Cache::get($onlinePrefix.$candidate->id, false);
            $lastSeenRaw = Cache::get($lastSeenPrefix.$candidate->id);
            $lastSeenTimestamp = is_numeric($lastSeenRaw) ? (int) $lastSeenRaw : null;
            $lastSeenAt = $lastSeenTimestamp ? date(DATE_ATOM, $lastSeenTimestamp) : null;

            $presenceUsers[] = [
                'id' => (int) $candidate->id,
                'name' => (string) $candidate->name,
                'avatar' => $candidate->avatar,
                'isOnline' => $isOnline,
                'lastSeenAt' => $lastSeenAt,
            ];
        }

        usort($presenceUsers, function (array $a, array $b): int {
            if ($a['isOnline'] !== $b['isOnline']) {
                return $a['isOnline'] ? -1 : 1;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $presenceUsers;
    }
}
