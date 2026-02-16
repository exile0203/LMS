import type { ComputedRef, Ref } from 'vue';
import type { ChatGroup, ChatMessage, NewMessagePayload } from '../../GroupChatComponents/types';

type MessageActionsDeps = {
    jsonHeaders: ComputedRef<Record<string, string>>;
    selectedGroup: ComputedRef<ChatGroup | null>;
    isTeacher: ComputedRef<boolean>;
    isPostingMessage: Ref<boolean>;
    runtimeError: Ref<string>;
    runtimeInfo: Ref<string>;
    typingUsers: Ref<string[]>;
    replyTarget: Ref<ChatMessage | null>;
    groupsState: Ref<ChatGroup[]>;
    replaceGroupMessages: (groupId: number, messages: ChatGroup['messages']) => void;
    replaceMessageInGroup: (groupId: number, updatedMessage: ChatMessage) => void;
    setTypingStatus: (isTyping: boolean) => Promise<void>;
};

export function useGroupChatMessageActions({
    jsonHeaders,
    selectedGroup,
    isTeacher,
    isPostingMessage,
    runtimeError,
    runtimeInfo,
    typingUsers,
    replyTarget,
    groupsState,
    replaceGroupMessages,
    replaceMessageInGroup,
    setTypingStatus,
}: MessageActionsDeps) {
    const sendMessage = async (payload: NewMessagePayload) => {
        if (!selectedGroup.value) return;
        if (payload.kind === 'quiz' && !isTeacher.value) {
            runtimeError.value = 'Only teachers can share quizzes in group chat.';
            return;
        }
        isPostingMessage.value = true;
        runtimeError.value = '';
        runtimeInfo.value = '';

        try {
            const endpoint = `/groupchat/groups/${selectedGroup.value.id}/messages`;
            let response: Response;

            if (payload.file) {
                const formData = new FormData();
                formData.append('kind', payload.kind);
                formData.append('body', payload.body);
                if (payload.fileName) formData.append('fileName', payload.fileName);
                if (payload.fileSize) formData.append('fileSize', payload.fileSize);
                if (payload.scheduledFor) formData.append('scheduledFor', payload.scheduledFor);
                if (payload.replyToMessageId) formData.append('replyToMessageId', String(payload.replyToMessageId));
                formData.append('file', payload.file);

                response = await fetch(endpoint, {
                    method: 'POST',
                    headers: jsonHeaders.value,
                    credentials: 'same-origin',
                    body: formData,
                });
            } else {
                response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        ...jsonHeaders.value,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        kind: payload.kind,
                        body: payload.body,
                        fileName: payload.fileName,
                        fileSize: payload.fileSize,
                        scheduledFor: payload.scheduledFor ?? null,
                        replyToMessageId: payload.replyToMessageId ?? null,
                    }),
                });
            }

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                runtimeError.value = data?.message || data?.error || 'Message failed to send.';
                return;
            }

            const data = await response.json();
            const message = data?.message;
            if (data?.scheduled) {
                runtimeInfo.value = `Message scheduled for ${new Date(data.scheduledFor).toLocaleString()}.`;
            }

            if (message) {
                replaceGroupMessages(selectedGroup.value.id, [
                    ...selectedGroup.value.messages,
                    message,
                ]);
                typingUsers.value = [];
                replyTarget.value = null;
            }
        } catch {
            runtimeError.value = 'Message failed to send.';
        } finally {
            isPostingMessage.value = false;
            void setTypingStatus(false);
        }
    };

    const reactToMessage = async (messageId: number, emoji: string) => {
        if (!selectedGroup.value) {
            return;
        }

        try {
            const response = await fetch(`/groupchat/messages/${messageId}/reactions`, {
                method: 'POST',
                headers: {
                    ...jsonHeaders.value,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ emoji }),
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (data?.message) {
                replaceMessageInGroup(selectedGroup.value.id, data.message);
            }
        } catch {
            // Best effort.
        }
    };

    const editMessage = async (messageId: number, body: string) => {
        if (!selectedGroup.value) {
            return;
        }

        try {
            const response = await fetch(`/groupchat/messages/${messageId}`, {
                method: 'PATCH',
                headers: {
                    ...jsonHeaders.value,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                runtimeError.value = data?.error || 'Unable to edit message.';
                return;
            }

            const data = await response.json();
            if (data?.message) {
                replaceMessageInGroup(selectedGroup.value.id, data.message);
            }
        } catch {
            runtimeError.value = 'Unable to edit message.';
        }
    };

    const deleteMessage = async (messageId: number) => {
        if (!selectedGroup.value) {
            return;
        }

        if (!window.confirm('Delete this message?')) {
            return;
        }

        try {
            const response = await fetch(`/groupchat/messages/${messageId}`, {
                method: 'DELETE',
                headers: jsonHeaders.value,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                runtimeError.value = data?.error || 'Unable to delete message.';
                return;
            }

            const data = await response.json();
            if (data?.message) {
                replaceMessageInGroup(selectedGroup.value.id, data.message);

                if (replyTarget.value?.id === messageId) {
                    replyTarget.value = null;
                }
            }
        } catch {
            runtimeError.value = 'Unable to delete message.';
        }
    };

    const pinMessage = async (messageId: number) => {
        if (!selectedGroup.value) {
            return;
        }

        try {
            const response = await fetch(`/groupchat/messages/${messageId}/pin`, {
                method: 'POST',
                headers: jsonHeaders.value,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                runtimeError.value = data?.error || 'Unable to pin/unpin message.';
                return;
            }

            const data = await response.json();
            if (data?.message) {
                replaceMessageInGroup(selectedGroup.value.id, data.message);
            }
        } catch {
            runtimeError.value = 'Unable to pin/unpin message.';
        }
    };

    const reportMessage = async (messageId: number) => {
        if (!selectedGroup.value) {
            return;
        }

        const reasonInput = window.prompt('Report reason (optional):', '');
        if (reasonInput === null) {
            return;
        }

        try {
            const response = await fetch(`/groupchat/messages/${messageId}/report`, {
                method: 'POST',
                headers: {
                    ...jsonHeaders.value,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ reason: reasonInput.trim() || null }),
            });

            const data = await response.json().catch(() => null);
            if (!response.ok) {
                runtimeError.value = data?.error || 'Unable to report message.';
                return;
            }

            runtimeError.value = '';
            runtimeInfo.value = data?.message || 'Message reported.';
        } catch {
            runtimeError.value = 'Unable to report message.';
        }
    };

    const setGroupMute = async (
        groupId: number,
        duration: 'off' | '1h' | '8h' | '24h' | 'forever' = 'forever',
    ) => {
        try {
            const response = await fetch(`/groupchat/groups/${groupId}/mute`, {
                method: 'POST',
                headers: {
                    ...jsonHeaders.value,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ duration }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                runtimeError.value = data?.error || 'Unable to update mute setting.';
                return;
            }

            const data = await response.json();
            const nextIsMuted = !!data?.isMuted;
            const mutedUntilAt = data?.mutedUntilAt ?? null;

            groupsState.value = groupsState.value.map((group) =>
                group.id === groupId
                    ? { ...group, isMuted: nextIsMuted, mutedUntilAt }
                    : group,
            );
        } catch {
            runtimeError.value = 'Unable to update mute setting.';
        }
    };

    return {
        sendMessage,
        reactToMessage,
        editMessage,
        deleteMessage,
        pinMessage,
        reportMessage,
        setGroupMute,
    };
}

