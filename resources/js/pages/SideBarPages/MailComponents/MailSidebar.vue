<script setup lang="ts">
import { 
    Inbox, Star, Clock, Send, File, Trash2, Tag 
} from 'lucide-vue-next';

interface Folder {
    name: string;
    icon: any;
    count: number;
    active: boolean;
}

defineProps<{
    folders: Folder[];
}>()

const emit = defineEmits(['select-folder']);

const folderIcons: Record<string, any> = {
    Inbox,
    Starred: Star,
    Snoozed: Clock,
    Sent: Send,
    Drafts: File,
    Spam: Tag,
    Trash: Trash2,
};

const handleFolderClick = (folderName: string) => {
    emit('select-folder', folderName);
};
</script>

<template>
    <aside class="w-64 flex flex-col border-r border-gray-100 p-2 space-y-0.5">
        <button class="flex items-center gap-4 bg-blue-100 text-blue-700 px-6 py-4 rounded-2xl mb-4 hover:shadow-md transition-shadow">
            <span class="font-semibold">Compose</span>
        </button>

        <nav
            v-for="folder in folders"
            :key="folder.name"
            class="flex items-center justify-between px-4 py-2 rounded-full cursor-pointer transition-colors"
            :class="folder.active ? 'bg-blue-50 text-blue-700 font-bold' : 'hover:bg-gray-100 text-gray-600'"
            @click="handleFolderClick(folder.name)"
        >
            <div class="flex items-center gap-4">
                <component :is="folderIcons[folder.name]" class="w-5 h-5" />
                <span class="text-sm">{{ folder.name }}</span>
            </div>
            <span v-if="folder.count > 0" class="text-xs">{{ folder.count }}</span>
        </nav>
    </aside>
</template>
