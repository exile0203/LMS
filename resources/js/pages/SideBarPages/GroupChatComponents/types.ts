export type ChatRole = 'teacher' | 'student';

export type PresenceUser = {
    id: number;
    name: string;
    avatar?: string | null;
    isOnline: boolean;
    lastSeenAt?: string | null;
};

export type MessageKind =
    | 'text'
    | 'quiz'
    | 'file'
    | 'image'
    | 'gif'
    | 'sticker'
    | 'emoji'
    | 'link';

export type ChatMessage = {
    id: number;
    senderId?: number;
    senderName: string;
    senderAvatar?: string | null;
    senderRole: ChatRole;
    replyToMessageId?: number | null;
    kind: MessageKind;
    body: string;
    createdAt: string;
    createdAtIso?: string | null;
    fileName?: string;
    fileSize?: string;
    replyTo?: {
        id: number;
        senderName: string;
        senderAvatar?: string | null;
        body: string;
        kind: MessageKind;
    } | null;
    reactions?: Array<{
        emoji: string;
        count: number;
        reacted: boolean;
    }>;
    seenUsers?: Array<{
        id: number;
        name: string;
        avatar?: string | null;
    }>;
    seenBy?: string[];
    seenCount?: number;
    isDeleted?: boolean;
    isEdited?: boolean;
    canEdit?: boolean;
    canDelete?: boolean;
    canPin?: boolean;
    isPinned?: boolean;
};

export type ChatGroup = {
    id: number;
    name: string;
    section: string;
    course: string;
    createdBy: string;
    messages: ChatMessage[];
    isMuted?: boolean;
    mutedUntilAt?: string | null;
    mutedLabel?: string;
    unreadCount?: number;
    onlineCount?: number;
    typingCount?: number;
    typingPreview?: string;
    previewText?: string;
    previewStatus?: string;
    previewTime?: string;
};

export type NewMessagePayload = {
    kind: MessageKind;
    body: string;
    fileName?: string;
    fileSize?: string;
    file?: File;
    scheduledFor?: string | null;
    replyToMessageId?: number | null;
};
