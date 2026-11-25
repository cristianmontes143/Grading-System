<?php

namespace App\Services\Students;

use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Audit\AuditLogService;
use App\Services\Curriculum\CurriculumResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function __construct(
        private readonly CurriculumResolverService $curriculumResolver,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function enrollStudent(Student $student, Section $section, ?string $enrolledAt = null): StudentEnrollment
    {
        if ($student->status === Student::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'student_id' => 'Archived students must be restored before enrolling.',
            ]);
        }

        $curriculum = $this->curriculumResolver->resolveFor($section->school_year_id, $section->grade_level_id);
        if (! $curriculum) {
            throw ValidationException::withMessages([
                'section_id' => "No curriculum is assigned to {$section->gradeLevel->name} for SY {$section->schoolYear->name}. Map it in Curricula & MATATAG first.",
            ]);
        }

        $existingCurrent = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('school_year_id', $section->school_year_id)
            ->where('is_current', true)
            ->first();

        if ($existingCurrent) {
            throw ValidationException::withMessages([
                'section_id' => "Student is already actively enrolled in SY {$section->schoolYear->name}. Use Section Transfer to change sections.",
            ]);
        }

        $enrollment = DB::transaction(function () use ($student, $section, $curriculum, $enrolledAt): StudentEnrollment {
            return StudentEnrollment::query()->create([
                'student_id' => $student->id,
                'school_year_id' => $section->school_year_id,
                'grade_level_id' => $section->grade_level_id,
                'section_id' => $section->id,
                'curriculum_id' => $curriculum->id,
                'shs_track_id' => $section->shs_track_id,
                'shs_strand_id' => $section->shs_strand_id,
                'enrollment_status' => StudentEnrollment::STATUS_ENROLLED,
                'enrolled_at' => $enrolledAt ?? now()->toDateString(),
                'is_current' => true,
            ]);
        });

        $this->auditLog->record(
            eventType: 'student.enrolled',
            auditable: $enrollment,
            newValues: [
                'student_id' => $student->id,
                'school_year_id' => $section->school_year_id,
                'section_id' => $section->id,
                'curriculum_id' => $curriculum->id,
            ],
            context: ['student_id' => $student->id]
        );

        return $enrollment;
    }

    /**
     * Transfer a student to another section within the same school year while
     * preserving the original enrollment record and all prior academic records.
     */
    public function transferSection(
        StudentEnrollment $currentEnrollment,
        Section $targetSection,
        string $reason
    ): StudentEnrollment {
        if (! $currentEnrollment->is_current) {
            throw ValidationException::withMessages([
                'target_section_id' => 'Only a student\'s current active enrollment can be transferred.',
            ]);
        }

        if ((int) $currentEnrollment->school_year_id !== (int) $targetSection->school_year_id) {
            throw ValidationException::withMessages([
                'target_section_id' => 'Section transfers must occur within the same School Year.',
            ]);
        }

        if ((int) $currentEnrollment->section_id === (int) $targetSection->id) {
            throw ValidationException::withMessages([
                'target_section_id' => 'Target section must be different from the student\'s current section.',
            ]);
        }

        $targetCurriculum = $this->curriculumResolver->resolveFor(
            $targetSection->school_year_id,
            $targetSection->grade_level_id
        );

        if (! $targetCurriculum) {
            throw ValidationException::withMessages([
                'target_section_id' => "No curriculum is mapped for {$targetSection->gradeLevel->name} in SY {$targetSection->schoolYear->name}.",
            ]);
        }

        $oldSectionId = $currentEnrollment->section_id;

        $newEnrollment = DB::transaction(function () use ($currentEnrollment, $targetSection, $targetCurriculum, $reason): StudentEnrollment {
            $currentEnrollment->update([
                'is_current' => false,
                'enrollment_status' => StudentEnrollment::STATUS_TRANSFERRED_SECTION,
                'transfer_reason' => trim($reason),
            ]);

            return StudentEnrollment::query()->create([
                'student_id' => $currentEnrollment->student_id,
                'school_year_id' => $targetSection->school_year_id,
                'grade_level_id' => $targetSection->grade_level_id,
                'section_id' => $targetSection->id,
                'curriculum_id' => $targetCurriculum->id,
                'shs_track_id' => $targetSection->shs_track_id,
                'shs_strand_id' => $targetSection->shs_strand_id,
                'enrollment_status' => StudentEnrollment::STATUS_ENROLLED,
                'enrolled_at' => now()->toDateString(),
                'transferred_from_enrollment_id' => $currentEnrollment->id,
                'transfer_reason' => trim($reason),
                'is_current' => true,
            ]);
        });

        $this->auditLog->record(
            eventType: 'student.section_transferred',
            auditable: $newEnrollment,
            oldValues: [
                'enrollment_id' => $currentEnrollment->id,
                'section_id' => $oldSectionId,
            ],
            newValues: [
                'enrollment_id' => $newEnrollment->id,
                'section_id' => $targetSection->id,
                'curriculum_id' => $targetCurriculum->id,
            ],
            reason: trim($reason),
            context: ['student_id' => $currentEnrollment->student_id]
        );

        return $newEnrollment;
    }
}
