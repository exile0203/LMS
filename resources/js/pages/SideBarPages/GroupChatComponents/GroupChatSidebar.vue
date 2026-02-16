<script setup lang="ts">
import { Bell, BellOff, MessageCircle, Plus, Users } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ChatGroup } from './types';

type Props = {
    groups: ChatGroup[];
    selectedGroupId: number | null;
    isTeacher: boolean;
    activeFilter: 'all' | 'unmuted' | 'muted';
    totalCount: number;
    unmutedCount: number;
    mutedCount: number;
    searchTerm: string;
};

const emit = defineEmits<{
    (e: 'select-group', groupId: number): void;
    (e: 'create-group'): void;
    (e: 'set-mute', payload: { groupId: number; duration: 'off' | '1h' | '8h' | '24h' | 'forever' }): void;
    (e: 'set-filter', filter: 'all' | 'unmuted' | 'muted'): void;
    (e: 'set-search', value: string): void;
}>();

const props = defineProps<Props>();
const searchInput = ref<HTMLInputElement | null>(null);
const keyboardIndex = ref(-1);

const isTypingTarget = (target: EventTarget | null): boolean => {
    const element = target as HTMLElement | null;
    if (!element) return false;

    const tag = element.tagName.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') {
        return true;
    }

    return !!element.closest('[contenteditable="true"]');
};

const handleKeydown = (event: KeyboardEvent) => {
    const isSearchFocused = document.activeElement === searchInput.value;
    const hasGroups = props.groups.length > 0;

    if (event.key === '/' && !event.metaKey && !event.ctrlKey && !event.altKey) {
        if (isTypingTarget(event.target)) {
            return;
        }
        event.preventDefault();
        searchInput.value?.focus();
        searchInput.value?.select();
        return;
    }

    if (event.key === 'Escape' && isSearchFocused) {
        if (searchInput.value?.value) {
            event.preventDefault();
            emit('set-search', '');
        }
        return;
    }

    if (!hasGroups) {
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (isTypingTarget(event.target) && !isSearchFocused) {
            return;
        }

        event.preventDefault();
        const direction = event.key === 'ArrowDown' ? 1 : -1;

        if (keyboardIndex.value < 0) {
            keyboardIndex.value = 0;
        } else {
            keyboardIndex.value =
                (keyboardIndex.value + direction + props.groups.length) %
                props.groups.length;
        }

        const targetGroup = props.groups[keyboardIndex.value];
        if (targetGroup) {
            emit('select-group', targetGroup.id);
        }
        return;
    }

    if (event.key === 'Enter') {
        if (!isSearchFocused && isTypingTarget(event.target)) {
            return;
        }

        event.preventDefault();
        const targetIndex =
            keyboardIndex.value >= 0 ? keyboardIndex.value : 0;
        const targetGroup = props.groups[targetIndex];
        if (targetGroup) {
            emit('select-group', targetGroup.id);
        }
        return;
    }

    if (event.altKey && !event.metaKey && !event.ctrlKey) {
        const jumpNumber = Number(event.key);
        if (!Number.isInteger(jumpNumber) || jumpNumber < 1 || jumpNumber > 9) {
            return;
        }

        const jumpIndex = jumpNumber - 1;
        if (jumpIndex >= props.groups.length) {
            return;
        }

        event.preventDefault();
        keyboardIndex.value = jumpIndex;
        const targetGroup = props.groups[jumpIndex];
        if (targetGroup) {
            emit('select-group', targetGroup.id);
        }
    }
};

