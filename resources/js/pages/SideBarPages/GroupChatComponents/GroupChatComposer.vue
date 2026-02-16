<script setup lang="ts">
import {
    CornerUpLeft,
    FileUp,
    ImagePlus,
    Link as LinkIcon,
    Send,
    Smile,
    Sticker,
    WandSparkles,
    X,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ChatMessage, NewMessagePayload } from './types';

type Props = {
    disabled: boolean;
    isTeacher?: boolean;
    replyTo?: ChatMessage | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'send', payload: NewMessagePayload): void;
    (e: 'typing', value: boolean): void;
    (e: 'clear-reply'): void;
}>();

const text = ref('');
const selectedQuiz = ref('');
const linkText = ref('');
const scheduleAt = ref('');

const isEmojiPickerOpen = ref(false);
const isGifPickerOpen = ref(false);
const isStickerPickerOpen = ref(false);

const emojiQuery = ref('');
const gifQuery = ref('');
const stickerQuery = ref('');

const quickQuizzes = [
    'Quiz: Math Set A',
    'Quiz: Science Set B',
    'Quiz: Reading Comprehension Set C',
];

const emojiCatalog = [
    '😀', '😃', '😄', '😁', '😆', '😂', '🤣', '🙂', '😉', '😊', '😍', '🥰', '😘', '😋', '😎',
    '🤩', '🥳', '😏', '🙃', '🤗', '🤔', '🫡', '🤭', '🤫', '🤐', '😶', '😐', '😑', '😬', '🙄',
    '😌', '😴', '🤤', '😪', '😵', '🥴', '🤯', '🤠', '🥶', '🥵', '😡', '😱', '😭', '😇', '🤓',
    '💯', '🔥', '✨', '💥', '🎉', '🎊', '🚀', '⭐', '🌟', '💡', '✅', '❌', '⚡', '📌', '📣',
    '👍', '👎', '👏', '🙌', '🤝', '🙏', '💪', '👌', '✌️', '🤟', '🫶', '👀', '🧠', '🫡', '💜',
    '❤️', '🩵', '💙', '🖤', '💚', '💛', '🧡', '🤍', '🤎', '💔', '💞', '💕', '💓', '💗', '💖',
    '📚', '📝', '🎓', '🏫', '🧪', '🔬', '🧮', '💻', '⌛', '⏰', '📅', '📎', '📁', '🗂️', '📤',
];

const gifOptions = [
    { id: 'g1', label: 'clap', url: 'https://media.giphy.com/media/26u4cqiYI30juCOGY/giphy.gif' },
    { id: 'g2', label: 'wow', url: 'https://media.giphy.com/media/l0HlHFRbmaZtBRhXG/giphy.gif' },
    { id: 'g3', label: 'hello', url: 'https://media.giphy.com/media/ASd0Ukj0y3qMM/giphy.gif' },
    { id: 'g4', label: 'thumbs up', url: 'https://media.giphy.com/media/111ebonMs90YLu/giphy.gif' },
    { id: 'g5', label: 'great', url: 'https://media.giphy.com/media/5GoVLqeAOo6PK/giphy.gif' },
    { id: 'g6', label: 'party', url: 'https://media.giphy.com/media/g9582DNuQppxC/giphy.gif' },
    { id: 'g7', label: 'mind blown', url: 'https://media.giphy.com/media/xT0xeJpnrWC4XWblEk/giphy.gif' },
    { id: 'g8', label: 'typing', url: 'https://media.giphy.com/media/LmNwrBhejkK9EFP504/giphy.gif' },
    { id: 'g9', label: 'study', url: 'https://media.giphy.com/media/3oriO0OEd9QIDdllqo/giphy.gif' },
    { id: 'g10', label: 'yes', url: 'https://media.giphy.com/media/3o6UB3VhArvomJHtdK/giphy.gif' },
    { id: 'g11', label: 'laugh', url: 'https://media.giphy.com/media/12msOFU8oL1eww/giphy.gif' },
    { id: 'g12', label: 'cool', url: 'https://media.giphy.com/media/3og0IPxMM0erATueVW/giphy.gif' },
    { id: 'g13', label: 'nice', url: 'https://media.giphy.com/media/xT9IgG50Fb7Mi0prBC/giphy.gif' },
    { id: 'g14', label: 'good job', url: 'https://media.giphy.com/media/xUPGcguWZHRC2HyBRS/giphy.gif' },
    { id: 'g15', label: 'happy', url: 'https://media.giphy.com/media/5xaOcLGvzHxDKjufnLW/giphy.gif' },
    { id: 'g16', label: 'dance', url: 'https://media.giphy.com/media/l3vRlT2k2L35Cnn5C/giphy.gif' },
    { id: 'g17', label: 'ok', url: 'https://media.giphy.com/media/xT5LMHxhOfscxPfIfm/giphy.gif' },
    { id: 'g18', label: 'wink', url: 'https://media.giphy.com/media/3oEjI6SIIHBdRxXI40/giphy.gif' },
    { id: 'g19', label: 'love', url: 'https://media.giphy.com/media/3oriO6qJiXajN0TyDu/giphy.gif' },
    { id: 'g20', label: 'applause', url: 'https://media.giphy.com/media/5VKbvrjxpVJCM/giphy.gif' },
];

