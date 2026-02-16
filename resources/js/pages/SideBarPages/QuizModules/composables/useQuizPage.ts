import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { AppPageProps } from '@/types';
import type { QuizPageProps } from './types';
import { useQuizAssignments } from './useQuizAssignments';
import { useQuizAttendance } from './useQuizAttendance';
import { useQuizGradebook } from './useQuizGradebook';

export type { QuizPageProps } from './types';

export function useQuizPage(props: QuizPageProps) {
    const page = usePage<AppPageProps>();

    const currentUserRole = computed(() => {
        const user = page.props.auth?.user;
        const possibleRole = user?.role ?? user?.user_type ?? user?.type;

        if (typeof possibleRole === 'string' && possibleRole.trim().length > 0) {
            return possibleRole.toLowerCase();
        }

        return 'student';
    });

    const isTeacher = computed(() => currentUserRole.value === 'teacher');
    const isStudent = computed(() => currentUserRole.value === 'student');
    const pageDescription = computed(() =>
        isTeacher.value
            ? 'Select section, course, and set for quiz assignment.'
            : 'Your section and course are assigned. Quiz set is controlled by your teacher.',
    );

    const flashMessage = computed(() => {
        const flash = page.props.flash as { success?: string; error?: string };
        return flash?.success ?? '';
    });
    const flashError = computed(() => {
        const flash = page.props.flash as { success?: string; error?: string };
        return flash?.error ?? '';
    });

    const selectedSection = ref(props.sectionOptions[0] ?? 'Section 1');
    const selectedCourse = ref(props.courseOptions[0] ?? 'Mathematics');
    const selectedQuizSet = ref(props.quizSetOptions[0] ?? 'Set A');
    const selectedMaxAttemptsInput = ref('');
    const selectedScorePolicy = ref<'latest' | 'best'>('latest');

    const selectedMaxAttempts = computed<number | null>(() => {
        const parsed = Number.parseInt(selectedMaxAttemptsInput.value, 10);
        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    });

    const normalizeLabel = (value: unknown): string | null => {
        if (typeof value !== 'string') {
            return null;
        }

        const trimmed = value.trim();
        return trimmed.length > 0 ? trimmed : null;
    };

    const studentSection = computed(() => {
        const user = page.props.auth?.user ?? {};
        return (
            normalizeLabel(props.studentAssignment?.section) ??
            normalizeLabel(user?.section) ??
            normalizeLabel(user?.section_name) ??
            normalizeLabel(user?.sectionName)
        );
    });

    const studentCourse = computed(() => {
        const user = page.props.auth?.user ?? {};
        return (
            normalizeLabel(props.studentAssignment?.course) ??
            normalizeLabel(user?.course) ??
            normalizeLabel(user?.course_name) ??
            normalizeLabel(user?.courseName)
        );
    });

    const filteredStudentQuizzes = computed(() =>
        props.quizzes.filter(
            (quiz) =>
                (studentSection.value ? quiz.section === studentSection.value : true) &&
                (studentCourse.value ? quiz.course === studentCourse.value : true),
        ),
    );

    const filteredStudentAssignments = computed(() =>
        props.assignments.filter(
            (assignment) =>
                (studentSection.value ? assignment.section === studentSection.value : true) &&
                (studentCourse.value ? assignment.course === studentCourse.value : true),
        ),
    );

    const teacherAssignments = computed(() => props.assignments);
    const teacherQuizAttempts = computed(() => props.quizAttempts);
    const teacherQuizInsights = computed(() => props.quizInsights);
    const teacherAttendanceSessions = computed(() => props.attendance.teacherSessions);
    const teacherAttendanceAtRisk = computed(() => props.attendance.teacherAtRisk);
    const studentQuizAttempts = computed(() =>
        props.quizAttempts.filter((attempt) => attempt.studentId === Number(page.props.auth?.user?.id ?? 0)),
    );
    const studentAttendanceRecords = computed(() => props.attendance.studentRecords);
    const studentAttendanceStats = computed(() => props.attendance.studentStats);
    const studentAttendanceAlert = computed(() => props.attendance.studentAlert);

    const csrfToken = computed(() =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    );

    const getCookie = (name: string) => {
        const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
        return match ? decodeURIComponent(match[1]) : '';
    };

    const jsonHeaders = computed(() => {
        const token = csrfToken.value || getCookie('XSRF-TOKEN');
        return {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token, 'X-XSRF-TOKEN': token } : {}),
        };
    });

    const assignmentsModule = useQuizAssignments({
        selectedSection,
        selectedCourse,
    });

    const gradebookModule = useQuizGradebook({
        teacherQuizAttempts,
        teacherQuizInsights,
        jsonHeaders,
    });

    const attendanceModule = useQuizAttendance({
        selectedSection,
        selectedCourse,
        jsonHeaders,
        teacherStudents: computed(() => props.attendance.teacherStudents),
        teacherAttendanceSessions,
        teacherAttendanceAtRisk,
        studentAttendanceRecords,
        studentAttendanceStats,
        studentAttendanceAlert,
    });

    const handleSubmitQuiz = (payload: { quizId: number; answers: Record<number, number | null> }) => {
        router.post(
            `/quiz/${payload.quizId}/submit`,
            { answers: payload.answers },
            {
                preserveScroll: true,
            },
        );
    };

    const formatDateTime = (iso?: string | null) => {
        if (!iso) {
            return 'No deadline';
        }

        const time = Date.parse(iso);
        if (Number.isNaN(time)) {
            return 'No deadline';
        }

        return new Date(time).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    return {
        isTeacher,
        isStudent,
        pageDescription,
        flashMessage,
        flashError,
        selectedSection,
        selectedCourse,
        selectedQuizSet,
        selectedMaxAttemptsInput,
        selectedScorePolicy,
        selectedMaxAttempts,
        studentSection,
        studentCourse,
        filteredStudentQuizzes,
        filteredStudentAssignments,
        teacherAssignments,
        teacherQuizAttempts,
        teacherQuizInsights,
        studentQuizAttempts,
        handleSubmitQuiz,
        formatDateTime,
        ...assignmentsModule,
        ...gradebookModule,
        ...attendanceModule,
    };
}
