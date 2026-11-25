<?php

namespace App\Services\Grading;

use App\Models\Assessment;
use App\Models\GradeSubmission;
use App\Models\GradingComponent;
use App\Models\GradingConfiguration;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\StudentAssessmentScore;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradebookService
{
    public function __construct(
        private readonly GradingConfigurationResolver $configResolver,
        private readonly GradeCalculationService $calculator,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function resolveConfiguration(TeacherAssignment $assignment): ?GradingConfiguration
    {
        $assignment->loadMissing(['section', 'subject']);

        return $this->configResolver->resolve(
            $assignment->school_year_id,
            $assignment->subject->curriculum_id,
            $assignment->section->grade_level_id,
            $assignment->subject
        );
    }

    /**
     * Return all leaf components teachers can create assessments under.
     *
     * @return Collection<int, array{id: int, code: string, label: string, weight_percentage: float}>
     */
    public function getLeafComponents(GradingConfiguration $config): Collection
    {
        $top = $config->relationLoaded('topLevelComponents')
            ? $config->topLevelComponents
            : $config->topLevelComponents()->with('children')->get();

        $leaves = collect();
        foreach ($top as $comp) {
            if ($comp->hasChildren()) {
                foreach ($comp->children as $child) {
                    $leaves->push([
                        'id' => $child->id,
                        'code' => "{$comp->code}-{$child->code}",
                        'label' => "{$comp->name} → {$child->name} ({$child->weight_percentage}% of {$comp->weight_percentage}%)",
                        'weight_percentage' => $child->weight_percentage,
                    ]);
                }
            } else {
                $leaves->push([
                    'id' => $comp->id,
                    'code' => $comp->code,
                    'label' => "{$comp->name} ({$comp->weight_percentage}%)",
                    'weight_percentage' => $comp->weight_percentage,
                ]);
            }
        }

        return $leaves;
    }

    /**
     * Resolve the effective GradeSubmission for this class and period.
     * Prefers the current assignment's submission; if none exists, falls back
     * to an official (submitted/approved) submission from a prior teacher
     * assignment for the same school year, section, and subject.
     */
    public function resolveEffectiveSubmission(TeacherAssignment $assignment, GradingPeriod $period): ?GradeSubmission
    {
        $own = GradeSubmission::query()
            ->with('teacherAssignment.teacher')
            ->where('teacher_assignment_id', $assignment->id)
            ->where('grading_period_id', $period->id)
            ->first();

        if ($own) {
            return $own;
        }

        $priorAssignmentIds = TeacherAssignment::query()
            ->where('school_year_id', $assignment->school_year_id)
            ->where('section_id', $assignment->section_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('id', '!=', $assignment->id)
            ->pluck('id');

        if ($priorAssignmentIds->isEmpty()) {
            return null;
        }

        return GradeSubmission::query()
            ->with('teacherAssignment.teacher')
            ->whereIn('teacher_assignment_id', $priorAssignmentIds)
            ->where('grading_period_id', $period->id)
            ->whereIn('status', [GradeSubmission::STATUS_SUBMITTED, GradeSubmission::STATUS_APPROVED])
            ->latest('submitted_at')
            ->first();
    }

    /**
     * Determine whether any QuarterlyGrade for this section + subject + period is locked
     * (including grades officially submitted by a previous teacher before reassignment).
     */
    public function hasLockedClassGrades(TeacherAssignment $assignment, GradingPeriod $period): bool
    {
        $enrollmentIds = StudentEnrollment::query()
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->pluck('id');

        return QuarterlyGrade::query()
            ->where('grading_period_id', $period->id)
            ->where('is_locked', true)
            ->where(function ($query) use ($assignment, $enrollmentIds): void {
                $query->where('teacher_assignment_id', $assignment->id);

                if ($enrollmentIds->isNotEmpty()) {
                    $query->orWhere(function ($sub) use ($assignment, $enrollmentIds): void {
                        $sub->where('subject_id', $assignment->subject_id)
                            ->whereIn('student_enrollment_id', $enrollmentIds);
                    });
                }
            })
            ->exists();
    }

    public function ensureEditable(TeacherAssignment $assignment, GradingPeriod $period): void
    {
        if ((int) $period->school_year_id !== (int) $assignment->school_year_id) {
            throw ValidationException::withMessages([
                'grading_period_id' => 'Grading period does not belong to this class\'s school year.',
            ]);
        }

        if ($assignment->schoolYear->is_closed) {
            throw ValidationException::withMessages([
                'grading_period_id' => 'This school year is closed. Grades can no longer be modified.',
            ]);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'grading_period_id' => "Grading period {$period->name} is currently {$period->status}. Grades can only be edited while the period is open.",
            ]);
        }

        $effectiveSubmission = $this->resolveEffectiveSubmission($assignment, $period);
        $hasLocked = $this->hasLockedClassGrades($assignment, $period)
            || ($effectiveSubmission && $effectiveSubmission->isOfficial());

        if ($hasLocked) {
            if ($effectiveSubmission && (int) $effectiveSubmission->teacher_assignment_id !== (int) $assignment->id) {
                $priorTeacher = $effectiveSubmission->teacherAssignment?->teacher?->full_name ?? 'the previous teacher';

                throw ValidationException::withMessages([
                    'grading_period_id' => "{$period->name} grades for this subject were already submitted and locked by {$priorTeacher} before reassignment. Contact the administrator to return the submission if revision is needed.",
                ]);
            }

            throw ValidationException::withMessages([
                'grading_period_id' => 'Grades for this class and period have already been submitted and locked. Contact the administrator to return or unlock them.',
            ]);
        }
    }

    public function createAssessment(
        TeacherAssignment $assignment,
        GradingPeriod $period,
        array $data,
        User $actor
    ): Assessment {
        $this->ensureEditable($assignment, $period);

        $config = $this->resolveConfiguration($assignment);
        if (! $config || $config->isDescriptive()) {
            throw ValidationException::withMessages([
                'grading_component_id' => 'Assessments with numeric scores cannot be added to a descriptive or unconfigured grading policy.',
            ]);
        }

        $leafIds = $this->getLeafComponents($config)->pluck('id')->all();
        if (! in_array((int) $data['grading_component_id'], $leafIds, true)) {
            throw ValidationException::withMessages([
                'grading_component_id' => 'Selected assessment component does not belong to this subject\'s active grading configuration.',
            ]);
        }

        $assessment = DB::transaction(function () use ($assignment, $period, $data, $actor): Assessment {
            $created = Assessment::query()->create([
                'teacher_assignment_id' => $assignment->id,
                'grading_period_id' => $period->id,
                'grading_component_id' => $data['grading_component_id'],
                'title' => trim($data['title']),
                'assessment_type' => $data['assessment_type'],
                'max_score' => round((float) $data['max_score'], 2),
                'assessment_date' => $data['assessment_date'] ?? now()->toDateString(),
                'is_published' => true,
                'created_by' => $actor->id,
            ]);

            $this->recalculateForClass($assignment, $period);

            return $created;
        });

        $this->auditLog->record(
            eventType: 'assessment.created',
            auditable: $assessment,
            newValues: $assessment->only(['title', 'assessment_type', 'max_score', 'grading_component_id']),
            actor: $actor,
            context: [
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
            ]
        );

        return $assessment;
    }

    public function deleteAssessment(TeacherAssignment $assignment, GradingPeriod $period, Assessment $assessment, User $actor): void
    {
        $this->ensureEditable($assignment, $period);

        if ((int) $assessment->teacher_assignment_id !== (int) $assignment->id || (int) $assessment->grading_period_id !== (int) $period->id) {
            abort(404);
        }

        DB::transaction(function () use ($assignment, $period, $assessment, $actor): void {
            $old = $assessment->only(['id', 'title', 'max_score']);
            $assessment->delete();
            $this->recalculateForClass($assignment, $period);

            $this->auditLog->record(
                eventType: 'assessment.deleted',
                oldValues: $old,
                actor: $actor,
                context: [
                    'teacher_id' => $assignment->teacher_id,
                    'subject_id' => $assignment->subject_id,
                ]
            );
        });
    }

    public function updateAssessment(
        TeacherAssignment $assignment,
        GradingPeriod $period,
        Assessment $assessment,
        array $data,
        User $actor
    ): Assessment {
        $this->ensureEditable($assignment, $period);

        if ((int) $assessment->teacher_assignment_id !== (int) $assignment->id || (int) $assessment->grading_period_id !== (int) $period->id) {
            abort(404);
        }

        $newMax = round((float) $data['max_score'], 2);
        $highestExistingScore = StudentAssessmentScore::query()
            ->where('assessment_id', $assessment->id)
            ->where('status', StudentAssessmentScore::STATUS_SCORED)
            ->max('raw_score');

        if ($highestExistingScore !== null && $newMax < (float) $highestExistingScore) {
            throw ValidationException::withMessages([
                'max_score' => "Cannot lower Max Score to {$newMax} because a learner already has a recorded score of {$highestExistingScore}. Update learner scores first.",
            ]);
        }

        $oldValues = $assessment->only(['title', 'assessment_type', 'max_score', 'assessment_date']);

        DB::transaction(function () use ($assignment, $period, $assessment, $data, $newMax): void {
            $assessment->update([
                'title' => trim($data['title']),
                'assessment_type' => $data['assessment_type'],
                'max_score' => $newMax,
                'assessment_date' => $data['assessment_date'] ?? $assessment->assessment_date,
            ]);

            $this->recalculateForClass($assignment, $period);
        });

        $assessment->refresh();

        $this->auditLog->record(
            eventType: 'assessment.updated',
            auditable: $assessment,
            oldValues: $oldValues,
            newValues: $assessment->only(['title', 'assessment_type', 'max_score', 'assessment_date']),
            actor: $actor,
            context: [
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
            ]
        );

        return $assessment;
    }

    /**
     * Clone assessment columns (not student scores) from a source class + period
     * sharing the exact same GradingConfiguration into the target class + period.
     */
    public function cloneAssessmentsFrom(
        TeacherAssignment $target,
        GradingPeriod $targetPeriod,
        TeacherAssignment $source,
        GradingPeriod $sourcePeriod,
        User $actor
    ): int {
        $this->ensureEditable($target, $targetPeriod);

        if (! $actor->isAdmin() && (int) $source->teacher_id !== (int) $target->teacher_id) {
            abort(403, 'You can only copy assessments from your own assigned classes.');
        }

        $targetConfig = $this->resolveConfiguration($target);
        $sourceConfig = $this->resolveConfiguration($source);

        if (! $targetConfig || ! $sourceConfig || $targetConfig->isDescriptive() || (int) $targetConfig->id !== (int) $sourceConfig->id) {
            throw ValidationException::withMessages([
                'source_assignment_id' => 'Source class must use the same numeric Grading Configuration as the target class.',
            ]);
        }

        $sourceAssessments = Assessment::query()
            ->where('teacher_assignment_id', $source->id)
            ->where('grading_period_id', $sourcePeriod->id)
            ->orderBy('grading_component_id')
            ->orderBy('id')
            ->get();

        if ($sourceAssessments->isEmpty()) {
            throw ValidationException::withMessages([
                'source_assignment_id' => 'The selected source class and grading period has no assessments to copy.',
            ]);
        }

        $existingKeys = Assessment::query()
            ->where('teacher_assignment_id', $target->id)
            ->where('grading_period_id', $targetPeriod->id)
            ->get()
            ->map(fn (Assessment $a) => $a->grading_component_id . ':' . mb_strtolower(trim($a->title)))
            ->all();

        $createdCount = 0;

        DB::transaction(function () use ($target, $targetPeriod, $sourceAssessments, $existingKeys, $actor, &$createdCount): void {
            foreach ($sourceAssessments as $src) {
                $key = $src->grading_component_id . ':' . mb_strtolower(trim($src->title));
                if (in_array($key, $existingKeys, true)) {
                    continue;
                }

                Assessment::query()->create([
                    'teacher_assignment_id' => $target->id,
                    'grading_period_id' => $targetPeriod->id,
                    'grading_component_id' => $src->grading_component_id,
                    'title' => $src->title,
                    'assessment_type' => $src->assessment_type,
                    'max_score' => $src->max_score,
                    'assessment_date' => now()->toDateString(),
                    'is_published' => true,
                    'created_by' => $actor->id,
                ]);
                $createdCount++;
            }

            if ($createdCount > 0) {
                $this->recalculateForClass($target, $targetPeriod);
            }
        });

        if ($createdCount > 0) {
            $this->auditLog->record(
                eventType: 'assessment.cloned',
                auditable: $target,
                newValues: [
                    'source_assignment_id' => $source->id,
                    'source_period_id' => $sourcePeriod->id,
                    'target_period_id' => $targetPeriod->id,
                    'cloned_count' => $createdCount,
                ],
                actor: $actor,
                context: [
                    'teacher_id' => $target->teacher_id,
                    'subject_id' => $target->subject_id,
                ]
            );
        }

        return $createdCount;
    }

    /**
     * Save bulk scores matrix: $scores[enrollment_id][assessment_id] = value (numeric | '' | 'M' | 'EX').
     */
    public function saveBulkScores(
        TeacherAssignment $assignment,
        GradingPeriod $period,
        array $scoresMatrix,
        User $actor,
        ?string $reason = null
    ): int {
        $this->ensureEditable($assignment, $period);

        $enrollments = StudentEnrollment::query()
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->get()
            ->keyBy('id');

        $assessments = Assessment::query()
            ->where('teacher_assignment_id', $assignment->id)
            ->where('grading_period_id', $period->id)
            ->get()
            ->keyBy('id');

        // Pre-validate every cell so partial invalid data is never saved
        $validationErrors = [];
        $parsedChanges = [];

        foreach ($scoresMatrix as $enrollmentId => $assessmentValues) {
            $enrollmentId = (int) $enrollmentId;
            if (! $enrollments->has($enrollmentId) || ! is_array($assessmentValues)) {
                continue;
            }

            foreach ($assessmentValues as $assessmentId => $rawInput) {
                $assessmentId = (int) $assessmentId;
                $assessment = $assessments->get($assessmentId);
                if (! $assessment) {
                    continue;
                }

                $val = is_string($rawInput) ? trim($rawInput) : $rawInput;

                if ($val === '' || $val === null || strtoupper((string) $val) === 'M') {
                    $parsedChanges[] = [
                        'enrollment' => $enrollments->get($enrollmentId),
                        'assessment' => $assessment,
                        'status' => StudentAssessmentScore::STATUS_MISSING,
                        'raw_score' => null,
                    ];
                    continue;
                }

                if (strtoupper((string) $val) === 'EX') {
                    $parsedChanges[] = [
                        'enrollment' => $enrollments->get($enrollmentId),
                        'assessment' => $assessment,
                        'status' => StudentAssessmentScore::STATUS_EXCUSED,
                        'raw_score' => null,
                    ];
                    continue;
                }

                if (! is_numeric($val)) {
                    $validationErrors["scores.{$enrollmentId}.{$assessmentId}"] = "Invalid score '{$val}' for {$assessment->title}. Use a number, blank/M (missing), or EX (excused).";
                    continue;
                }

                $numeric = round((float) $val, 2);
                if ($numeric < 0 || $numeric > $assessment->max_score) {
                    $validationErrors["scores.{$enrollmentId}.{$assessmentId}"] = "Score {$numeric} for {$assessment->title} must be between 0 and {$assessment->max_score}.";
                    continue;
                }

                $parsedChanges[] = [
                    'enrollment' => $enrollments->get($enrollmentId),
                    'assessment' => $assessment,
                    'status' => StudentAssessmentScore::STATUS_SCORED,
                    'raw_score' => $numeric,
                ];
            }
        }

        if (! empty($validationErrors)) {
            throw ValidationException::withMessages($validationErrors);
        }

        $updatedCount = 0;

        DB::transaction(function () use ($assignment, $period, $parsedChanges, $actor, $reason, &$updatedCount): void {
            $existingScores = StudentAssessmentScore::query()
                ->whereIn('assessment_id', collect($parsedChanges)->pluck('assessment.id')->unique())
                ->get()
                ->keyBy(fn (StudentAssessmentScore $s) => $s->assessment_id . ':' . $s->student_enrollment_id);

            foreach ($parsedChanges as $change) {
                /** @var StudentEnrollment $enrollment */
                $enrollment = $change['enrollment'];
                /** @var Assessment $assessment */
                $assessment = $change['assessment'];

                $key = $assessment->id . ':' . $enrollment->id;
                /** @var StudentAssessmentScore|null $existing */
                $existing = $existingScores->get($key);

                $prevDisplay = $existing
                    ? ($existing->status === 'scored' ? (string) $existing->raw_score : strtoupper($existing->status))
                    : null;
                $newDisplay = $change['status'] === 'scored'
                    ? (string) $change['raw_score']
                    : strtoupper($change['status']);

                // Skip unchanged cells
                if ($existing && $prevDisplay === $newDisplay) {
                    continue;
                }
                if (! $existing && $change['status'] === StudentAssessmentScore::STATUS_MISSING) {
                    continue;
                }

                $record = StudentAssessmentScore::query()->updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'student_enrollment_id' => $enrollment->id,
                    ],
                    [
                        'raw_score' => $change['raw_score'],
                        'status' => $change['status'],
                        'encoded_by' => $actor->id,
                    ]
                );

                $updatedCount++;

                $this->auditLog->recordGradeChange(
                    eventType: $existing ? 'score.updated' : 'score.created',
                    auditable: $record,
                    teacherId: $assignment->teacher_id,
                    studentId: $enrollment->student_id,
                    subjectId: $assignment->subject_id,
                    previousGrade: $prevDisplay,
                    updatedGrade: $newDisplay,
                    reason: $reason ?? "Assessment: {$assessment->title} ({$period->code})",
                    actor: $actor
                );
            }

            $this->recalculateForClass($assignment, $period);
        });

        return $updatedCount;
    }

    /**
     * Save Key Stage 1 descriptive ratings: $ratings[enrollment_id] = 'CO' | 'DV' | 'BG' | ''
     */
    public function saveDescriptiveRatings(
        TeacherAssignment $assignment,
        GradingPeriod $period,
        array $ratingsByEnrollmentId,
        User $actor
    ): int {
        $this->ensureEditable($assignment, $period);

        $config = $this->resolveConfiguration($assignment);
        if (! $config || ! $config->isDescriptive()) {
            throw ValidationException::withMessages([
                'ratings' => 'This class does not use a descriptive grading configuration.',
            ]);
        }

        $allowedCodes = collect($config->descriptive_scale_json ?? [])->pluck('code')->filter()->all();

        $enrollments = StudentEnrollment::query()
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->get()
            ->keyBy('id');

        $updated = 0;

        DB::transaction(function () use ($assignment, $period, $config, $ratingsByEnrollmentId, $allowedCodes, $enrollments, $actor, &$updated): void {
            foreach ($ratingsByEnrollmentId as $enrollmentId => $code) {
                $enrollment = $enrollments->get((int) $enrollmentId);
                if (! $enrollment) {
                    continue;
                }

                $cleanCode = trim((string) $code) === '' ? null : strtoupper(trim((string) $code));
                if ($cleanCode !== null && ! empty($allowedCodes) && ! in_array($cleanCode, $allowedCodes, true)) {
                    throw ValidationException::withMessages([
                        "ratings.{$enrollmentId}" => "Invalid descriptive rating '{$cleanCode}'. Allowed: " . implode(', ', $allowedCodes),
                    ]);
                }

                $computed = $this->calculator->computePeriodGrade($config, [], $cleanCode);

                $existing = QuarterlyGrade::query()
                    ->where('student_enrollment_id', $enrollment->id)
                    ->where('subject_id', $assignment->subject_id)
                    ->where('grading_period_id', $period->id)
                    ->first();

                $prevRating = $existing?->descriptive_rating;
                if ($existing && $prevRating === $cleanCode) {
                    continue;
                }

                $grade = QuarterlyGrade::query()->updateOrCreate(
                    [
                        'student_enrollment_id' => $enrollment->id,
                        'subject_id' => $assignment->subject_id,
                        'grading_period_id' => $period->id,
                    ],
                    [
                        'teacher_assignment_id' => $assignment->id,
                        'grading_configuration_id' => $config->id,
                        'component_breakdown_json' => [],
                        'initial_grade' => null,
                        'transmuted_grade' => null,
                        'final_grade' => null,
                        'descriptive_rating' => $computed['descriptive_rating'],
                        'remarks' => $computed['remarks'],
                        'missing_scores_count' => $computed['missing_count'],
                        'computed_at' => now(),
                    ]
                );

                $updated++;

                $this->auditLog->recordGradeChange(
                    eventType: $existing ? 'grade.updated' : 'grade.created',
                    auditable: $grade,
                    teacherId: $assignment->teacher_id,
                    studentId: $enrollment->student_id,
                    subjectId: $assignment->subject_id,
                    previousGrade: $prevRating,
                    updatedGrade: $cleanCode,
                    reason: "Descriptive rating ({$period->code})",
                    actor: $actor
                );
            }
        });

        return $updated;
    }

    /**
     * Recompute all numeric QuarterlyGrades for a class + period inside current transaction.
     */
    public function recalculateForClass(TeacherAssignment $assignment, GradingPeriod $period): void
    {
        $config = $this->resolveConfiguration($assignment);
        if (! $config || $config->isDescriptive()) {
            return;
        }

        $enrollments = StudentEnrollment::query()
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->get();

        $assessments = Assessment::query()
            ->where('teacher_assignment_id', $assignment->id)
            ->where('grading_period_id', $period->id)
            ->get();

        $scoresByKey = StudentAssessmentScore::query()
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy(fn (StudentAssessmentScore $s) => $s->student_enrollment_id . ':' . $s->assessment_id);

        foreach ($enrollments as $enrollment) {
            $existing = QuarterlyGrade::query()
                ->where('student_enrollment_id', $enrollment->id)
                ->where('subject_id', $assignment->subject_id)
                ->where('grading_period_id', $period->id)
                ->first();

            if ($existing && $existing->is_locked) {
                continue;
            }

            $scoresByComponent = [];
            foreach ($assessments as $assessment) {
                $scoreRecord = $scoresByKey->get($enrollment->id . ':' . $assessment->id);
                $scoresByComponent[$assessment->grading_component_id][] = [
                    'raw_score' => $scoreRecord?->raw_score,
                    'max_score' => $assessment->max_score,
                    'status' => $scoreRecord?->status ?? StudentAssessmentScore::STATUS_MISSING,
                ];
            }

            $computed = $this->calculator->computePeriodGrade($config, $scoresByComponent);

            QuarterlyGrade::query()->updateOrCreate(
                [
                    'student_enrollment_id' => $enrollment->id,
                    'subject_id' => $assignment->subject_id,
                    'grading_period_id' => $period->id,
                ],
                [
                    'teacher_assignment_id' => $assignment->id,
                    'grading_configuration_id' => $config->id,
                    'component_breakdown_json' => $computed['component_breakdown'],
                    'initial_grade' => $computed['initial_grade'],
                    'transmuted_grade' => $computed['transmuted_grade'],
                    'final_grade' => $computed['final_grade'],
                    'descriptive_rating' => null,
                    'remarks' => $computed['remarks'],
                    'missing_scores_count' => $computed['missing_count'],
                    'computed_at' => now(),
                ]
            );
        }
    }
}
