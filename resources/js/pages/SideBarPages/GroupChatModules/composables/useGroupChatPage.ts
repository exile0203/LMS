import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import type { AppPageProps } from '@/types';
import type { ChatGroup, ChatMessage, PresenceUser } from '../../GroupChatComponents/types';
import { useGroupChatMessageActions } from './useGroupChatMessageActions';

export type GroupChatPageProps = {
    groups: ChatGroup[];
    sectionOptions: string[];
    courseOptions: string[];
};

type ChatFilter = 'all' | 'unmuted' | 'muted';

export function useGroupChatPage(props: GroupChatPageProps) {
const page = usePage<AppPageProps>();
const showCreateGroupModal = ref(false);
const showCommandPalette = ref(false);
const isPostingMessage = ref(false);
const isCreatingGroup = ref(false);
const runtimeError = ref('');
const typingUsers = ref<string[]>([]);
const activeUsers = ref<string[]>([]);
const presenceUsers = ref<PresenceUser[]>([]);
const onlineCountByGroup = ref<Record<number, number>>({});
const typingUsersByGroup = ref<Record<number, string[]>>({});
const lastTypingSent = ref<boolean | null>(null);
const chatFilter = ref<ChatFilter>('all');
const sidebarSearch = ref('');
const commandQuery = ref('');
const commandInput = ref<HTMLInputElement | null>(null);
const replyTarget = ref<ChatMessage | null>(null);
const lastSeenSyncedByGroup = ref<Record<number, number>>({});
const groupsState = ref<ChatGroup[]>(props.groups);
const flashSuccess = computed(() => {
    const flash = page.props.flash as { success?: string; error?: string };
    return flash?.success ?? '';
});
const flashError = computed(() => {
    const flash = page.props.flash as { success?: string; error?: string };
    return flash?.error ?? '';
});
const runtimeInfo = ref('');

const normalizeLabel = (value: unknown): string | null => {
    if (typeof value !== 'string') return null;
    const trimmed = value.trim();
    return trimmed.length > 0 ? trimmed : null;
};

const formatRelativeTime = (iso?: string | null): string => {
    if (!iso) return '';

    const timestamp = Date.parse(iso);
    if (Number.isNaN(timestamp)) return '';

    const now = Date.now();
    const diffMs = Math.max(0, now - timestamp);
    const minute = 60 * 1000;
    const hour = 60 * minute;
    const day = 24 * hour;

    if (diffMs < minute) return 'now';
    if (diffMs < hour) return `${Math.floor(diffMs / minute)}m`;
    if (diffMs < day) return `${Math.floor(diffMs / hour)}h`;
    if (diffMs < day * 2) return 'yesterday';
    if (diffMs < day * 7) return `${Math.floor(diffMs / day)}d`;

    return new Date(timestamp).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

const formatMutedUntilLabel = (iso?: string | null): string => {
    if (!iso) return 'Muted';

    const timestamp = Date.parse(iso);
    if (Number.isNaN(timestamp)) return 'Muted';

    const relative = formatRelativeTime(iso);
    if (relative === 'now') return 'Muted';

    const absolute = new Date(timestamp).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

    if (relative.endsWith('m') || relative.endsWith('h') || relative === 'yesterday') {
        return `Muted for ${relative}`;
    }

    return `Muted until ${absolute}`;
};

const currentUserName = computed(() => {
    const name = page.props.auth?.user?.name;
    return typeof name === 'string' && name.trim() ? name : 'User';
});
const currentUserId = computed(() => Number(page.props.auth?.user?.id ?? 0));

const currentUserRole = computed(() => {
    const user = page.props.auth?.user;
    const possibleRole = user?.role ?? user?.user_type ?? user?.type;
    if (typeof possibleRole === 'string' && possibleRole.trim().length > 0) {
        return possibleRole.toLowerCase();
    }
    return 'student';
});

const isTeacher = computed(() => currentUserRole.value === 'teacher');

const studentSection = computed(() => {
    const user = page.props.auth?.user;
    return (
        normalizeLabel(user?.section) ??
        normalizeLabel(user?.section_name) ??
        normalizeLabel(user?.sectionName)
    );
});

const studentCourse = computed(() => {
    const user = page.props.auth?.user;
    return (
        normalizeLabel(user?.course) ??
        normalizeLabel(user?.course_name) ??
        normalizeLabel(user?.courseName)
    );
});

const hasStudentAssignment = computed(
    () => !!studentSection.value && !!studentCourse.value,
);

const visibleGroups = computed(() => {
    if (isTeacher.value) return groupsState.value;
    if (!hasStudentAssignment.value) return [];
    return groupsState.value.filter(
        (group) =>
            group.section === studentSection.value &&
            group.course === studentCourse.value,
    );
});

const seenStorageKey = computed(() =>
    `groupchat_seen_${currentUserId.value || 'guest'}`,
);
const seenByGroup = ref<Record<number, number>>({});

const loadSeenMap = () => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const raw = localStorage.getItem(seenStorageKey.value);
        const parsed = raw ? JSON.parse(raw) : {};
        seenByGroup.value = typeof parsed === 'object' && parsed ? parsed : {};
    } catch {
        seenByGroup.value = {};
    }
};

