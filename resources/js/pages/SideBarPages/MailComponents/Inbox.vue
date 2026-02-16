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

// Mock Email Data
const emails = ref([
    { id: 1, sender: 'Google Security', subject: 'Security alert for your account', snippet: 'A new sign-in was detected on a Linux device...', time: '10:24 AM', unread: true, starred: false },
    { id: 2, sender: 'GitHub', subject: '[GitHub] A personal access token has expired', snippet: 'Hi there, your token "Dev-Access" expired today...', time: '9:15 AM', unread: false, starred: true },
    { id: 3, sender: 'Dribbble', subject: 'Top design shots this week', snippet: 'Check out the most popular shots from the community...', time: 'Yesterday', unread: true, starred: false },
    { id: 4, sender: 'Figma', subject: 'New comment in "Dashboard Design"', snippet: 'Sarah left a comment: "Can we change the blue to..."', time: 'Feb 10', unread: false, starred: false },
]);

const folders = [
    { name: 'Inbox', icon: Inbox, count: 12, active: true },
    { name: 'Starred', icon: Star, count: 0, active: false },
    { name: 'Snoozed', icon: Clock, count: 0, active: false },
    { name: 'Sent', icon: Send, count: 0, active: false },
    { name: 'Drafts', icon: File, count: 2, active: false },
    { name: 'Spam', icon: Tag, count: 0, active: false },
    { name: 'Trash', icon: Trash2, count: 0, active: false },
];
</script>

<template>
    <Head title="Inbox - Mail" />

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
