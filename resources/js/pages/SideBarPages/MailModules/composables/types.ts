import type { Component } from 'vue';

export const MAIL_FOLDER = {
    INBOX: 'Inbox',
    STARRED: 'Starred',
    SNOOZED: 'Snoozed',
    SENT: 'Sent',
    DRAFTS: 'Drafts',
    SPAM: 'Spam',
    ARCHIVED: 'Archived',
    TRASH: 'Trash',
} as const;

export type MailFolderName = (typeof MAIL_FOLDER)[keyof typeof MAIL_FOLDER];

export const MAIL_FOLDERS: MailFolderName[] = [
    MAIL_FOLDER.INBOX,
    MAIL_FOLDER.STARRED,
    MAIL_FOLDER.SNOOZED,
    MAIL_FOLDER.SENT,
    MAIL_FOLDER.DRAFTS,
    MAIL_FOLDER.SPAM,
    MAIL_FOLDER.ARCHIVED,
    MAIL_FOLDER.TRASH,
];

export type MailEmail = {
    id: number;
    sender: string;
    subject: string;
    snippet: string;
    time: string;
    unread: boolean;
    starred: boolean;
    folder: MailFolderName;
};

export type MailFolder = {
    name: MailFolderName;
    icon: Component;
    active: boolean;
    count: number;
};

export type MailPageProps = {
    emails: MailEmail[];
    pagination: {
        currentPage: number;
        hasMorePages: boolean;
        nextPage: number | null;
    };
};
