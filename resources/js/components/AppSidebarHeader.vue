<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Search, Bell, MessageSquare, Maximize, Minimize,
    Settings, X, Send, CheckCircle2, Moon, Sun, SlidersHorizontal
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import type { AppPageProps, BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [
            { title: 'Dashboard', href: '#' },
            { title: 'Mail Box', href: '#' },
        ],
    },
);

type HeaderNotification = {
    id: number;
    title: string;
    text: string;
    time: string;
    isRead: boolean;
    link?: string | null;
};

type NotificationPreferences = {
    mail: boolean;
    group_chat: boolean;
    quiz: boolean;
    attendance: boolean;
    support: boolean;
    general: boolean;
};

type SupportMessage = {
    id: number;
    senderType: 'user' | 'system';
    message: string;
    time: string;
};

type SearchResult = {
    type: string;
    title: string;
    subtitle: string;
    link: string;
};

const isChatOpen = ref(false);
const isNotificationsOpen = ref(false);
const isNotificationPreferencesOpen = ref(false);
const isFullscreen = ref(false);
const isSearchOpen = ref(false);

const notifications = ref<HeaderNotification[]>([]);
const unreadCount = ref(0);
const notificationPreferences = ref<NotificationPreferences>({
    mail: true,
    group_chat: true,
    quiz: true,
    attendance: true,
    support: true,
    general: true,
});
const isSavingPreference = ref(false);
const supportMessages = ref<SupportMessage[]>([]);
const supportInput = ref('');
const searchTerm = ref('');
const searchResults = ref<SearchResult[]>([]);
const isSearching = ref(false);
const isSendingSupport = ref(false);
const supportError = ref('');
const { resolvedAppearance, updateAppearance } = useAppearance();

const page = usePage<AppPageProps>();

const currentUser = computed(() => page.props.auth?.user);
const currentUserName = computed(() =>
    typeof currentUser.value?.name === 'string' && currentUser.value.name.trim().length > 0
        ? currentUser.value.name
        : 'User',
);
const currentUserRole = computed(() => {
    const possibleRole =
        currentUser.value?.role ??
        currentUser.value?.user_type ??
        currentUser.value?.type;

    if (typeof possibleRole === 'string' && possibleRole.trim().length > 0) {
        return possibleRole.toLowerCase();
    }

    return 'student';
});
const isTeacher = computed(() => currentUserRole.value === 'teacher');
const roleLabel = computed(() => (isTeacher.value ? 'Teacher View' : 'Student View'));
const roleBadgeClass = computed(() =>
    isTeacher.value
        ? 'bg-emerald-100 text-emerald-700'
        : 'bg-amber-100 text-amber-700',
);
const avatarClass = computed(() =>
    isTeacher.value
        ? 'bg-emerald-700'
        : 'bg-amber-600',
);
const currentUserInitials = computed(() =>
    currentUserName.value
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);
const currentUserAvatarUrl = computed(() => {
    const avatar = currentUser.value?.avatar;
    return typeof avatar === 'string' && avatar.trim().length > 0 ? avatar : null;
});
const isDarkMode = computed(() => resolvedAppearance.value === 'dark');

const csrfToken = computed(() =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
);

const getCookie = (name: string) => {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : '';
};

const requestHeaders = (isJson = false): Record<string, string> => {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (isJson) {
        headers['Content-Type'] = 'application/json';
    }

    const token = csrfToken.value || getCookie('XSRF-TOKEN');
    if (token) {
        headers['X-CSRF-TOKEN'] = token;
        headers['X-XSRF-TOKEN'] = token;
    }

    return headers;
};

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
};

const toggleAppearance = () => {
    updateAppearance(isDarkMode.value ? 'light' : 'dark');
};

const syncFullscreen = () => {
    isFullscreen.value = !!document.fullscreenElement;
};