const persistSeenMap = () => {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(seenStorageKey.value, JSON.stringify(seenByGroup.value));
};

loadSeenMap();

const selectedGroupId = ref<number | null>(props.groups[0]?.id ?? null);
const groupsForSidebar = computed(() =>
    visibleGroups.value
        .map((group) => {
        const muteUntilTimestamp = group.mutedUntilAt ? Date.parse(group.mutedUntilAt) : NaN;
        const currentlyMuted = !!group.isMuted && (
            !group.mutedUntilAt || (Number.isFinite(muteUntilTimestamp) && muteUntilTimestamp > Date.now())
        );
        const latestMessage = group.messages[group.messages.length - 1] ?? null;
        const lastSeenId = seenByGroup.value[group.id] ?? 0;
        const unreadCount = group.messages.reduce((count, message) => {
            const isOwnMessage = message.senderId
                ? message.senderId === currentUserId.value
                : message.senderName === currentUserName.value;

            if (message.id > lastSeenId && !isOwnMessage) {
                return count + 1;
            }
            return count;
        }, 0);
        const effectiveUnreadCount = currentlyMuted ? 0 : unreadCount;

        const isOwnLatestMessage = latestMessage
            ? (latestMessage.senderId
                ? latestMessage.senderId === currentUserId.value
                : latestMessage.senderName === currentUserName.value)
            : false;

        const latestPreviewBody = (() => {
            if (!latestMessage) return 'No messages yet';
            if (latestMessage.isDeleted) return 'Message removed';

            switch (latestMessage.kind) {
                case 'file':
                    return `Sent file: ${latestMessage.fileName || 'Attachment'}`;
                case 'image':
                    return 'Sent an image';
                case 'gif':
                    return 'Sent a GIF';
                case 'sticker':
                    return 'Sent a sticker';
                case 'quiz':
                    return `Shared quiz: ${latestMessage.body}`;
                case 'link':
                    return `Shared link: ${latestMessage.body}`;
                default:
                    return latestMessage.body || 'New message';
            }
        })();

        const previewText = latestMessage
            ? `${isOwnLatestMessage ? 'You: ' : `${latestMessage.senderName}: `}${latestPreviewBody}${latestMessage.isEdited ? ' (edited)' : ''}`
            : latestPreviewBody;

        const previewStatus = (() => {
            if (!latestMessage || !isOwnLatestMessage) return '';
            if (latestMessage.isDeleted) return 'Removed';
            return (latestMessage.seenCount ?? 0) > 0 ? 'Seen' : 'Delivered';
        })();
        const previewTime = formatRelativeTime(latestMessage?.createdAtIso);
        const mutedLabel = currentlyMuted ? formatMutedUntilLabel(group.mutedUntilAt) : '';

        return {
            ...group,
            isMuted: currentlyMuted,
            mutedLabel,
            unreadCount: effectiveUnreadCount,
            onlineCount: onlineCountByGroup.value[group.id] ?? 0,
            typingCount: typingUsersByGroup.value[group.id]?.length ?? 0,
            typingPreview: (typingUsersByGroup.value[group.id] ?? []).slice(0, 2).join(', '),
            previewText,
            previewStatus,
            previewTime,
        };
        })
        .sort((a, b) => {
            if (!!a.isMuted !== !!b.isMuted) {
                return a.isMuted ? 1 : -1;
            }
            const aLastId = a.messages[a.messages.length - 1]?.id ?? 0;
            const bLastId = b.messages[b.messages.length - 1]?.id ?? 0;
            return bLastId - aLastId;
        }),
);
const filteredGroupsForSidebar = computed(() => {
    const byMute = (() => {
        if (chatFilter.value === 'muted') {
            return groupsForSidebar.value.filter((group) => !!group.isMuted);
        }
        if (chatFilter.value === 'unmuted') {
            return groupsForSidebar.value.filter((group) => !group.isMuted);
        }
        return groupsForSidebar.value;
    })();

    const query = sidebarSearch.value.trim().toLowerCase();
    if (!query) {
        return byMute;
    }

    return byMute.filter((group) => {
        const haystack = [
            group.name,
            group.section,
            group.course,
            group.createdBy,
            group.previewText ?? '',
        ]
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});
const selectedGroup = computed(
    () => filteredGroupsForSidebar.value.find((group) => group.id === selectedGroupId.value) ?? null,
);
const totalGroupCount = computed(() => groupsForSidebar.value.length);
const mutedGroupCount = computed(
    () => groupsForSidebar.value.filter((group) => !!group.isMuted).length,
);
const unmutedGroupCount = computed(
    () => groupsForSidebar.value.filter((group) => !group.isMuted).length,
);

type CommandAction =
    | { key: string; label: string; description: string; type: 'filter'; filter: ChatFilter }
    | { key: string; label: string; description: string; type: 'search-reset' }
    | { key: string; label: string; description: string; type: 'mute-selected'; duration: 'off' | '1h' | '8h' | '24h' | 'forever' }
    | { key: string; label: string; description: string; type: 'jump'; groupId: number };

const commandActions = computed<CommandAction[]>(() => {
    const actions: CommandAction[] = [
        { key: 'filter-all', label: 'Show all chats', description: 'Set chat filter to All', type: 'filter', filter: 'all' },
        { key: 'filter-unmuted', label: 'Show unmuted chats', description: 'Set chat filter to Unmuted', type: 'filter', filter: 'unmuted' },
        { key: 'filter-muted', label: 'Show muted chats', description: 'Set chat filter to Muted', type: 'filter', filter: 'muted' },
        { key: 'search-reset', label: 'Clear sidebar search', description: 'Reset group search query', type: 'search-reset' },
    ];

    if (selectedGroup.value) {
        const isMuted = !!selectedGroup.value.isMuted;
        actions.push({
            key: isMuted ? 'mute-selected-off' : 'mute-selected-24h',
            label: isMuted ? `Unmute "${selectedGroup.value.name}"` : `Mute "${selectedGroup.value.name}" for 24h`,
            description: isMuted ? 'Turn off mute for selected chat' : 'Silence selected chat notifications',
            type: 'mute-selected',
            duration: isMuted ? 'off' : '24h',
        });
    }

    for (const group of groupsForSidebar.value.slice(0, 15)) {
        actions.push({
            key: `jump-${group.id}`,
            label: `Open: ${group.name}`,
            description: `${group.section} · ${group.course}`,
            type: 'jump',
            groupId: group.id,
        });
    }

    return actions;
});

const filteredCommandActions = computed(() => {
    const query = commandQuery.value.trim().toLowerCase();
    if (!query) return commandActions.value;

    return commandActions.value.filter((action) =>
        `${action.label} ${action.description}`.toLowerCase().includes(query),
    );
});

const csrfToken = computed(() =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
);

const getCookie = (name: string) => {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
};

const jsonHeaders = computed(() => {
    const token = csrfToken.value || getCookie('XSRF-TOKEN');
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token, 'X-XSRF-TOKEN': token } : {}),
    };
});

