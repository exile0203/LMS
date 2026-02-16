export type QuizQuestion = {
    id: number;
    prompt: string;
    choices: string[];
    correctChoiceIndex: number;
};

export type Quiz = {
    id: number;
    title: string;
    description: string;
    section: string;
    course: string;
    quizSet: string;
    maxAttempts?: number | null;
    scorePolicy?: 'latest' | 'best';
    myAttempt?: {
        id: number;
        score: number;
        effectiveScore?: number;
        totalItems: number;
        submittedAt?: string | null;
        isOverridden: boolean;
        overrideNote?: string | null;
        attemptCount?: number;
        bestScore?: number;
    } | null;
    questions: QuizQuestion[];
};

export type AssignmentSubmission = {
    id: number | null;
    body: string;
    fileName?: string | null;
    fileUrl?: string | null;
    fileSize?: string | null;
    submittedAt?: string | null;
    status: 'Pending' | 'Submitted' | 'Late';
    comments?: AssignmentSubmissionComment[];
};

export type TeacherAssignmentSubmission = {
    id: number;
    studentId: number;
    studentName: string;
    studentAvatar?: string | null;
    body: string;
    fileName?: string | null;
    fileUrl?: string | null;
    fileSize?: string | null;
    submittedAt?: string | null;
    status: 'Pending' | 'Submitted' | 'Late';
    comments?: AssignmentSubmissionComment[];
};

export type AssignmentSubmissionComment = {
    id: number;
    senderId: number;
    senderName: string;
    senderAvatar?: string | null;
    senderRole: 'teacher' | 'student';
    body: string;
    createdAt?: string | null;
};

export type Assignment = {
    id: number;
    title: string;
    description: string;
    section: string;
    course: string;
    dueAt?: string | null;
    allowFile: boolean;
    allowLink: boolean;
    isClosed?: boolean;
    closedAt?: string | null;
    createdBy: string;
    isPastDue: boolean;
    submissionCount: number;
    mySubmission?: AssignmentSubmission;
    submissions?: TeacherAssignmentSubmission[];
};

export type QuizAttempt = {
    id: number;
    quizId: number;
    quizTitle: string;
    section: string;
    course: string;
    quizSet: string;
    studentId: number;
    studentName: string;
    studentAvatar?: string | null;
    attemptNo: number;
    score: number;
    totalItems: number;
    submittedAt?: string | null;
    isOverridden: boolean;
    overrideNote?: string | null;
    overriddenBy?: string | null;
};

export type QuizInsightQuestion = {
    questionId: number;
    questionNo: number;
    prompt: string;
    correctChoiceIndex: number;
    correctChoiceText: string;
    answeredCount: number;
    correctCount: number;
    correctRate: number;
    difficulty: 'easy' | 'medium' | 'hard';
    commonWrongChoiceIndex?: number | null;
    commonWrongChoiceText?: string | null;
};

export type QuizInsight = {
    quizId: number;
    quizTitle: string;
    section: string;
    course: string;
    quizSet: string;
    attemptsCount: number;
    participantCount: number;
    overallCorrectRate: number;
    questions: QuizInsightQuestion[];
};

export type AttendanceStudent = {
    id: number;
    name: string;
    avatar?: string | null;
    section: string;
    course: string;
};

export type AttendanceRosterRow = {
    studentId: number;
    studentName: string;
    studentAvatar?: string | null;
    status: 'present' | 'late' | 'absent' | 'excused';
    note?: string;
};

export type AttendanceSessionSummary = {
    id: number;
    section: string;
    course: string;
    attendanceDate?: string | null;
    recordCount: number;
};

export type AttendanceStudentRecord = {
    id: number;
    section: string;
    course: string;
    attendanceDate?: string | null;
    status: 'present' | 'late' | 'absent' | 'excused';
    note?: string | null;
};

export type AttendancePayload = {
    teacherStudents: AttendanceStudent[];
    teacherSessions: AttendanceSessionSummary[];
    teacherAtRisk: Array<{
        studentId: number;
        studentName: string;
        studentAvatar?: string | null;
        section: string;
        course: string;
        attendanceRate: number;
        total: number;
        present: number;
        late: number;
        absent: number;
        excused: number;
    }>;
    studentRecords: AttendanceStudentRecord[];
    studentAlert?: {
        level: 'warning' | 'info';
        title: string;
        message: string;
    } | null;
    studentStats: {
        total: number;
        present: number;
        late: number;
        absent: number;
        excused: number;
        attendanceRate: number;
    };
};