const fetchNotifications = async () => {
    try {
        const response = await fetch('/sidebar/notifications', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        notifications.value = data.notifications ?? [];
        unreadCount.value = data.unreadCount ?? 0;
        if (data.preferences) {
            notificationPreferences.value = {
                ...notificationPreferences.value,
                ...data.preferences,
            };
        }
    } catch {
        notifications.value = [];
        unreadCount.value = 0;
    }
};

const fetchNotificationPreferences = async () => {
    try {
        const response = await fetch('/sidebar/notification-preferences', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        if (!data.preferences) return;
        notificationPreferences.value = {
            ...notificationPreferences.value,
            ...data.preferences,
        };
    } catch {
        // Keep defaults when endpoint is unavailable.
    }
};

const updateNotificationPreference = async (key: keyof NotificationPreferences, value: boolean) => {
    if (isSavingPreference.value) return;
    isSavingPreference.value = true;
    const previous = notificationPreferences.value[key];
    notificationPreferences.value = {
        ...notificationPreferences.value,
        [key]: value,
    };

    try {
        const response = await fetch('/sidebar/notification-preferences', {
            method: 'PUT',
            headers: requestHeaders(true),
            credentials: 'same-origin',
            body: JSON.stringify({ [key]: value }),
        });

        if (!response.ok) {
            notificationPreferences.value = {
                ...notificationPreferences.value,
                [key]: previous,
            };
            return;
        }

        const data = await response.json();
        if (data.preferences) {
            notificationPreferences.value = {
                ...notificationPreferences.value,
                ...data.preferences,
            };
        }
    } finally {
        isSavingPreference.value = false;
    }
};

const onPreferenceToggle = (event: Event, key: keyof NotificationPreferences) => {
    const target = event.target as HTMLInputElement | null;
    if (!target) return;
    updateNotificationPreference(key, target.checked);
};

const markAllNotificationsAsRead = async () => {
    await fetch('/sidebar/notifications/read-all', {
        method: 'POST',
        headers: requestHeaders(true),
        credentials: 'same-origin',
    });
    await fetchNotifications();
};

const fetchSupportMessages = async () => {
    try {
        const response = await fetch('/sidebar/support/messages', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        supportMessages.value = data.messages ?? [];
        supportError.value = '';
    } catch {
        supportError.value = 'Unable to load chat right now.';
    }
};

const sendSupportMessage = async () => {
    const message = supportInput.value.trim();
    if (!message) return;
    isSendingSupport.value = true;
    supportError.value = '';

    try {
        const response = await fetch('/sidebar/support/messages', {
            method: 'POST',
            headers: requestHeaders(true),
            credentials: 'same-origin',
            body: JSON.stringify({ message }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => null);
            supportError.value = data?.message || data?.error || 'Message failed to send.';
            return;
        }

        const data = await response.json();
        supportMessages.value = data.messages ?? [];
        supportInput.value = '';
    } catch {
        supportError.value = 'Message failed to send.';
    } finally {
        isSendingSupport.value = false;
    }
};

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
let notificationPoll: ReturnType<typeof setInterval> | null = null;
watch(searchTerm, (query) => {
    if (searchDebounce) clearTimeout(searchDebounce);

    if (query.trim().length < 2) {
        searchResults.value = [];
        isSearchOpen.value = false;
        return;
    }

    searchDebounce = setTimeout(async () => {
        isSearching.value = true;
        try {
            const response = await fetch(`/sidebar/search?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const data = await response.json();
            searchResults.value = data.results ?? [];
            isSearchOpen.value = true;
        } finally {
            isSearching.value = false;
        }
    }, 250);
});

const goToSearchResult = (link: string) => {
    isSearchOpen.value = false;
    searchTerm.value = '';
    router.visit(link);
};

const openSettings = () => {
    router.visit('/settings/profile');
};

const openNotification = (notification: HeaderNotification) => {
    if (notification.link) {
        router.visit(notification.link);
    }
};

watch(isNotificationsOpen, (open) => {
    if (open) {
        fetchNotifications();
        fetchNotificationPreferences();
    } else {
        isNotificationPreferencesOpen.value = false;
    }
});

watch(isChatOpen, (open) => {
    if (open) {
        fetchSupportMessages();
    }
});

onMounted(() => {
    document.addEventListener('fullscreenchange', syncFullscreen);
    fetchNotifications();
    fetchNotificationPreferences();
    fetchSupportMessages();
    notificationPoll = setInterval(fetchNotifications, 30000);
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', syncFullscreen);
    if (searchDebounce) clearTimeout(searchDebounce);
    if (notificationPoll) clearInterval(notificationPoll);
});
</script>

<template>
    <header
        class="animate-fade-in-down sticky top-0 z-40 flex h-16 w-full shrink-0 items-center justify-between border-b border-border/80 bg-background/85 px-6 backdrop-blur-md transition-all"
    >
        <div class="flex items-center gap-4">
            <SidebarTrigger class="-ml-1" />

            <div class="relative hidden w-64 md:block">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <Search class="h-4 w-4 text-muted-foreground" />
                </span>
                <input
                    v-model="searchTerm"
                    type="text"
                    placeholder="Search mail, quiz, group..."
                    class="w-full rounded-lg border border-input bg-background py-2 pl-10 pr-4 text-sm text-foreground outline-none transition-all placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20"
                    @focus="isSearchOpen = searchResults.length > 0"
                />

                <div
                    v-if="isSearchOpen"
                    class="animate-soft-pop absolute left-0 right-0 z-50 mt-2 max-h-80 overflow-y-auto rounded-xl border border-border bg-popover shadow-2xl"
                >
                    <div v-if="isSearching" class="p-3 text-xs text-muted-foreground">
                        Searching...
                    </div>
                    <button
                        v-for="(result, index) in searchResults"
                        :key="`${result.type}-${index}`"
                        type="button"
                        class="w-full border-b border-border p-3 text-left hover:bg-accent/50"
                        @click="goToSearchResult(result.link)"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary">{{ result.type }}</p>
                        <p class="text-sm font-medium text-foreground">{{ result.title }}</p>
                        <p class="text-xs text-muted-foreground">{{ result.subtitle }}</p>
                    </button>
                    <div v-if="!isSearching && !searchResults.length" class="p-3 text-xs text-muted-foreground">
                        No results found.
                    </div>
                </div>
            </div>

            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="mx-2 hidden h-4 w-px bg-border lg:block"></div>
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center space-x-2 md:space-x-4">
            <TooltipProvider :delay-duration="120">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            class="relative rounded-full p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            :class="{ 'bg-accent text-accent-foreground': isChatOpen }"
                            @click="isChatOpen = !isChatOpen; isNotificationsOpen = false"
                        >
                            <MessageSquare class="h-5 w-5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Support Chat</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            :title="isDarkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                            @click="toggleAppearance"
                        >
                            <component :is="isDarkMode ? Sun : Moon" class="h-5 w-5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ isDarkMode ? 'Switch To Light' : 'Switch To Dark' }}</TooltipContent>
                </Tooltip>

                <div class="relative">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                class="relative rounded-full p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                :class="{ 'bg-accent text-accent-foreground': isNotificationsOpen }"
                                @click="isNotificationsOpen = !isNotificationsOpen; isChatOpen = false"
                            >
                                <Bell class="h-5 w-5" />
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full border-2 border-white bg-red-500 px-1 text-[10px] font-semibold text-white"
                                >
                                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                                </span>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>Notifications</TooltipContent>
                    </Tooltip>

                    <div
                        v-if="isNotificationsOpen"
                        class="animate-soft-pop absolute right-0 z-50 mt-3 w-80 overflow-hidden rounded-xl border border-border bg-popover shadow-2xl"
                    >
                        <div class="flex items-center justify-between border-b border-border bg-muted/40 p-4">
                            <span class="text-sm font-bold text-foreground">Notifications</span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="rounded-md p-1 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                                    title="Notification preferences"
                                    @click="isNotificationPreferencesOpen = !isNotificationPreferencesOpen"
                                >
                                    <SlidersHorizontal class="h-4 w-4" />
                                </button>
                                <CheckCircle2 class="h-4 w-4 text-blue-500" />
                            </div>
                        </div>
                        <div
                            v-if="isNotificationPreferencesOpen"
                            class="space-y-2 border-b border-border bg-muted/20 p-4 text-xs"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Notification Preferences</p>
                            <label class="flex items-center justify-between gap-3 text-foreground">
                                <span>Mail</span>
                                <input
                                    :checked="notificationPreferences.mail"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                    :disabled="isSavingPreference"
                                    @change="onPreferenceToggle($event, 'mail')"
                                />
                            </label>
                            <label class="flex items-center justify-between gap-3 text-foreground">
                                <span>Group Chat</span>
                                <input
                                    :checked="notificationPreferences.group_chat"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                    :disabled="isSavingPreference"
                                    @change="onPreferenceToggle($event, 'group_chat')"
                                />
                            </label>
                            <label class="flex items-center justify-between gap-3 text-foreground">
                                <span>Quiz</span>
                                <input
                                    :checked="notificationPreferences.quiz"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                    :disabled="isSavingPreference"
                                    @change="onPreferenceToggle($event, 'quiz')"
                                />
                            </label>
                            <label class="flex items-center justify-between gap-3 text-foreground">
                                <span>Attendance</span>
                                <input
                                    :checked="notificationPreferences.attendance"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                    :disabled="isSavingPreference"
                                    @change="onPreferenceToggle($event, 'attendance')"
                                />
                            </label>
                            <label class="flex items-center justify-between gap-3 text-foreground">
                                <span>Support</span>
                                <input
                                    :checked="notificationPreferences.support"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                    :disabled="isSavingPreference"
                                    @change="onPreferenceToggle($event, 'support')"
                                />
                            </label>
                            <label class="flex items-center justify-between gap-3 text-foreground">
                                <span>General</span>
                                <input
                                    :checked="notificationPreferences.general"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                    :disabled="isSavingPreference"
                                    @change="onPreferenceToggle($event, 'general')"
                                />
                            </label>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <button
                                v-for="notification in notifications"
                                :key="notification.id"
                                type="button"
                                class="w-full border-b border-border p-4 text-left hover:bg-accent/50"
                                @click="openNotification(notification)"
                            >
                                <p class="text-sm font-semibold text-foreground">{{ notification.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ notification.text }}</p>
                                <p class="mt-1 text-[10px] text-muted-foreground">{{ notification.time }}</p>
                            </button>
                            <p v-if="!notifications.length" class="p-4 text-xs text-muted-foreground">
                                No notifications yet.
                            </p>
                        </div>
                        <button
                            class="w-full py-3 text-xs font-medium text-primary hover:bg-accent/50"
                            @click="markAllNotificationsAsRead"
                        >
                            Mark all as read
                        </button>
                    </div>
                </div>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <button class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground" @click="toggleFullscreen">
                            <component :is="isFullscreen ? Minimize : Maximize" class="h-5 w-5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}</TooltipContent>
                </Tooltip>

                <div class="mx-1 h-6 w-px bg-border"></div>

                <div class="group flex cursor-pointer items-center space-x-3">
                    <div class="hidden text-right sm:block">
                        <p class="leading-tight text-sm font-semibold text-foreground">{{ currentUserName }}</p>
                        <p class="text-[11px] text-muted-foreground">{{ roleLabel }}</p>
                    </div>
                    <span
                        class="hidden rounded-full px-2 py-1 text-[10px] font-semibold md:inline-flex"
                        :class="roleBadgeClass"
                    >
                        {{ roleLabel }}
                    </span>
                    <img
                        v-if="currentUserAvatarUrl"
                        :src="currentUserAvatarUrl"
                        :alt="`${currentUserName} avatar`"
                        class="h-9 w-9 rounded-full border-2 border-white object-cover shadow-sm"
                    >
                    <div
                        v-else
                        class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white text-sm font-bold text-white shadow-sm"
                        :class="avatarClass"
                    >
                        {{ currentUserInitials }}
                    </div>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button class="p-1 text-muted-foreground transition-colors group-hover:text-primary" @click="openSettings">
                                <Settings class="h-5 w-5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>Settings</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>
    </header>

    <Teleport to="body">
        <div
            v-if="isChatOpen"
            class="animate-soft-pop fixed bottom-6 right-6 z-[60] flex h-96 w-80 flex-col overflow-hidden rounded-2xl border border-border bg-popover shadow-[0_20px_50px_rgba(8,_112,_184,_0.22)] dark:shadow-[0_20px_50px_rgba(8,_20,_60,_0.55)]"
        >
            <div class="flex items-center justify-between border-b border-border bg-primary p-4 text-primary-foreground shadow-md">
                <span class="font-bold">Support Chat</span>
                <button @click="isChatOpen = false">
                    <X class="h-4 w-4 transition-transform hover:scale-110" />
                </button>
            </div>
            <div class="flex-1 space-y-3 overflow-y-auto bg-muted/30 p-4">
                <div
                    v-for="message in supportMessages"
                    :key="message.id"
                    class="max-w-[85%] rounded-2xl border p-3 text-sm"
                    :class="
                        message.senderType === 'user'
                            ? 'ml-auto rounded-br-none border-primary/30 bg-primary/10 text-foreground'
                            : 'rounded-tl-none border-border bg-card text-foreground'
                    "
                >
                    <p>{{ message.message }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">{{ message.time }}</p>
                </div>
                <p v-if="supportError" class="text-xs text-destructive">{{ supportError }}</p>
            </div>
            <div class="flex gap-2 border-t border-border bg-popover p-3">
                <input
                    v-model="supportInput"
                    type="text"
                    placeholder="Aa"
                    class="flex-1 rounded-full border border-input bg-background px-4 text-sm text-foreground ring-1 ring-transparent placeholder:text-muted-foreground focus:ring-primary"
                    :disabled="isSendingSupport"
                    @keydown.enter.prevent="sendSupportMessage"
                />
                <button class="p-1 text-primary disabled:opacity-50" :disabled="isSendingSupport" @click="sendSupportMessage">
                    <Send class="h-5 w-5" />
                </button>
            </div>
        </div>
    </Teleport>

    <div
        v-if="isNotificationsOpen || isSearchOpen"
        class="fixed inset-0 z-40 bg-transparent"
        @click="isNotificationsOpen = false; isSearchOpen = false"
    ></div>
</template>
