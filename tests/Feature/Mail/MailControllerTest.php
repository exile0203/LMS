<?php

namespace Tests\Feature\Mail;

use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_mail_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('mail.index'))
            ->assertOk();
    }

    public function test_mail_index_returns_paginated_data_with_metadata(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 30) as $index) {
            MailMessage::create([
                'user_id' => $user->id,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_email' => $user->email,
                'subject' => "Mail {$index}",
                'snippet' => "Snippet {$index}",
                'body' => "Body {$index}",
                'folder' => 'Inbox',
                'unread' => true,
                'starred' => false,
                'created_at' => now()->subMinutes(31 - $index),
                'updated_at' => now()->subMinutes(31 - $index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('mail.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SideBarPages/MailPage')
                ->has('emails', 25)
                ->where('emails.0.subject', 'Mail 30')
                ->where('pagination.currentPage', 1)
                ->where('pagination.hasMorePages', true)
                ->where('pagination.nextPage', 2)
            );
    }

    public function test_mail_index_second_page_returns_remaining_emails(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 30) as $index) {
            MailMessage::create([
                'user_id' => $user->id,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_email' => $user->email,
                'subject' => "Mail {$index}",
                'snippet' => "Snippet {$index}",
                'body' => "Body {$index}",
                'folder' => 'Inbox',
                'unread' => true,
                'starred' => false,
                'created_at' => now()->subMinutes(31 - $index),
                'updated_at' => now()->subMinutes(31 - $index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('mail.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SideBarPages/MailPage')
                ->has('emails', 5)
                ->where('emails.0.subject', 'Mail 5')
                ->where('pagination.currentPage', 2)
                ->where('pagination.hasMorePages', false)
                ->where('pagination.nextPage', null)
            );
    }

    public function test_mail_index_pagination_only_includes_authenticated_users_messages(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        foreach (range(1, 20) as $index) {
            MailMessage::create([
                'user_id' => $user->id,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_email' => $user->email,
                'subject' => "User Mail {$index}",
                'snippet' => "User Snippet {$index}",
                'body' => "User Body {$index}",
                'folder' => 'Inbox',
                'unread' => true,
                'starred' => false,
                'created_at' => now()->subMinutes(100 - $index),
                'updated_at' => now()->subMinutes(100 - $index),
            ]);
        }

        foreach (range(1, 20) as $index) {
            MailMessage::create([
                'user_id' => $otherUser->id,
                'sender_id' => $otherUser->id,
                'sender_name' => $otherUser->name,
                'sender_email' => $otherUser->email,
                'subject' => "Other Mail {$index}",
                'snippet' => "Other Snippet {$index}",
                'body' => "Other Body {$index}",
                'folder' => 'Inbox',
                'unread' => true,
                'starred' => false,
                'created_at' => now()->subMinutes(50 - $index),
                'updated_at' => now()->subMinutes(50 - $index),
            ]);
        }

        $this->actingAs($user)
            ->get(route('mail.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SideBarPages/MailPage')
                ->has('emails', 20)
                ->where('pagination.currentPage', 1)
                ->where('pagination.hasMorePages', false)
                ->where('pagination.nextPage', null)
                ->where('emails.0.subject', 'User Mail 20')
                ->where('emails.19.subject', 'User Mail 1')
            );
    }

    public function test_user_can_compose_mail_and_creates_sender_and_recipient_copies(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender)
            ->from(route('mail.index'))
            ->post(route('mail.compose'), [
                'to' => $recipient->email,
                'subject' => 'Project Update',
                'body' => 'Initial draft is ready.',
            ]);

        $response->assertRedirect(route('mail.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('mail_messages', 2);

        $this->assertDatabaseHas('mail_messages', [
            'user_id' => $sender->id,
            'sender_id' => $sender->id,
            'folder' => 'Sent',
            'subject' => 'Project Update',
            'unread' => false,
        ]);

        $this->assertDatabaseHas('mail_messages', [
            'user_id' => $recipient->id,
            'sender_id' => $sender->id,
            'folder' => 'Inbox',
            'subject' => 'Project Update',
            'unread' => true,
        ]);
    }

    public function test_compose_fails_for_unknown_recipient_email(): void
    {
        $sender = User::factory()->create();

        $response = $this->actingAs($sender)
            ->from(route('mail.index'))
            ->post(route('mail.compose'), [
                'to' => 'not-found@example.com',
                'subject' => 'Hello',
                'body' => 'Test message.',
            ]);

        $response->assertRedirect(route('mail.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_user_can_toggle_star_mark_read_and_move_folder_on_own_mail(): void
    {
        $user = User::factory()->create();

        $mail = MailMessage::create([
            'user_id' => $user->id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_email' => $user->email,
            'subject' => 'Reminder',
            'snippet' => 'Don\'t forget this.',
            'body' => 'Don\'t forget this.',
            'folder' => 'Inbox',
            'unread' => true,
            'starred' => false,
        ]);

        $this->actingAs($user)
            ->from(route('mail.index'))
            ->patch(route('mail.star', $mail))
            ->assertRedirect(route('mail.index'));

        $this->assertDatabaseHas('mail_messages', [
            'id' => $mail->id,
            'starred' => true,
        ]);

        $this->actingAs($user)
            ->from(route('mail.index'))
            ->patch(route('mail.read', $mail))
            ->assertRedirect(route('mail.index'));

        $this->assertDatabaseHas('mail_messages', [
            'id' => $mail->id,
            'unread' => false,
        ]);

        $this->actingAs($user)
            ->from(route('mail.index'))
            ->patch(route('mail.folder', $mail), [
                'folder' => 'Trash',
            ])
            ->assertRedirect(route('mail.index'));

        $this->assertDatabaseHas('mail_messages', [
            'id' => $mail->id,
            'folder' => 'Trash',
        ]);
    }

    public function test_user_cannot_modify_another_users_mail(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $mail = MailMessage::create([
            'user_id' => $owner->id,
            'sender_id' => $owner->id,
            'sender_name' => $owner->name,
            'sender_email' => $owner->email,
            'subject' => 'Private',
            'snippet' => 'Private snippet',
            'body' => 'Private body',
            'folder' => 'Inbox',
            'unread' => true,
            'starred' => false,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('mail.star', $mail))
            ->assertForbidden();

        $this->assertDatabaseHas('mail_messages', [
            'id' => $mail->id,
            'starred' => false,
            'unread' => true,
            'folder' => 'Inbox',
        ]);
    }

    public function test_move_folder_validates_allowed_values(): void
    {
        $user = User::factory()->create();

        $mail = MailMessage::create([
            'user_id' => $user->id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_email' => $user->email,
            'subject' => 'Validation',
            'snippet' => 'Validation snippet',
            'body' => 'Validation body',
            'folder' => 'Inbox',
            'unread' => true,
            'starred' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('mail.index'))
            ->patch(route('mail.folder', $mail), [
                'folder' => 'NotARealFolder',
            ]);

        $response->assertRedirect(route('mail.index'));
        $response->assertSessionHasErrors('folder');
        $this->assertDatabaseHas('mail_messages', [
            'id' => $mail->id,
            'folder' => 'Inbox',
        ]);
    }
}
