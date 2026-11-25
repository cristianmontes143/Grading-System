<?php

namespace App\Services\Academic;

use App\Models\Role;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Services\Audit\AuditLogService;
use App\Services\Curriculum\CurriculumResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAssignmentService
{
    public function __construct(
        private readonly CurriculumResolverService $curriculumResolver,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function assignAdviser(Section $section, ?Teacher $teacher): Section
    {
        if ($teacher && ! $teacher->is_active) {
            throw ValidationException::withMessages([
                'adviser_id' => 'Cannot assign an inactive teacher as section adviser.',
            ]);
        }

        $oldAdviserId = $section->adviser_id;

        DB::transaction(function () use ($section, $teacher): void {
            $section->update(['adviser_id' => $teacher?->id]);

            if ($teacher) {
                $adviserRole = Role::query()->where('name', Role::ADVISER)->first();
                if ($adviserRole) {
                    $teacher->user->roles()->syncWithoutDetaching([$adviserRole->id]);
                }
            }
        });

        $this->auditLog->record(
            eventType: 'section.adviser_assigned',
            auditable: $section,
            oldValues: ['adviser_id' => $oldAdviserId],
            newValues: ['adviser_id' => $teacher?->id, 'adviser_name' => $teacher?->full_name],
            context: ['teacher_id' => $teacher?->id]
        );

        return $section->fresh(['adviser']);
    }

    public function assignSubjectTeacher(
        Section $section,
        Subject $subject,
        Teacher $teacher,
        ?string $scheduleSummary = null
    ): TeacherAssignment {
        if (! $teacher->is_active) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Cannot assign an inactive teacher to a subject.',
            ]);
        }

        if ((int) $subject->grade_level_id !== (int) $section->grade_level_id) {
            throw ValidationException::withMessages([
                'subject_id' => 'Selected subject does not match the section grade level.',
            ]);
        }

        $resolvedCurriculum = $this->curriculumResolver->resolveFor(
            $section->school_year_id,
            $section->grade_level_id
        );

        if ($resolvedCurriculum && (int) $subject->curriculum_id !== (int) $resolvedCurriculum->id) {
            throw ValidationException::withMessages([
                'subject_id' => "Selected subject belongs to a different curriculum. This section uses {$resolvedCurriculum->name}.",
            ]);
        }

        $assignment = DB::transaction(function () use ($section, $subject, $teacher, $scheduleSummary): TeacherAssignment {
            $existing = TeacherAssignment::query()
                ->where('school_year_id', $section->school_year_id)
                ->where('section_id', $section->id)
                ->where('subject_id', $subject->id)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                if ((int) $existing->teacher_id === (int) $teacher->id) {
                    $existing->update(['schedule_summary' => $scheduleSummary]);

                    return $existing;
                }

                // Preserve previous assignment for historical grade integrity
                $existing->update(['is_active' => false]);
            }

            $created = TeacherAssignment::query()->create([
                'school_year_id' => $section->school_year_id,
                'section_id' => $section->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'schedule_summary' => $scheduleSummary,
                'is_active' => true,
            ]);

            // Ensure teacher has subject_teacher role if they have neither adviser nor subject_teacher
            if (! $teacher->user->hasAnyRole([Role::ADVISER, Role::SUBJECT_TEACHER])) {
                $subjectRole = Role::query()->where('name', Role::SUBJECT_TEACHER)->first();
                if ($subjectRole) {
                    $teacher->user->roles()->syncWithoutDetaching([$subjectRole->id]);
                }
            }

            return $created;
        });

        $this->auditLog->record(
            eventType: 'teacher_assignment.assigned',
            auditable: $assignment,
            newValues: [
                'school_year_id' => $section->school_year_id,
                'section_id' => $section->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name,
                'schedule_summary' => $scheduleSummary,
            ],
            context: [
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
            ]
        );

        return $assignment;
    }

    public function deactivateAssignment(TeacherAssignment $assignment, ?string $reason = null): void
    {
        $assignment->update(['is_active' => false]);

        $this->auditLog->record(
            eventType: 'teacher_assignment.deactivated',
            auditable: $assignment,
            oldValues: ['is_active' => true],
            newValues: ['is_active' => false],
            reason: $reason,
            context: [
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
            ]
        );
    }
}
