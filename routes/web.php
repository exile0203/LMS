<?php

use App\Http\Controllers\MailController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\GroupChatController;
use App\Http\Controllers\SidebarController;
use App\Http\Controllers\UserAvatarController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/users/{user}/avatar', [UserAvatarController::class, 'show'])->name('users.avatar');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('mail')->name('mail.')->group(function () {
        Route::get('/', [MailController::class, 'index'])->name('index');
        Route::post('/compose', [MailController::class, 'compose'])->name('compose');
        Route::patch('/{mail}/star', [MailController::class, 'toggleStar'])->name('star');
        Route::patch('/{mail}/read', [MailController::class, 'markRead'])->name('read');
        Route::patch('/{mail}/folder', [MailController::class, 'moveFolder'])->name('folder');
    });

    Route::prefix('groupchat')->name('groupchat.')->group(function () {
        Route::get('/', [GroupChatController::class, 'index'])->name('index');
        Route::post('/groups', [GroupChatController::class, 'storeGroup'])->middleware('throttle:15,1')->name('groups.store');
        Route::get('/messages/{message}/media', [GroupChatController::class, 'messageMedia'])->name('messages.media');
        Route::get('/groups/{group}/messages', [GroupChatController::class, 'messages'])->name('messages.index');
        Route::get('/groups/{group}/stream', [GroupChatController::class, 'stream'])->name('messages.stream');
        Route::post('/groups/{group}/messages/seen', [GroupChatController::class, 'markSeen'])->middleware('throttle:120,1')->name('messages.seen');
        Route::post('/messages/{message}/reactions', [GroupChatController::class, 'reactToMessage'])->middleware('throttle:120,1')->name('messages.reactions');
        Route::post('/messages/{message}/report', [GroupChatController::class, 'reportMessage'])->middleware('throttle:60,1')->name('messages.report');
        Route::post('/messages/{message}/pin', [GroupChatController::class, 'pinMessage'])->middleware('throttle:120,1')->name('messages.pin');
        Route::patch('/messages/{message}', [GroupChatController::class, 'updateMessage'])->middleware('throttle:60,1')->name('messages.update');
        Route::delete('/messages/{message}', [GroupChatController::class, 'deleteMessage'])->middleware('throttle:60,1')->name('messages.delete');
        Route::post('/groups/{group}/messages/typing', [GroupChatController::class, 'setTyping'])->middleware('throttle:120,1')->name('messages.typing.set');
        Route::get('/groups/{group}/messages/typing', [GroupChatController::class, 'typingStatus'])->name('messages.typing.status');
        Route::post('/groups/{group}/presence', [GroupChatController::class, 'setPresence'])->middleware('throttle:120,1')->name('presence.set');
        Route::get('/groups/{group}/presence', [GroupChatController::class, 'presenceStatus'])->name('presence.status');
        Route::post('/groups/{group}/mute', [GroupChatController::class, 'toggleGroupMute'])->middleware('throttle:60,1')->name('groups.mute');
        Route::post('/groups/{group}/messages', [GroupChatController::class, 'storeMessage'])->middleware('throttle:60,1')->name('messages.store');
    });

    Route::prefix('quiz')->name('quiz.')->group(function () {
        Route::get('/', [QuizController::class, 'index'])->name('index');
        Route::post('/', [QuizController::class, 'store'])->name('store');
        Route::get('/attendance/roster', [QuizController::class, 'attendanceRoster'])->name('attendance.roster');
        Route::post('/attendance/mark', [QuizController::class, 'markAttendance'])->name('attendance.mark');
        Route::post('/assignments', [QuizController::class, 'storeAssignment'])->name('assignments.store');
        Route::patch('/assignments/{assignment}', [QuizController::class, 'updateAssignment'])->name('assignments.update');
        Route::post('/assignments/{assignment}/toggle-closed', [QuizController::class, 'toggleAssignmentClosed'])->name('assignments.toggle-closed');
        Route::delete('/assignments/{assignment}', [QuizController::class, 'deleteAssignment'])->name('assignments.delete');
        Route::post('/assignments/{assignment}/submit', [QuizController::class, 'submitAssignment'])->name('assignments.submit');
        Route::post('/submissions/{submission}/comments', [QuizController::class, 'addAssignmentComment'])->name('assignments.comments.store');
        Route::post('/{quiz}/submit', [QuizController::class, 'submitQuizAttempt'])->name('attempts.submit');
        Route::patch('/attempts/{attempt}/score', [QuizController::class, 'overrideQuizAttemptScore'])->name('attempts.override');
    });

    Route::prefix('sidebar')->name('sidebar.')->group(function () {
        Route::get('/notifications', [SidebarController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [SidebarController::class, 'markNotificationsRead'])->name('notifications.read-all');
        Route::get('/notification-preferences', [SidebarController::class, 'notificationPreferences'])->name('notifications.preferences');
        Route::put('/notification-preferences', [SidebarController::class, 'updateNotificationPreferences'])->name('notifications.preferences.update');
        Route::get('/support/messages', [SidebarController::class, 'supportMessages'])->name('support.messages');
        Route::post('/support/messages', [SidebarController::class, 'sendSupportMessage'])->middleware('throttle:30,1')->name('support.messages.send');
        Route::get('/search', [SidebarController::class, 'search'])->name('search');
    });
});

require __DIR__.'/settings.php';
