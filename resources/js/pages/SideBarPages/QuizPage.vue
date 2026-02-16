<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import QuizStudentBoard from './QuizComponents/QuizStudentBoard.vue';
import QuizTeacherBuilder from './QuizComponents/QuizTeacherBuilder.vue';
import { useQuizPage, type QuizPageProps } from './QuizModules/composables/useQuizPage';

const props = withDefaults(defineProps<QuizPageProps>(), {
    quizzes: () => [],
    assignments: () => [],
    quizAttempts: () => [],
    quizInsights: () => [],
    attendance: () => ({
        teacherStudents: [],
        teacherSessions: [],
        teacherAtRisk: [],
        studentRecords: [],
        studentAlert: null,
        studentStats: {
            total: 0,
            present: 0,
            late: 0,
            absent: 0,
            excused: 0,
            attendanceRate: 0,
        },
    }),
    sectionOptions: () => ['Section 1', 'Section 2', 'Section 3'],
    courseOptions: () => ['Mathematics', 'Science', 'English'],
    quizSetOptions: () => ['Set A', 'Set B', 'Set C'],
    studentAssignment: () => ({
        section: null,
        course: null,
    }),
});

const {
    gradebookError,
    isSubmitting,
    isSubmittingAssignment,
    isSubmittingAssignmentResponse,
    isPostingAssignmentCommentFor,
    isOverridingAttempt,
    isUpdatingAssignmentId,
    editingAssignmentId,
    attendanceError,
    isLoadingAttendanceRoster,
    isSavingAttendance,
    attendanceDate,
    attendanceRoster,
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
    teacherAttendanceSessions,
    teacherAttendanceAtRisk,
    studentQuizAttempts,
    studentAttendanceRecords,
    studentAttendanceStats,
    studentAttendanceAlert,
    assignmentForm,
    studentSubmissionDrafts,
    assignmentCommentDrafts,
    overrideScoreDrafts,
    overrideNoteDrafts,
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
    loadAttendanceRoster,
    saveAttendance,
    handleSubmitQuiz,
    overrideAttemptScore,
    formatDateTime,
    filteredTeacherQuizAttempts,
    exportGradebookCsv,
    teacherAttendanceAverageRate,
    exportAttendanceCsv,
    sortedAndFilteredQuizInsights,
    gradebookSearch,
    gradebookSectionFilter,
    gradebookCourseFilter,
    gradebookSetFilter,
    analysisDifficultyFilter,
    analysisSort,
} = useQuizPage(props);

const nameInitials = (name: string) =>
    name
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase();
</script>

