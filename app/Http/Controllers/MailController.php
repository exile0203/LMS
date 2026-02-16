<?php

namespace App\Http\Controllers;

use App\Enums\MailFolder;
use App\Models\AppNotification;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MailController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $paginator = MailMessage::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $emails = collect($paginator->items())
            ->map(fn (MailMessage $mail) => [
                'id' => $mail->id,
                'sender' => $mail->sender_name,
                'subject' => $mail->subject,
                'snippet' => $mail->snippet ?? '',
                'time' => $mail->created_at?->format('h:i A') ?? '',
                'unread' => (bool) $mail->unread,
                'starred' => (bool) $mail->starred,
                'folder' => $mail->folder,
            ])
            ->values();

        return Inertia::render('SideBarPages/MailPage', [
            'emails' => $emails,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'hasMorePages' => $paginator->hasMorePages(),
                'nextPage' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            ],
        ]);
    }

    public function compose(Request $request)
    {
        $validated = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $sender = $request->user();
        $recipient = User::query()->where('email', $validated['to'])->first();

        if (! $recipient) {
            return back()->with('error', 'Recipient email is not registered in the system.');
        }

        $snippet = mb_substr(trim((string) ($validated['body'] ?? '')), 0, 120);

        DB::transaction(function () use ($sender, $recipient, $validated, $snippet): void {
            MailMessage::create([
                'user_id' => $sender->id,
                'sender_id' => $sender->id,
                'sender_name' => $sender->name,
                'sender_email' => $sender->email,
                'subject' => $validated['subject'],
                'snippet' => $snippet,
                'body' => $validated['body'] ?? null,
                'folder' => MailFolder::SENT->value,
                'unread' => false,
                'starred' => false,
            ]);

            MailMessage::create([
                'user_id' => $recipient->id,
                'sender_id' => $sender->id,
                'sender_name' => $sender->name,
                'sender_email' => $sender->email,
                'subject' => $validated['subject'],
                'snippet' => $snippet,
                'body' => $validated['body'] ?? null,
                'folder' => MailFolder::INBOX->value,
                'unread' => true,
                'starred' => false,
            ]);

            AppNotification::createForUserIfEnabled($recipient->id, [
                'type' => 'mail',
                'title' => 'New Mail Received',
                'body' => "{$sender->name} sent: {$validated['subject']}",
                'link' => '/mail',
            ]);

            AppNotification::createForUserIfEnabled($sender->id, [
                'type' => 'mail',
                'title' => 'Mail Sent',
                'body' => "Your message \"{$validated['subject']}\" was sent.",
                'link' => '/mail',
            ]);
        });

        return back()->with('success', 'Email sent successfully.');
    }

    public function toggleStar(Request $request, MailMessage $mail)
    {
        $this->authorizeMailOwnership($request, $mail);

        $mail->update([
            'starred' => ! $mail->starred,
        ]);

        return back();
    }

    public function markRead(Request $request, MailMessage $mail)
    {
        $this->authorizeMailOwnership($request, $mail);

        $mail->update([
            'unread' => false,
        ]);

        return back();
    }

    public function moveFolder(Request $request, MailMessage $mail)
    {
        $this->authorizeMailOwnership($request, $mail);

        $validated = $request->validate([
            'folder' => ['required', 'string', Rule::in(MailFolder::values())],
        ]);

        $mail->update([
            'folder' => $validated['folder'],
        ]);

        return back();
    }

    private function authorizeMailOwnership(Request $request, MailMessage $mail): void
    {
        abort_unless($mail->user_id === $request->user()->id, 403, 'Not allowed.');
    }
}
