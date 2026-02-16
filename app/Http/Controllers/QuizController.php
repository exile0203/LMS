<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionComment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isTeacher = $this->isTeacher($user);
        $tableMissing = ! $this->hasQuizTables();
        $attemptTablesReady = $this->hasQuizAttemptTables();
        $attemptHistoryTablesReady = $this->hasQuizAttemptHistoryTables();
        $assignmentTablesReady = $this->hasAssignmentTables();
        $attendanceTablesReady = $this->hasAttendanceTables();

        if ($tableMissing) {
            return Inertia::render('SideBarPages/QuizPage', [
                'quizzes' => [],
                'assignments' => [],
                'quizAttempts' => [],
                'quizInsights' => [],
                'attendance' => [
                    'teacherStudents' => [],
                    'teacherSessions' => [],
                    'teacherAtRisk' => [],
                    'studentRecords' => [],
                    'studentAlert' => null,
                    'studentStats' => [
                        'total' => 0,
                        'present' => 0,
                        'late' => 0,
                        'absent' => 0,
                        'excused' => 0,
                        'attendanceRate' => 0,
                    ],
                ],
                'sectionOptions' => ['Section 1', 'Section 2', 'Section 3'],
                'courseOptions' => ['Mathematics', 'Science', 'English'],
                'quizSetOptions' => ['Set A', 'Set B', 'Set C'],
                'studentAssignment' => [
                    'section' => $user?->section,
                    'course' => $user?->course,
                ],
            ]);
        }

        $quizQuery = Quiz::query()
            ->with(['questions'])
            ->latest();

        if (! $isTeacher) {
            if ($user?->section && $user?->course) {
                $quizQuery
                    ->where('section', $user->section)
                    ->where('course', $user->course);
            } else {
                $quizQuery->whereRaw('1 = 0');
            }
        }

        $myAttemptsByQuiz = [];
        $myAttemptStatsByQuiz = [];
        if ($attemptTablesReady && $user) {
            $myAttemptsByQuiz = QuizAttempt::query()
                ->where('user_id', $user->id)
                ->get(['id', 'quiz_id', 'score', 'total_items', 'submitted_at', 'is_overridden', 'override_note'])
                ->keyBy('quiz_id');

            if ($attemptHistoryTablesReady) {
                $stats = QuizAttemptHistory::query()
                    ->where('user_id', $user->id)
                    ->selectRaw('quiz_id, COUNT(*) as attempts_count, MAX(score) as best_score')
                    ->groupBy('quiz_id')
                    ->get();

                foreach ($stats as $row) {
                    $myAttemptStatsByQuiz[(int) $row->quiz_id] = [
                        'attemptCount' => (int) $row->attempts_count,
                        'bestScore' => (int) $row->best_score,
                    ];
                }
            }
        }

        $quizzes = $quizQuery
            ->get()
            ->map(function (Quiz $quiz) use ($attemptTablesReady, $myAttemptsByQuiz, $myAttemptStatsByQuiz) {
                $myAttempt = null;
                if ($attemptTablesReady) {
                    $attempt = $myAttemptsByQuiz[$quiz->id] ?? null;
                    $stats = $myAttemptStatsByQuiz[$quiz->id] ?? null;

                    if ($attempt) {
                        $effectiveScore = $quiz->score_policy === 'best'
                            ? (int) ($stats['bestScore'] ?? $attempt->score)
                            : (int) $attempt->score;

                        $myAttempt = [
                            'id' => $attempt->id,
                            'score' => (int) $attempt->score,
                            'effectiveScore' => $effectiveScore,
                            'totalItems' => (int) $attempt->total_items,
                            'submittedAt' => $attempt->submitted_at?->toIso8601String(),
                            'isOverridden' => (bool) $attempt->is_overridden,
                            'overrideNote' => $attempt->override_note,
                            'attemptCount' => (int) ($stats['attemptCount'] ?? 1),
                            'bestScore' => (int) ($stats['bestScore'] ?? $attempt->score),
                        ];
                    }
                }

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description ?? '',
                    'section' => $quiz->section,
                    'course' => $quiz->course,
                    'quizSet' => $quiz->quiz_set,
                    'maxAttempts' => $quiz->max_attempts,
                    'scorePolicy' => in_array($quiz->score_policy, ['latest', 'best'], true) ? $quiz->score_policy : 'latest',
                    'myAttempt' => $myAttempt,
                    'questions' => $quiz->questions->map(fn ($question) => [
                        'id' => $question->id,
                        'prompt' => $question->prompt,
                        'choices' => $question->choices,
                        'correctChoiceIndex' => $question->correct_choice_index,
                    ])->values(),
                ];
            })
            ->values();

        $quizInsights = collect();
        if ($isTeacher && ($attemptHistoryTablesReady || $attemptTablesReady)) {
            $quizIds = $quizzes->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($quizIds !== []) {
                $attemptRows = $attemptHistoryTablesReady
                    ? QuizAttemptHistory::query()
                        ->whereIn('quiz_id', $quizIds)
                        ->get(['quiz_id', 'user_id', 'answers'])
                    : QuizAttempt::query()
                        ->whereIn('quiz_id', $quizIds)
                        ->get(['quiz_id', 'user_id', 'answers']);

                $attemptsByQuiz = $attemptRows->groupBy('quiz_id');

                $quizInsights = $quizzes->map(function (array $quiz) use ($attemptsByQuiz) {
                    $quizId = (int) $quiz['id'];
                    $attempts = $attemptsByQuiz->get($quizId, collect());
                    $attemptCount = (int) $attempts->count();
                    $participantCount = (int) $attempts
                        ->pluck('user_id')
                        ->filter()
                        ->unique()
                        ->count();

                    $questionInsights = collect($quiz['questions'] ?? [])->map(function (array $question, int $index) use ($attempts) {
                        $questionId = (int) ($question['id'] ?? 0);
                        $choices = collect($question['choices'] ?? [])->values()->all();
                        $correctChoiceIndex = (int) ($question['correctChoiceIndex'] ?? -1);
                        $choiceCounts = array_fill(0, count($choices), 0);
                        $answeredCount = 0;
                        $correctCount = 0;

                        foreach ($attempts as $attempt) {
                            $answers = (array) ($attempt->answers ?? []);
                            $selected = $answers[(string) $questionId] ?? $answers[$questionId] ?? null;

                            if (! is_numeric($selected)) {
                                continue;
                            }

                            $selectedIndex = (int) $selected;
                            if (! array_key_exists($selectedIndex, $choiceCounts)) {
                                continue;
                            }

                            $choiceCounts[$selectedIndex]++;
                            $answeredCount++;

                            if ($selectedIndex === $correctChoiceIndex) {
                                $correctCount++;
                            }
                        }

                        $correctRate = $answeredCount > 0
                            ? round(($correctCount / $answeredCount) * 100, 1)
                            : 0.0;

                        $difficulty = $correctRate >= 80
                            ? 'easy'
                            : ($correctRate >= 50 ? 'medium' : 'hard');

                        $wrongChoiceCounts = collect($choiceCounts)
                            ->map(fn (int $count, int $choiceIndex) => [
                                'choiceIndex' => $choiceIndex,
                                'count' => $count,
                            ])
                            ->filter(fn (array $item) => $item['choiceIndex'] !== $correctChoiceIndex)
                            ->sortByDesc('count')
                            ->values();

                        $topWrong = $wrongChoiceCounts->first();
                        $commonWrongChoiceIndex = ($topWrong && $topWrong['count'] > 0)
                            ? (int) $topWrong['choiceIndex']
                            : null;

                        return [
                            'questionId' => $questionId,
                            'questionNo' => $index + 1,
                            'prompt' => (string) ($question['prompt'] ?? ''),
                            'correctChoiceIndex' => $correctChoiceIndex,
                            'correctChoiceText' => $choices[$correctChoiceIndex] ?? '',
                            'answeredCount' => $answeredCount,
                            'correctCount' => $correctCount,
                            'correctRate' => $correctRate,
                            'difficulty' => $difficulty,
                            'commonWrongChoiceIndex' => $commonWrongChoiceIndex,
                            'commonWrongChoiceText' => ! is_null($commonWrongChoiceIndex)
                                ? ($choices[$commonWrongChoiceIndex] ?? null)
                                : null,
                        ];
                    })->values();

                    $overallCorrectRate = $questionInsights->count() > 0
                        ? round((float) $questionInsights->avg('correctRate'), 1)
                        : 0.0;

                    return [
                        'quizId' => $quizId,
                        'quizTitle' => (string) ($quiz['title'] ?? 'Quiz'),
                        'section' => (string) ($quiz['section'] ?? ''),
                        'course' => (string) ($quiz['course'] ?? ''),
                        'quizSet' => (string) ($quiz['quizSet'] ?? ''),
                        'attemptsCount' => $attemptCount,
                        'participantCount' => $participantCount,
                        'overallCorrectRate' => $overallCorrectRate,
                        'questions' => $questionInsights,
                    ];
                })->values();
            }
        }

        $quizAttempts = collect();
        if ($attemptHistoryTablesReady) {
            $attemptQuery = QuizAttemptHistory::query()
                ->with([
                    'quiz:id,title,section,course,quiz_set',
                    'student:id,name,avatar_path,updated_at',
                    'overriddenBy:id,name',
                ])
                ->latest('submitted_at');

            if (! $isTeacher) {
                $attemptQuery->where('user_id', $user?->id ?? 0);
            }

            $quizAttempts = $attemptQuery
                ->get()
                ->map(fn (QuizAttemptHistory $attempt) => [
                    'id' => $attempt->id,
                    'quizId' => (int) $attempt->quiz_id,
                    'quizTitle' => $attempt->quiz?->title ?? 'Quiz',
                    'section' => $attempt->quiz?->section ?? '',
                    'course' => $attempt->quiz?->course ?? '',
                    'quizSet' => $attempt->quiz?->quiz_set ?? '',
                    'studentId' => (int) $attempt->user_id,
                    'studentName' => $attempt->student?->name ?? 'Student',
                    'studentAvatar' => $attempt->student?->avatar,
                    'attemptNo' => (int) $attempt->attempt_no,
                    'score' => (int) $attempt->score,
                    'totalItems' => (int) $attempt->total_items,
                    'submittedAt' => $attempt->submitted_at?->toIso8601String(),
                    'isOverridden' => (bool) $attempt->is_overridden,
                    'overrideNote' => $attempt->override_note,
                    'overriddenBy' => $attempt->overriddenBy?->name,
                ])
                ->values();
        } elseif ($attemptTablesReady) {
            $attemptQuery = QuizAttempt::query()
                ->with([
                    'quiz:id,title,section,course,quiz_set',
                    'student:id,name,avatar_path,updated_at',
                    'overriddenBy:id,name',
                ])
                ->latest('submitted_at');

            if (! $isTeacher) {
                $attemptQuery->where('user_id', $user?->id ?? 0);
            }

            $quizAttempts = $attemptQuery
                ->get()
                ->map(fn (QuizAttempt $attempt) => [
                    'id' => $attempt->id,
                    'quizId' => (int) $attempt->quiz_id,
                    'quizTitle' => $attempt->quiz?->title ?? 'Quiz',
                    'section' => $attempt->quiz?->section ?? '',
                    'course' => $attempt->quiz?->course ?? '',
                    'quizSet' => $attempt->quiz?->quiz_set ?? '',
                    'studentId' => (int) $attempt->user_id,
                    'studentName' => $attempt->student?->name ?? 'Student',
                    'studentAvatar' => $attempt->student?->avatar,
                    'attemptNo' => 1,
                    'score' => (int) $attempt->score,
                    'totalItems' => (int) $attempt->total_items,
                    'submittedAt' => $attempt->submitted_at?->toIso8601String(),
                    'isOverridden' => (bool) $attempt->is_overridden,
                    'overrideNote' => $attempt->override_note,
                    'overriddenBy' => $attempt->overriddenBy?->name,
                ])
                ->values();
        }

        $assignments = collect();
        if ($assignmentTablesReady) {
            $assignmentQuery = Assignment::query()
                ->with([
                    'creator:id,name',
                    'submissions' => fn ($query) => $query
                        ->with([
                            'student:id,name,avatar_path,updated_at',
                            'comments' => fn ($commentQuery) => $commentQuery
                                ->with(['sender:id,name,role,avatar_path,updated_at'])
                                ->oldest('created_at'),
                        ])
                        ->latest('submitted_at'),
                ])
                ->latest();

            if (! $isTeacher) {
                if ($user?->section && $user?->course) {
                    $assignmentQuery
                        ->where('section', $user->section)
                        ->where('course', $user->course);
                } else {
                    $assignmentQuery->whereRaw('1 = 0');
                }
            }

            $assignments = $assignmentQuery
                ->get()
                ->map(function (Assignment $assignment) use ($isTeacher, $user) {
                    $dueAt = $assignment->due_at;
                    $isPastDue = $dueAt ? $dueAt->isPast() : false;

                    $mapped = [
                        'id' => $assignment->id,
                        'title' => $assignment->title,
                        'description' => $assignment->description ?? '',
                        'section' => $assignment->section,
                        'course' => $assignment->course,
                        'dueAt' => $dueAt?->toIso8601String(),
                        'allowFile' => (bool) $assignment->allow_file,
                        'allowLink' => (bool) $assignment->allow_link,
                        'isClosed' => (bool) $assignment->is_closed,
                        'closedAt' => $assignment->closed_at?->toIso8601String(),
                        'createdBy' => $assignment->creator?->name ?? 'Teacher',
                        'isPastDue' => $isPastDue,
                        'submissionCount' => $assignment->submissions->count(),
                    ];

                    if ($isTeacher) {
                        $mapped['submissions'] = $assignment->submissions
                            ->map(function (AssignmentSubmission $submission) use ($dueAt) {
                                $status = 'Pending';
                                if ($submission->submitted_at) {
                                    $status = ($dueAt && $submission->submitted_at->greaterThan($dueAt))
                                        ? 'Late'
                                        : 'Submitted';
                                }

                                return [
                                    'id' => $submission->id,
                                    'studentId' => $submission->user_id,
                                    'studentName' => $submission->student?->name ?? 'Student',
                                    'studentAvatar' => $submission->student?->avatar,
                                    'body' => $submission->body ?? '',
                                    'fileName' => $submission->file_name,
                                    'fileUrl' => $submission->file_path ? Storage::disk('public')->url($submission->file_path) : null,
                                    'fileSize' => $submission->file_size,
                                    'submittedAt' => $submission->submitted_at?->toIso8601String(),
                                    'status' => $status,
                                    'comments' => $submission->comments->map(fn (AssignmentSubmissionComment $comment) => [
                                        'id' => $comment->id,
                                        'senderId' => $comment->user_id,
                                        'senderName' => $comment->sender?->name ?? 'User',
                                        'senderAvatar' => $comment->sender?->avatar,
                                        'senderRole' => strtolower((string) ($comment->sender?->role ?? 'student')),
                                        'body' => $comment->body,
                                        'createdAt' => $comment->created_at?->toIso8601String(),
                                    ])->values(),
                                ];
                            })
                            ->values();
                    } else {
                        $mySubmission = $assignment->submissions
                            ->firstWhere('user_id', $user?->id);

                        $status = 'Pending';
                        if ($mySubmission?->submitted_at) {
                            $status = ($dueAt && $mySubmission->submitted_at->greaterThan($dueAt))
                                ? 'Late'
                                : 'Submitted';
                        } elseif ($isPastDue) {
                            $status = 'Late';
                        }

                        $mapped['mySubmission'] = $mySubmission ? [
                            'id' => $mySubmission->id,
                            'body' => $mySubmission->body ?? '',
                            'fileName' => $mySubmission->file_name,
                            'fileUrl' => $mySubmission->file_path ? Storage::disk('public')->url($mySubmission->file_path) : null,
                            'fileSize' => $mySubmission->file_size,
                            'submittedAt' => $mySubmission->submitted_at?->toIso8601String(),
                            'status' => $status,
                            'comments' => $mySubmission->comments->map(fn (AssignmentSubmissionComment $comment) => [
                                'id' => $comment->id,
                                'senderId' => $comment->user_id,
                                'senderName' => $comment->sender?->name ?? 'User',
                                'senderAvatar' => $comment->sender?->avatar,
                                'senderRole' => strtolower((string) ($comment->sender?->role ?? 'student')),
                                'body' => $comment->body,
                                'createdAt' => $comment->created_at?->toIso8601String(),
                            ])->values(),
                        ] : [
                            'id' => null,
                            'body' => '',
                            'fileName' => null,
                            'fileUrl' => null,
                            'fileSize' => null,
                            'submittedAt' => null,
                            'status' => $status,
                            'comments' => [],
                        ];
                    }

                    return $mapped;
                })
                ->values();
        }

        $attendance = [
            'teacherStudents' => [],
            'teacherSessions' => [],
            'teacherAtRisk' => [],
            'studentRecords' => [],
            'studentAlert' => null,
            'studentStats' => [
                'total' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'excused' => 0,
                'attendanceRate' => 0,
            ],
        ];

        if ($attendanceTablesReady) {
            if ($isTeacher) {
                $attendance['teacherStudents'] = User::query()
                    ->whereRaw('LOWER(COALESCE(role, \'student\')) = ?', ['student'])
                    ->whereNotNull('section')
                    ->whereNotNull('course')
                    ->orderBy('section')
                    ->orderBy('course')
                    ->orderBy('name')
                    ->get(['id', 'name', 'section', 'course', 'avatar_path', 'updated_at'])
                    ->map(fn (User $student) => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'avatar' => $student->avatar,
                        'section' => $student->section,
                        'course' => $student->course,
                    ])
                    ->values();

                $attendance['teacherSessions'] = AttendanceSession::query()
                    ->withCount('records')
                    ->latest('attendance_date')
                    ->limit(20)
                    ->get(['id', 'section', 'course', 'attendance_date', 'created_by'])
                    ->map(fn (AttendanceSession $session) => [
                        'id' => $session->id,
                        'section' => $session->section,
                        'course' => $session->course,
                        'attendanceDate' => $session->attendance_date?->toDateString(),
                        'recordCount' => (int) $session->records_count,
                    ])
                    ->values();

                $attendanceStatsRows = AttendanceRecord::query()
                    ->selectRaw("
                        student_id,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count
                    ")
                    ->groupBy('student_id')
                    ->get();

                $studentLookup = User::query()
                    ->whereIn('id', $attendanceStatsRows->pluck('student_id')->all())
                    ->get(['id', 'name', 'section', 'course', 'avatar_path', 'updated_at'])
                    ->keyBy('id');

                $attendance['teacherAtRisk'] = $attendanceStatsRows
                    ->map(function ($row) use ($studentLookup) {
                        $student = $studentLookup->get((int) $row->student_id);
                        if (! $student) {
                            return null;
                        }

                        $total = (int) $row->total;
                        $present = (int) $row->present_count;
                        $late = (int) $row->late_count;
                        $rate = $total > 0 ? (int) round((($present + $late) / $total) * 100) : 0;
                        if ($total < 3 || $rate >= 75) {
                            return null;
                        }

                        return [
                            'studentId' => (int) $student->id,
                            'studentName' => $student->name,
                            'studentAvatar' => $student->avatar,
                            'section' => $student->section,
                            'course' => $student->course,
                            'attendanceRate' => $rate,
                            'total' => $total,
                            'present' => $present,
                            'late' => $late,
                            'absent' => (int) $row->absent_count,
                            'excused' => (int) $row->excused_count,
                        ];
                    })
                    ->filter()
                    ->sortBy('attendanceRate')
                    ->values();
            } elseif ($user) {
                $records = AttendanceRecord::query()
                    ->with(['session:id,section,course,attendance_date'])
                    ->where('student_id', $user->id)
                    ->latest('marked_at')
                    ->limit(60)
                    ->get();

                $present = $records->where('status', 'present')->count();
                $late = $records->where('status', 'late')->count();
                $absent = $records->where('status', 'absent')->count();
                $excused = $records->where('status', 'excused')->count();
                $total = $records->count();
                $attendanceRate = $total > 0
                    ? (int) round((($present + $late) / $total) * 100)
                    : 0;

                $attendance['studentRecords'] = $records
                    ->map(fn (AttendanceRecord $record) => [
                        'id' => $record->id,
                        'section' => $record->session?->section ?? '',
                        'course' => $record->session?->course ?? '',
                        'attendanceDate' => $record->session?->attendance_date?->toDateString(),
                        'status' => $record->status,
                        'note' => $record->note,
                    ])
                    ->values();
                $attendance['studentStats'] = [
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'excused' => $excused,
                    'attendanceRate' => $attendanceRate,
                ];

                if ($total >= 3 && $attendanceRate < 75) {
                    $attendance['studentAlert'] = [
                        'level' => 'warning',
                        'title' => 'Low Attendance Warning',
                        'message' => "Your current attendance rate is {$attendanceRate}%. Please coordinate with your teacher.",
                    ];
                }
            }
        }

        return Inertia::render('SideBarPages/QuizPage', [
            'quizzes' => $quizzes,
            'assignments' => $assignments,
            'quizAttempts' => $quizAttempts,
            'quizInsights' => $quizInsights,
            'attendance' => $attendance,
            'sectionOptions' => ['Section 1', 'Section 2', 'Section 3'],
            'courseOptions' => ['Mathematics', 'Science', 'English'],
            'quizSetOptions' => ['Set A', 'Set B', 'Set C'],
            'studentAssignment' => [
                'section' => $user?->section,
                'course' => $user?->course,
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->hasQuizTables()) {
            return back()->with('error', 'Quiz tables are missing. Run database migrations first.');
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can create quizzes.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'section' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'quizSet' => ['required', 'string', 'max:255'],
            'maxAttempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'scorePolicy' => ['required', 'string', 'in:latest,best'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.prompt' => ['required', 'string'],
            'questions.*.choices' => ['required', 'array', 'size:4'],
            'questions.*.choices.*' => ['required', 'string', 'max:255'],
            'questions.*.correctChoiceIndex' => ['required', 'integer', 'between:0,3'],
        ]);

        DB::transaction(function () use ($validated, $user) {
            $quiz = Quiz::create([
                'created_by' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'section' => $validated['section'],
                'course' => $validated['course'],
                'quiz_set' => $validated['quizSet'],
                'max_attempts' => $validated['maxAttempts'] ?? null,
                'score_policy' => $validated['scorePolicy'],
            ]);

            foreach ($validated['questions'] as $index => $question) {
                $quiz->questions()->create([
                    'order' => $index + 1,
                    'prompt' => $question['prompt'],
                    'choices' => array_values($question['choices']),
                    'correct_choice_index' => $question['correctChoiceIndex'],
                ]);
            }

            if (Schema::hasTable('app_notifications')) {
                AppNotification::createForUserIfEnabled($user->id, [
                    'type' => 'quiz',
                    'title' => 'Quiz Published',
                    'body' => "You published \"{$quiz->title}\" for {$quiz->section} · {$quiz->course} ({$quiz->quiz_set}).",
                    'link' => '/quiz',
                ]);
            }
        });

        return back()->with('success', 'Quiz created successfully.');
    }

    public function storeAssignment(Request $request)
    {
        if (! $this->hasAssignmentTables()) {
            return back()->with('error', 'Assignment tables are missing. Run database migrations first.');
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can create assignments.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'section' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'dueAt' => ['nullable', 'date'],
            'allowFile' => ['required', 'boolean'],
            'allowLink' => ['required', 'boolean'],
        ]);

        Assignment::create([
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'section' => $validated['section'],
            'course' => $validated['course'],
            'due_at' => $validated['dueAt'] ?? null,
            'allow_file' => (bool) $validated['allowFile'],
            'allow_link' => (bool) $validated['allowLink'],
            'is_closed' => false,
            'closed_at' => null,
        ]);

        return back()->with('success', 'Assignment created successfully.');
    }

    public function updateAssignment(Request $request, Assignment $assignment)
    {
        if (! $this->hasAssignmentTables()) {
            return back()->with('error', 'Assignment tables are missing. Run database migrations first.');
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can edit assignments.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'dueAt' => ['nullable', 'date'],
            'allowFile' => ['required', 'boolean'],
            'allowLink' => ['required', 'boolean'],
        ]);

        $assignment->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['dueAt'] ?? null,
            'allow_file' => (bool) $validated['allowFile'],
            'allow_link' => (bool) $validated['allowLink'],
        ]);

        return back()->with('success', 'Assignment updated successfully.');
    }

    public function toggleAssignmentClosed(Request $request, Assignment $assignment)
    {
        if (! $this->hasAssignmentTables()) {
            return back()->with('error', 'Assignment tables are missing. Run database migrations first.');
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can manage assignment submissions.');

        $nextClosed = ! (bool) $assignment->is_closed;
        $assignment->update([
            'is_closed' => $nextClosed,
            'closed_at' => $nextClosed ? now() : null,
        ]);

        return back()->with('success', $nextClosed ? 'Submissions closed.' : 'Submissions reopened.');
    }

    public function deleteAssignment(Request $request, Assignment $assignment)
    {
        if (! $this->hasAssignmentTables()) {
            return back()->with('error', 'Assignment tables are missing. Run database migrations first.');
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can delete assignments.');

        $assignment->delete();
        return back()->with('success', 'Assignment deleted.');
    }

    public function submitAssignment(Request $request, Assignment $assignment)
    {
        if (! $this->hasAssignmentTables()) {
            return back()->with('error', 'Assignment tables are missing. Run database migrations first.');
        }

        $user = $request->user();
        abort_if($this->isTeacher($user), 403, 'Teachers cannot submit assignments.');
        abort_unless($user?->section && $user?->course, 403, 'Student profile missing section/course.');
        abort_unless(
            $assignment->section === $user->section && $assignment->course === $user->course,
            403,
            'Not allowed to submit this assignment.',
        );
        if ($assignment->is_closed) {
            return back()->with('error', 'Assignment submissions are closed.');
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $existingSubmission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->first();

        $body = trim((string) ($validated['body'] ?? ''));
        $filePath = $existingSubmission?->file_path;
        $fileName = $existingSubmission?->file_name;
        $fileSize = $existingSubmission?->file_size;

        if ($request->hasFile('file')) {
            if (! $assignment->allow_file) {
                return back()->with('error', 'File submissions are disabled for this assignment.');
            }

            $file = $request->file('file');
            $filePath = $file->store('assignment-submissions', 'public');
            $fileName = $file->getClientOriginalName();
            $fileSize = $this->formatBytes((int) $file->getSize());
        }

        if ($body === '' && ! $filePath) {
            return back()->with('error', 'Submission message or file is required.');
        }

        if ($body !== '' && ! $assignment->allow_link && filter_var($body, FILTER_VALIDATE_URL)) {
            return back()->with('error', 'Link submissions are disabled for this assignment.');
        }

        AssignmentSubmission::query()->updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
            ],
            [
                'body' => $body !== '' ? $body : null,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'submitted_at' => now(),
            ],
        );

        return back()->with('success', 'Assignment submitted successfully.');
    }

    public function addAssignmentComment(Request $request, AssignmentSubmission $submission): JsonResponse
    {
        if (! $this->hasAssignmentTables()) {
            return response()->json(['error' => 'Assignment tables are missing. Run database migrations first.'], 503);
        }

        $user = $request->user();
        $assignment = $submission->assignment;

        if (! $assignment) {
            return response()->json(['error' => 'Assignment not found for this submission.'], 404);
        }

        $isTeacher = $this->isTeacher($user);
        if ($isTeacher) {
            abort_unless((int) $assignment->created_by === (int) $user->id, 403, 'Only the assignment teacher can comment.');
        } else {
            abort_unless((int) $submission->user_id === (int) $user->id, 403, 'You can only comment on your own submission.');
            abort_unless(
                ($user?->section ?? null) === $assignment->section && ($user?->course ?? null) === $assignment->course,
                403,
                'Not allowed to comment on this assignment.',
            );
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = AssignmentSubmissionComment::query()->create([
            'assignment_submission_id' => $submission->id,
            'user_id' => $user->id,
            'body' => trim((string) $validated['body']),
        ]);

        return response()->json([
            'ok' => true,
            'comment' => [
                'id' => $comment->id,
                'senderId' => $comment->user_id,
                'senderName' => $user->name,
                'senderAvatar' => $user->avatar,
                'senderRole' => strtolower((string) ($user->role ?? 'student')),
                'body' => $comment->body,
                'createdAt' => $comment->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function submitQuizAttempt(Request $request, Quiz $quiz)
    {
        if (! $this->hasQuizAttemptTables()) {
            return back()->with('error', 'Quiz attempt table is missing. Run database migrations first.');
        }

        $user = $request->user();
        abort_if($this->isTeacher($user), 403, 'Teachers cannot submit quiz attempts.');
        abort_unless($user?->section && $user?->course, 403, 'Student profile missing section/course.');
        abort_unless(
            $quiz->section === $user->section && $quiz->course === $user->course,
            403,
            'Not allowed to submit this quiz.',
        );

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $attemptCount = 0;
        if ($this->hasQuizAttemptHistoryTables()) {
            $attemptCount = (int) (QuizAttemptHistory::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->count() ?? 0);
        } elseif ($this->hasQuizAttemptTables()) {
            $attemptCount = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->exists() ? 1 : 0;
        }

        if (! is_null($quiz->max_attempts) && $attemptCount >= (int) $quiz->max_attempts) {
            return back()->with('error', 'Maximum attempts reached for this quiz.');
        }

        $questions = $quiz->questions()->get(['id', 'correct_choice_index']);
        $answers = $validated['answers'];
        $score = 0;

        foreach ($questions as $question) {
            $selected = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
            if (is_numeric($selected) && (int) $selected === (int) $question->correct_choice_index) {
                $score++;
            }
        }

        $latestAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $latestAttempt) {
            QuizAttempt::query()->create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'answers' => $answers,
                'score' => $score,
                'total_items' => $questions->count(),
                'submitted_at' => now(),
                'is_overridden' => false,
                'overridden_by' => null,
                'override_note' => null,
            ]);
        } else {
            $snapshotScore = $quiz->score_policy === 'best'
                ? max((int) $latestAttempt->score, $score)
                : $score;

            $latestAttempt->update([
                'answers' => $answers,
                'score' => $snapshotScore,
                'total_items' => $questions->count(),
                'submitted_at' => now(),
                'is_overridden' => false,
                'overridden_by' => null,
                'override_note' => null,
            ]);
        }

        if ($this->hasQuizAttemptHistoryTables()) {
            $nextAttemptNo = (int) (
                QuizAttemptHistory::query()
                    ->where('quiz_id', $quiz->id)
                    ->where('user_id', $user->id)
                    ->max('attempt_no') ?? 0
            ) + 1;

            QuizAttemptHistory::query()->create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'attempt_no' => $nextAttemptNo,
                'answers' => $answers,
                'score' => $score,
                'total_items' => $questions->count(),
                'submitted_at' => now(),
                'is_overridden' => false,
                'overridden_by' => null,
                'override_note' => null,
            ]);
        }

        return back()->with('success', "Quiz submitted. Score: {$score} / ".$questions->count().'.');
    }

    public function overrideQuizAttemptScore(Request $request, int $attempt): JsonResponse
    {
        if (! $this->hasQuizAttemptTables() && ! $this->hasQuizAttemptHistoryTables()) {
            return response()->json(['error' => 'Quiz attempt tables are missing.'], 503);
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can override scores.');

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $attemptHistory = null;
        if ($this->hasQuizAttemptHistoryTables()) {
            $attemptHistory = QuizAttemptHistory::query()->find($attempt);
        }

        if ($attemptHistory) {
            $total = max(0, (int) $attemptHistory->total_items);
            $score = min((int) $validated['score'], $total);

            $attemptHistory->update([
                'score' => $score,
                'is_overridden' => true,
                'overridden_by' => $user->id,
                'override_note' => $validated['note'] ?? null,
            ]);

            $latestHistoryId = (int) (QuizAttemptHistory::query()
                ->where('quiz_id', $attemptHistory->quiz_id)
                ->where('user_id', $attemptHistory->user_id)
                ->orderByDesc('attempt_no')
                ->value('id') ?? 0);

            if ($latestHistoryId === (int) $attemptHistory->id && $this->hasQuizAttemptTables()) {
                $quiz = Quiz::query()->find($attemptHistory->quiz_id);
                $scoreForSnapshot = $score;
                if ($quiz && $quiz->score_policy === 'best') {
                    $scoreForSnapshot = (int) (QuizAttemptHistory::query()
                        ->where('quiz_id', $attemptHistory->quiz_id)
                        ->where('user_id', $attemptHistory->user_id)
                        ->max('score') ?? $score);
                }

                QuizAttempt::query()
                    ->where('quiz_id', $attemptHistory->quiz_id)
                    ->where('user_id', $attemptHistory->user_id)
                    ->update([
                        'score' => $scoreForSnapshot,
                        'is_overridden' => true,
                        'overridden_by' => $user->id,
                        'override_note' => $validated['note'] ?? null,
                    ]);
            }

            return response()->json([
                'ok' => true,
                'attempt' => [
                    'id' => $attemptHistory->id,
                    'score' => $attemptHistory->score,
                    'totalItems' => $attemptHistory->total_items,
                    'isOverridden' => (bool) $attemptHistory->is_overridden,
                    'overrideNote' => $attemptHistory->override_note,
                    'overriddenBy' => $user->name,
                ],
            ]);
        }

        $latestAttempt = QuizAttempt::query()->find($attempt);
        if (! $latestAttempt) {
            return response()->json(['error' => 'Attempt not found.'], 404);
        }

        $total = max(0, (int) $latestAttempt->total_items);
        $score = min((int) $validated['score'], $total);
        $latestAttempt->update([
            'score' => $score,
            'is_overridden' => true,
            'overridden_by' => $user->id,
            'override_note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'attempt' => [
                'id' => $latestAttempt->id,
                'score' => $latestAttempt->score,
                'totalItems' => $latestAttempt->total_items,
                'isOverridden' => (bool) $latestAttempt->is_overridden,
                'overrideNote' => $latestAttempt->override_note,
                'overriddenBy' => $user->name,
            ],
        ]);
    }

    public function attendanceRoster(Request $request): JsonResponse
    {
        if (! $this->hasAttendanceTables()) {
            return response()->json(['error' => 'Attendance tables are missing.'], 503);
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can access attendance roster.');

        $validated = $request->validate([
            'section' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'attendanceDate' => ['required', 'date'],
        ]);

        $session = AttendanceSession::query()->firstOrCreate(
            [
                'section' => $validated['section'],
                'course' => $validated['course'],
                'attendance_date' => $validated['attendanceDate'],
            ],
            [
                'created_by' => $user->id,
                'note' => null,
            ],
        );

        $students = User::query()
            ->whereRaw('LOWER(COALESCE(role, \'student\')) = ?', ['student'])
            ->where('section', $validated['section'])
            ->where('course', $validated['course'])
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_path', 'updated_at']);

        $recordLookup = AttendanceRecord::query()
            ->where('attendance_session_id', $session->id)
            ->get(['student_id', 'status', 'note'])
            ->keyBy('student_id');

        $roster = $students->map(function (User $student) use ($recordLookup) {
            $record = $recordLookup->get($student->id);
            return [
                'studentId' => $student->id,
                'studentName' => $student->name,
                'studentAvatar' => $student->avatar,
                'status' => $record?->status ?? 'present',
                'note' => $record?->note ?? '',
            ];
        })->values();

        return response()->json([
            'sessionId' => $session->id,
            'roster' => $roster,
        ]);
    }

    public function markAttendance(Request $request): JsonResponse
    {
        if (! $this->hasAttendanceTables()) {
            return response()->json(['error' => 'Attendance tables are missing.'], 503);
        }

        $user = $request->user();
        abort_unless($this->isTeacher($user), 403, 'Only teachers can mark attendance.');

        $validated = $request->validate([
            'section' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'attendanceDate' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.studentId' => ['required', 'integer', 'exists:users,id'],
            'records.*.status' => ['required', 'string', 'in:present,late,absent,excused'],
            'records.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $session = AttendanceSession::query()->firstOrCreate(
            [
                'section' => $validated['section'],
                'course' => $validated['course'],
                'attendance_date' => $validated['attendanceDate'],
            ],
            [
                'created_by' => $user->id,
                'note' => null,
            ],
        );

        $allowedStudentIds = User::query()
            ->whereRaw('LOWER(COALESCE(role, \'student\')) = ?', ['student'])
            ->where('section', $validated['section'])
            ->where('course', $validated['course'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $allowedLookup = array_fill_keys($allowedStudentIds, true);

        foreach ($validated['records'] as $record) {
            $studentId = (int) $record['studentId'];
            if (! isset($allowedLookup[$studentId])) {
                continue;
            }

            AttendanceRecord::query()->updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentId,
                ],
                [
                    'status' => $record['status'],
                    'note' => $record['note'] ?? null,
                    'marked_by' => $user->id,
                    'marked_at' => now(),
                ],
            );
        }

        $this->dispatchAttendanceAlerts(
            section: $validated['section'],
            course: $validated['course'],
            studentIds: $allowedStudentIds,
            markedBy: (int) $user->id,
        );

        return response()->json([
            'ok' => true,
            'sessionId' => $session->id,
        ]);
    }

    private function isTeacher($user): bool
    {
        if (! $user) {
            return false;
        }

        $role = strtolower((string) ($user->role ?? $user->user_type ?? $user->type ?? 'student'));
        return $role === 'teacher';
    }

    private function hasQuizTables(): bool
    {
        try {
            return Schema::hasTable('quizzes')
                && Schema::hasTable('quiz_questions')
                && Schema::hasColumn('quizzes', 'max_attempts')
                && Schema::hasColumn('quizzes', 'score_policy');
        } catch (QueryException) {
            return false;
        }
    }

    private function hasAssignmentTables(): bool
    {
        try {
            return Schema::hasTable('assignments')
                && Schema::hasTable('assignment_submissions')
                && Schema::hasTable('assignment_submission_comments')
                && Schema::hasColumn('assignments', 'is_closed')
                && Schema::hasColumn('assignments', 'closed_at');
        } catch (QueryException) {
            return false;
        }
    }

    private function hasQuizAttemptTables(): bool
    {
        try {
            return Schema::hasTable('quiz_attempts');
        } catch (QueryException) {
            return false;
        }
    }

    private function hasQuizAttemptHistoryTables(): bool
    {
        try {
            return Schema::hasTable('quiz_attempt_histories');
        } catch (QueryException) {
            return false;
        }
    }

    private function hasAttendanceTables(): bool
    {
        try {
            return Schema::hasTable('attendance_sessions')
                && Schema::hasTable('attendance_records');
        } catch (QueryException) {
            return false;
        }
    }

    private function dispatchAttendanceAlerts(string $section, string $course, array $studentIds, int $markedBy): void
    {
        if ($studentIds === [] || ! Schema::hasTable('app_notifications')) {
            return;
        }

        $today = now()->toDateString();
        $atRiskCount = 0;

        foreach ($studentIds as $studentId) {
            $records = AttendanceRecord::query()
                ->where('student_id', $studentId)
                ->whereHas('session', function ($query) use ($section, $course) {
                    $query->where('section', $section)
                        ->where('course', $course);
                })
                ->get(['status']);

            $total = $records->count();
            if ($total < 3) {
                continue;
            }

            $present = $records->where('status', 'present')->count();
            $late = $records->where('status', 'late')->count();
            $rate = (int) round((($present + $late) / $total) * 100);
            if ($rate >= 75) {
                continue;
            }

            $alreadyNotifiedToday = AppNotification::query()
                ->where('user_id', $studentId)
                ->where('type', 'attendance_alert')
                ->whereDate('created_at', $today)
                ->exists();

            if (! $alreadyNotifiedToday) {
                AppNotification::createForUserIfEnabled($studentId, [
                    'type' => 'attendance_alert',
                    'title' => 'Low Attendance Warning',
                    'body' => "Your attendance rate in {$section} · {$course} is {$rate}%. Please improve attendance.",
                    'link' => '/quiz',
                    'meta' => [
                        'section' => $section,
                        'course' => $course,
                        'attendanceRate' => $rate,
                        'total' => $total,
                    ],
                ]);
            }

            $atRiskCount++;
        }

        if ($atRiskCount > 0) {
            $teacherAlreadyNotifiedToday = AppNotification::query()
                ->where('user_id', $markedBy)
                ->where('type', 'attendance_alert_summary')
                ->whereDate('created_at', $today)
                ->exists();

            if (! $teacherAlreadyNotifiedToday) {
                AppNotification::createForUserIfEnabled($markedBy, [
                    'type' => 'attendance_alert_summary',
                    'title' => 'Attendance Risk Summary',
                    'body' => "{$atRiskCount} at-risk student(s) found in {$section} · {$course}.",
                    'link' => '/quiz',
                    'meta' => [
                        'section' => $section,
                        'course' => $course,
                        'atRiskCount' => $atRiskCount,
                    ],
                ]);
            }
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
