<script setup lang="ts">
import { ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Quiz, QuizQuestion } from './types';

type Props = {
    section: string;
    course: string;
    quizSet: string;
    maxAttempts?: number | null;
    scorePolicy: 'latest' | 'best';
};

const props = defineProps<Props>();

type BuilderQuestion = {
    id: number;
    prompt: string;
    choices: string[];
    correctChoiceIndex: number;
};

const emit = defineEmits<{
    (e: 'create-quiz', quiz: Quiz): void;
}>();

const title = ref('');
const description = ref('');
const error = ref('');
const nextQuestionId = ref(1);

const questions = ref<BuilderQuestion[]>([
    {
        id: nextQuestionId.value++,
        prompt: '',
        choices: ['', '', '', ''],
        correctChoiceIndex: 0,
    },
]);

const addQuestion = () => {
    questions.value.push({
        id: nextQuestionId.value++,
        prompt: '',
        choices: ['', '', '', ''],
        correctChoiceIndex: 0,
    });
};

const removeQuestion = (questionId: number) => {
    if (questions.value.length === 1) {
        return;
    }
    questions.value = questions.value.filter((question) => question.id !== questionId);
};

const validateQuestion = (question: BuilderQuestion) => {
    const hasPrompt = question.prompt.trim().length > 0;
    const hasEmptyChoice = question.choices.some((choice) => choice.trim().length === 0);
    return hasPrompt && !hasEmptyChoice;
};

const resetForm = () => {
    title.value = '';
    description.value = '';
    error.value = '';
    nextQuestionId.value = 1;
    questions.value = [
        {
            id: nextQuestionId.value++,
            prompt: '',
            choices: ['', '', '', ''],
            correctChoiceIndex: 0,
        },
    ];
};

const createQuiz = () => {
    error.value = '';

    if (!props.section || !props.course || !props.quizSet) {
        error.value = 'Please select section, course, and set first.';
        return;
    }

    if (title.value.trim().length === 0) {
        error.value = 'Quiz title is required.';
        return;
    }

    if (questions.value.some((question) => !validateQuestion(question))) {
        error.value = 'Each question needs a prompt and all four choices.';
        return;
    }

    const quizQuestions: QuizQuestion[] = questions.value.map((question) => ({
        id: Date.now() + question.id,
        prompt: question.prompt.trim(),
        choices: question.choices.map((choice) => choice.trim()),
        correctChoiceIndex: question.correctChoiceIndex,
    }));

    emit('create-quiz', {
        id: Date.now(),
        title: title.value.trim(),
        description: description.value.trim(),
        section: props.section,
        course: props.course,
        quizSet: props.quizSet,
        maxAttempts: props.maxAttempts ?? null,
        scorePolicy: props.scorePolicy,
        questions: quizQuestions,
    });

    resetForm();
};
</script>

<template>
    <Card class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-foreground">Create New Quiz</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Build quizzes for your students with multiple-choice questions.
        </p>
        <div class="mt-3 rounded-lg bg-muted p-3 text-xs text-muted-foreground">
            Creating for:
            <span class="font-semibold text-foreground">{{ props.section }}</span>
            ·
            <span class="font-semibold text-foreground">{{ props.course }}</span>
            ·
            <span class="font-semibold text-foreground">{{ props.quizSet }}</span>
            ·
            <span class="font-semibold text-foreground">
                {{ props.maxAttempts ? `Max ${props.maxAttempts} attempt(s)` : 'Unlimited attempts' }}
            </span>
            ·
            <span class="font-semibold text-foreground">
                {{ props.scorePolicy === 'best' ? 'Best score policy' : 'Latest score policy' }}
            </span>
        </div>

        <div class="mt-5 flex min-h-0 flex-1 flex-col overflow-hidden">
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                <div>
                    <Label class="mb-1 block text-sm font-medium text-foreground">Quiz Title</Label>
                    <Input
                        v-model="title"
                        type="text"
                        placeholder="e.g. Introduction to Biology"
                    />
                </div>

                <div>
                    <Label class="mb-1 block text-sm font-medium text-foreground">Description</Label>
                    <textarea
                        v-model="description"
                        rows="3"
                        placeholder="Add short quiz instructions..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(question, index) in questions"
                        :key="question.id"
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-foreground">
                                Question {{ index + 1 }}
                            </h3>
                            <button
                                type="button"
                                class="text-xs font-medium text-red-500 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="questions.length === 1"
                                @click="removeQuestion(question.id)"
                            >
                                Remove
                            </button>
                        </div>

                        <Input
                            v-model="question.prompt"
                            type="text"
                            placeholder="Type your question..."
                            class="mb-3"
                        />

                        <div class="space-y-2">
                            <div
                                v-for="(choice, choiceIndex) in question.choices"
                                :key="choiceIndex"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="question.correctChoiceIndex"
                                    :value="choiceIndex"
                                    type="radio"
                                    :name="`correct-choice-${question.id}`"
                                    class="h-4 w-4"
                                />
                                <Input
                                    v-model="question.choices[choiceIndex]"
                                    type="text"
                                    :placeholder="`Choice ${choiceIndex + 1}`"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="shrink-0 border-t border-border bg-card pt-3">
                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="addQuestion"
                    >
                        Add Question
                    </Button>
                    <Button
                        type="button"
                        @click="createQuiz"
                    >
                        Publish Quiz
                    </Button>
                </div>

                <Alert v-if="error" variant="destructive" class="mt-3">
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>
            </div>
        </div>
    </Card>
</template>