const stickerOptions = [
    { id: 's1', label: 'smile', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f600.svg' },
    { id: 's2', label: 'heart eyes', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f60d.svg' },
    { id: 's3', label: 'laugh', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f606.svg' },
    { id: 's4', label: 'wink', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f609.svg' },
    { id: 's5', label: 'party', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f973.svg' },
    { id: 's6', label: 'fire', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f525.svg' },
    { id: 's7', label: 'rocket', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f680.svg' },
    { id: 's8', label: 'idea', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f4a1.svg' },
    { id: 's9', label: 'thumbs up', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f44d.svg' },
    { id: 's10', label: 'clap', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f44f.svg' },
    { id: 's11', label: 'muscle', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f4aa.svg' },
    { id: 's12', label: 'check', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/2705.svg' },
    { id: 's13', label: 'books', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f4da.svg' },
    { id: 's14', label: 'computer', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f4bb.svg' },
    { id: 's15', label: 'graduation', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f393.svg' },
    { id: 's16', label: 'star', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/2b50.svg' },
    { id: 's17', label: 'sparkles', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/2728.svg' },
    { id: 's18', label: 'target', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f3af.svg' },
    { id: 's19', label: 'warning', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/26a0.svg' },
    { id: 's20', label: 'heart', url: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/2764.svg' },
];

const filteredEmojis = computed(() => {
    const query = emojiQuery.value.trim().toLowerCase();
    if (!query) return emojiCatalog;

    return emojiCatalog.filter((emoji) => {
        if (query === 'heart') return ['❤️', '💜', '🩵', '💙', '💚', '💛', '🧡', '🤍', '🤎', '🖤'].includes(emoji);
        if (query === 'fire') return ['🔥', '💥', '⚡'].includes(emoji);
        if (query === 'school' || query === 'study') return ['📚', '📝', '🎓', '🏫', '🧪', '🔬', '🧮', '💻'].includes(emoji);
        return false;
    });
});

const filteredGifs = computed(() => {
    const query = gifQuery.value.trim().toLowerCase();
    if (!query) return gifOptions;
    return gifOptions.filter((gif) => gif.label.includes(query));
});

const filteredStickers = computed(() => {
    const query = stickerQuery.value.trim().toLowerCase();
    if (!query) return stickerOptions;
    return stickerOptions.filter((sticker) => sticker.label.includes(query));
});

const closePickers = () => {
    isEmojiPickerOpen.value = false;
    isGifPickerOpen.value = false;
    isStickerPickerOpen.value = false;
};

const toggleEmojiPicker = () => {
    const next = !isEmojiPickerOpen.value;
    closePickers();
    isEmojiPickerOpen.value = next;
};

const toggleGifPicker = () => {
    const next = !isGifPickerOpen.value;
    closePickers();
    isGifPickerOpen.value = next;
};

const toggleStickerPicker = () => {
    const next = !isStickerPickerOpen.value;
    closePickers();
    isStickerPickerOpen.value = next;
};

const formatBytes = (size: number) => {
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const sendText = () => {
    const body = text.value.trim();
    if (!body) return;
    emit('send', {
        kind: 'text',
        body,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('typing', false);
    emit('clear-reply');
    text.value = '';
};

const sendQuiz = () => {
    if (!props.isTeacher) return;
    const body = selectedQuiz.value.trim();
    if (!body) return;
    emit('send', {
        kind: 'quiz',
        body,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('clear-reply');
    selectedQuiz.value = '';
};

const sendLink = () => {
    const body = linkText.value.trim();
    if (!body) return;
    emit('send', {
        kind: 'link',
        body,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('clear-reply');
    linkText.value = '';
};

const sendEmoji = (emoji: string) => {
    emit('send', {
        kind: 'emoji',
        body: emoji,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('clear-reply');
};

const sendGif = (gifUrl: string) => {
    emit('send', {
        kind: 'gif',
        body: gifUrl,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('clear-reply');
    isGifPickerOpen.value = false;
};

const sendSticker = (stickerUrl: string) => {
    emit('send', {
        kind: 'sticker',
        body: stickerUrl,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('clear-reply');
    isStickerPickerOpen.value = false;
};

const onPickFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    emit('send', {
        kind: 'file',
        body: file.name,
        fileName: file.name,
        fileSize: formatBytes(file.size),
        file,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('clear-reply');

    input.value = '';
};

const onPickImage = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    emit('send', {
        kind: 'image',
        body: file.name,
        fileName: file.name,
        fileSize: formatBytes(file.size),
        file,
        scheduledFor: scheduleValue(),
        replyToMessageId: props.replyTo?.id ?? null,
    });
    emit('clear-reply');

    input.value = '';
};

let typingIdleTimeout: ReturnType<typeof setTimeout> | null = null;

watch(text, (value) => {
    const hasValue = value.trim().length > 0;
    emit('typing', hasValue);

    if (typingIdleTimeout) {
        clearTimeout(typingIdleTimeout);
        typingIdleTimeout = null;
    }

    if (hasValue) {
        typingIdleTimeout = setTimeout(() => {
            emit('typing', false);
        }, 2500);
    }
});

onUnmounted(() => {
    if (typingIdleTimeout) {
        clearTimeout(typingIdleTimeout);
    }
    emit('typing', false);
});

const scheduleValue = (): string | null => {
    if (!props.isTeacher) {
        return null;
    }

    const value = scheduleAt.value.trim();
    if (!value) {
        return null;
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.toISOString();
};
</script>

<template>
    <footer class="relative shrink-0 border-t border-border bg-card p-3 md:p-4">
        <div
            v-if="props.replyTo"
            class="mb-3 flex items-start justify-between gap-2 rounded-lg border border-border bg-muted/70 px-3 py-2"
        >
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Replying to {{ props.replyTo.senderName }}</p>
                <p class="line-clamp-1 text-xs text-foreground">{{ props.replyTo.body }}</p>
            </div>
            <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-[10px]" @click="emit('clear-reply')">
                <CornerUpLeft class="h-3 w-3" />
                Cancel
            </Button>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <label class="cursor-pointer">
                <span class="inline-flex items-center gap-1">
                    <Button type="button" variant="outline" size="sm" as-child>
                        <span><FileUp class="h-3.5 w-3.5" /> File</span>
                    </Button>
                </span>
                <input type="file" class="hidden" :disabled="disabled" @change="onPickFile" />
            </label>

            <label class="cursor-pointer">
                <span class="inline-flex items-center gap-1">
                    <Button type="button" variant="outline" size="sm" as-child>
                        <span><ImagePlus class="h-3.5 w-3.5" /> Image</span>
                    </Button>
                </span>
                <input type="file" accept="image/*" class="hidden" :disabled="disabled" @change="onPickImage" />
            </label>

            <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="toggleGifPicker">
                <WandSparkles class="h-3.5 w-3.5" />
                GIF
            </Button>

            <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="toggleStickerPicker">
                <Sticker class="h-3.5 w-3.5" />
                Sticker
            </Button>

            <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="toggleEmojiPicker">
                <Smile class="h-3.5 w-3.5" />
                Emoji
            </Button>
        </div>

        <div v-if="props.isTeacher" class="mb-2 flex flex-col gap-2 md:flex-row md:items-center">
            <label class="text-xs text-muted-foreground md:w-32">Schedule send</label>
            <Input
                v-model="scheduleAt"
                type="datetime-local"
                class="h-9 md:max-w-xs"
                :disabled="disabled"
            />
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="disabled || !scheduleAt"
                @click="scheduleAt = ''"
            >
                Clear
            </Button>
        </div>

        <div
            v-if="isGifPickerOpen"
            class="mb-3 rounded-xl border border-border bg-popover p-3 shadow-lg"
        >
            <div class="mb-2 flex items-center gap-2">
                <Input v-model="gifQuery" placeholder="Search GIF..." class="h-9" />
                <Button type="button" size="icon-sm" variant="ghost" @click="isGifPickerOpen = false">
                    <X class="h-4 w-4" />
                </Button>
            </div>
            <div class="grid max-h-44 grid-cols-2 gap-2 overflow-y-auto pr-1 md:grid-cols-4">
                <button
                    v-for="gif in filteredGifs"
                    :key="gif.id"
                    type="button"
                    class="overflow-hidden rounded-lg border border-border bg-background hover:border-primary/40"
                    @click="sendGif(gif.url)"
                >
                    <img :src="gif.url" :alt="gif.label" class="h-20 w-full object-cover" />
                </button>
            </div>
        </div>

        <div
            v-if="isStickerPickerOpen"
            class="mb-3 rounded-xl border border-border bg-popover p-3 shadow-lg"
        >
            <div class="mb-2 flex items-center gap-2">
                <Input v-model="stickerQuery" placeholder="Search sticker..." class="h-9" />
                <Button type="button" size="icon-sm" variant="ghost" @click="isStickerPickerOpen = false">
                    <X class="h-4 w-4" />
                </Button>
            </div>
            <div class="grid max-h-44 grid-cols-4 gap-2 overflow-y-auto pr-1 md:grid-cols-8">
                <button
                    v-for="sticker in filteredStickers"
                    :key="sticker.id"
                    type="button"
                    class="flex h-14 items-center justify-center rounded-lg border border-border bg-background p-2 hover:border-primary/40"
                    @click="sendSticker(sticker.url)"
                >
                    <img :src="sticker.url" :alt="sticker.label" class="h-10 w-10 object-contain" />
                </button>
            </div>
        </div>

        <div
            v-if="isEmojiPickerOpen"
            class="mb-3 rounded-xl border border-border bg-popover p-3 shadow-lg"
        >
            <div class="mb-2 flex items-center gap-2">
                <Input v-model="emojiQuery" placeholder="Search emoji by keyword (heart, fire, study)..." class="h-9" />
                <Button type="button" size="icon-sm" variant="ghost" @click="isEmojiPickerOpen = false">
                    <X class="h-4 w-4" />
                </Button>
            </div>
            <div class="grid max-h-44 grid-cols-8 gap-2 overflow-y-auto pr-1 md:grid-cols-12">
                <button
                    v-for="emoji in filteredEmojis"
                    :key="emoji"
                    type="button"
                    class="rounded-md border border-border bg-background px-1 py-1 text-xl hover:border-primary/40 hover:bg-accent"
                    @click="sendEmoji(emoji)"
                >
                    {{ emoji }}
                </button>
            </div>
        </div>

        <div v-if="props.isTeacher" class="mb-2 flex flex-col gap-2 md:flex-row">
            <select
                v-model="selectedQuiz"
                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-ring md:max-w-sm"
                :disabled="disabled"
            >
                <option value="">Paste quiz to chat...</option>
                <option v-for="quiz in quickQuizzes" :key="quiz" :value="quiz">
                    {{ quiz }}
                </option>
            </select>
            <Button
                type="button"
                variant="outline"
                :disabled="disabled || !selectedQuiz"
                @click="sendQuiz"
            >
                Share Quiz
            </Button>
        </div>

        <div class="mb-2 flex flex-col gap-2 md:flex-row">
            <div class="relative w-full">
                <LinkIcon class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="linkText"
                    type="url"
                    placeholder="Paste link..."
                    class="py-2 pl-9 pr-3"
                    :disabled="disabled"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="disabled || !linkText.trim()"
                @click="sendLink"
            >
                Send Link
            </Button>
        </div>

        <div class="flex items-end gap-2">
            <textarea
                v-model="text"
                rows="2"
                placeholder="Write a message..."
                class="w-full resize-none rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-ring"
                :disabled="disabled"
                @keydown.enter.prevent="sendText"
            />
            <Button
                type="button"
                class="h-10"
                :disabled="disabled || !text.trim()"
                @click="sendText"
            >
                <Send class="h-3.5 w-3.5" />
                Send
            </Button>
        </div>
    </footer>
</template>
