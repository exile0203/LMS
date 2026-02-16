<script setup lang="ts">
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { Quiz } from './types';

type Props = {
    quizzes: Quiz[];
    sectionLabel: string;
    courseLabel: string;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'submit-quiz', payload: { quizId: number; answers: Record<number, number | null> }): void;
}>();

const selectedQuizId = ref<number | null>(null);
const currentQuestionIndex = ref(0);
const selectedAnswers = ref<Record<number, number | null>>({});
const submitted = ref(false);

const selectedQuiz = computed(() =>
    props.quizzes.find((quiz) => quiz.id === selectedQuizId.value),
);

const currentQuestion = computed(() => {
    if (!selectedQuiz.value) {
        return null;
    }
    return selectedQuiz.value.questions[currentQuestionIndex.value] ?? null;
});

const score = computed(() => {
    if (!selectedQuiz.value) {
        return 0;
    }

    return selectedQuiz.value.questions.reduce((total, question) => {
        if (selectedAnswers.value[question.id] === question.correctChoiceIndex) {
            return total + 1;
        }
        return total;
    }, 0);
});

const attemptsUsed = computed(() => selectedQuiz.value?.myAttempt?.attemptCount ?? 0);
const attemptsLimit = computed(() => selectedQuiz.value?.maxAttempts ?? null);
const attemptsRemaining = computed(() => {
    if (!selectedQuiz.value) return null;
    if (attemptsLimit.value === null || attemptsLimit.value === undefined) return null;
    return Math.max(0, attemptsLimit.value - attemptsUsed.value);
});
const canSubmitAttempt = computed(() => {
    if (!selectedQuiz.value) return false;
    if (attemptsLimit.value === null || attemptsLimit.value === undefined) return true;
    return attemptsUsed.value < attemptsLimit.value;
});

const availableSets = computed(() =>
    Array.from(new Set(props.quizzes.map((quiz) => quiz.quizSet))),
);

const startQuiz = (quizId: number) => {
    const quiz = props.quizzes.find((item) => item.id === quizId);
    if (!quiz) {
        return;
    }

    selectedQuizId.value = quizId;
    currentQuestionIndex.value = 0;
    submitted.value = false;
    selectedAnswers.value = Object.fromEntries(quiz.questions.map((q) => [q.id, null]));
};

const nextQuestion = () => {
    if (!selectedQuiz.value) {
        return;
    }
    if (currentQuestionIndex.value < selectedQuiz.value.questions.length - 1) {
        currentQuestionIndex.value += 1;
    }
};

const previousQuestion = () => {
    if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value -= 1;
    }
};

const submitQuiz = () => {
    if (!canSubmitAttempt.value) {
        return;
    }

    submitted.value = true;
    if (selectedQuiz.value) {
        emit('submit-quiz', {
            quizId: selectedQuiz.value.id,
            answers: selectedAnswers.value,
        });
    }
};

const retakeQuiz = () => {
    if (!selectedQuiz.value) {
        return;
    }

    if (!canSubmitAttempt.value) {
        return;
    }

    submitted.value = false;
    currentQuestionIndex.value = 0;
    selectedAnswers.value = Object.fromEntries(selectedQuiz.value.questions.map((q) => [q.id, null]));
};
</script>

