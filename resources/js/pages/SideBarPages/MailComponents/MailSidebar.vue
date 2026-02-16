<script setup lang="ts">
import { 
    Inbox, Star, Clock, Send, File, Trash2, Tag, Archive, PenSquare
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { MailFolder, MailFolderName } from '../MailModules/composables/types';

type Folder = MailFolder;

defineProps<{
    folders: Folder[];
}>()

const emit = defineEmits<{
    (e: 'select-folder', folderName: MailFolderName): void;
    (e: 'compose'): void;
}>();

const folderIcons: Record<MailFolderName, any> = {
    Inbox,
    Starred: Star,
    Snoozed: Clock,
    Sent: Send,
    Drafts: File,
    Spam: Tag,
    Archived: Archive,
    Trash: Trash2,
};

const handleFolderClick = (folderName: MailFolderName) => {
    emit('select-folder', folderName);
};
</script>

<template>
    <aside class="w-full border-b border-border bg-card/75 p-3 backdrop-blur-sm md:w-72 md:border-b-0 md:border-r">
        <div class="mb-4 space-y-2">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Workspace</p>
            <Button type="button" class="w-full justify-start gap-2 rounded-xl font-semibold shadow-sm" @click="emit('compose')">
                <PenSquare class="h-4 w-4" />
                New Message
            </Button>
        </div>

        <div class="mb-2 flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Folders</p>
            <Badge variant="outline" class="rounded-full px-2 py-0.5 text-[10px]">
                {{ folders.length }}
            </Badge>
        </div>

        <nav class="grid gap-1 md:block md:space-y-1">
            <button
                v-for="folder in folders"
                :key="folder.name"
                type="button"
                class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left transition-all duration-200"
                :class="folder.active ? 'bg-primary text-primary-foreground shadow-sm ring-1 ring-primary/25' : 'text-muted-foreground hover:bg-accent/80 hover:text-foreground'"
                @click="handleFolderClick(folder.name)"
            >
                <div class="flex items-center gap-3">
                    <component :is="folderIcons[folder.name]" class="h-4 w-4" />
                    <span class="text-sm font-medium">{{ folder.name }}</span>
                </div>
                <Badge
                    v-if="folder.count > 0"
                    :variant="folder.active ? 'secondary' : 'outline'"
                    class="rounded-full px-2 py-0 text-[10px]"
                >
                    {{ folder.count }}
                </Badge>
            </button>
        </nav>
    </aside>
</template>