const replaceGroupMessages = (groupId: number, messages: ChatGroup['messages']) => {
    groupsState.value = groupsState.value.map((group) =>
        group.id === groupId ? { ...group, messages } : group,
    );
};

const replaceMessageInGroup = (groupId: number, updatedMessage: ChatMessage) => {
    groupsState.value = groupsState.value.map((group) => {
        if (group.id !== groupId) {
            return group;
        }

        return {
            ...group,
            messages: group.messages.map((message) =>
                message.id === updatedMessage.id ? updatedMessage : message,
            ),
        };
    });
};

const markGroupAsSeen = (groupId: number | null) => {
    if (!groupId) {
        return;
    }

    const group = groupsState.value.find((item) => item.id === groupId);
    if (!group || !group.messages.length) {
        return;
    }

    const latestId = group.messages[group.messages.length - 1]?.id ?? 0;
    if (latestId <= (seenByGroup.value[groupId] ?? 0)) {
        return;
    }

    seenByGroup.value = {
        ...seenByGroup.value,
        [groupId]: latestId,
    };
    persistSeenMap();
};

const getLatestMessageId = (groupId: number | null) => {
    if (!groupId) return 0;
    const group = groupsState.value.find((item) => item.id === groupId);
    return group?.messages[group.messages.length - 1]?.id ?? 0;
};

