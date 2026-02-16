import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref } from 'vue';
import type { Assignment, Quiz } from '../../QuizComponents/types';

type UseQuizAssignmentsDeps = {
    selectedSection: Ref<string>;
    selectedCourse: Ref<string>;
};

export function useQuizAssignments({ selectedSection, selectedCourse }: UseQuizAssignmentsDeps) {
    const isSubmitting = ref(false);
    const isSubmittingAssignment = ref(false);
    const isSubmittingAssignmentResponse = ref<number | null>(null);
    const isPostingAssignmentCommentFor = ref<number | null>(null);
    const isUpdatingAssignmentId = ref<number | null>(null);
    const editingAssignmentId = ref<number | null>(null);

    const assignmentForm = ref({
        title: '',
        description: '',
        dueAt: '',
        allowFile: true,
        allowLink: true,
    });

    const studentSubmissionDrafts = ref<Record<number, string>>({});
    const studentSubmissionFiles = ref<Record<number, File | null>>({});
    const assignmentCommentDrafts = ref<Record<number, string>>({});
    const assignmentEditForm = ref({
        title: '',
        description: '',
        dueAt: '',
        allowFile: true,
        allowLink: true,
    });

    const handleCreateQuiz = (quiz: Quiz) => {
        isSubmitting.value = true;

        router.post(
            '/quiz',
            {
                title: quiz.title,
                description: quiz.description,
                section: quiz.section,
                course: quiz.course,
                quizSet: quiz.quizSet,
                maxAttempts: quiz.maxAttempts ?? null,
                scorePolicy: quiz.scorePolicy ?? 'latest',
                questions: quiz.questions.map((question) => ({
                    prompt: question.prompt,
                    choices: question.choices,
                    correctChoiceIndex: question.correctChoiceIndex,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    isSubmitting.value = false;
                },
            },
        );
    };

    const handleCreateAssignment = () => {
        if (!assignmentForm.value.title.trim()) {
            return;
        }

        isSubmittingAssignment.value = true;
        router.post(
            '/quiz/assignments',
            {
                title: assignmentForm.value.title.trim(),
                description: assignmentForm.value.description.trim() || null,
                section: selectedSection.value,
                course: selectedCourse.value,
                dueAt: assignmentForm.value.dueAt || null,
                allowFile: assignmentForm.value.allowFile,
                allowLink: assignmentForm.value.allowLink,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    assignmentForm.value = {
                        title: '',
                        description: '',
                        dueAt: '',
                        allowFile: true,
                        allowLink: true,
                    };
                },
                onFinish: () => {
                    isSubmittingAssignment.value = false;
                },
            },
        );
    };

    const onAssignmentFileChange = (assignmentId: number, event: Event) => {
        const target = event.target as HTMLInputElement | null;
        studentSubmissionFiles.value[assignmentId] = target?.files?.[0] ?? null;
    };

    const submitAssignment = (assignment: Assignment) => {
        const body = (studentSubmissionDrafts.value[assignment.id] ?? '').trim();
        const file = studentSubmissionFiles.value[assignment.id] ?? null;

        if (!body && !file) {
            return;
        }

        isSubmittingAssignmentResponse.value = assignment.id;
        const formData = new FormData();
        if (body) {
            formData.append('body', body);
        }
        if (file) {
            formData.append('file', file);
        }

        router.post(`/quiz/assignments/${assignment.id}/submit`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                studentSubmissionFiles.value[assignment.id] = null;
                studentSubmissionDrafts.value[assignment.id] = '';
            },
            onFinish: () => {
                isSubmittingAssignmentResponse.value = null;
            },
        });
    };

    const postAssignmentComment = (submissionId: number) => {
        const body = (assignmentCommentDrafts.value[submissionId] ?? '').trim();
        if (!body) {
            return;
        }

        isPostingAssignmentCommentFor.value = submissionId;
        router.post(
            `/quiz/submissions/${submissionId}/comments`,
            { body },
            {
                preserveScroll: true,
                onSuccess: () => {
                    assignmentCommentDrafts.value[submissionId] = '';
                },
                onFinish: () => {
                    isPostingAssignmentCommentFor.value = null;
                },
            },
        );
    };

    const beginEditAssignment = (assignment: Assignment) => {
        editingAssignmentId.value = assignment.id;
        assignmentEditForm.value = {
            title: assignment.title,
            description: assignment.description || '',
            dueAt: assignment.dueAt
                ? new Date(assignment.dueAt).toISOString().slice(0, 16)
                : '',
            allowFile: !!assignment.allowFile,
            allowLink: !!assignment.allowLink,
        };
    };

    const cancelEditAssignment = () => {
        editingAssignmentId.value = null;
    };

    const saveEditAssignment = (assignmentId: number) => {
        if (!assignmentEditForm.value.title.trim()) {
            return;
        }

        isUpdatingAssignmentId.value = assignmentId;
        router.patch(
            `/quiz/assignments/${assignmentId}`,
            {
                title: assignmentEditForm.value.title.trim(),
                description: assignmentEditForm.value.description.trim() || null,
                dueAt: assignmentEditForm.value.dueAt || null,
                allowFile: assignmentEditForm.value.allowFile,
                allowLink: assignmentEditForm.value.allowLink,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    isUpdatingAssignmentId.value = null;
                    editingAssignmentId.value = null;
                },
            },
        );
    };

    const toggleAssignmentClosed = (assignmentId: number) => {
        isUpdatingAssignmentId.value = assignmentId;
        router.post(
            `/quiz/assignments/${assignmentId}/toggle-closed`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    isUpdatingAssignmentId.value = null;
                },
            },
        );
    };

    const deleteAssignment = (assignmentId: number) => {
        if (!window.confirm('Delete this assignment? This also removes submissions.')) {
            return;
        }

        isUpdatingAssignmentId.value = assignmentId;
        router.delete(`/quiz/assignments/${assignmentId}`, {
            preserveScroll: true,
            onFinish: () => {
                isUpdatingAssignmentId.value = null;
            },
        });
    };

    return {
        isSubmitting,
        isSubmittingAssignment,
        isSubmittingAssignmentResponse,
        isPostingAssignmentCommentFor,
        isUpdatingAssignmentId,
        editingAssignmentId,
        assignmentForm,
        studentSubmissionDrafts,
        studentSubmissionFiles,
        assignmentCommentDrafts,
        assignmentEditForm,
        handleCreateQuiz,
        handleCreateAssignment,
        onAssignmentFileChange,
        submitAssignment,
        postAssignmentComment,
        beginEditAssignment,
        cancelEditAssignment,
        saveEditAssignment,
        toggleAssignmentClosed,
        deleteAssignment,
    };
}
