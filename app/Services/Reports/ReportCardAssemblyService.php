<?php

namespace App\Services\Reports;

use App\Models\AttendanceRecord;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\ReportCardTemplate;
use App\Models\SchoolSetting;
use App\Models\StudentEnrollment;
use App\Models\StudentObservedValue;
use App\Models\Subject;
use App\Services\Grading\GradeCalculationService;
use Illuminate\Database\Eloquent\Builder;

class ReportCardAssemblyService
{
    public function __construct(private readonly GradeCalculationService $calculator)
    {
    }

    /**
     * Assemble a learner's report card strictly using the curriculum snapshot
     * recorded on their StudentEnrollment (preserving historical curriculum accuracy)
     * and ONLY official (submitted/approved) grades.
     *
     * @return array<string, mixed>
     */
    public function assemble(StudentEnrollment $enrollment, ?GradingPeriod $targetPeriod = null): array
    {
        $enrollment->loadMissing([
            'student',
            'schoolYear.gradingPeriods',
            'gradeLevel',
            'section.adviser',
            'curriculum.policyReference',
            'shsTrack',
            'shsStrand',
        ]);

        $periods = $enrollment->schoolYear->gradingPeriods;
        $evaluatedPeriods = $targetPeriod
            ? $periods->filter(fn (GradingPeriod $p) => $p->sequence <= $targetPeriod->sequence)->values()
            : $periods;

        // Subjects strictly resolved from the enrollment's historical curriculum snapshot!
        $subjectsQuery = Subject::query()
            ->where('curriculum_id', $enrollment->curriculum_id)
            ->where('grade_level_id', $enrollment->grade_level_id)
            ->where('is_active', true)
            ->where('is_graded', true)
            ->whereNull('parent_subject_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($enrollment->shs_track_id) {
            $subjectsQuery->where(function (Builder $q) use ($enrollment) {
                $q->whereNull('shs_track_id')
                    ->orWhere(function (Builder $trackQ) use ($enrollment) {
                        $trackQ->where('shs_track_id', $enrollment->shs_track_id);
                        if ($enrollment->shs_strand_id) {
                            $trackQ->where(function (Builder $strandQ) use ($enrollment) {
                                $strandQ->whereNull('shs_strand_id')
                                    ->orWhere('shs_strand_id', $enrollment->shs_strand_id);
                            });
                        }
                    });
            });
        }

        $subjects = $subjectsQuery->get();

        // Only OFFICIAL grades (is_official = true) appear on report cards!
        $officialGrades = QuarterlyGrade::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->whereIn('grading_period_id', $evaluatedPeriods->pluck('id'))
            ->where('is_official', true)
            ->get()
            ->keyBy(fn (QuarterlyGrade $g) => $g->subject_id . ':' . $g->grading_period_id);

        $subjectRows = [];
        $missingSubjects = [];
        $finalNumericGrades = [];
        $isAllDescriptive = true;
        $hasAnyNumerical = false;

        foreach ($subjects as $subject) {
            $periodGrades = [];
            $numericForSubject = [];
            $missingPeriodsForSubject = [];
            $lastRemarks = null;

            foreach ($evaluatedPeriods as $p) {
                /** @var QuarterlyGrade|null $qg */
                $qg = $officialGrades->get($subject->id . ':' . $p->id);
                if (! $qg) {
                    $periodGrades[$p->id] = null;
                    $missingPeriodsForSubject[] = $p->code;
                } else {
                    $periodGrades[$p->id] = $qg->display_grade;
                    $lastRemarks = $qg->remarks;
                    if ($qg->final_grade !== null) {
                        $numericForSubject[] = $qg->final_grade;
                        $hasAnyNumerical = true;
                        $isAllDescriptive = false;
                    }
                }
            }

            if (! empty($missingPeriodsForSubject)) {
                $missingSubjects[] = [
                    'subject_code' => $subject->code,
                    'subject_name' => $subject->name,
                    'missing_periods' => $missingPeriodsForSubject,
                ];
            }

            // Compute Final Subject Grade when all evaluated periods have numeric grades
            $subjectFinal = null;
            if (empty($missingPeriodsForSubject) && count($numericForSubject) === $evaluatedPeriods->count() && count($numericForSubject) > 0) {
                $subjectFinal = $this->calculator->computeFinalSubjectGrade($numericForSubject, 0);
                $finalNumericGrades[] = $subjectFinal;
            } elseif (! empty($missingPeriodsForSubject)) {
                $finalNumericGrades[] = null;
            }

            $subjectRemarks = match (true) {
                ! empty($missingPeriodsForSubject) => 'Pending (' . implode(', ', $missingPeriodsForSubject) . ')',
                $subjectFinal !== null => $subjectFinal >= 75 ? 'Passed' : 'Did Not Meet Expectations',
                default => $lastRemarks ?? 'Completed',
            };

            $subjectRows[] = [
                'subject' => $subject,
                'period_grades' => $periodGrades,
                'final_grade' => $subjectFinal,
                'remarks' => $subjectRemarks,
                'is_missing' => ! empty($missingPeriodsForSubject),
            ];
        }

        $hasMissingGrades = ! empty($missingSubjects) || $subjects->isEmpty();

        if ($hasAnyNumerical && ! $hasMissingGrades) {
            $summary = $this->calculator->computeGeneralAverageAndStatus($finalNumericGrades, 75.0, 2, 0);
            $generalAverage = $summary['general_average'];
            $promotionStatus = $targetPeriod && $targetPeriod->sequence < $periods->count()
                ? 'in_progress'
                : $summary['promotion_status'];
        } elseif ($isAllDescriptive && ! $hasMissingGrades) {
            $generalAverage = null;
            $promotionStatus = $targetPeriod && $targetPeriod->sequence < $periods->count()
                ? 'in_progress'
                : 'promoted';
        } else {
            $generalAverage = null;
            $promotionStatus = 'incomplete';
        }

        $attendance = AttendanceRecord::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->orderBy('year_number')
            ->orderBy('month_number')
            ->get();

        $observedValues = StudentObservedValue::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->whereIn('grading_period_id', $evaluatedPeriods->pluck('id'))
            ->get()
            ->keyBy(fn (StudentObservedValue $v) => $v->behavior_statement_code . ':' . $v->grading_period_id);

        $template = ReportCardTemplate::query()
            ->where('curriculum_id', $enrollment->curriculum_id)
            ->first()
            ?? ReportCardTemplate::defaultTemplate();

        return [
            'school' => SchoolSetting::current(),
            'template' => $template,
            'enrollment' => $enrollment,
            'periods' => $evaluatedPeriods,
            'all_periods' => $periods,
            'target_period' => $targetPeriod,
            'subject_rows' => $subjectRows,
            'missing_subjects' => $missingSubjects,
            'has_missing_grades' => $hasMissingGrades,
            'general_average' => $generalAverage,
            'promotion_status' => $promotionStatus,
            'is_descriptive_only' => $isAllDescriptive && ! $hasAnyNumerical,
            'attendance' => $attendance,
            'attendance_totals' => [
                'school_days' => round((float) $attendance->sum('school_days'), 1),
                'days_present' => round((float) $attendance->sum('days_present'), 1),
                'days_absent' => round((float) $attendance->sum('days_absent'), 1),
                'days_tardy' => round((float) $attendance->sum('days_tardy'), 1),
            ],
            'observed_values' => $observedValues,
            'core_value_statements' => StudentObservedValue::CORE_VALUE_STATEMENTS,
        ];
    }
}
