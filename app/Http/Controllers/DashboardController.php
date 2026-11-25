<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\PolicyReference;
use App\Models\QuarterlyGrade;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Services\Grading\GradebookService;
use App\Services\Grading\GradeSubmissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GradeSubmissionService $submissionService,
        private readonly GradebookService $gradebookService
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user()->load(['roles', 'teacher']);
        $school = SchoolSetting::current();
        $activeSchoolYear = SchoolYear::current();
        $activePeriods = $activeSchoolYear ? $activeSchoolYear->gradingPeriods()->get() : collect();

        $requestedPeriodId = $request->integer('period_id');
        $currentPeriod = ($requestedPeriodId ? $activePeriods->firstWhere('id', $requestedPeriodId) : null)
            ?? $activePeriods->firstWhere('status', GradingPeriod::STATUS_OPEN)
            ?? $activePeriods->first();

        $adminStats = null;
        $adviserCards = collect();
        $teacherDashboard = null;
        $recentLogs = collect();

        if ($user->isAdmin()) {
            $activeAssignments = $activeSchoolYear
                ? TeacherAssignment::query()->where('school_year_id', $activeSchoolYear->id)->active()->get()
                : collect();
            $assignmentsCount = $activeAssignments->count();

            $submissionCounts = ['approved' => 0, 'submitted' => 0, 'returned' => 0, 'pending' => 0];
            if ($activeSchoolYear && $currentPeriod && $assignmentsCount > 0) {
                $ownSubs = GradeSubmission::query()
                    ->where('grading_period_id', $currentPeriod->id)
                    ->whereIn('teacher_assignment_id', $activeAssignments->pluck('id'))
                    ->get()
                    ->keyBy('teacher_assignment_id');

                $priorOfficialSubs = GradeSubmission::query()
                    ->with('teacherAssignment')
                    ->where('grading_period_id', $currentPeriod->id)
                    ->whereIn('status', [GradeSubmission::STATUS_SUBMITTED, GradeSubmission::STATUS_APPROVED])
                    ->whereHas('teacherAssignment', fn ($q) => $q->where('school_year_id', $activeSchoolYear->id)->where('is_active', false))
                    ->orderByDesc('submitted_at')
                    ->get()
                    ->unique(fn (GradeSubmission $s) => $s->teacherAssignment->section_id . ':' . $s->teacherAssignment->subject_id)
                    ->keyBy(fn (GradeSubmission $s) => $s->teacherAssignment->section_id . ':' . $s->teacherAssignment->subject_id);

                foreach ($activeAssignments as $ta) {
                    $sub = $ownSubs->get($ta->id) ?? $priorOfficialSubs->get($ta->section_id . ':' . $ta->subject_id);
                    if (! $sub) {
                        $submissionCounts['pending']++;
                    } elseif ($sub->status === GradeSubmission::STATUS_APPROVED) {
                        $submissionCounts['approved']++;
                    } elseif ($sub->status === GradeSubmission::STATUS_SUBMITTED) {
                        $submissionCounts['submitted']++;
                    } elseif ($sub->status === GradeSubmission::STATUS_RETURNED) {
                        $submissionCounts['returned']++;
                    } else {
                        $submissionCounts['pending']++;
                    }
                }
            }

            $officialSubmitted = $submissionCounts['approved'] + $submissionCounts['submitted'];
            $completionPct = $assignmentsCount > 0 ? (int) round(($officialSubmitted / $assignmentsCount) * 100) : 0;

            $adminStats = [
                'active_sy' => $activeSchoolYear?->name ?? 'Not Set',
                'current_period' => $currentPeriod,
                'periods' => $activePeriods,
                'students_count' => Student::query()->active()->count(),
                'teachers_count' => Teacher::query()->active()->count(),
                'sections_count' => $activeSchoolYear
                    ? Section::query()->where('school_year_id', $activeSchoolYear->id)->active()->count()
                    : Section::query()->active()->count(),
                'subjects_count' => Subject::query()->active()->count(),
                'assignments_count' => $assignmentsCount,
                'submitted_grades_count' => $officialSubmitted,
                'pending_grades_count' => $submissionCounts['pending'] + $submissionCounts['returned'],
                'submission_breakdown' => $submissionCounts,
                'completion_percent' => $completionPct,
                'curricula_count' => Curriculum::query()->active()->count(),
                'unconfirmed_policies' => PolicyReference::query()->where('is_confirmed_by_admin', false)->count(),
                'audit_events_today' => AuditLog::query()->whereDate('created_at', today())->count(),
            ];

            $recentLogs = AuditLog::query()
                ->with('actor')
                ->latest('created_at')
                ->limit(8)
                ->get();
        } elseif ($user->teacher) {
            $teacher = $user->teacher;

            // Adviser metrics (if teacher advises any sections)
            if ($user->isAdviser()) {
                $advisorySections = Section::query()
                    ->with(['gradeLevel', 'schoolYear'])
                    ->withCount('currentEnrollments')
                    ->where('adviser_id', $teacher->id)
                    ->when($activeSchoolYear, fn ($q) => $q->where('school_year_id', $activeSchoolYear->id))
                    ->get();

                $adviserCards = $advisorySections->map(function (Section $section) use ($currentPeriod) {
                    $matrix = $currentPeriod
                        ? $this->submissionService->getSectionCollectionMatrix($section, $currentPeriod)
                        : null;

                    return [
                        'section' => $section,
                        'students_count' => $section->current_enrollments_count,
                        'matrix' => $matrix,
                    ];
                });
            }

            // Subject Teacher metrics (applies to BOTH Teacher 1 Adviser and Teacher 2 Subject Teacher)
            $assignments = TeacherAssignment::query()
                ->with(['section.gradeLevel', 'subject', 'schoolYear'])
                ->where('teacher_id', $teacher->id)
                ->active()
                ->when($activeSchoolYear, fn ($q) => $q->where('school_year_id', $activeSchoolYear->id))
                ->orderBy('section_id')
                ->get();

            $studentCountsBySection = StudentEnrollment::query()
                ->whereIn('section_id', $assignments->pluck('section_id')->unique())
                ->where('is_current', true)
                ->selectRaw('section_id, COUNT(*) as cnt')
                ->groupBy('section_id')
                ->pluck('cnt', 'section_id');

            $assessmentCountsByTa = $currentPeriod
                ? Assessment::query()
                    ->whereIn('teacher_assignment_id', $assignments->pluck('id'))
                    ->where('grading_period_id', $currentPeriod->id)
                    ->selectRaw('teacher_assignment_id, COUNT(*) as cnt')
                    ->groupBy('teacher_assignment_id')
                    ->pluck('cnt', 'teacher_assignment_id')
                : collect();

            $missingScoresByTa = $currentPeriod
                ? QuarterlyGrade::query()
                    ->whereIn('teacher_assignment_id', $assignments->pluck('id'))
                    ->where('grading_period_id', $currentPeriod->id)
                    ->selectRaw('teacher_assignment_id, COALESCE(SUM(missing_scores_count), 0) as cnt')
                    ->groupBy('teacher_assignment_id')
                    ->pluck('cnt', 'teacher_assignment_id')
                : collect();

            $classRows = $assignments->map(function (TeacherAssignment $ta) use ($currentPeriod, $studentCountsBySection, $assessmentCountsByTa, $missingScoresByTa) {
                $sub = $currentPeriod ? $this->gradebookService->resolveEffectiveSubmission($ta, $currentPeriod) : null;
                $isPriorSub = $sub !== null && (int) $sub->teacher_assignment_id !== (int) $ta->id;
                $status = $sub?->status ?? (($assessmentCountsByTa[$ta->id] ?? 0) > 0 ? 'draft' : 'pending');

                return [
                    'assignment' => $ta,
                    'students_count' => (int) ($studentCountsBySection[$ta->section_id] ?? 0),
                    'assessments_count' => (int) ($assessmentCountsByTa[$ta->id] ?? 0),
                    'missing_scores' => $isPriorSub ? (int) ($sub->missing_scores_count ?? 0) : (int) ($missingScoresByTa[$ta->id] ?? 0),
                    'status' => $status,
                    'is_submitted' => $sub?->isOfficial() ?? false,
                    'prior_teacher_name' => $isPriorSub ? $sub->teacherAssignment?->teacher?->full_name : null,
                ];
            });

            $totalClasses = $classRows->count();
            $submittedClasses = $classRows->where('is_submitted', true)->count();

            $teacherDashboard = [
                'current_period' => $currentPeriod,
                'total_classes' => $totalClasses,
                'unique_sections' => $assignments->pluck('section_id')->unique()->count(),
                'total_students' => $classRows->sum('students_count'),
                'submitted_classes' => $submittedClasses,
                'pending_classes' => $totalClasses - $submittedClasses,
                'total_missing_scores' => $classRows->sum('missing_scores'),
                'progress_percent' => $totalClasses > 0 ? (int) round(($submittedClasses / $totalClasses) * 100) : 0,
                'class_rows' => $classRows,
            ];
        }

        $announcements = Announcement::query()
            ->with('author')
            ->forUser($user)
            ->limit(4)
            ->get();

        return view('dashboard.index', [
            'user' => $user,
            'school' => $school,
            'activeSchoolYear' => $activeSchoolYear,
            'activePeriods' => $activePeriods,
            'currentPeriod' => $currentPeriod,
            'announcements' => $announcements,
            'adminStats' => $adminStats,
            'adviserCards' => $adviserCards,
            'teacherDashboard' => $teacherDashboard,
            'recentLogs' => $recentLogs,
        ]);
    }
}