<template>
    <Card class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-foreground">Student Quiz Area</h2>
        <p class="mt-1 text-sm text-muted-foreground">Select a quiz and answer each question.</p>
        <div class="mt-3 rounded-lg bg-muted p-3 text-xs text-muted-foreground">
            Assigned:
            <span class="font-semibold text-foreground">{{ props.sectionLabel }}</span>
            ·
            <span class="font-semibold text-foreground">{{ props.courseLabel }}</span>
            ·
            <span class="font-semibold text-foreground">
                {{
                    availableSets.length
                        ? `Sets: ${availableSets.join(', ')}`
                        : 'Set assigned by teacher'
                }}
            </span>
        </div>

        <div v-if="!quizzes.length" class="mt-4 rounded-xl bg-muted p-4 text-sm text-muted-foreground">
            No quizzes are available yet. Ask your teacher to create one.
        </div>

        <div v-else-if="!selectedQuiz" class="mt-5 grid min-h-0 flex-1 gap-3 overflow-y-auto pr-1 md:grid-cols-2">
            <button
                v-for="quiz in quizzes"
                :key="quiz.id"
                type="button"
                class="text-left"
                @click="startQuiz(quiz.id)"
            >
                <Card class="rounded-xl border border-border bg-card p-4 transition hover:border-ring/50 hover:bg-accent/40">
                    <p class="text-[11px] uppercase tracking-wide text-muted-foreground">
                        {{ quiz.section }} · {{ quiz.course }} · {{ quiz.quizSet }}
                    </p>
                    <p class="text-sm font-semibold text-foreground">{{ quiz.title }}</p>
                    <Badge variant="secondary" class="mt-1">
                        {{ quiz.questions.length }} questions
                    </Badge>
                    <p class="mt-2 text-sm text-muted-foreground">{{ quiz.description || 'No description' }}</p>
                </Card>
            </button>
        </div>

        <div v-else class="mt-5 flex min-h-0 flex-1 flex-col overflow-hidden">
            <div class="mb-4 flex items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-semibold text-foreground">{{ selectedQuiz.title }}</p>
                    <p class="text-xs text-muted-foreground">
                        Question {{ currentQuestionIndex + 1 }} of {{ selectedQuiz.questions.length }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{ selectedQuiz.scorePolicy === 'best' ? 'Best score policy' : 'Latest score policy' }}
                        ·
                        {{
                            selectedQuiz.maxAttempts
                                ? `${attemptsUsed} / ${selectedQuiz.maxAttempts} attempts used`
                                : 'Unlimited attempts'
                        }}
                        <template v-if="attemptsRemaining !== null">
                            · {{ attemptsRemaining }} remaining
                        </template>
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="selectedQuizId = null"
                >
                    Back to Quizzes
                </Button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                <div v-if="currentQuestion" class="rounded-xl border border-border bg-card p-4">
                    <p class="text-sm font-medium text-foreground">{{ currentQuestion.prompt }}</p>
                    <div class="mt-3 space-y-2">
                        <label
                            v-for="(choice, choiceIndex) in currentQuestion.choices"
                            :key="choiceIndex"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 hover:bg-accent/40"
                        >
                            <input
                                v-model="selectedAnswers[currentQuestion.id]"
                                :value="choiceIndex"
                                type="radio"
                                :name="`question-${currentQuestion.id}`"
                                class="h-4 w-4"
                                :disabled="submitted"
                            />
                            <span class="text-sm text-foreground">{{ choice }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-4 shrink-0 border-t border-border pt-3">
                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="currentQuestionIndex === 0 || submitted"
                        @click="previousQuestion"
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="!selectedQuiz || currentQuestionIndex >= selectedQuiz.questions.length - 1 || submitted"
                        @click="nextQuestion"
                    >
                        Next
                    </Button>
                    <Button
                        type="button"
                        :disabled="submitted || !canSubmitAttempt"
                        @click="submitQuiz"
                    >
                        Submit Quiz
                    </Button>
                </div>
                <p v-if="!canSubmitAttempt" class="mt-2 text-xs text-destructive">
                    Attempt limit reached for this quiz.
                </p>

                <div v-if="submitted && selectedQuiz" class="mt-3 rounded-xl bg-emerald-500/10 p-4">
                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-300">
                        Score: {{ score }} / {{ selectedQuiz.questions.length }}
                    </p>
                </div>
                <div v-if="selectedQuiz?.myAttempt" class="mt-2 rounded-xl bg-primary/10 p-3">
                    <p class="text-xs font-semibold text-primary">
                        Recorded score: {{ selectedQuiz.myAttempt.score }} / {{ selectedQuiz.myAttempt.totalItems }}
                        <span v-if="selectedQuiz.myAttempt.isOverridden">(Overridden by teacher)</span>
                    </p>
                    <p class="mt-1 text-[11px] text-primary/80">
                        Attempts: {{ selectedQuiz.myAttempt.attemptCount ?? 1 }} ·
                        Best score: {{ selectedQuiz.myAttempt.bestScore ?? selectedQuiz.myAttempt.score }} / {{ selectedQuiz.myAttempt.totalItems }}
                    </p>
                    <p class="mt-1 text-[11px] text-primary/80">
                        Effective score: {{ selectedQuiz.myAttempt.effectiveScore ?? selectedQuiz.myAttempt.score }} / {{ selectedQuiz.myAttempt.totalItems }}
                    </p>
                </div>
                <div class="mt-2">
                    <Button type="button" variant="ghost" size="sm" @click="retakeQuiz">
                        Retake Quiz
                    </Button>
                </div>
            </div>
        </div>
    </Card>
</template>
