<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    /**
     * Show the user's notification settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Notifications', [
            'preferences' => AppNotification::preferencesForUser((int) $request->user()->id),
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail' => ['required', 'boolean'],
            'group_chat' => ['required', 'boolean'],
            'quiz' => ['required', 'boolean'],
            'attendance' => ['required', 'boolean'],
            'support' => ['required', 'boolean'],
            'general' => ['required', 'boolean'],
        ]);

        AppNotification::setPreferencesForUser(
            userId: (int) $request->user()->id,
            input: $validated,
        );

        return back()->with('status', 'notification-preferences-updated');
    }
}
