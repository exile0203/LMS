import { router } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed, ref } from 'vue';
import type { QuizAttempt, QuizInsight } from '../../QuizComponents/types';

type UseQuizGradebookDeps = {
    teacherQuizAttempts: ComputedRef<QuizAttempt[]>;
    teacherQuizInsights: ComputedRef<QuizInsight[]>;
    jsonHeaders: ComputedRef<Record<string, string>>;
};

export function useQuizGradebook({ teacherQuizAttempts, teacherQuizInsights, jsonHeaders }: UseQuizGradebookDeps) {
    const gradebookError = ref('');
    const isOverridingAttempt = ref<number | null>(null);
    const overrideScoreDrafts = ref<Record<number, number>>({});
    const overrideNoteDrafts = ref<Record<number, string>>({});
    const gradebookSearch = ref('');
    const gradebookSectionFilter = ref<'all' | string>('all');
    const gradebookCourseFilter = ref<'all' | string>('all');
    const gradebookSetFilter = ref<'all' | string>('all');
    const analysisDifficultyFilter = ref<'all' | 'hard' | 'medium' | 'easy'>('all');
    const analysisSort = ref<'default' | 'hardest' | 'easiest'>('hardest');

    const overrideAttemptScore = async (attempt: QuizAttempt) => {
        const scoreDraft = overrideScoreDrafts.value[attempt.id];
        const score = Number.isFinite(scoreDraft) ? scoreDraft : attempt.score;
        const note = (overrideNoteDrafts.value[attempt.id] ?? '').trim();

        gradebookError.value = '';
        isOverridingAttempt.value = attempt.id;
        try {
            const response = await fetch(`/quiz/attempts/${attempt.id}/score`, {
                method: 'PATCH',
                headers: {
                    ...jsonHeaders.value,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ score, note: note || null }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                gradebookError.value = data?.error || 'Unable to override score.';
                return;
            }

            router.reload({ only: ['quizAttempts', 'quizzes'] });
        } catch {
            gradebookError.value = 'Unable to override score.';
        } finally {
            isOverridingAttempt.value = null;
        }
    };

    const filteredTeacherQuizAttempts = computed(() => {
        const search = gradebookSearch.value.trim().toLowerCase();

        return teacherQuizAttempts.value.filter((attempt) => {
            const sectionMatch = gradebookSectionFilter.value === 'all' || attempt.section === gradebookSectionFilter.value;
            const courseMatch = gradebookCourseFilter.value === 'all' || attempt.course === gradebookCourseFilter.value;
            const setMatch = gradebookSetFilter.value === 'all' || attempt.quizSet === gradebookSetFilter.value;
            const searchMatch =
                search.length === 0 ||
                attempt.studentName.toLowerCase().includes(search) ||
                attempt.quizTitle.toLowerCase().includes(search);

            return sectionMatch && courseMatch && setMatch && searchMatch;
        });
    });

    const exportGradebookCsv = () => {
        const rows = filteredTeacherQuizAttempts.value.map((attempt) => [
            attempt.studentName,
            attempt.quizTitle,
            String(attempt.attemptNo),
            attempt.section,
            attempt.course,
            attempt.quizSet,
            String(attempt.score),
            String(attempt.totalItems),
            attempt.submittedAt ? new Date(attempt.submittedAt).toLocaleString() : '',
            attempt.isOverridden ? 'Yes' : 'No',
            attempt.overriddenBy ?? '',
            attempt.overrideNote ?? '',
        ]);

        const escapeCsv = (value: string) => `"${value.replace(/"/g, '""')}"`;
        const header = [
            'Student',
            'Quiz',
            'Attempt',
            'Section',
            'Course',
            'Set',
            'Score',
            'Total',
            'Submitted At',
            'Overridden',
            'Overridden By',
            'Override Note',
        ];
        const csv = [header, ...rows]
            .map((line) => line.map((cell) => escapeCsv(String(cell ?? ''))).join(','))
            .join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `gradebook-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        URL.revokeObjectURL(url);
    };

    const sortedAndFilteredQuizInsights = computed(() =>
        teacherQuizInsights.value
            .map((insight) => {
                const questions = [...insight.questions]
                    .filter((questionInsight) =>
                        analysisDifficultyFilter.value === 'all'
                            ? true
                            : questionInsight.difficulty === analysisDifficultyFilter.value,
                    )
                    .sort((a, b) => {
                        if (analysisSort.value === 'default') {
                            return a.questionNo - b.questionNo;
                        }

                        if (analysisSort.value === 'easiest') {
                            if (b.correctRate === a.correctRate) {
                                return a.questionNo - b.questionNo;
                            }

                            return b.correctRate - a.correctRate;
                        }

                        if (b.correctRate === a.correctRate) {
                            return a.questionNo - b.questionNo;
                        }

                        return a.correctRate - b.correctRate;
                    });

                return {
                    ...insight,
                    questions,
                };
            })
            .filter((insight) => insight.questions.length > 0),
    );

    return {
        gradebookError,
        isOverridingAttempt,
        overrideScoreDrafts,
        overrideNoteDrafts,
        gradebookSearch,
        gradebookSectionFilter,
        gradebookCourseFilter,
        gradebookSetFilter,
        analysisDifficultyFilter,
        analysisSort,
        overrideAttemptScore,
        filteredTeacherQuizAttempts,
        exportGradebookCsv,
        sortedAndFilteredQuizInsights,
    };
}

