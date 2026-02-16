<?php

namespace Tests\Feature\Settings;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preferences_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/notifications');

        $response->assertOk();
    }

    public function test_notification_preferences_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/notifications')
            ->put('/settings/notifications', [
                'mail' => false,
                'group_chat' => true,
                'quiz' => false,
                'attendance' => true,
                'support' => false,
                'general' => true,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/notifications');

        $preferences = AppNotification::preferencesForUser((int) $user->id);
        $this->assertSame([
            'mail' => false,
            'group_chat' => true,
            'quiz' => false,
            'attendance' => true,
            'support' => false,
            'general' => true,
        ], $preferences);
    }
}