const markMessagesSeenOnServer = async (groupId: number | null) => {
    if (!groupId) {
        return;
    }

    const latestMessageId = getLatestMessageId(groupId);
    if (latestMessageId <= 0) {
        return;
    }

    if ((lastSeenSyncedByGroup.value[groupId] ?? 0) >= latestMessageId) {
        return;
    }

    try {
        const response = await fetch(`/groupchat/groups/${groupId}/messages/seen`, {
            method: 'POST',
            headers: {
                ...jsonHeaders.value,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ lastMessageId: latestMessageId }),
        });

        if (response.ok) {
            lastSeenSyncedByGroup.value = {
                ...lastSeenSyncedByGroup.value,
                [groupId]: latestMessageId,
            };
        }
    } catch {
        // Best effort sync.
    }
};

watch(
    filteredGroupsForSidebar,
    (newGroups) => {
        const stillVisible = newGroups.some((group) => group.id === selectedGroupId.value);
        if (!stillVisible) {
            selectedGroupId.value = newGroups[0]?.id ?? null;
        }
    },
    { immediate: true },
);

watch(
    () => props.groups,
    (newGroups) => {
        groupsState.value = newGroups;
        markGroupAsSeen(selectedGroupId.value);
    },
);

const newGroup = ref({
    name: '',
    section: props.sectionOptions[0] ?? 'Section 1',
    course: props.courseOptions[0] ?? 'Mathematics',
});

const openCreateGroup = () => {
    if (!isTeacher.value) return;
    showCreateGroupModal.value = true;
};

const createGroup = () => {
    if (!isTeacher.value) return;
    if (!newGroup.value.name.trim()) return;
    isCreatingGroup.value = true;

    router.post(
        '/groupchat/groups',
        {
            name: newGroup.value.name.trim(),
            section: newGroup.value.section,
            course: newGroup.value.course,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCreateGroupModal.value = false;
                newGroup.value = {
                    name: '',
                    section: props.sectionOptions[0] ?? 'Section 1',
                    course: props.courseOptions[0] ?? 'Mathematics',
                };
            },
            onFinish: () => {
                isCreatingGroup.value = false;
            },
        },
    );
};

