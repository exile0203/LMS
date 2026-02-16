import type {
    Assignment,
    AttendancePayload,
    Quiz,
    QuizAttempt,
    QuizInsight,
} from '../../QuizComponents/types';

export type QuizPageProps = {
    quizzes: Quiz[];
    assignments: Assignment[];
    quizAttempts: QuizAttempt[];
    quizInsights: QuizInsight[];
    attendance: AttendancePayload;
    sectionOptions: string[];
    courseOptions: string[];
    quizSetOptions: string[];
    studentAssignment?: {
        section?: string | null;
        course?: string | null;
    };
};

