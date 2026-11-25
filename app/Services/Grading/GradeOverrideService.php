<?php

namespace App\Services\Grading;

use App\Models\QuarterlyGrade;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeOverrideService
{
    public function __construct(
        private readonly GradeCalculationService $calculator,
        private readonly AuditLogService $auditLog
    ) {
    }

    /**
     * Admin-authorized override of a submitted or locked grade with mandatory reason and immutable audit log.
     */
    public function override(
        QuarterlyGrade $grade,
        User $admin,
        ?float $newFinalGrade,
        ?string $newDescriptiveRating,
        string $reason
    ): QuarterlyGrade {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'reason' => 'A detailed reason (at least 5 characters) is mandatory for authorized grade overrides.',
            ]);
        }

        $grade->loadMissing(['enrollment', 'gradingConfiguration', 'teacherAssignment']);
        $config = $grade->gradingConfiguration;

        $previousDisplay = $grade->display_grade;

        DB::transaction(function () use ($grade, $config, $newFinalGrade, $newDescriptiveRating): void {
            if ($config->isDescriptive()) {
                $clean = strtoupper(trim((string) $newDescriptiveRating));
                $computed = $this->calculator->computePeriodGrade($config, [], $clean);
                $grade->update([
                    'descriptive_rating' => $computed['descriptive_rating'],
                    'remarks' => $computed['remarks'],
                    'computed_at' => now(),
                ]);
            } else {
                $rounded = round((float) $newFinalGrade, $config->rounding_precision);
                $grade->update([
                    'final_grade' => $rounded,
                    'remarks' => $this->calculator->resolveRemarks($config, $rounded),
                    'computed_at' => now(),
                ]);
            }
        });

        $grade->refresh();

        $this->auditLog->recordGradeChange(
            eventType: 'grade.admin_override',
            auditable: $grade,
            teacherId: $grade->teacherAssignment->teacher_id,
            studentId: $grade->enrollment->student_id,
            subjectId: $grade->subject_id,
            previousGrade: $previousDisplay,
            updatedGrade: $grade->display_grade,
            reason: $reason,
            actor: $admin
        );

        return $grade;
    }
}