const fetchGroupMessages = async (groupId: number) => {
    try {
        const response = await fetch(`/groupchat/groups/${groupId}/messages`, {
            headers: jsonHeaders.value,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        replaceGroupMessages(groupId, data.messages ?? []);
        presenceUsers.value = data.presenceUsers ?? [];
        activeUsers.value = data.activeUsers ?? [];

        if (selectedGroupId.value === groupId) {
            markGroupAsSeen(groupId);
            markMessagesSeenOnServer(groupId);
        }
    } catch {
        // Keep UI responsive; polling will retry.
    }
};

const fetchTypingStatus = async (groupId: number, updateThreadTyping = false) => {
    try {
        const response = await fetch(`/groupchat/groups/${groupId}/messages/typing`, {
            headers: jsonHeaders.value,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const groupTyping = Array.isArray(data.typingUsers) ? data.typingUsers : [];
        typingUsersByGroup.value = {
            ...typingUsersByGroup.value,
            [groupId]: groupTyping,
        };

        if (updateThreadTyping) {
            typingUsers.value = groupTyping;
        }
    } catch {
        // Keep UI responsive; polling will retry.
    }
};

const fetchPresenceStatus = async (groupId: number, updateThreadPresence = false) => {
    try {
        const response = await fetch(`/groupchat/groups/${groupId}/presence`, {
            headers: jsonHeaders.value,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const snapshot = Array.isArray(data.presenceUsers) ? data.presenceUsers : [];
        const onlineCount = snapshot.filter((user: PresenceUser) => !!user?.isOnline).length;
        onlineCountByGroup.value = {
            ...onlineCountByGroup.value,
            [groupId]: onlineCount,
        };

        if (updateThreadPresence) {
            activeUsers.value = data.activeUsers ?? [];
            presenceUsers.value = snapshot;
        }
    } catch {
        // Keep UI responsive; polling will retry.
    }
};

const pingPresence = async (groupId: number | null) => {
    if (!groupId) {
        return;
    }

    try {
        await fetch(`/groupchat/groups/${groupId}/presence`, {
            method: 'POST',
            headers: jsonHeaders.value,
            credentials: 'same-origin',
        });
    } catch {
        // Best effort only.
    }
};

const setTypingStatus = async (isTyping: boolean) => {
    if (!selectedGroupId.value) {
        return;
    }

    if (lastTypingSent.value === isTyping) {
        return;
    }

    try {
        await fetch(`/groupchat/groups/${selectedGroupId.value}/messages/typing`, {
            method: 'POST',
            headers: {
                ...jsonHeaders.value,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ isTyping }),
        });
        lastTypingSent.value = isTyping;
    } catch {
        // Best effort; do not block composer.
    }
};

const {
    sendMessage,
    reactToMessage,
    editMessage,
    deleteMessage,
    pinMessage,
    reportMessage,
    setGroupMute,
} = useGroupChatMessageActions({
    jsonHeaders,
    selectedGroup,
    isTeacher,
    isPostingMessage,
    runtimeError,
    runtimeInfo,
    typingUsers,
    replyTarget,
    groupsState,
    replaceGroupMessages,
    replaceMessageInGroup,
    setTypingStatus,
});

const runCommand = async (action: CommandAction) => {
    if (action.type === 'filter') {
        chatFilter.value = action.filter;
    } else if (action.type === 'search-reset') {
        sidebarSearch.value = '';
    } else if (action.type === 'mute-selected') {
        if (selectedGroup.value) {
            await setGroupMute(selectedGroup.value.id, action.duration);
        }
    } else if (action.type === 'jump') {
        selectedGroupId.value = action.groupId;
    }

    showCommandPalette.value = false;
};

const handleCommandPaletteShortcut = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        showCommandPalette.value = true;
        commandQuery.value = '';
    }
};

let pollTimer: ReturnType<typeof setInterval> | null = null;
let streamSource: EventSource | null = null;
const streamConnected = ref(false);

const closeStream = () => {
    if (streamSource) {
        streamSource.close();
        streamSource = null;
    }
    streamConnected.value = false;
};

const openStream = (groupId: number) => {
    if (typeof window === 'undefined' || typeof EventSource === 'undefined') {
        streamConnected.value = false;
        return;
    }

    closeStream();

    const source = new EventSource(`/groupchat/groups/${groupId}/stream`, { withCredentials: true });
    streamSource = source;

    source.addEventListener('snapshot', (event) => {
        streamConnected.value = true;
        const payload = JSON.parse((event as MessageEvent).data ?? '{}');

        if (Array.isArray(payload.messages)) {
            replaceGroupMessages(groupId, payload.messages);
            if (selectedGroupId.value === groupId) {
                markGroupAsSeen(groupId);
                markMessagesSeenOnServer(groupId);
            }
        }

        if (Array.isArray(payload.typingUsers) && selectedGroupId.value === groupId) {
            typingUsers.value = payload.typingUsers;
            typingUsersByGroup.value = {
                ...typingUsersByGroup.value,
                [groupId]: payload.typingUsers,
            };
        }
        if (Array.isArray(payload.activeUsers) && selectedGroupId.value === groupId) {
            activeUsers.value = payload.activeUsers;
        }
        if (Array.isArray(payload.presenceUsers) && selectedGroupId.value === groupId) {
            presenceUsers.value = payload.presenceUsers;
            onlineCountByGroup.value = {
                ...onlineCountByGroup.value,
                [groupId]: payload.presenceUsers.filter((user: PresenceUser) => !!user?.isOnline).length,
            };
        }
    });

    source.addEventListener('close', () => {
        closeStream();
    });

    source.onerror = () => {
        closeStream();
    };
};

watch(
    selectedGroupId,
    (groupId) => {
        if (groupId) {
            lastTypingSent.value = null;
            replyTarget.value = null;
            fetchGroupMessages(groupId);
            fetchTypingStatus(groupId, true);
            fetchPresenceStatus(groupId, true);
            pingPresence(groupId);
            markGroupAsSeen(groupId);
            markMessagesSeenOnServer(groupId);
            openStream(groupId);
        } else {
            typingUsers.value = [];
            activeUsers.value = [];
            presenceUsers.value = [];
            typingUsersByGroup.value = {};
            replyTarget.value = null;
            closeStream();
        }
    },
    { immediate: true },
);

onMounted(() => {
    markGroupAsSeen(selectedGroupId.value);
    window.addEventListener('keydown', handleCommandPaletteShortcut);
    pollTimer = setInterval(() => {
        if (typeof document !== 'undefined' && document.hidden) {
            return;
        }

        visibleGroups.value.forEach((group) => {
            fetchTypingStatus(group.id, selectedGroupId.value === group.id);
            fetchPresenceStatus(group.id, selectedGroupId.value === group.id);
        });

        if (selectedGroupId.value) {
            if (streamConnected.value) {
                pingPresence(selectedGroupId.value);
                return;
            }
            fetchGroupMessages(selectedGroupId.value);
            pingPresence(selectedGroupId.value);
            markGroupAsSeen(selectedGroupId.value);
            markMessagesSeenOnServer(selectedGroupId.value);
        }
    }, 2000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
    closeStream();
    setTypingStatus(false);
    window.removeEventListener('keydown', handleCommandPaletteShortcut);
});

watch(showCommandPalette, async (open) => {
    if (!open) return;
    await nextTick();
    commandInput.value?.focus();
});

    return {
        showCreateGroupModal,
        showCommandPalette,
        isPostingMessage,
        isCreatingGroup,
        runtimeError,
        typingUsers,
        activeUsers,
        presenceUsers,
        chatFilter,
        sidebarSearch,
        commandQuery,
        commandInput,
        replyTarget,
        flashSuccess,
        flashError,
        runtimeInfo,
        currentUserName,
        currentUserId,
        isTeacher,
        hasStudentAssignment,
        filteredGroupsForSidebar,
        selectedGroupId,
        selectedGroup,
        totalGroupCount,
        mutedGroupCount,
        unmutedGroupCount,
        newGroup,
        filteredCommandActions,
        openCreateGroup,
        createGroup,
        sendMessage,
        reactToMessage,
        editMessage,
        deleteMessage,
        pinMessage,
        reportMessage,
        setTypingStatus,
        setGroupMute,
        runCommand,
    };
}
