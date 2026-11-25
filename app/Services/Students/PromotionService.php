<?php

namespace App\Services\Students;

use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Reports\ReportCardAssemblyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public const ACTION_PROMOTE = 'promote';
    public const ACTION_RETAIN = 'retain';
    public const ACTION_GRADUATE = 'graduate';

    public function __construct(
        private readonly ReportCardAssemblyService $assemblyService,
        private readonly EnrollmentService $enrollmentService,
        private readonly AuditLogService $auditLog
    ) {
    }

    /**
     * Evaluate all currently enrolled learners in a source section for year-end promotion/rollover.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluateSection(Section $sourceSection): Collection
    {
        $sourceSection->loadMissing(['schoolYear', 'gradeLevel']);

        $enrollments = StudentEnrollment::query()
            ->with(['student', 'gradeLevel', 'section', 'curriculum'])
            ->where('section_id', $sourceSection->id)
            ->where('school_year_id', $sourceSection->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn (StudentEnrollment $e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $isGraduatingLevel = $sourceSection->gradeLevel->code === 'G12';

        return $enrollments->map(function (StudentEnrollment $enrollment) use ($isGraduatingLevel) {
            $assembled = $this->assemblyService->assemble($enrollment);
            $failedSubjects = collect($assembled['subject_rows'])
                ->filter(fn (array $r) => $r['final_grade'] !== null && (float) $r['final_grade'] < 75.0)
                ->map(fn (array $r) => $r['subject']->code)
                ->values()
                ->all();

            $recommended = match ($assembled['promotion_status']) {
                'promoted' => $isGraduatingLevel ? self::ACTION_GRADUATE : self::ACTION_PROMOTE,
                'conditional' => 'conditional',
                'retained' => self::ACTION_RETAIN,
                default => 'incomplete',
            };

            return [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'general_average' => $assembled['general_average'],
                'is_descriptive_only' => $assembled['is_descriptive_only'],
                'promotion_status' => $assembled['promotion_status'],
                'failed_subjects' => $failedSubjects,
                'missing_count' => count($assembled['missing_subjects']),
                'recommended_action' => $recommended,
                'already_finalized' => in_array($enrollment->enrollment_status, [
                    StudentEnrollment::STATUS_PROMOTED,
                    StudentEnrollment::STATUS_RETAINED,
                    StudentEnrollment::STATUS_COMPLETED,
                ], true),
            ];
        });
    }

    /**
     * Execute batch year-end promotion, retention, or graduation for selected enrollments.
     *
     * @param  array<int, int>  $enrollmentIds
     * @return array{processed: int, enrolled: int, skipped: array<int, string>}
     */
    public function executeBatch(
        Section $sourceSection,
        array $enrollmentIds,
        string $action,
        ?Section $targetSection,
        User $actor
    ): array {
        if (empty($enrollmentIds)) {
            throw ValidationException::withMessages([
                'enrollment_ids' => 'Select at least one learner to process.',
            ]);
        }

        if (! in_array($action, [self::ACTION_PROMOTE, self::ACTION_RETAIN, self::ACTION_GRADUATE], true)) {
            throw ValidationException::withMessages([
                'action' => 'Invalid promotion action selected.',
            ]);
        }

        if ($targetSection && (int) $targetSection->school_year_id === (int) $sourceSection->school_year_id) {
            throw ValidationException::withMessages([
                'target_section_id' => 'Target section for year-end rollover must belong to a different School Year. Use Section Transfer for same-year section changes.',
            ]);
        }

        $enrollments = StudentEnrollment::query()
            ->with(['student', 'schoolYear', 'gradeLevel', 'section'])
            ->where('section_id', $sourceSection->id)
            ->where('school_year_id', $sourceSection->school_year_id)
            ->where('is_current', true)
            ->whereIn('id', $enrollmentIds)
            ->get();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'enrollment_ids' => 'No valid active enrollments found for the selected section.',
            ]);
        }

        $processed = 0;
        $enrolled = 0;
        $skipped = [];

        DB::transaction(function () use ($enrollments, $action, $targetSection, $actor, &$processed, &$enrolled, &$skipped): void {
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;
                $oldStatus = $enrollment->enrollment_status;

                $newEnrollmentStatus = match ($action) {
                    self::ACTION_PROMOTE => StudentEnrollment::STATUS_PROMOTED,
                    self::ACTION_RETAIN => StudentEnrollment::STATUS_RETAINED,
                    self::ACTION_GRADUATE => StudentEnrollment::STATUS_COMPLETED,
                };

                $enrollment->update([
                    'enrollment_status' => $newEnrollmentStatus,
                ]);
                $processed++;

                $newEnrollmentId = null;

                if ($action === self::ACTION_GRADUATE) {
                    $student->update(['status' => Student::STATUS_GRADUATED]);
                } elseif ($targetSection) {
                    $alreadyInTargetSy = StudentEnrollment::query()
                        ->where('student_id', $student->id)
                        ->where('school_year_id', $targetSection->school_year_id)
                        ->where('is_current', true)
                        ->exists();

                    if ($alreadyInTargetSy) {
                        $skipped[] = "{$student->formal_name} is already actively enrolled in SY {$targetSection->schoolYear->name} (source status updated to {$newEnrollmentStatus}).";
                    } else {
                        $created = $this->enrollmentService->enrollStudent($student, $targetSection);
                        $newEnrollmentId = $created->id;
                        $enrolled++;
                    }
                }

                $this->auditLog->record(
                    eventType: "student.batch_{$action}",
                    auditable: $enrollment,
                    oldValues: ['enrollment_status' => $oldStatus],
                    newValues: [
                        'enrollment_status' => $newEnrollmentStatus,
                        'target_section_id' => $targetSection?->id,
                        'new_enrollment_id' => $newEnrollmentId,
                        'student_status' => $student->status,
                    ],
                    actor: $actor,
                    context: ['student_id' => $student->id]
                );
            }
        });

        return [
            'processed' => $processed,
            'enrolled' => $enrolled,
            'skipped' => $skipped,
        ];
    }
}
