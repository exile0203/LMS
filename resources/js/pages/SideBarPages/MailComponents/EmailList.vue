<script setup lang="ts">
import { Square, Star, Archive, Trash2, MailOpen, Clock } from 'lucide-vue-next';

interface Email {
    id: number;
    sender: string;
    subject: string;
    snippet: string;
    time: string;
    unread: boolean;
    starred: boolean;
    folder?: string;
}

defineProps<{
    emails: Email[];
}>()

const emit = defineEmits<{
    'toggle-star': [emailId: number]
    'delete-email': [emailId: number]
    'archive-email': [emailId: number]
    'mark-as-read': [emailId: number]
}>()
</script>

<template>
    <div class="flex-1 overflow-y-auto">
        <div
            v-for="email in emails"
            :key="email.id"
            class="group flex items-center px-4 py-2 border-b border-gray-100 bg-white hover:shadow-md hover:z-10 cursor-pointer relative"
            :class="{ 'font-bold bg-blue-50/30': email.unread }"
        >
            <div class="flex items-center gap-3 mr-4 shrink-0">
                <Square class="w-4 h-4 text-gray-300 group-hover:text-gray-400" />
                <button
                    class="p-0 hover:scale-110 transition-transform"
                    @click.stop="$emit('toggle-star', email.id)"
                >
                    <Star
                        class="w-4 h-4"
                        :class="email.starred ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'"
                    />
                </button>
            </div>

            <div class="w-48 text-sm truncate mr-4">
                {{ email.sender }}
            </div>

            <div class="flex-1 text-sm truncate flex gap-2">
                <span class="text-gray-900">{{ email.subject }}</span>
                <span class="text-gray-400 font-normal">- {{ email.snippet }}</span>
            </div>

            <div class="w-20 text-right text-xs text-gray-500 group-hover:hidden">
                {{ email.time }}
            </div>

            <div class="hidden group-hover:flex items-center gap-2 bg-white pl-4 ml-auto">
                <button 
                    class="p-2 hover:bg-gray-100 rounded-full transition-colors" 
                    title="Archive"
                    @click.stop="$emit('archive-email', email.id)"
                >
                    <Archive class="w-4 h-4" />
                </button>
                <button 
                    class="p-2 hover:bg-gray-100 rounded-full transition-colors" 
                    title="Delete"
                    @click.stop="$emit('delete-email', email.id)"
                >
                    <Trash2 class="w-4 h-4" />
                </button>
                <button 
                    class="p-2 hover:bg-gray-100 rounded-full transition-colors" 
                    title="Mark as read"
                    @click.stop="$emit('mark-as-read', email.id)"
                >
                    <MailOpen class="w-4 h-4" />
                </button>
                <button 
                    class="p-2 hover:bg-gray-100 rounded-full transition-colors" 
                    title="Snooze"
                    @click.stop
                >
                    <Clock class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div v-if="emails.length === 0" class="flex items-center justify-center h-full text-gray-500">
            <p class="text-lg">No emails in this folder</p>
        </div>
    </div>
</template>

<style scoped>
.truncate {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
