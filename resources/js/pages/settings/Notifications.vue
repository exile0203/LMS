<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type NotificationPreferences = {
    mail: boolean;
    group_chat: boolean;
    quiz: boolean;
    attendance: boolean;
    support: boolean;
    general: boolean;
};

const props = defineProps<{
    preferences: NotificationPreferences;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: '/settings/notifications',
    },
];

const form = useForm<NotificationPreferences>({
    mail: props.preferences.mail,
    group_chat: props.preferences.group_chat,
    quiz: props.preferences.quiz,
    attendance: props.preferences.attendance,
    support: props.preferences.support,
    general: props.preferences.general,
});

const resetForm = () => {
    form.mail = props.preferences.mail;
    form.group_chat = props.preferences.group_chat;
    form.quiz = props.preferences.quiz;
    form.attendance = props.preferences.attendance;
    form.support = props.preferences.support;
    form.general = props.preferences.general;
};

const save = () => {
    form.put('/settings/notifications', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notification settings" />

        <h1 class="sr-only">Notification Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Notification settings"
                    description="Control which alerts you receive across mail, quizzes, group chat, attendance, and support."
                />

                <Card class="border-border/80">
                    <CardHeader>
                        <CardTitle>Delivery Channels</CardTitle>
                        <CardDescription>
                            Turn off categories you do not want. You can still view related content in each page.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <label class="flex items-center justify-between rounded-lg border border-border/70 bg-card px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">Mail</p>
                                <p class="text-xs text-muted-foreground">New mail and sent confirmations</p>
                            </div>
                            <input v-model="form.mail" type="checkbox" class="h-4 w-4 rounded border-input text-primary" />
                        </label>

                        <label class="flex items-center justify-between rounded-lg border border-border/70 bg-card px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">Group Chat</p>
                                <p class="text-xs text-muted-foreground">New messages and new group announcements</p>
                            </div>
                            <input v-model="form.group_chat" type="checkbox" class="h-4 w-4 rounded border-input text-primary" />
                        </label>

                        <label class="flex items-center justify-between rounded-lg border border-border/70 bg-card px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">Quiz</p>
                                <p class="text-xs text-muted-foreground">Quiz published and quiz-related events</p>
                            </div>
                            <input v-model="form.quiz" type="checkbox" class="h-4 w-4 rounded border-input text-primary" />
                        </label>

                        <label class="flex items-center justify-between rounded-lg border border-border/70 bg-card px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">Attendance</p>
                                <p class="text-xs text-muted-foreground">Low attendance warnings and summaries</p>
                            </div>
                            <input v-model="form.attendance" type="checkbox" class="h-4 w-4 rounded border-input text-primary" />
                        </label>

                        <label class="flex items-center justify-between rounded-lg border border-border/70 bg-card px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">Support</p>
                                <p class="text-xs text-muted-foreground">Support chat and helpdesk updates</p>
                            </div>
                            <input v-model="form.support" type="checkbox" class="h-4 w-4 rounded border-input text-primary" />
                        </label>

                        <label class="flex items-center justify-between rounded-lg border border-border/70 bg-card px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">General</p>
                                <p class="text-xs text-muted-foreground">System-wide announcements</p>
                            </div>
                            <input v-model="form.general" type="checkbox" class="h-4 w-4 rounded border-input text-primary" />
                        </label>
                    </CardContent>
                </Card>

                <div class="flex items-center gap-3">
                    <Button :disabled="form.processing" @click="save">Save changes</Button>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="resetForm">Reset</Button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600">Saved.</span>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
