<script setup lang="ts">
import { Check, CornerUpLeft, Download, ExternalLink, FileText, Flag, Link as LinkIcon, Pencil, Pin, Search, SmilePlus, Trash2, Users, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { ChatGroup, ChatMessage, PresenceUser } from './types';

type Props = {
    group: ChatGroup | null;
    currentUserName: string;
    currentUserId: number;
    isSending?: boolean;
    typingUsers?: string[];
    activeUsers?: string[];
    presenceUsers?: PresenceUser[];
};

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'reply-message', message: ChatMessage): void;
    (e: 'react-message', payload: { messageId: number; emoji: string }): void;
    (e: 'edit-message', payload: { messageId: number; body: string }): void;
    (e: 'delete-message', payload: { messageId: number }): void;
    (e: 'pin-message', payload: { messageId: number }): void;
    (e: 'report-message', payload: { messageId: number }): void;
}>();

const messages = computed(() => props.group?.messages ?? []);
const searchTerm = ref('');
const scrollContainer = ref<HTMLElement | null>(null);

const isMine = (message: ChatMessage) => {
    if (message.senderId) {
        return message.senderId === props.currentUserId;
    }

    return message.senderName === props.currentUserName;
};

const isLikelyUrl = (value: string) => {
    try {
        const parsed = new URL(value);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
};

const scrollToLatest = async (smooth = true) => {
    await nextTick();

    if (!scrollContainer.value) {
        return;
    }

    scrollContainer.value.scrollTo({
        top: scrollContainer.value.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto',
    });
};

watch(
    () => messages.value.length,
    () => {
        scrollToLatest(true);
    },
);

watch(
    () => props.group?.id,
    () => {
        activeThreadRootId.value = null;
        scrollToLatest(false);
    },
);

onMounted(() => {
    scrollToLatest(false);
});

const quickReactions = ['👍', '❤️', '😂', '🔥'];
const editingMessageId = ref<number | null>(null);
const editDraft = ref('');
const activeThreadRootId = ref<number | null>(null);

const seenLabel = (message: ChatMessage) => {
    const seenBy = message.seenBy ?? [];
    if (seenBy.length === 0) {
        return 'Delivered';
    }

    if (seenBy.length <= 2) {
        return `Seen by ${seenBy.join(', ')}`;
    }

    return `Seen by ${seenBy.slice(0, 2).join(', ')} +${seenBy.length - 2}`;
};

const nameInitials = (name: string) =>
    name
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase();

const seenUsersForMessage = (message: ChatMessage) => {
    if (message.seenUsers?.length) {
        return message.seenUsers;
    }

    return (message.seenBy ?? []).map((name, index) => ({
        id: -(index + 1),
        name,
    }));
};

const pinnedMessages = computed(() =>
    messages.value.filter((message) => !!message.isPinned && !message.isDeleted),
);

const usersPresence = computed<PresenceUser[]>(() => {
    if (props.presenceUsers?.length) {
        return props.presenceUsers;
    }

    return (props.activeUsers ?? []).map((name, index) => ({
        id: -(index + 1),
        name,
        isOnline: true,
        lastSeenAt: null,
    }));
});

const onlineUsers = computed(() => usersPresence.value.filter((user) => user.isOnline));
const offlineUsers = computed(() => usersPresence.value.filter((user) => !user.isOnline && !!user.lastSeenAt));
const avatarForName = (name: string) =>
    usersPresence.value.find((user) => user.name === name)?.avatar ?? null;

const formatLastSeen = (value?: string | null) => {
    if (!value) {
        return 'unknown';
    }

    const timestamp = Date.parse(value);
    if (Number.isNaN(timestamp)) {
        return 'unknown';
    }

    const diff = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
};

const filteredMessages = computed(() => {
    const query = searchTerm.value.trim().toLowerCase();
    if (!query) return messages.value;

    return messages.value.filter((message) => {
        const text = `${message.senderName} ${message.body} ${message.replyTo?.body ?? ''}`.toLowerCase();
        return text.includes(query);
    });
});

const messageMap = computed(() => {
    const map = new Map<number, ChatMessage>();
    for (const message of messages.value) {
        map.set(message.id, message);
    }
    return map;
});

const resolveThreadRootId = (message: ChatMessage): number => {
    let current = message;
    let guard = 0;

    while (current.replyToMessageId && guard < 50) {
        const parent = messageMap.value.get(current.replyToMessageId);
        if (!parent) {
            return current.replyToMessageId;
        }
        current = parent;
        guard++;
    }

    return current.id;
};

const openThread = (message: ChatMessage) => {
    activeThreadRootId.value = resolveThreadRootId(message);
};

const closeThread = () => {
    activeThreadRootId.value = null;
};

const threadMessages = computed(() => {
    const rootId = activeThreadRootId.value;
    if (!rootId) return [] as ChatMessage[];

    return messages.value
        .filter((message) => resolveThreadRootId(message) === rootId)
        .sort((a, b) => a.id - b.id);
});

const threadRootMessage = computed(() => {
    if (!activeThreadRootId.value) return null;
    return messageMap.value.get(activeThreadRootId.value) ?? null;
});

const startEdit = (message: ChatMessage) => {
    if (!message.canEdit) return;
    editingMessageId.value = message.id;
    editDraft.value = message.body;
};

const cancelEdit = () => {
    editingMessageId.value = null;
    editDraft.value = '';
};

const saveEdit = (messageId: number) => {
    const body = editDraft.value.trim();
    if (!body) return;
    emit('edit-message', { messageId, body });
    cancelEdit();
};
</script>

<template>
    <section class="flex min-h-0 flex-1 flex-col overflow-hidden bg-background">
        <div v-if="!group" class="m-auto text-center">
            <p class="text-lg font-semibold text-foreground">Select a group chat</p>
            <p class="text-sm text-muted-foreground">Choose a section group from the left panel.</p>
        </div>

        <template v-else>
            <div class="border-b border-border bg-card px-5 py-4">
                <h3 class="text-base font-semibold text-foreground">{{ group.name }}</h3>
                <p class="text-xs text-muted-foreground">{{ group.section }} · {{ group.course }}</p>
                <div v-if="props.typingUsers?.length" class="mt-1 flex flex-wrap items-center gap-1">
                    <span
                        v-for="name in props.typingUsers"
                        :key="`typing-${name}`"
                        class="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-[10px] text-primary"
                    >
                        <Avatar class="h-4 w-4 rounded-full border border-primary/25">
                            <AvatarImage v-if="avatarForName(name)" :src="avatarForName(name)!" :alt="name" />
                            <AvatarFallback class="bg-primary/20 text-[9px] font-semibold text-primary">
                                {{ nameInitials(name) }}
                            </AvatarFallback>
                        </Avatar>
                        {{ name }}
                    </span>
                    <span class="text-xs text-primary/80">
                        {{ props.typingUsers.length > 1 ? 'are typing...' : 'is typing...' }}
                    </span>
                </div>
                <p v-if="onlineUsers.length" class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <Users class="h-3 w-3 text-emerald-500" />
                    {{ onlineUsers.length }} online:
                    {{ onlineUsers.slice(0, 3).map((user) => user.name).join(', ') }}<span v-if="onlineUsers.length > 3"> +{{ onlineUsers.length - 3 }}</span>
                </p>
                <p v-if="offlineUsers.length" class="mt-1 text-xs text-muted-foreground">
                    Last seen:
                    {{ offlineUsers.slice(0, 2).map((user) => `${user.name} ${formatLastSeen(user.lastSeenAt)}`).join(', ') }}
                </p>

                <div class="mt-2 flex items-center gap-2">
                    <div class="relative w-full max-w-xs">
                        <Search class="pointer-events-none absolute left-2 top-2 h-3.5 w-3.5 text-muted-foreground" />
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search messages..."
                            class="h-8 w-full rounded-md border border-input bg-background pl-7 pr-2 text-xs text-foreground outline-none focus:border-ring"
                        />
                    </div>
                    <span v-if="pinnedMessages.length" class="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                        <Pin class="h-3 w-3" />
                        {{ pinnedMessages.length }} pinned
                    </span>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-hidden">
                <div
                    class="grid h-full min-h-0"
                    :class="activeThreadRootId ? 'grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px]' : 'grid-cols-1'"
                >
                    <div ref="scrollContainer" class="min-h-0 space-y-3 overflow-y-auto overflow-x-hidden p-4">
                <div v-if="pinnedMessages.length" class="mb-2 rounded-lg border border-border bg-muted/50 p-2">
                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Pinned Messages</p>
                    <div class="space-y-1">
                        <button
                            v-for="pinned in pinnedMessages.slice(-3)"
                            :key="`pinned-${pinned.id}`"
                            type="button"
                            class="w-full truncate rounded-md border border-border bg-background px-2 py-1 text-left text-xs text-foreground hover:bg-accent"
                            @click="searchTerm = pinned.body"
                        >
                            {{ pinned.senderName }}: {{ pinned.body }}
                        </button>
                    </div>
                </div>

                <div
                    v-for="message in filteredMessages"
                    :key="message.id"
                    class="flex"
                    :class="isMine(message) ? 'justify-end' : 'justify-start'"
                >
                    <Card
                        class="max-w-[85%] px-3 py-2 shadow-sm md:max-w-[70%]"
                        :class="isMine(message) ? 'border-primary/40 bg-primary/10' : 'border-border bg-card'"
                    >
                        <div v-if="message.replyTo" class="mb-2 rounded-lg border border-border/70 bg-muted/60 px-2 py-1">
                            <p class="text-[10px] font-semibold text-muted-foreground inline-flex items-center gap-1">
                                <Avatar class="h-4 w-4 rounded-full border border-border/70">
                                    <AvatarImage v-if="message.replyTo.senderAvatar" :src="message.replyTo.senderAvatar" :alt="message.replyTo.senderName" />
                                    <AvatarFallback class="text-[9px] font-semibold">{{ nameInitials(message.replyTo.senderName) }}</AvatarFallback>
                                </Avatar>
                                Reply to {{ message.replyTo.senderName }}
                            </p>
                            <p class="line-clamp-1 text-xs text-foreground/90">{{ message.replyTo.body }}</p>
                        </div>

                        <p class="mb-1 text-[11px] font-medium text-muted-foreground">
                            <span class="inline-flex items-center gap-1">
                                <Avatar class="h-5 w-5 rounded-full border border-border">
                                    <AvatarImage v-if="message.senderAvatar" :src="message.senderAvatar" :alt="message.senderName" />
                                    <AvatarFallback class="text-[9px] font-semibold">{{ nameInitials(message.senderName) }}</AvatarFallback>
                                </Avatar>
                                {{ message.senderName }} · {{ message.createdAt }}
                            </span>
                            <span v-if="message.isEdited" class="ml-1">(edited)</span>
                            <span v-if="message.isPinned" class="ml-1 inline-flex items-center gap-0.5 text-primary">
                                <Pin class="h-3 w-3" />
                                pinned
                            </span>
                        </p>

                        <div
                            v-if="editingMessageId === message.id && message.canEdit"
                            class="mb-2 rounded-lg border border-border bg-background p-2"
                        >
                            <textarea
                                v-model="editDraft"
                                rows="2"
                                class="w-full resize-none rounded-md border border-input bg-background px-2 py-1 text-sm text-foreground outline-none focus:border-ring"
                            />
                            <div class="mt-2 flex items-center justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="cancelEdit">
                                    <X class="h-3 w-3" />
                                    Cancel
                                </Button>
                                <Button type="button" size="sm" class="h-7 px-2 text-xs" @click="saveEdit(message.id)">
                                    <Check class="h-3 w-3" />
                                    Save
                                </Button>
                            </div>
                        </div>

                        <p v-else-if="message.kind === 'text' || message.kind === 'emoji'" class="text-sm text-foreground">
                            {{ message.body }}
                        </p>

                        <div
                            v-else-if="message.kind === 'quiz'"
                            class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3"
                        >
                            <Badge variant="secondary" class="bg-amber-500/20 text-amber-700 dark:text-amber-300">Quiz Shared</Badge>
                            <p class="mt-1 text-sm font-medium text-foreground">{{ message.body }}</p>
                        </div>

                        <div
                            v-else-if="message.kind === 'file'"
                            class="flex items-center gap-2 rounded-xl border border-border bg-muted p-3"
                        >
                            <FileText class="h-4 w-4 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-foreground">{{ message.fileName || 'File' }}</p>
                                <p class="text-xs text-muted-foreground">{{ message.fileSize || '' }}</p>
                            </div>
                            <a
                                v-if="isLikelyUrl(message.body)"
                                :href="message.body"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 rounded-md border border-border bg-background px-2 py-1 text-xs text-foreground hover:bg-accent"
                            >
                                <ExternalLink class="h-3.5 w-3.5" />
                                Open
                            </a>
                            <a
                                v-if="isLikelyUrl(message.body)"
                                :href="message.body"
                                :download="message.fileName || 'file'"
                                class="inline-flex items-center gap-1 rounded-md border border-border bg-background px-2 py-1 text-xs text-foreground hover:bg-accent"
                            >
                                <Download class="h-3.5 w-3.5" />
                                Save
                            </a>
                        </div>

                        <a
                            v-else-if="
                                message.kind === 'image' ||
                                message.kind === 'gif' ||
                                message.kind === 'sticker'
                            "
                            :href="message.body"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block"
                        >
                            <img
                                :src="message.body"
                                :alt="message.kind"
                                class="max-h-64 rounded-xl border border-border object-cover"
                            />
                        </a>

                        <a
                            v-else-if="message.kind === 'link'"
                            :href="message.body"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                        >
                            <LinkIcon class="h-3.5 w-3.5" />
                            {{ message.body }}
                        </a>

                        <p v-else class="text-sm text-foreground">{{ message.body }}</p>

                        <div class="mt-2 flex flex-wrap items-center gap-1">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-6 px-2 text-[10px]"
                                :disabled="!!message.isDeleted"
                                @click="emit('reply-message', message)"
                            >
                                <CornerUpLeft class="h-3 w-3" />
                                Reply
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-6 px-2 text-[10px]"
                                :disabled="!!message.isDeleted"
                                @click="openThread(message)"
                            >
                                Thread
                            </Button>

                            <Button
                                v-if="message.canEdit"
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-6 px-2 text-[10px]"
                                @click="startEdit(message)"
                            >
                                <Pencil class="h-3 w-3" />
                                Edit
                            </Button>

                            <Button
                                v-if="message.canDelete"
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-6 px-2 text-[10px] text-destructive hover:text-destructive"
                                @click="emit('delete-message', { messageId: message.id })"
                            >
                                <Trash2 class="h-3 w-3" />
                                Delete
                            </Button>

                            <Button
                                v-if="message.canPin"
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-6 px-2 text-[10px]"
                                @click="emit('pin-message', { messageId: message.id })"
                            >
                                <Pin class="h-3 w-3" />
                                {{ message.isPinned ? 'Unpin' : 'Pin' }}
                            </Button>

                            <Button
                                v-if="!isMine(message) && !message.isDeleted"
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-6 px-2 text-[10px] text-amber-700 hover:text-amber-700 dark:text-amber-300 dark:hover:text-amber-300"
                                @click="emit('report-message', { messageId: message.id })"
                            >
                                <Flag class="h-3 w-3" />
                                Report
                            </Button>

                            <Button
                                v-for="emoji in quickReactions"
                                :key="`${message.id}-${emoji}`"
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="h-6 px-2 text-xs"
                                :disabled="!!message.isDeleted"
                                @click="emit('react-message', { messageId: message.id, emoji })"
                            >
                                {{ emoji }}
                            </Button>
                            <SmilePlus class="h-3.5 w-3.5 text-muted-foreground" />
                        </div>

                        <div v-if="message.reactions?.length" class="mt-2 flex flex-wrap gap-1">
                            <button
                                v-for="reaction in message.reactions"
                                :key="`${message.id}-${reaction.emoji}`"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition"
                                :class="reaction.reacted ? 'border-primary/40 bg-primary/15 text-foreground' : 'border-border bg-background text-muted-foreground hover:bg-accent'"
                                :disabled="!!message.isDeleted"
                                @click="emit('react-message', { messageId: message.id, emoji: reaction.emoji })"
                            >
                                <span>{{ reaction.emoji }}</span>
                                <span>{{ reaction.count }}</span>
                            </button>
                        </div>

                        <div v-if="isMine(message)" class="mt-1">
                            <div v-if="seenUsersForMessage(message).length" class="flex items-center justify-end gap-1">
                                <span
                                    v-for="(user, index) in seenUsersForMessage(message).slice(0, 5)"
                                    :key="`seen-${message.id}-${user.id}-${index}`"
                                    :title="user.name"
                                >
                                    <Avatar class="h-5 w-5 rounded-full border border-border">
                                        <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                        <AvatarFallback class="bg-muted text-[9px] font-semibold text-muted-foreground">
                                            {{ nameInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </span>
                                <span
                                    v-if="seenUsersForMessage(message).length > 5"
                                    class="text-[10px] text-muted-foreground"
                                >
                                    +{{ seenUsersForMessage(message).length - 5 }}
                                </span>
                            </div>
                            <p class="text-right text-[10px] text-muted-foreground">
                                {{ seenLabel(message) }}
                            </p>
                        </div>
                    </Card>
                </div>

                        <div v-if="props.isSending" class="flex justify-end">
                            <Card class="max-w-[85%] border-primary/30 bg-primary/10 px-3 py-2 shadow-sm md:max-w-[70%]">
                                <p class="text-xs text-muted-foreground animate-pulse">Sending...</p>
                            </Card>
                        </div>
                    </div>
                    <aside
                        v-if="activeThreadRootId"
                        class="hidden min-h-0 border-l border-border bg-card/60 lg:flex lg:flex-col"
                    >
                        <div class="flex items-center justify-between border-b border-border px-3 py-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Thread</p>
                                <p class="text-sm font-medium text-foreground">
                                    {{ threadMessages.length }} message{{ threadMessages.length === 1 ? '' : 's' }}
                                </p>
                            </div>
                            <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="closeThread">
                                <X class="h-3 w-3" />
                                Close
                            </Button>
                        </div>
                        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-3">
                            <div
                                v-if="threadRootMessage"
                                class="rounded-lg border border-primary/30 bg-primary/10 px-3 py-2 text-xs text-foreground"
                            >
                                <p class="font-semibold text-primary inline-flex items-center gap-1">
                                    <Avatar class="h-4 w-4 rounded-full border border-primary/30">
                                        <AvatarImage v-if="threadRootMessage.senderAvatar" :src="threadRootMessage.senderAvatar" :alt="threadRootMessage.senderName" />
                                        <AvatarFallback class="text-[9px] font-semibold">{{ nameInitials(threadRootMessage.senderName) }}</AvatarFallback>
                                    </Avatar>
                                    Root: {{ threadRootMessage.senderName }}
                                </p>
                                <p class="mt-1 line-clamp-3 break-words">{{ threadRootMessage.body }}</p>
                            </div>
                            <div
                                v-for="threadMessage in threadMessages"
                                :key="`thread-item-${threadMessage.id}`"
                                class="rounded-lg border border-border bg-background px-3 py-2"
                            >
                                <p class="text-[11px] font-medium text-muted-foreground">
                                    <span class="inline-flex items-center gap-1">
                                        <Avatar class="h-4 w-4 rounded-full border border-border">
                                            <AvatarImage v-if="threadMessage.senderAvatar" :src="threadMessage.senderAvatar" :alt="threadMessage.senderName" />
                                            <AvatarFallback class="text-[9px] font-semibold">{{ nameInitials(threadMessage.senderName) }}</AvatarFallback>
                                        </Avatar>
                                        {{ threadMessage.senderName }} · {{ threadMessage.createdAt }}
                                    </span>
                                </p>
                                <p class="mt-1 break-words text-sm text-foreground">{{ threadMessage.body }}</p>
                                <div class="mt-2 flex items-center justify-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-6 px-2 text-[10px]"
                                        :disabled="!!threadMessage.isDeleted"
                                        @click="emit('reply-message', threadMessage)"
                                    >
                                        <CornerUpLeft class="h-3 w-3" />
                                        Reply
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </template>
    </section>
</template>
