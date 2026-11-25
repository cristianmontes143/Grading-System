<?php

namespace App\Services\Grading;

use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Notifications\GradeSubmissionNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Curriculum\CurriculumResolverService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeSubmissionService
{
    public function __construct(
        private readonly GradebookService $gradebookService,
        private readonly CurriculumResolverService $curriculumResolver,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function submit(TeacherAssignment $assignment, GradingPeriod $period, User $actor): GradeSubmission
    {
        $this->gradebookService->ensureEditable($assignment, $period);

        $assignment->loadMissing(['section.gradeLevel', 'section.adviser.user', 'subject', 'teacher', 'schoolYear']);

        // Ensure latest numerical computations are up to date before submission
        $this->gradebookService->recalculateForClass($assignment, $period);

        $enrollments = StudentEnrollment::query()
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->get();

        $totalStudents = $enrollments->count();
        if ($totalStudents === 0) {
            throw ValidationException::withMessages([
                'submission' => 'Cannot submit grades for a section with no enrolled students.',
            ]);
        }

        $grades = QuarterlyGrade::query()
            ->where('subject_id', $assignment->subject_id)
            ->where('grading_period_id', $period->id)
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->get();

        $gradedStudents = $grades->filter(fn (QuarterlyGrade $g) => $g->final_grade !== null || $g->descriptive_rating !== null)->count();
        $missingScores = (int) $grades->sum('missing_scores_count');

        if ($gradedStudents < $totalStudents) {
            throw ValidationException::withMessages([
                'submission' => "Cannot submit yet: only {$gradedStudents} of {$totalStudents} enrolled student(s) have computed grades. Please encode scores/ratings first.",
            ]);
        }

        $submission = DB::transaction(function () use ($assignment, $period, $actor, $enrollments, $totalStudents, $gradedStudents, $missingScores): GradeSubmission {
            QuarterlyGrade::query()
                ->where('subject_id', $assignment->subject_id)
                ->where('grading_period_id', $period->id)
                ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->update([
                    'teacher_assignment_id' => $assignment->id,
                    'is_official' => true,
                    'is_locked' => true,
                ]);

            return GradeSubmission::query()->updateOrCreate(
                [
                    'teacher_assignment_id' => $assignment->id,
                    'grading_period_id' => $period->id,
                ],
                [
                    'status' => GradeSubmission::STATUS_SUBMITTED,
                    'total_students' => $totalStudents,
                    'graded_students' => $gradedStudents,
                    'missing_scores_count' => $missingScores,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'return_reason' => null,
                ]
            );
        });

        $this->auditLog->record(
            eventType: 'grade_submission.submitted',
            auditable: $submission,
            newValues: [
                'status' => GradeSubmission::STATUS_SUBMITTED,
                'total_students' => $totalStudents,
                'graded_students' => $gradedStudents,
                'missing_scores_count' => $missingScores,
            ],
            actor: $actor,
            context: [
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
            ]
        );

        // Automatically notify the Section Adviser
        $adviserUser = $assignment->section->adviser?->user;
        if ($adviserUser && $adviserUser->id !== $actor->id) {
            $adviserUser->notify(new GradeSubmissionNotification(
                title: "Grades Submitted: {$assignment->subject->name} ({$period->code})",
                message: "{$assignment->teacher->full_name} submitted {$period->name} grades for {$assignment->section->gradeLevel->name} – {$assignment->section->name}.",
                url: route('adviser.sections.show', ['section' => $assignment->section_id, 'period_id' => $period->id]),
                type: 'success'
            ));
        }

        return $submission;
    }

    public function approve(GradeSubmission $submission, User $admin): GradeSubmission
    {
        $submission->loadMissing(['teacherAssignment.teacher.user', 'teacherAssignment.subject', 'teacherAssignment.section.gradeLevel', 'gradingPeriod']);
        $oldStatus = $submission->status;

        DB::transaction(function () use ($submission, $admin): void {
            QuarterlyGrade::query()
                ->where('teacher_assignment_id', $submission->teacher_assignment_id)
                ->where('grading_period_id', $submission->grading_period_id)
                ->update([
                    'is_official' => true,
                    'is_locked' => true,
                ]);

            $submission->update([
                'status' => GradeSubmission::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'return_reason' => null,
            ]);
        });

        $this->auditLog->record(
            eventType: 'grade_submission.approved',
            auditable: $submission,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => GradeSubmission::STATUS_APPROVED],
            actor: $admin,
            context: [
                'teacher_id' => $submission->teacherAssignment->teacher_id,
                'subject_id' => $submission->teacherAssignment->subject_id,
            ]
        );

        $teacherUser = $submission->teacherAssignment->teacher->user;
        if ($teacherUser) {
            $teacherUser->notify(new GradeSubmissionNotification(
                title: "Grades Approved: {$submission->teacherAssignment->subject->name} ({$submission->gradingPeriod->code})",
                message: "Your {$submission->gradingPeriod->name} grade submission for {$submission->teacherAssignment->section->gradeLevel->name} – {$submission->teacherAssignment->section->name} has been approved.",
                url: route('teacher.gradebook.show', [$submission->teacher_assignment_id, $submission->grading_period_id]),
                type: 'success'
            ));
        }

        return $submission->fresh();
    }

    public function returnForRevision(GradeSubmission $submission, User $admin, string $reason): GradeSubmission
    {
        $submission->loadMissing(['teacherAssignment.teacher.user', 'teacherAssignment.subject', 'teacherAssignment.section.gradeLevel', 'gradingPeriod']);
        $oldStatus = $submission->status;

        DB::transaction(function () use ($submission, $admin, $reason): void {
            $enrollmentIds = StudentEnrollment::query()
                ->where('section_id', $submission->teacherAssignment->section_id)
                ->where('school_year_id', $submission->teacherAssignment->school_year_id)
                ->where('is_current', true)
                ->pluck('id');

            QuarterlyGrade::query()
                ->where('grading_period_id', $submission->grading_period_id)
                ->where(function ($query) use ($submission, $enrollmentIds): void {
                    $query->where('teacher_assignment_id', $submission->teacher_assignment_id);

                    if ($enrollmentIds->isNotEmpty()) {
                        $query->orWhere(function ($sub) use ($submission, $enrollmentIds): void {
                            $sub->where('subject_id', $submission->teacherAssignment->subject_id)
                                ->whereIn('student_enrollment_id', $enrollmentIds);
                        });
                    }
                })
                ->update([
                    'is_official' => false,
                    'is_locked' => false,
                ]);

            $submission->update([
                'status' => GradeSubmission::STATUS_RETURNED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'return_reason' => trim($reason),
            ]);
        });

        $this->auditLog->record(
            eventType: 'grade_submission.returned',
            auditable: $submission,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => GradeSubmission::STATUS_RETURNED],
            reason: trim($reason),
            actor: $admin,
            context: [
                'teacher_id' => $submission->teacherAssignment->teacher_id,
                'subject_id' => $submission->teacherAssignment->subject_id,
            ]
        );

        $activeAssignment = $submission->teacherAssignment->is_active
            ? $submission->teacherAssignment
            : TeacherAssignment::query()
                ->with('teacher.user')
                ->where('school_year_id', $submission->teacherAssignment->school_year_id)
                ->where('section_id', $submission->teacherAssignment->section_id)
                ->where('subject_id', $submission->teacherAssignment->subject_id)
                ->where('is_active', true)
                ->first();

        $notifyAssignment = $activeAssignment ?? $submission->teacherAssignment;
        $teacherUser = $notifyAssignment->teacher?->user;
        if ($teacherUser) {
            $teacherUser->notify(new GradeSubmissionNotification(
                title: "Grades Returned for Revision: {$submission->teacherAssignment->subject->name} ({$submission->gradingPeriod->code})",
                message: "Returned by Administrator. Reason: {$reason}",
                url: route('teacher.gradebook.show', [$notifyAssignment->id, $submission->grading_period_id]),
                type: 'warning'
            ));
        }

        return $submission->fresh();
    }

    /**
     * Automatic Grade Collection Matrix for a Section + Grading Period.
     * Exposes ONLY official/submitted grades for consolidation and report cards.
     *
     * @return array{
     *   subject_rows: Collection,
     *   total_subjects: int,
     *   submitted_subjects: int,
     *   pending_subjects: int,
     *   progress_percent: int,
     *   ready_for_report_cards: bool,
     *   official_grades_matrix: array<int, array<int, QuarterlyGrade>>,
     *   students_with_incomplete_grades: int
     * }
     */
    public function getSectionCollectionMatrix(Section $section, GradingPeriod $period): array
    {
        $offerings = $this->curriculumResolver->getOfferingsForSection($section)
            ->filter(fn ($s) => $s->is_graded)
            ->values();

        $assignmentsBySubject = TeacherAssignment::query()
            ->with('teacher')
            ->where('section_id', $section->id)
            ->where('school_year_id', $section->school_year_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('subject_id');

        $submissionsByAssignment = GradeSubmission::query()
            ->with('teacherAssignment.teacher')
            ->whereIn('teacher_assignment_id', $assignmentsBySubject->pluck('id'))
            ->where('grading_period_id', $period->id)
            ->get()
            ->keyBy('teacher_assignment_id');

        // Fall back to official submissions from prior (reassigned) teacher assignments for the same section+subject
        $priorOfficialSubmissionsBySubject = GradeSubmission::query()
            ->with('teacherAssignment.teacher')
            ->where('grading_period_id', $period->id)
            ->whereIn('status', [GradeSubmission::STATUS_SUBMITTED, GradeSubmission::STATUS_APPROVED])
            ->whereHas('teacherAssignment', function ($q) use ($section): void {
                $q->where('section_id', $section->id)
                    ->where('school_year_id', $section->school_year_id)
                    ->where('is_active', false);
            })
            ->orderByDesc('submitted_at')
            ->get()
            ->unique(fn (GradeSubmission $s) => $s->teacherAssignment->subject_id)
            ->keyBy(fn (GradeSubmission $s) => $s->teacherAssignment->subject_id);

        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->where('school_year_id', $section->school_year_id)
            ->where('is_current', true)
            ->get();

        // Only OFFICIAL grades are collected for the adviser summary & report cards
        $officialGrades = QuarterlyGrade::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('grading_period_id', $period->id)
            ->where('is_official', true)
            ->get();

        $officialMatrix = [];
        foreach ($officialGrades as $qg) {
            $officialMatrix[$qg->student_enrollment_id][$qg->subject_id] = $qg;
        }

        $draftCountsByAssignment = QuarterlyGrade::query()
            ->whereIn('teacher_assignment_id', $assignmentsBySubject->pluck('id'))
            ->where('grading_period_id', $period->id)
            ->where(function ($q) {
                $q->whereNotNull('final_grade')->orWhereNotNull('descriptive_rating');
            })
            ->selectRaw('teacher_assignment_id, COUNT(*) as cnt')
            ->groupBy('teacher_assignment_id')
            ->pluck('cnt', 'teacher_assignment_id');

        $subjectRows = $offerings->map(function ($subject) use ($assignmentsBySubject, $submissionsByAssignment, $priorOfficialSubmissionsBySubject, $draftCountsByAssignment, $enrollments) {
            $assignment = $assignmentsBySubject->get($subject->id);
            $submission = ($assignment ? $submissionsByAssignment->get($assignment->id) : null)
                ?? $priorOfficialSubmissionsBySubject->get($subject->id);

            $priorTeacherName = ($submission && (! $assignment || (int) $submission->teacher_assignment_id !== (int) $assignment->id))
                ? $submission->teacherAssignment?->teacher?->full_name
                : null;

            if (! $assignment && ! $submission) {
                $status = 'unassigned';
            } elseif ($submission) {
                $status = $submission->status;
            } elseif (($draftCountsByAssignment[$assignment->id] ?? 0) > 0) {
                $status = GradeSubmission::STATUS_DRAFT;
            } else {
                $status = GradeSubmission::STATUS_PENDING;
            }

            return [
                'subject' => $subject,
                'assignment' => $assignment,
                'teacher' => $assignment?->teacher,
                'submitted_by_prior_teacher' => $priorTeacherName,
                'submission' => $submission,
                'status' => $status,
                'is_official' => $submission?->isOfficial() ?? false,
                'graded_count' => $submission?->graded_students ?? (int) ($draftCountsByAssignment[$assignment?->id] ?? 0),
                'total_students' => $enrollments->count(),
                'missing_scores' => $submission?->missing_scores_count ?? 0,
                'submitted_at' => $submission?->submitted_at,
            ];
        });

        $totalSubjects = $subjectRows->count();
        $submittedSubjects = $subjectRows->where('is_official', true)->count();
        $pendingSubjects = $totalSubjects - $submittedSubjects;
        $progressPercent = $totalSubjects > 0 ? (int) round(($submittedSubjects / $totalSubjects) * 100) : 0;

        $studentsWithIncomplete = 0;
        foreach ($enrollments as $enr) {
            foreach ($offerings as $sub) {
                if (! isset($officialMatrix[$enr->id][$sub->id])) {
                    $studentsWithIncomplete++;
                    break;
                }
            }
        }

        return [
            'subject_rows' => $subjectRows,
            'total_subjects' => $totalSubjects,
            'submitted_subjects' => $submittedSubjects,
            'pending_subjects' => $pendingSubjects,
            'progress_percent' => $progressPercent,
            'ready_for_report_cards' => $totalSubjects > 0 && $pendingSubjects === 0 && $studentsWithIncomplete === 0,
            'official_grades_matrix' => $officialMatrix,
            'students_with_incomplete_grades' => $studentsWithIncomplete,
        ];
    }
}
