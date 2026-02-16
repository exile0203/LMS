<script setup lang="ts">
import { Square, Star, Archive, Trash2, MailOpen, Clock } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

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
    hasMoreEmails: boolean;
    isLoadingMoreEmails: boolean;
}>()

const emit = defineEmits<{
    'toggle-star': [emailId: number]
    'delete-email': [emailId: number]
    'archive-email': [emailId: number]
    'mark-as-read': [emailId: number]
    'snooze-email': [emailId: number]
    'load-more-emails': []
}>()
</script>

<template>
    <div class="flex-1 overflow-y-auto bg-gradient-to-b from-background/40 to-background">
        <div
            v-for="email in emails"
            :key="email.id"
            class="group relative mx-3 my-2 grid cursor-pointer grid-cols-[auto_1fr_auto] gap-3 rounded-xl border border-border/70 bg-card/85 px-3 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:bg-card md:grid-cols-[auto_180px_1fr_auto]"
            :class="email.unread ? 'border-primary/40 ring-1 ring-primary/20' : ''"
        >
            <div class="flex shrink-0 items-center gap-2">
                <Square class="h-4 w-4 text-muted-foreground group-hover:text-foreground" />
                <span v-if="email.unread" class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    @click.stop="emit('toggle-star', email.id)"
                >
                    <Star
                        class="h-4 w-4"
                        :class="email.starred ? 'fill-yellow-400 text-yellow-400' : 'text-muted-foreground'"
                    />
                </Button>
            </div>

            <div class="truncate text-sm font-medium text-foreground">
                {{ email.sender }}
            </div>

            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ email.subject }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ email.snippet }}</p>
            </div>

            <div class="flex items-center gap-2 justify-self-end">
                <div class="hidden text-right text-[11px] text-muted-foreground md:block">
                    {{ email.time }}
                </div>

                <div class="flex items-center gap-1 opacity-100 md:opacity-0 md:transition-opacity md:group-hover:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Archive"
                        @click.stop="emit('archive-email', email.id)"
                    >
                        <Archive class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Delete"
                        @click.stop="emit('delete-email', email.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Mark as read"
                        @click.stop="emit('mark-as-read', email.id)"
                    >
                        <MailOpen class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Snooze"
                        @click.stop="emit('snooze-email', email.id)"
                    >
                        <Clock class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="emails.length === 0" class="mx-3 my-6 flex min-h-64 items-center justify-center rounded-xl border border-dashed border-border bg-card/60 text-muted-foreground">
            <p class="text-sm">No emails in this folder yet.</p>
        </div>

        <div v-if="hasMoreEmails && emails.length > 0" class="flex justify-center p-4">
            <Button
                type="button"
                variant="outline"
                class="min-w-36 rounded-full"
                :disabled="isLoadingMoreEmails"
                @click="emit('load-more-emails')"
            >
                {{ isLoadingMoreEmails ? 'Loading...' : 'Load more emails' }}
            </Button>
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
