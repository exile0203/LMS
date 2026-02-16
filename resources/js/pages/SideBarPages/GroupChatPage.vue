<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import GroupChatComposer from './GroupChatComponents/GroupChatComposer.vue';
import GroupChatSidebar from './GroupChatComponents/GroupChatSidebar.vue';
import GroupChatThread from './GroupChatComponents/GroupChatThread.vue';
import { useGroupChatPage, type GroupChatPageProps } from './GroupChatModules/composables/useGroupChatPage';

const props = withDefaults(defineProps<GroupChatPageProps>(), {
    groups: () => [],
    sectionOptions: () => ['Section 1', 'Section 2', 'Section 3'],
    courseOptions: () => ['Mathematics', 'Science', 'English'],
});

const {
    showCreateGroupModal,
    showCommandPalette,
    isPostingMessage,
    isCreatingGroup,
    runtimeError,
    typingUsers,
    activeUsers,
    presenceUsers,
    chatFilter,
    sidebarSearch,
    commandQuery,
    commandInput,
    replyTarget,
    flashSuccess,
    flashError,
    runtimeInfo,
    currentUserName,
    currentUserId,
    isTeacher,
    hasStudentAssignment,
    filteredGroupsForSidebar,
    selectedGroupId,
    selectedGroup,
    totalGroupCount,
    mutedGroupCount,
    unmutedGroupCount,
    newGroup,
    filteredCommandActions,
    openCreateGroup,
    createGroup,
    sendMessage,
    reactToMessage,
    editMessage,
    deleteMessage,
    pinMessage,
    reportMessage,
    setTypingStatus,
    setGroupMute,
    runCommand,
} = useGroupChatPage(props);
</script>

<template>
    <Head title="Group Chat" />

    <AppLayout>
        <div class="h-[calc(100vh-64px)] overflow-hidden bg-background animate-fade-in-up">
            <Alert v-if="flashSuccess" class="mx-4 mt-3 border-emerald-200 bg-emerald-50 text-emerald-700">
                <AlertDescription>{{ flashSuccess }}</AlertDescription>
            </Alert>
            <Alert v-if="flashError" variant="destructive" class="mx-4 mt-3">
                <AlertDescription>{{ flashError }}</AlertDescription>
            </Alert>
            <div class="surface-panel grid h-full grid-cols-1 overflow-hidden md:grid-cols-[320px_1fr]">
                <GroupChatSidebar
                    :groups="filteredGroupsForSidebar"
                    :selected-group-id="selectedGroupId"
                    :is-teacher="isTeacher"
                    :active-filter="chatFilter"
                    :total-count="totalGroupCount"
                    :unmuted-count="unmutedGroupCount"
                    :muted-count="mutedGroupCount"
                    :search-term="sidebarSearch"
                    @select-group="selectedGroupId = $event"
                    @create-group="openCreateGroup"
                    @set-mute="setGroupMute($event.groupId, $event.duration)"
                    @set-filter="chatFilter = $event"
                    @set-search="sidebarSearch = $event"
                />

                <div class="flex h-full min-h-0 flex-col overflow-hidden">
                    <div
                        v-if="!isTeacher && !hasStudentAssignment"
                        class="border-b border-amber-500/30 bg-amber-500/10 px-4 py-2 text-xs text-amber-700 dark:text-amber-300"
                    >
                        Your student profile is missing section/course assignment.
                    </div>
                    <GroupChatThread
                        :group="selectedGroup"
                        :current-user-name="currentUserName"
                        :current-user-id="currentUserId"
                        :is-sending="isPostingMessage"
                        :typing-users="typingUsers"
                        :active-users="activeUsers"
                        :presence-users="presenceUsers"
                        @reply-message="replyTarget = $event"
                        @react-message="reactToMessage($event.messageId, $event.emoji)"
                        @edit-message="editMessage($event.messageId, $event.body)"
                        @delete-message="deleteMessage($event.messageId)"
                        @pin-message="pinMessage($event.messageId)"
                        @report-message="reportMessage($event.messageId)"
                    />
                    <GroupChatComposer
                        :disabled="!selectedGroup || isPostingMessage"
                        :is-teacher="isTeacher"
                        :reply-to="replyTarget"
                        @send="sendMessage"
                        @typing="setTypingStatus"
                        @clear-reply="replyTarget = null"
                    />
                    <p v-if="runtimeError" class="px-4 pb-3 text-xs text-destructive">{{ runtimeError }}</p>
                    <p v-else-if="runtimeInfo" class="px-4 pb-3 text-xs text-emerald-600">{{ runtimeInfo }}</p>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showCreateGroupModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create Group Chat</DialogTitle>
                    <DialogDescription>
                        Only teachers can create section group chats.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div>
                        <Label class="mb-1 block text-xs uppercase tracking-wide">Group Name</Label>
                        <Input
                            v-model="newGroup.name"
                            type="text"
                            placeholder="e.g. Section 1 Group Chat"
                        />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs uppercase tracking-wide">Section</Label>
                        <select
                            v-model="newGroup.section"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="section in props.sectionOptions" :key="section" :value="section">
                                {{ section }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs uppercase tracking-wide">Course</Label>
                        <select
                            v-model="newGroup.course"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="course in props.courseOptions" :key="course" :value="course">
                                {{ course }}
                            </option>
                        </select>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="showCreateGroupModal = false">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="!newGroup.name.trim() || isCreatingGroup"
                        @click="createGroup"
                    >
                        {{ isCreatingGroup ? 'Creating...' : 'Create Group' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showCommandPalette">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Command Palette</DialogTitle>
                    <DialogDescription>
                        Use Ctrl/Cmd + K to open. Type to search actions.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-3">
                    <input
                        ref="commandInput"
                        v-model="commandQuery"
                        type="text"
                        placeholder="Type an action..."
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus:border-ring"
                    />
                    <div class="max-h-80 space-y-1 overflow-y-auto rounded-md border border-border p-1">
                        <button
                            v-for="action in filteredCommandActions"
                            :key="action.key"
                            type="button"
                            class="w-full rounded-md px-3 py-2 text-left transition hover:bg-accent"
                            @click="runCommand(action)"
                        >
                            <p class="text-sm font-medium text-foreground">{{ action.label }}</p>
                            <p class="text-xs text-muted-foreground">{{ action.description }}</p>
                        </button>
                        <p
                            v-if="!filteredCommandActions.length"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            No matching commands.
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
