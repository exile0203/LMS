<?php

namespace App\Enums;

enum MailFolder: string
{
    case INBOX = 'Inbox';
    case STARRED = 'Starred';
    case SNOOZED = 'Snoozed';
    case SENT = 'Sent';
    case DRAFTS = 'Drafts';
    case SPAM = 'Spam';
    case ARCHIVED = 'Archived';
    case TRASH = 'Trash';

    public static function values(): array
    {
        return array_map(static fn (self $folder) => $folder->value, self::cases());
    }
}
