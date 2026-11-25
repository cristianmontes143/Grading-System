<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\SchoolYear;
use App\Models\TeacherAssignment;
use App\Notifications\GradeSubmissionNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Grading\GradeOverrideService;
use App\Services\Grading\GradeSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeSubmissionController extends Controller
{
    public function __construct(
        private readonly GradeSubmissionService $submissionService,
        private readonly GradeOverrideService $overrideService,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function index(Request $request): View
    {
        $schoolYears = SchoolYear::query()->with('gradingPeriods')->orderByDesc('start_date')->get();
        $selectedSy = $schoolYears->firstWhere('id', $request->integer('school_year_id'))
            ?? $schoolYears->firstWhere('is_active', true)
            ?? $schoolYears->first();

        $periods = $selectedSy?->gradingPeriods ?? collect();
        $selectedPeriod = $periods->firstWhere('id', $request->integer('grading_period_id'))
            ?? $periods->firstWhere('status', GradingPeriod::STATUS_OPEN)
            ?? $periods->first();

        $gradeLevels = GradeLevel::query()->active()->ordered()->get();

        $rows = collect();
        $stats = ['total' => 0, 'submitted' => 0, 'approved' => 0, 'pending' => 0, 'returned' => 0];

        if ($selectedSy && $selectedPeriod) {
            $assignmentsQuery = TeacherAssignment::query()
                ->with(['section.gradeLevel', 'section.adviser', 'subject', 'teacher'])
                ->where('school_year_id', $selectedSy->id)
                ->where('is_active', true)
                ->orderBy('section_id')
                ->orderBy('subject_id');

            if ($glId = $request->integer('grade_level_id')) {
                $assignmentsQuery->whereHas('section', fn ($q) => $q->where('grade_level_id', $glId));
            }

            $assignments = $assignmentsQuery->get();

            $submissions = GradeSubmission::query()
                ->with('teacherAssignment.teacher')
                ->whereIn('teacher_assignment_id', $assignments->pluck('id'))
                ->where('grading_period_id', $selectedPeriod->id)
                ->get()
                ->keyBy('teacher_assignment_id');

            $priorOfficialSubmissions = GradeSubmission::query()
                ->with('teacherAssignment.teacher')
                ->where('grading_period_id', $selectedPeriod->id)
                ->whereIn('status', [GradeSubmission::STATUS_SUBMITTED, GradeSubmission::STATUS_APPROVED])
                ->whereHas('teacherAssignment', function ($q) use ($selectedSy): void {
                    $q->where('school_year_id', $selectedSy->id)
                        ->where('is_active', false);
                })
                ->orderByDesc('submitted_at')
                ->get()
                ->unique(fn (GradeSubmission $s) => $s->teacherAssignment->section_id . ':' . $s->teacherAssignment->subject_id)
                ->keyBy(fn (GradeSubmission $s) => $s->teacherAssignment->section_id . ':' . $s->teacherAssignment->subject_id);

            $rows = $assignments->map(function (TeacherAssignment $ta) use ($submissions, $priorOfficialSubmissions) {
                $ownSub = $submissions->get($ta->id);
                $sub = $ownSub ?? $priorOfficialSubmissions->get($ta->section_id . ':' . $ta->subject_id);
                $status = $sub?->status ?? GradeSubmission::STATUS_PENDING;
                $priorTeacherName = (! $ownSub && $sub)
                    ? $sub->teacherAssignment?->teacher?->full_name
                    : null;

                return [
                    'assignment' => $ta,
                    'submission' => $sub,
                    'status' => $status,
                    'prior_teacher_name' => $priorTeacherName,
                ];
            });

            $stats['total'] = $rows->count();
            $stats['submitted'] = $rows->where('status', GradeSubmission::STATUS_SUBMITTED)->count();
            $stats['approved'] = $rows->where('status', GradeSubmission::STATUS_APPROVED)->count();
            $stats['returned'] = $rows->where('status', GradeSubmission::STATUS_RETURNED)->count();
            $stats['pending'] = $rows->whereIn('status', [GradeSubmission::STATUS_PENDING, GradeSubmission::STATUS_DRAFT])->count();

            if ($statusFilter = $request->query('status')) {
                $rows = $rows->where('status', $statusFilter)->values();
            }
        }

        return view('admin.submissions.index', [
            'schoolYears' => $schoolYears,
            'selectedSy' => $selectedSy,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'gradeLevels' => $gradeLevels,
            'rows' => $rows,
            'stats' => $stats,
        ]);
    }

    public function show(GradeSubmission $gradeSubmission): View
    {
        $gradeSubmission->load([
            'teacherAssignment.section.gradeLevel',
            'teacherAssignment.section.adviser',
            'teacherAssignment.subject',
            'teacherAssignment.teacher',
            'gradingPeriod',
            'submitter',
            'reviewer',
        ]);

        $grades = QuarterlyGrade::query()
            ->with(['enrollment.student', 'gradingConfiguration'])
            ->where('teacher_assignment_id', $gradeSubmission->teacher_assignment_id)
            ->where('grading_period_id', $gradeSubmission->grading_period_id)
            ->get()
            ->sortBy(fn ($g) => $g->enrollment->student->last_name . ' ' . $g->enrollment->student->first_name)
            ->values();

        return view('admin.submissions.show', [
            'submission' => $gradeSubmission,
            'grades' => $grades,
        ]);
    }

    public function approve(Request $request, GradeSubmission $gradeSubmission): RedirectResponse
    {
        $this->submissionService->approve($gradeSubmission, $request->user());

        return back()->with('status', 'Grade submission approved.');
    }

    public function approveAllSubmitted(Request $request, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $gradeLevelId = $request->integer('grade_level_id') ?: null;

        $submissions = GradeSubmission::query()
            ->with(['teacherAssignment.section', 'teacherAssignment.teacher.user', 'teacherAssignment.subject', 'gradingPeriod'])
            ->where('grading_period_id', $gradingPeriod->id)
            ->where('status', GradeSubmission::STATUS_SUBMITTED)
            ->whereHas('teacherAssignment', function ($q) use ($gradingPeriod, $gradeLevelId): void {
                $q->where('school_year_id', $gradingPeriod->school_year_id);
                if ($gradeLevelId) {
                    $q->whereHas('section', fn ($secQ) => $secQ->where('grade_level_id', $gradeLevelId));
                }
            })
            ->get();

        $approvedCount = 0;
        foreach ($submissions as $sub) {
            $this->submissionService->approve($sub, $request->user());
            $approvedCount++;
        }

        return back()->with('status', $approvedCount > 0
            ? "Approved {$approvedCount} submitted grade class(es) for {$gradingPeriod->name}."
            : "No pending 'Submitted' grade classes found to approve for {$gradingPeriod->name}.");
    }

    public function returnSubmission(Request $request, GradeSubmission $gradeSubmission): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->submissionService->returnForRevision($gradeSubmission, $request->user(), $validated['reason']);

        return back()->with('status', 'Grade submission returned to subject teacher for revision and logged.');
    }

    public function overrideGrade(Request $request, QuarterlyGrade $quarterlyGrade): RedirectResponse
    {
        $validated = $request->validate([
            'final_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'descriptive_rating' => ['nullable', 'string', 'max:30'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->overrideService->override(
            $quarterlyGrade,
            $request->user(),
            isset($validated['final_grade']) ? (float) $validated['final_grade'] : null,
            $validated['descriptive_rating'] ?? null,
            $validated['reason']
        );

        return back()->with('status', 'Authorized grade override saved and recorded in the immutable audit log.');
    }

    public function remind(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $teacherAssignment->loadMissing(['teacher.user', 'subject', 'section.gradeLevel']);
        $teacherUser = $teacherAssignment->teacher?->user;

        if (! $teacherUser) {
            return back()->with('error', 'No active user account found for this teacher.');
        }

        $teacherUser->notify(new GradeSubmissionNotification(
            title: "Reminder: Submit {$gradingPeriod->code} Grades ({$teacherAssignment->subject->name})",
            message: "School Administration reminds you to finalize and submit {$gradingPeriod->name} grades for {$teacherAssignment->section->gradeLevel->name} – {$teacherAssignment->section->name}.",
            url: route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod]),
            type: 'warning'
        ));

        $this->auditLog->record(
            eventType: 'grade_submission.reminder_sent',
            auditable: $teacherAssignment,
            newValues: ['grading_period_id' => $gradingPeriod->id, 'period_code' => $gradingPeriod->code],
            actor: $request->user(),
            context: ['teacher_id' => $teacherAssignment->teacher_id, 'subject_id' => $teacherAssignment->subject_id]
        );

        return back()->with('status', "Submission reminder sent to {$teacherAssignment->teacher->full_name} for {$teacherAssignment->subject->name}.");
    }

    public function remindAllPending(Request $request, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $assignments = TeacherAssignment::query()
            ->with(['teacher.user', 'subject', 'section.gradeLevel'])
            ->where('school_year_id', $gradingPeriod->school_year_id)
            ->where('is_active', true)
            ->get();

        $officialTaIds = GradeSubmission::query()
            ->where('grading_period_id', $gradingPeriod->id)
            ->whereIn('status', [GradeSubmission::STATUS_SUBMITTED, GradeSubmission::STATUS_APPROVED])
            ->pluck('teacher_assignment_id')
            ->all();

        $priorOfficialKeys = GradeSubmission::query()
            ->with('teacherAssignment')
            ->where('grading_period_id', $gradingPeriod->id)
            ->whereIn('status', [GradeSubmission::STATUS_SUBMITTED, GradeSubmission::STATUS_APPROVED])
            ->whereHas('teacherAssignment', fn ($q) => $q->where('school_year_id', $gradingPeriod->school_year_id)->where('is_active', false))
            ->get()
            ->map(fn (GradeSubmission $s) => $s->teacherAssignment->section_id . ':' . $s->teacherAssignment->subject_id)
            ->unique()
            ->all();

        $sentCount = 0;
        foreach ($assignments as $ta) {
            if (in_array($ta->id, $officialTaIds, true) || in_array($ta->section_id . ':' . $ta->subject_id, $priorOfficialKeys, true)) {
                continue;
            }
            $teacherUser = $ta->teacher?->user;
            if (! $teacherUser) {
                continue;
            }

            $teacherUser->notify(new GradeSubmissionNotification(
                title: "Reminder: Submit {$gradingPeriod->code} Grades ({$ta->subject->name})",
                message: "School Administration reminds you to finalize and submit {$gradingPeriod->name} grades for {$ta->section->gradeLevel->name} – {$ta->section->name}.",
                url: route('teacher.gradebook.show', [$ta, $gradingPeriod]),
                type: 'warning'
            ));
            $sentCount++;
        }

        $this->auditLog->record(
            eventType: 'grade_submission.bulk_reminder_sent',
            auditable: $gradingPeriod,
            newValues: ['period_code' => $gradingPeriod->code, 'reminders_sent' => $sentCount],
            actor: $request->user()
        );

        return back()->with('status', "Sent {$sentCount} submission reminder(s) to teachers with pending {$gradingPeriod->name} grades.");
    }
}