<template>
    <Head title="Quiz" />

    <AppLayout>
        <div class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col overflow-hidden p-4 md:p-6 animate-fade-in-up">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Quiz Management</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ pageDescription }}
                    </p>
                </div>
            </div>

            <Alert v-if="flashMessage" class="mb-4 border-emerald-200 bg-emerald-50 text-emerald-700">
                <AlertDescription>{{ flashMessage }}</AlertDescription>
            </Alert>
            <Alert v-if="flashError" variant="destructive" class="mb-4">
                <AlertDescription>{{ flashError }}</AlertDescription>
            </Alert>

            <div class="min-h-0 flex-1 overflow-hidden">
                <template v-if="isTeacher">
                    <div class="flex h-full min-h-0 flex-col stagger-children">
                        <Card class="mb-4 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-5">
                            <div>
                                <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Section</Label>
                                <select
                                    v-model="selectedSection"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option v-for="section in props.sectionOptions" :key="section" :value="section">
                                        {{ section }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Course</Label>
                                <select
                                    v-model="selectedCourse"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option v-for="course in props.courseOptions" :key="course" :value="course">
                                        {{ course }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Quiz Set</Label>
                                <select
                                    v-model="selectedQuizSet"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option v-for="setOption in props.quizSetOptions" :key="setOption" :value="setOption">
                                        {{ setOption }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Max Attempts</Label>
                                <Input
                                    v-model="selectedMaxAttemptsInput"
                                    type="number"
                                    min="1"
                                    max="20"
                                    placeholder="Unlimited"
                                />
                            </div>

                            <div>
                                <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Score Policy</Label>
                                <select
                                    v-model="selectedScorePolicy"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="latest">Latest score</option>
                                    <option value="best">Best score</option>
                                </select>
                            </div>
                        </Card>

                        <QuizTeacherBuilder
                            class="min-h-0 flex-1"
                            :section="selectedSection"
                            :course="selectedCourse"
                            :quiz-set="selectedQuizSet"
                            :max-attempts="selectedMaxAttempts"
                            :score-policy="selectedScorePolicy"
                            @create-quiz="handleCreateQuiz"
                        />
                        <p v-if="isSubmitting" class="mt-2 text-xs text-muted-foreground">Saving quiz...</p>

                        <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                            <h2 class="text-base font-semibold text-foreground">Assignment + Deadline</h2>
                            <p class="mb-3 text-xs text-muted-foreground">
                                Create assignments for the selected section and course.
                            </p>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Title</Label>
                                    <Input v-model="assignmentForm.title" type="text" placeholder="e.g. Chapter 1 Worksheet" />
                                </div>
                                <div>
                                    <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Due Date</Label>
                                    <Input v-model="assignmentForm.dueAt" type="datetime-local" />
                                </div>
                                <div class="md:col-span-2">
                                    <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Description</Label>
                                    <textarea
                                        v-model="assignmentForm.description"
                                        rows="3"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        placeholder="Add instructions for students..."
                                    />
                                </div>
                                <div class="md:col-span-2 flex flex-wrap items-center gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm text-foreground">
                                        <input v-model="assignmentForm.allowFile" type="checkbox" class="rounded border-border" />
                                        Allow file submission
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-foreground">
                                        <input v-model="assignmentForm.allowLink" type="checkbox" class="rounded border-border" />
                                        Allow link/text submission
                                    </label>
                                    <Button
                                        type="button"
                                        class="ml-auto"
                                        :disabled="!assignmentForm.title.trim() || isSubmittingAssignment"
                                        @click="handleCreateAssignment"
                                    >
                                        {{ isSubmittingAssignment ? 'Creating...' : 'Create Assignment' }}
                                    </Button>
                                </div>
                            </div>
                        </Card>

                        <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                            <h2 class="text-base font-semibold text-foreground">Published Assignments</h2>
                            <div v-if="teacherAssignments.length" class="mt-3 space-y-3">
                                <div
                                    v-for="assignment in teacherAssignments"
                                    :key="assignment.id"
                                    class="rounded-lg border border-border bg-background p-3"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-foreground">{{ assignment.title }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ assignment.section }} · {{ assignment.course }} · Due {{ formatDateTime(assignment.dueAt) }}
                                            </p>
                                        </div>
                                        <span class="rounded-full border border-border px-2 py-0.5 text-[11px] text-muted-foreground">
                                            {{ assignment.submissionCount }} submission(s)
                                        </span>
                                    </div>
                                    <p
                                        v-if="assignment.isClosed"
                                        class="mt-1 text-xs font-semibold text-amber-600 dark:text-amber-300"
                                    >
                                        Submissions closed
                                    </p>
                                    <p v-if="assignment.description" class="mt-2 text-sm text-muted-foreground">{{ assignment.description }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <Button type="button" variant="outline" size="sm" :disabled="isUpdatingAssignmentId === assignment.id" @click="beginEditAssignment(assignment)">
                                            Edit
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" :disabled="isUpdatingAssignmentId === assignment.id" @click="toggleAssignmentClosed(assignment.id)">
                                            {{ assignment.isClosed ? 'Reopen' : 'Close' }}
                                        </Button>
                                        <Button type="button" variant="destructive" size="sm" :disabled="isUpdatingAssignmentId === assignment.id" @click="deleteAssignment(assignment.id)">
                                            Delete
                                        </Button>
                                    </div>

                                    <div
                                        v-if="editingAssignmentId === assignment.id"
                                        class="mt-3 space-y-2 rounded-lg border border-border bg-card p-3"
                                    >
                                        <Input v-model="assignmentEditForm.title" type="text" placeholder="Assignment title" />
                                        <textarea
                                            v-model="assignmentEditForm.description"
                                            rows="2"
                                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            placeholder="Description..."
                                        />
                                        <Input v-model="assignmentEditForm.dueAt" type="datetime-local" />
                                        <div class="flex flex-wrap items-center gap-4">
                                            <label class="inline-flex items-center gap-2 text-xs text-foreground">
                                                <input v-model="assignmentEditForm.allowFile" type="checkbox" class="rounded border-border" />
                                                Allow file
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-xs text-foreground">
                                                <input v-model="assignmentEditForm.allowLink" type="checkbox" class="rounded border-border" />
                                                Allow link/text
                                            </label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Button type="button" size="sm" :disabled="isUpdatingAssignmentId === assignment.id" @click="saveEditAssignment(assignment.id)">
                                                Save
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" @click="cancelEditAssignment">
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                    <div v-if="assignment.submissions?.length" class="mt-3 space-y-2">
                                        <div
                                            v-for="submission in assignment.submissions"
                                            :key="submission.id"
                                            class="rounded-md border border-border/70 bg-card px-3 py-2"
                                        >
                                            <p class="text-xs font-semibold text-foreground inline-flex items-center gap-1">
                                                <Avatar class="h-5 w-5 rounded-full border border-border">
                                                    <AvatarImage v-if="submission.studentAvatar" :src="submission.studentAvatar" :alt="submission.studentName" />
                                                    <AvatarFallback class="text-[9px] font-semibold">{{ nameInitials(submission.studentName) }}</AvatarFallback>
                                                </Avatar>
                                                {{ submission.studentName }} · {{ submission.status }}
                                            </p>
                                            <p v-if="submission.body" class="text-xs text-muted-foreground">{{ submission.body }}</p>
                                            <a
                                                v-if="submission.fileUrl"
                                                :href="submission.fileUrl"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-xs text-primary hover:underline"
                                            >
                                                {{ submission.fileName || 'View file' }} {{ submission.fileSize ? `(${submission.fileSize})` : '' }}
                                            </a>
                                            <div class="mt-2 rounded-md border border-border/70 bg-background p-2">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                    Feedback Thread
                                                </p>
                                                <div v-if="submission.comments?.length" class="mt-2 space-y-1">
                                                    <div
                                                        v-for="comment in submission.comments"
                                                        :key="comment.id"
                                                        class="rounded-md border border-border bg-card px-2 py-1 text-xs"
                                                    >
                                                        <p class="font-semibold text-foreground inline-flex items-center gap-1">
                                                            <Avatar class="h-5 w-5 rounded-full border border-border">
                                                                <AvatarImage v-if="comment.senderAvatar" :src="comment.senderAvatar" :alt="comment.senderName" />
                                                                <AvatarFallback class="text-[9px] font-semibold">{{ nameInitials(comment.senderName) }}</AvatarFallback>
                                                            </Avatar>
                                                            {{ comment.senderName }}
                                                            <span class="text-muted-foreground">· {{ comment.senderRole }}</span>
                                                        </p>
                                                        <p class="text-muted-foreground">{{ comment.body }}</p>
                                                    </div>
                                                </div>
                                                <p v-else class="mt-2 text-xs text-muted-foreground">No comments yet.</p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <Input
                                                        v-model="assignmentCommentDrafts[submission.id]"
                                                        type="text"
                                                        placeholder="Write feedback comment..."
                                                    />
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        :disabled="isPostingAssignmentCommentFor === submission.id"
                                                        @click="postAssignmentComment(submission.id)"
                                                    >
                                                        {{ isPostingAssignmentCommentFor === submission.id ? 'Posting...' : 'Post' }}
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="mt-2 text-sm text-muted-foreground">No assignments yet.</p>
                        </Card>

                        <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h2 class="text-base font-semibold text-foreground">Gradebook</h2>
                                <Button type="button" size="sm" variant="outline" @click="exportGradebookCsv">
                                    Export CSV
                                </Button>
                            </div>
                            <p class="mb-3 text-xs text-muted-foreground">Review quiz scores and override when needed.</p>
                            <div class="mb-3 grid gap-2 md:grid-cols-4">
                                <Input v-model="gradebookSearch" type="text" placeholder="Search student or quiz..." />
                                <select
                                    v-model="gradebookSectionFilter"
                                    class="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                >
                                    <option value="all">All sections</option>
                                    <option v-for="section in props.sectionOptions" :key="`gb-section-${section}`" :value="section">
                                        {{ section }}
                                    </option>
                                </select>
                                <select
                                    v-model="gradebookCourseFilter"
                                    class="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                >
                                    <option value="all">All courses</option>
                                    <option v-for="course in props.courseOptions" :key="`gb-course-${course}`" :value="course">
                                        {{ course }}
                                    </option>
                                </select>
                                <select
                                    v-model="gradebookSetFilter"
                                    class="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                >
                                    <option value="all">All sets</option>
                                    <option v-for="setOption in props.quizSetOptions" :key="`gb-set-${setOption}`" :value="setOption">
                                        {{ setOption }}
                                    </option>
                                </select>
                            </div>
                            <p v-if="gradebookError" class="mb-2 text-xs text-destructive">{{ gradebookError }}</p>
                            <div v-if="filteredTeacherQuizAttempts.length" class="space-y-2">
                                <div
                                    v-for="attempt in filteredTeacherQuizAttempts"
                                    :key="attempt.id"
                                    class="rounded-md border border-border bg-background p-3"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-foreground inline-flex items-center gap-1">
                                                <Avatar class="h-6 w-6 rounded-full border border-border">
                                                    <AvatarImage v-if="attempt.studentAvatar" :src="attempt.studentAvatar" :alt="attempt.studentName" />
                                                    <AvatarFallback class="text-[10px] font-semibold">{{ nameInitials(attempt.studentName) }}</AvatarFallback>
                                                </Avatar>
                                                {{ attempt.studentName }} · {{ attempt.quizTitle }} · Attempt #{{ attempt.attemptNo }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ attempt.section }} · {{ attempt.course }} · {{ attempt.quizSet }}
                                            </p>
                                        </div>
                                        <span class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground">
                                            {{ attempt.score }} / {{ attempt.totalItems }}
                                        </span>
                                    </div>
                                    <div class="mt-2 grid gap-2 md:grid-cols-[120px_1fr_auto]">
                                        <Input
                                            v-model.number="overrideScoreDrafts[attempt.id]"
                                            type="number"
                                            min="0"
                                            :max="attempt.totalItems"
                                            :placeholder="String(attempt.score)"
                                        />
                                        <Input
                                            v-model="overrideNoteDrafts[attempt.id]"
                                            type="text"
                                            placeholder="Override note (optional)"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="isOverridingAttempt === attempt.id"
                                            @click="overrideAttemptScore(attempt)"
                                        >
                                            {{ isOverridingAttempt === attempt.id ? 'Saving...' : 'Override' }}
                                        </Button>
                                    </div>
                                    <p v-if="attempt.isOverridden" class="mt-2 text-xs text-primary">
                                        Overridden {{ attempt.overriddenBy ? `by ${attempt.overriddenBy}` : '' }}
                                        <span v-if="attempt.overrideNote"> · {{ attempt.overrideNote }}</span>
                                    </p>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">No attempts match your current filters.</p>
                        </Card>

                        <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h2 class="text-base font-semibold text-foreground">Quiz Item Analysis</h2>
                                <div class="flex flex-wrap items-center gap-2">
                                    <select
                                        v-model="analysisDifficultyFilter"
                                        class="rounded-md border border-input bg-background px-2 py-1 text-xs"
                                    >
                                        <option value="all">All difficulties</option>
                                        <option value="hard">Hard only</option>
                                        <option value="medium">Medium only</option>
                                        <option value="easy">Easy only</option>
                                    </select>
                                    <select
                                        v-model="analysisSort"
                                        class="rounded-md border border-input bg-background px-2 py-1 text-xs"
                                    >
                                        <option value="hardest">Hardest first</option>
                                        <option value="easiest">Easiest first</option>
                                        <option value="default">Question order</option>
                                    </select>
                                </div>
                            </div>
                            <p class="mb-3 text-xs text-muted-foreground">
                                Difficulty and common mistakes per question based on student attempts.
                            </p>

                            <div v-if="sortedAndFilteredQuizInsights.length" class="space-y-3">
                                <div
                                    v-for="insight in sortedAndFilteredQuizInsights"
                                    :key="insight.quizId"
                                    class="rounded-md border border-border bg-background p-3"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-foreground">
                                                {{ insight.quizTitle }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ insight.section }} · {{ insight.course }} · {{ insight.quizSet }}
                                            </p>
                                        </div>
                                        <span class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground">
                                            {{ insight.participantCount }} student(s), {{ insight.attemptsCount }} attempt(s), Avg {{ insight.overallCorrectRate }}%
                                        </span>
                                    </div>

                                    <div class="mt-2 space-y-2">
                                        <div
                                            v-for="questionInsight in insight.questions"
                                            :key="questionInsight.questionId"
                                            class="rounded-md border border-border/80 bg-card p-2"
                                        >
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <p class="text-xs font-semibold text-foreground">
                                                    Q{{ questionInsight.questionNo }} · {{ questionInsight.prompt }}
                                                </p>
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[11px]"
                                                    :class="
                                                        questionInsight.difficulty === 'easy'
                                                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                            : questionInsight.difficulty === 'medium'
                                                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                                                : 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
                                                    "
                                                >
                                                    {{ questionInsight.difficulty }} · {{ questionInsight.correctRate }}%
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Correct: {{ questionInsight.correctChoiceText || 'N/A' }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                Answered: {{ questionInsight.answeredCount }} · Correct: {{ questionInsight.correctCount }}
                                            </p>
                                            <p
                                                v-if="questionInsight.commonWrongChoiceText"
                                                class="text-xs text-amber-700 dark:text-amber-300"
                                            >
                                                Common wrong answer: {{ questionInsight.commonWrongChoiceText }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No matching quiz analytics yet. Student attempts are required.
                            </p>
                        </Card>

                        <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h2 class="text-base font-semibold text-foreground">Attendance</h2>
                                <Button type="button" size="sm" variant="outline" @click="exportAttendanceCsv">
                                    Export At-Risk CSV
                                </Button>
                            </div>
                            <p class="mb-3 text-xs text-muted-foreground">
                                Mark daily attendance for selected section/course.
                            </p>
                            <div class="mb-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-md border border-border bg-background p-3">
                                    <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Students Tracked</p>
                                    <p class="text-lg font-semibold text-foreground">{{ props.attendance.teacherStudents.length }}</p>
                                </div>
                                <div class="rounded-md border border-border bg-background p-3">
                                    <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Sessions Logged</p>
                                    <p class="text-lg font-semibold text-foreground">{{ teacherAttendanceSessions.length }}</p>
                                </div>
                                <div class="rounded-md border border-border bg-background p-3">
                                    <p class="text-[11px] uppercase tracking-wide text-muted-foreground">At-Risk Students</p>
                                    <p class="text-lg font-semibold text-amber-700 dark:text-amber-300">{{ teacherAttendanceAtRisk.length }}</p>
                                </div>
                                <div class="rounded-md border border-border bg-background p-3">
                                    <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Avg Risk Rate</p>
                                    <p class="text-lg font-semibold text-foreground">{{ teacherAttendanceAverageRate }}%</p>
                                </div>
                            </div>
                            <div class="grid gap-2 md:grid-cols-[220px_auto_auto] md:items-end">
                                <div>
                                    <Label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Date</Label>
                                    <Input v-model="attendanceDate" type="date" />
                                </div>
                                <Button type="button" variant="outline" :disabled="isLoadingAttendanceRoster" @click="loadAttendanceRoster">
                                    {{ isLoadingAttendanceRoster ? 'Loading...' : 'Load Roster' }}
                                </Button>
                                <Button type="button" :disabled="isSavingAttendance || !attendanceRoster.length" @click="saveAttendance">
                                    {{ isSavingAttendance ? 'Saving...' : 'Save Attendance' }}
                                </Button>
                            </div>
                            <p v-if="attendanceError" class="mt-2 text-xs text-destructive">{{ attendanceError }}</p>

                            <div v-if="attendanceRoster.length" class="mt-3 space-y-2">
                                <div
                                    v-for="row in attendanceRoster"
                                    :key="row.studentId"
                                    class="grid gap-2 rounded-md border border-border bg-background p-2 md:grid-cols-[1fr_140px_1fr]"
                                >
                                    <p class="text-sm font-medium text-foreground inline-flex items-center gap-1">
                                        <Avatar class="h-6 w-6 rounded-full border border-border">
                                            <AvatarImage v-if="row.studentAvatar" :src="row.studentAvatar" :alt="row.studentName" />
                                            <AvatarFallback class="text-[10px] font-semibold">{{ nameInitials(row.studentName) }}</AvatarFallback>
                                        </Avatar>
                                        {{ row.studentName }}
                                    </p>
                                    <select
                                        v-model="row.status"
                                        class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                                    >
                                        <option value="present">Present</option>
                                        <option value="late">Late</option>
                                        <option value="absent">Absent</option>
                                        <option value="excused">Excused</option>
                                    </select>
                                    <Input v-model="row.note" type="text" placeholder="Note (optional)" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <h3 class="text-sm font-semibold text-foreground">Recent Sessions</h3>
                                <div v-if="teacherAttendanceSessions.length" class="mt-2 space-y-1">
                                    <div
                                        v-for="session in teacherAttendanceSessions"
                                        :key="session.id"
                                        class="flex flex-wrap items-center justify-between rounded-md border border-border bg-background px-3 py-2 text-xs text-muted-foreground"
                                    >
                                        <span>{{ session.attendanceDate }} · {{ session.section }} · {{ session.course }}</span>
                                        <span>{{ session.recordCount }} record(s)</span>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">No attendance sessions yet.</p>
                            </div>

                            <div class="mt-4">
                                <h3 class="text-sm font-semibold text-foreground">At-Risk Students (&lt; 75%)</h3>
                                <div v-if="teacherAttendanceAtRisk.length" class="mt-2 space-y-1">
                                    <div
                                        v-for="risk in teacherAttendanceAtRisk"
                                        :key="risk.studentId"
                                        class="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs"
                                    >
                                        <p class="font-semibold text-amber-700 dark:text-amber-300 inline-flex items-center gap-1">
                                            <Avatar class="h-5 w-5 rounded-full border border-amber-500/40">
                                                <AvatarImage v-if="risk.studentAvatar" :src="risk.studentAvatar" :alt="risk.studentName" />
                                                <AvatarFallback class="bg-amber-500/20 text-[9px] font-semibold text-amber-800 dark:text-amber-200">{{ nameInitials(risk.studentName) }}</AvatarFallback>
                                            </Avatar>
                                            {{ risk.studentName }} · {{ risk.section }} · {{ risk.course }}
                                        </p>
                                        <p class="text-amber-700/90 dark:text-amber-300/90">
                                            Rate: {{ risk.attendanceRate }}% (Present {{ risk.present }}, Late {{ risk.late }}, Absent {{ risk.absent }}, Excused {{ risk.excused }})
                                        </p>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">No at-risk students currently.</p>
                            </div>
                        </Card>
                    </div>
                </template>
                <div v-else-if="isStudent" class="h-full min-h-0 overflow-y-auto pr-1">
                    <QuizStudentBoard
                        :quizzes="filteredStudentQuizzes"
                        :section-label="studentSection || 'Assigned Section'"
                        :course-label="studentCourse || 'Assigned Course'"
                        @submit-quiz="handleSubmitQuiz"
                    />
                    <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                        <h2 class="text-base font-semibold text-foreground">Assignments</h2>
                        <p class="mb-3 text-xs text-muted-foreground">
                            Submit before deadline to avoid late status.
                        </p>
                        <div v-if="filteredStudentAssignments.length" class="space-y-3">
                            <div
                                v-for="assignment in filteredStudentAssignments"
                                :key="assignment.id"
                                class="rounded-lg border border-border bg-background p-3"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">{{ assignment.title }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            Due {{ formatDateTime(assignment.dueAt) }}
                                        </p>
                                    </div>
                                    <span class="rounded-full border border-border px-2 py-0.5 text-[11px] text-muted-foreground">
                                        {{ assignment.mySubmission?.status || 'Pending' }}
                                    </span>
                                </div>
                                <p v-if="assignment.description" class="mt-2 text-sm text-muted-foreground">{{ assignment.description }}</p>
                                <div v-if="assignment.mySubmission?.submittedAt" class="mt-2 text-xs text-emerald-600 dark:text-emerald-300">
                                    Submitted {{ formatDateTime(assignment.mySubmission?.submittedAt) }}
                                </div>
                                <a
                                    v-if="assignment.mySubmission?.fileUrl"
                                    :href="assignment.mySubmission.fileUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-1 inline-block text-xs text-primary hover:underline"
                                >
                                    {{ assignment.mySubmission.fileName || 'View uploaded file' }}
                                </a>
                                <div v-if="assignment.mySubmission?.id" class="mt-2 rounded-md border border-border/70 bg-card p-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                        Feedback Thread
                                    </p>
                                    <div v-if="assignment.mySubmission.comments?.length" class="mt-2 space-y-1">
                                        <div
                                            v-for="comment in assignment.mySubmission.comments"
                                            :key="comment.id"
                                            class="rounded-md border border-border bg-background px-2 py-1 text-xs"
                                        >
                                            <p class="font-semibold text-foreground inline-flex items-center gap-1">
                                                <Avatar class="h-5 w-5 rounded-full border border-border">
                                                    <AvatarImage v-if="comment.senderAvatar" :src="comment.senderAvatar" :alt="comment.senderName" />
                                                    <AvatarFallback class="text-[9px] font-semibold">{{ nameInitials(comment.senderName) }}</AvatarFallback>
                                                </Avatar>
                                                {{ comment.senderName }}
                                                <span class="text-muted-foreground">· {{ comment.senderRole }}</span>
                                            </p>
                                            <p class="text-muted-foreground">{{ comment.body }}</p>
                                        </div>
                                    </div>
                                    <p v-else class="mt-2 text-xs text-muted-foreground">No comments yet.</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <Input
                                            v-model="assignmentCommentDrafts[assignment.mySubmission.id]"
                                            type="text"
                                            placeholder="Reply to teacher..."
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="isPostingAssignmentCommentFor === assignment.mySubmission.id"
                                            @click="postAssignmentComment(assignment.mySubmission.id)"
                                        >
                                            {{ isPostingAssignmentCommentFor === assignment.mySubmission.id ? 'Posting...' : 'Post' }}
                                        </Button>
                                    </div>
                                </div>
                                <div class="mt-3 space-y-2">
                                    <textarea
                                        v-model="studentSubmissionDrafts[assignment.id]"
                                        rows="2"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        :placeholder="assignment.allowLink ? 'Add response text or link...' : 'Add response text...'"
                                        :disabled="!!assignment.isClosed"
                                    />
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input
                                            v-if="assignment.allowFile"
                                            type="file"
                                            class="text-xs"
                                            :disabled="!!assignment.isClosed"
                                            @change="onAssignmentFileChange(assignment.id, $event)"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="isSubmittingAssignmentResponse === assignment.id || !!assignment.isClosed"
                                            @click="submitAssignment(assignment)"
                                        >
                                            {{ isSubmittingAssignmentResponse === assignment.id ? 'Submitting...' : 'Submit' }}
                                        </Button>
                                    </div>
                                    <p v-if="assignment.isClosed" class="text-xs text-amber-600 dark:text-amber-300">
                                        Submission is closed by teacher.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No assignments for your section/course yet.</p>
                    </Card>

                    <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                        <h2 class="text-base font-semibold text-foreground">My Quiz Scores</h2>
                        <div v-if="studentQuizAttempts.length" class="mt-3 space-y-2">
                            <div
                                v-for="attempt in studentQuizAttempts"
                                :key="attempt.id"
                                class="rounded-md border border-border bg-background p-3"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-foreground">
                                        {{ attempt.quizTitle }} · Attempt #{{ attempt.attemptNo }}
                                    </p>
                                    <span class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground">
                                        {{ attempt.score }} / {{ attempt.totalItems }}
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ attempt.section }} · {{ attempt.course }} · {{ attempt.quizSet }}
                                </p>
                                <p v-if="attempt.isOverridden" class="mt-1 text-xs text-primary">
                                    Adjusted by teacher
                                    <span v-if="attempt.overrideNote"> · {{ attempt.overrideNote }}</span>
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No submitted quiz scores yet.</p>
                    </Card>

                    <Card class="mt-4 rounded-xl border border-border bg-card p-4">
                        <h2 class="text-base font-semibold text-foreground">My Attendance</h2>
                        <Alert v-if="studentAttendanceAlert" variant="destructive" class="mb-2 mt-2">
                            <AlertDescription>
                                <span class="font-semibold">{{ studentAttendanceAlert.title }}:</span>
                                {{ studentAttendanceAlert.message }}
                            </AlertDescription>
                        </Alert>
                        <p class="text-xs text-muted-foreground">
                            Attendance rate: {{ studentAttendanceStats.attendanceRate }}%
                            (Present: {{ studentAttendanceStats.present }}, Late: {{ studentAttendanceStats.late }},
                            Absent: {{ studentAttendanceStats.absent }}, Excused: {{ studentAttendanceStats.excused }})
                        </p>
                        <div v-if="studentAttendanceRecords.length" class="mt-3 space-y-2">
                            <div
                                v-for="record in studentAttendanceRecords"
                                :key="record.id"
                                class="rounded-md border border-border bg-background px-3 py-2"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-foreground">
                                        {{ record.attendanceDate }} · {{ record.section }} · {{ record.course }}
                                    </p>
                                    <span class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground">
                                        {{ record.status }}
                                    </span>
                                </div>
                                <p v-if="record.note" class="text-xs text-muted-foreground">{{ record.note }}</p>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-sm text-muted-foreground">No attendance records yet.</p>
                    </Card>
                </div>
                <div v-else class="rounded-xl border border-border bg-card p-5 text-sm text-muted-foreground">
                    Your role does not have access to this page.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
