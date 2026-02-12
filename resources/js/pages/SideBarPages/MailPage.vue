<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Inbox, Star, Clock, Send, File, Trash2, Tag 
} from 'lucide-vue-next';
import MailSidebar from './MailComponents/MailSidebar.vue';
import EmailToolbar from './MailComponents/EmailToolbar.vue';
import EmailList from './MailComponents/EmailList.vue';

// Mock Email Data
const allEmails = ref([
    { id: 1, sender: 'Google Security', subject: 'Security alert for your account', snippet: 'A new sign-in was detected on a Linux device...', time: '10:24 AM', unread: true, starred: false, folder: 'Inbox' },
    { id: 2, sender: 'GitHub', subject: '[GitHub] A personal access token has expired', snippet: 'Hi there, your token "Dev-Access" expired today...', time: '9:15 AM', unread: false, starred: true, folder: 'Inbox' },
    { id: 3, sender: 'Dribbble', subject: 'Top design shots this week', snippet: 'Check out the most popular shots from the community...', time: 'Yesterday', unread: true, starred: false, folder: 'Inbox' },
    { id: 4, sender: 'Figma', subject: 'New comment in "Dashboard Design"', snippet: 'Sarah left a comment: "Can we change the blue to..."', time: 'Feb 10', unread: false, starred: false, folder: 'Inbox' },
]);

const currentFolder = ref('Inbox');

const folders = ref([
    { name: 'Inbox', icon: Inbox, count: 4, active: true },
    { name: 'Starred', icon: Star, count: 1, active: false },
    { name: 'Snoozed', icon: Clock, count: 0, active: false },
    { name: 'Sent', icon: Send, count: 0, active: false },
    { name: 'Drafts', icon: File, count: 2, active: false },
    { name: 'Spam', icon: Tag, count: 0, active: false },
    { name: 'Trash', icon: Trash2, count: 0, active: false },
]);

// Get emails for current folder
const currentEmails = computed(() => {
    if (currentFolder.value === 'Starred') {
        return allEmails.value.filter(email => email.starred);
    }
    if (currentFolder.value === 'Trash') {
        return allEmails.value.filter(email => email.folder === 'Trash');
    }
    if (currentFolder.value === 'Inbox') {
        return allEmails.value.filter(email => email.folder === 'Inbox');
    }
    return []; // Empty for Sent, Drafts, Spam, Snoozed
});

// Update active folder styling
const activeFolders = computed(() => {
    return folders.value.map(folder => ({
        ...folder,
        active: folder.name === currentFolder.value,
        count: folder.name === 'Starred' ? allEmails.value.filter(e => e.starred).length : folder.count,
    }));
});

const selectFolder = (folderName: string) => {
    currentFolder.value = folderName;
};

const toggleStar = (emailId: number) => {
    const email = allEmails.value.find(e => e.id === emailId);
    if (email) {
        email.starred = !email.starred;
    }
};

const deleteEmail = (emailId: number) => {
    const email = allEmails.value.find(e => e.id === emailId);
    if (email) {
        email.folder = 'Trash';
    }
};

const archiveEmail = (emailId: number) => {
    allEmails.value = allEmails.value.filter(e => e.id !== emailId);
};

const markAsRead = (emailId: number) => {
    const email = allEmails.value.find(e => e.id === emailId);
    if (email) {
        email.unread = false;
    }
};
</script>

<template>
    <Head :title="`${currentFolder} - Mail`" />

    <AppLayout>
        <div class="flex h-[calc(100vh-64px)] bg-white overflow-hidden">
            <MailSidebar :folders="activeFolders" @select-folder="selectFolder" />

            <main class="flex-1 flex flex-col bg-gray-50/30">
                <EmailToolbar />
                <EmailList 
                    :emails="currentEmails" 
                    @toggle-star="toggleStar"
                    @delete-email="deleteEmail"
                    @archive-email="archiveEmail"
                    @mark-as-read="markAsRead"
                />
            </main>
        </div>
    </AppLayout>
</template>