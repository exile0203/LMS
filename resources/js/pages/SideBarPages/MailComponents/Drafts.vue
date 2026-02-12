<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Inbox, Star, Clock, Send, File, Trash2, Tag 
} from 'lucide-vue-next';
import MailSidebar from './MailSidebar.vue';
import EmailToolbar from './EmailToolbar.vue';
import EmailList from './EmailList.vue';

// Empty emails for Drafts
const emails = ref([]);

const folders = [
    { name: 'Inbox', icon: Inbox, count: 12, active: false },
    { name: 'Starred', icon: Star, count: 0, active: false },
    { name: 'Snoozed', icon: Clock, count: 0, active: false },
    { name: 'Sent', icon: Send, count: 0, active: false },
    { name: 'Drafts', icon: File, count: 2, active: true },
    { name: 'Spam', icon: Tag, count: 0, active: false },
    { name: 'Trash', icon: Trash2, count: 0, active: false },
];
</script>

<template>
    <Head title="Drafts - Mail" />

    <AppLayout>
        <div class="flex h-[calc(100vh-64px)] bg-white overflow-hidden">
            <MailSidebar :folders="folders" />

            <main class="flex-1 flex flex-col bg-gray-50/30">
                <EmailToolbar />
                <EmailList :emails="emails" />
            </main>
        </div>
    </AppLayout>
</template>
