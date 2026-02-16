<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import EmailList from './MailComponents/EmailList.vue';
import EmailToolbar from './MailComponents/EmailToolbar.vue';
import MailSidebar from './MailComponents/MailSidebar.vue';
import { useMailPage, type MailPageProps } from './MailModules/composables/useMailPage';

const props = withDefaults(defineProps<MailPageProps>(), {
    emails: () => [],
    pagination: () => ({
        currentPage: 1,
        hasMorePages: false,
        nextPage: null,
    }),
});

const {
    currentFolder,
    currentEmails,
    activeFolders,
    isComposing,
    composeForm,
    flashSuccess,
    flashError,
    selectFolder,
    toggleStar,
    deleteEmail,
    archiveEmail,
    markAsRead,
    snoozeEmail,
    sendMail,
    hasMoreEmails,
    isLoadingMoreEmails,
    loadMoreEmails,
} = useMailPage(props);

const currentFolderCount = computed(() =>
    activeFolders.value.find((folder) => folder.name === currentFolder.value)?.count ?? 0,
);
const totalFolderCount = computed(() =>
    activeFolders.value
        .filter((folder) => folder.name !== 'Starred')
        .reduce((sum, folder) => sum + folder.count, 0),
);
</script>

<template>
    <Head :title="`${currentFolder} - Mail`" />

    <AppLayout>
        <div class="space-y-3 px-3 py-3 md:px-6">
            <div class="surface-panel animate-fade-in-up border-primary/15 bg-gradient-to-r from-card via-card to-primary/5 px-4 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Campus Mail</p>
                        <h1 class="mt-1 text-xl font-bold text-foreground md:text-2xl">{{ currentFolder }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Quickly process messages with folder actions and keyboard-friendly controls.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary" class="rounded-full px-3 py-1">
                            {{ currentFolderCount }} in folder
                        </Badge>
                        <Badge variant="outline" class="rounded-full px-3 py-1">
                            {{ totalFolderCount }} total
                        </Badge>
                    </div>
                </div>
            </div>

            <Alert v-if="flashSuccess" class="border-emerald-200 bg-emerald-50 text-emerald-700">
                <AlertDescription>{{ flashSuccess }}</AlertDescription>
            </Alert>
            <Alert v-if="flashError" variant="destructive">
                <AlertDescription>{{ flashError }}</AlertDescription>
            </Alert>

            <div class="surface-panel animate-fade-in-up flex h-[calc(100vh-220px)] min-h-[34rem] flex-col overflow-hidden md:h-[calc(100vh-210px)] md:flex-row">
                <MailSidebar
                    :folders="activeFolders"
                    @select-folder="selectFolder"
                    @compose="isComposing = true"
                />

                <main class="flex flex-1 flex-col bg-background/70">
                    <EmailToolbar
                        :current-folder="currentFolder"
                        :visible-count="currentEmails.length"
                        :total-count="totalFolderCount"
                    />
                    <EmailList
                        :emails="currentEmails"
                        :has-more-emails="hasMoreEmails"
                        :is-loading-more-emails="isLoadingMoreEmails"
                        @toggle-star="toggleStar"
                        @delete-email="deleteEmail"
                        @archive-email="archiveEmail"
                        @mark-as-read="markAsRead"
                        @snooze-email="snoozeEmail"
                        @load-more-emails="loadMoreEmails"
                    />
                </main>
            </div>
        </div>

        <Dialog v-model:open="isComposing">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Compose Email</DialogTitle>
                    <DialogDescription>Write and send an email to another user.</DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div>
                        <Label class="mb-1 block text-xs uppercase tracking-wide">To</Label>
                        <Input v-model="composeForm.to" type="email" placeholder="Recipient email" />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs uppercase tracking-wide">Subject</Label>
                        <Input v-model="composeForm.subject" type="text" placeholder="Subject" />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs uppercase tracking-wide">Message</Label>
                        <textarea
                            v-model="composeForm.body"
                            rows="6"
                            placeholder="Message"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="isComposing = false">
                        Cancel
                    </Button>
                    <Button type="button" @click="sendMail">
                        Send
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