watch(
    () => props.groups,
    (nextGroups) => {
        if (nextGroups.length === 0) {
            keyboardIndex.value = -1;
            return;
        }

        const selectedIndex = nextGroups.findIndex(
            (group) => group.id === props.selectedGroupId,
        );
        keyboardIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
    },
    { immediate: true },
);

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <aside class="flex h-full w-full flex-col border-r border-border bg-card md:w-80">
        <div class="border-b border-border p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-muted p-2 text-muted-foreground">
                        <MessageCircle class="h-4 w-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Group Chats</h2>
                        <p class="text-xs text-muted-foreground">{{ groups.length }} active groups</p>
                    </div>
                </div>
                <Button
                    v-if="isTeacher"
                    size="sm"
                    @click="emit('create-group')"
                >
                    <Plus class="h-3.5 w-3.5" />
                    New
                </Button>
            </div>
            <div class="mt-3 flex items-center gap-1 rounded-md border border-border bg-muted/40 p-1">
                <button
                    type="button"
                    class="rounded px-2 py-1 text-[11px] font-medium transition"
                    :class="activeFilter === 'all' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="emit('set-filter', 'all')"
                >
                    All ({{ totalCount }})
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 text-[11px] font-medium transition"
                    :class="activeFilter === 'unmuted' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="emit('set-filter', 'unmuted')"
                >
                    Unmuted ({{ unmutedCount }})
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 text-[11px] font-medium transition"
                    :class="activeFilter === 'muted' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="emit('set-filter', 'muted')"
                >
                    Muted ({{ mutedCount }})
                </button>
            </div>
            <div class="mt-2">
                <input
                    ref="searchInput"
                    :value="searchTerm"
                    type="text"
                    placeholder="Search groups..."
                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs text-foreground outline-none focus:border-ring"
                    @input="emit('set-search', ($event.target as HTMLInputElement).value)"
                />
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-2">
            <button
                v-for="(group, groupIndex) in groups"
                :key="group.id"
                type="button"
                class="mb-2 w-full text-left"
                :class="
                    selectedGroupId === group.id
                        ? 'ring-2 ring-primary/40'
                        : ''
                "
                @click="emit('select-group', group.id)"
            >
                <Card class="p-3 transition hover:bg-accent/40">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold text-foreground">{{ group.name }}</p>
                        <div class="flex items-center gap-1">
                            <span
                                v-if="groupIndex < 9"
                                class="rounded border border-border bg-muted px-1 py-0.5 text-[10px] text-muted-foreground"
                                :title="`Alt+${groupIndex + 1}`"
                            >
                                {{ groupIndex + 1 }}
                            </span>
                            <span v-if="group.previewTime" class="text-[10px] text-muted-foreground">
                                {{ group.previewTime }}
                            </span>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded text-muted-foreground transition hover:bg-accent hover:text-foreground"
                                        :title="group.isMuted ? 'Mute options' : 'Mute group'"
                                        @click.stop
                                    >
                                        <BellOff v-if="group.isMuted" class="h-3.5 w-3.5" />
                                        <Bell v-else class="h-3.5 w-3.5" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44" @click.stop>
                                    <DropdownMenuItem
                                        v-if="group.isMuted"
                                        @click="emit('set-mute', { groupId: group.id, duration: 'off' })"
                                    >
                                        Unmute
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else
                                        @click="emit('set-mute', { groupId: group.id, duration: '1h' })"
                                    >
                                        Mute for 1 hour
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="!group.isMuted"
                                        @click="emit('set-mute', { groupId: group.id, duration: '8h' })"
                                    >
                                        Mute for 8 hours
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="!group.isMuted"
                                        @click="emit('set-mute', { groupId: group.id, duration: '24h' })"
                                    >
                                        Mute for 24 hours
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="!group.isMuted"
                                        @click="emit('set-mute', { groupId: group.id, duration: 'forever' })"
                                    >
                                        Mute until turned off
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Badge
                                v-if="(group.unreadCount ?? 0) > 0 && !group.isMuted"
                                class="h-5 min-w-5 rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground"
                            >
                                {{ group.unreadCount && group.unreadCount > 9 ? '9+' : group.unreadCount }}
                            </Badge>
                            <Badge variant="secondary" class="text-[10px]">
                                {{ group.messages.length }}
                            </Badge>
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] uppercase tracking-wide text-muted-foreground">
                        {{ group.section }} · {{ group.course }}
                    </p>
                    <p
                        v-if="group.isMuted"
                        class="mt-1 text-[10px] font-medium text-amber-600 dark:text-amber-300"
                    >
                        {{ group.mutedLabel || 'Muted' }}
                    </p>
                    <div class="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                        <Users class="h-3 w-3" />
                        <span>Created by {{ group.createdBy }}</span>
                        <span
                            v-if="(group.onlineCount ?? 0) > 0"
                            class="ml-auto inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] text-emerald-700 dark:text-emerald-300"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            {{ group.onlineCount }} online
                        </span>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <p class="line-clamp-1 text-xs text-muted-foreground">
                            {{ group.previewText || 'No messages yet' }}
                        </p>
                        <span
                            v-if="group.previewStatus"
                            class="shrink-0 rounded-full border border-primary/30 bg-primary/10 px-1.5 py-0.5 text-[10px] text-primary"
                        >
                            {{ group.previewStatus }}
                        </span>
                    </div>
                    <p
                        v-if="(group.typingCount ?? 0) > 0"
                        class="mt-1 line-clamp-1 text-[11px] italic text-emerald-600 dark:text-emerald-300"
                    >
                        {{ group.typingPreview }}
                        {{ (group.typingCount ?? 0) > 1 ? 'are typing...' : 'is typing...' }}
                    </p>
                </Card>
            </button>
        </div>
    </aside>
</template>
