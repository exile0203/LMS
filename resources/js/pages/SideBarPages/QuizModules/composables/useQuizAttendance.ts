import { router } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { AttendanceRosterRow, AttendanceSessionSummary, AttendanceStudent, AttendanceStudentRecord } from '../../QuizComponents/types';

type AtRiskStudent = {
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
};

type UseQuizAttendanceDeps = {
    selectedSection: Ref<string>;
    selectedCourse: Ref<string>;
    jsonHeaders: ComputedRef<Record<string, string>>;
    teacherStudents: ComputedRef<AttendanceStudent[]>;
    teacherAttendanceSessions: ComputedRef<AttendanceSessionSummary[]>;
    teacherAttendanceAtRisk: ComputedRef<AtRiskStudent[]>;
    studentAttendanceRecords: ComputedRef<AttendanceStudentRecord[]>;
    studentAttendanceStats: ComputedRef<{
        total: number;
        present: number;
        late: number;
        absent: number;
        excused: number;
        attendanceRate: number;
    }>;
    studentAttendanceAlert: ComputedRef<{
        level: 'warning' | 'info';
        title: string;
        message: string;
    } | null | undefined>;
};

export function useQuizAttendance({
    selectedSection,
    selectedCourse,
    jsonHeaders,
    teacherStudents,
    teacherAttendanceSessions,
    teacherAttendanceAtRisk,
    studentAttendanceRecords,
    studentAttendanceStats,
    studentAttendanceAlert,
}: UseQuizAttendanceDeps) {
    const attendanceError = ref('');
    const isLoadingAttendanceRoster = ref(false);
    const isSavingAttendance = ref(false);
    const attendanceDate = ref(new Date().toISOString().slice(0, 10));
    const attendanceRoster = ref<AttendanceRosterRow[]>([]);

    const loadAttendanceRoster = async () => {
        if (!selectedSection.value || !selectedCourse.value || !attendanceDate.value) {
            return;
        }

        attendanceError.value = '';
        isLoadingAttendanceRoster.value = true;
        try {
            const params = new URLSearchParams({
                section: selectedSection.value,
                course: selectedCourse.value,
                attendanceDate: attendanceDate.value,
            });
            const response = await fetch(`/quiz/attendance/roster?${params.toString()}`, {
                headers: jsonHeaders.value,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                attendanceError.value = data?.error || 'Unable to load attendance roster.';
                return;
            }

            const data = await response.json();
            attendanceRoster.value = Array.isArray(data?.roster) ? data.roster : [];
        } catch {
            attendanceError.value = 'Unable to load attendance roster.';
        } finally {
            isLoadingAttendanceRoster.value = false;
        }
    };

    const saveAttendance = async () => {
        if (!attendanceRoster.value.length) {
            return;
        }

        attendanceError.value = '';
        isSavingAttendance.value = true;
        try {
            const response = await fetch('/quiz/attendance/mark', {
                method: 'POST',
                headers: {
                    ...jsonHeaders.value,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    section: selectedSection.value,
                    course: selectedCourse.value,
                    attendanceDate: attendanceDate.value,
                    records: attendanceRoster.value.map((row) => ({
                        studentId: row.studentId,
                        status: row.status,
                        note: row.note || null,
                    })),
                }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                attendanceError.value = data?.error || 'Unable to save attendance.';
                return;
            }

            router.reload({ only: ['attendance'] });
        } catch {
            attendanceError.value = 'Unable to save attendance.';
        } finally {
            isSavingAttendance.value = false;
        }
    };

    const teacherAttendanceAverageRate = computed(() => {
        if (!teacherAttendanceAtRisk.value.length) {
            return 100;
        }

        const totalRate = teacherAttendanceAtRisk.value.reduce((sum, row) => sum + (row.attendanceRate ?? 0), 0);
        return Math.round((totalRate / teacherAttendanceAtRisk.value.length) * 10) / 10;
    });

    const exportAttendanceCsv = () => {
        const header = ['Student', 'Section', 'Course', 'Attendance Rate', 'Present', 'Late', 'Absent', 'Excused', 'Total'];
        const rows = teacherAttendanceAtRisk.value.map((risk) => [
            risk.studentName,
            risk.section,
            risk.course,
            `${risk.attendanceRate}%`,
            String(risk.present),
            String(risk.late),
            String(risk.absent),
            String(risk.excused),
            String(risk.total),
        ]);

        const escapeCsv = (value: string) => `"${value.replace(/"/g, '""')}"`;
        const csv = [header, ...rows]
            .map((line) => line.map((cell) => escapeCsv(String(cell ?? ''))).join(','))
            .join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `attendance-risk-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        URL.revokeObjectURL(url);
    };

    return {
        attendanceError,
        isLoadingAttendanceRoster,
        isSavingAttendance,
        attendanceDate,
        attendanceRoster,
        teacherStudents,
        teacherAttendanceSessions,
        teacherAttendanceAtRisk,
        studentAttendanceRecords,
        studentAttendanceStats,
        studentAttendanceAlert,
        loadAttendanceRoster,
        saveAttendance,
        teacherAttendanceAverageRate,
        exportAttendanceCsv,
    };
}
