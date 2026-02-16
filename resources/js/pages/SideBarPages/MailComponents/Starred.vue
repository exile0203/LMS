<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { 
    Inbox, Star, Clock, Send, File, Trash2, Tag 
} from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import EmailList from './EmailList.vue';
import EmailToolbar from './EmailToolbar.vue';
import MailSidebar from './MailSidebar.vue';

// Mock Email Data for Starred
const emails = ref([
    { id: 2, sender: 'GitHub', subject: '[GitHub] A personal access token has expired', snippet: 'Hi there, your token "Dev-Access" expired today...', time: '9:15 AM', unread: false, starred: true },
]);

const folders = [
    { name: 'Inbox', icon: Inbox, count: 12, active: false },
    { name: 'Starred', icon: Star, count: 1, active: true },
    { name: 'Snoozed', icon: Clock, count: 0, active: false },
    { name: 'Sent', icon: Send, count: 0, active: false },
    { name: 'Drafts', icon: File, count: 2, active: false },
    { name: 'Spam', icon: Tag, count: 0, active: false },
    { name: 'Trash', icon: Trash2, count: 0, active: false },
];
</script>

<template>
    <Head title="Starred - Mail" />

    <AppLayout>
        <div class="flex h-[calc(100vh-64px)] bg-background overflow-hidden">
            <MailSidebar :folders="folders" />

            <main class="flex-1 flex flex-col bg-background">
                <EmailToolbar />
                <EmailList :emails="emails" />
            </main>
        </div>
    </AppLayout>
</template>
