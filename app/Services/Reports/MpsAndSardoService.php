<?php

namespace App\Services\Reports;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\GradingPeriod;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\StudentAssessmentScore;
use App\Models\StudentEnrollment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MpsAndSardoService
{
    public const DEPED_MPS_TARGET = 75.0;
    public const DEPED_SARDO_ABSENCE_THRESHOLD = 20.0;
    public const WARNING_ABSENCE_THRESHOLD = 10.0;

    /**
     * DepEd NETRC / BEA descriptive mastery equivalent for Mean Percentage Score.
     */
    public static function resolveMasteryLevel(float $mps): string
    {
        return match (true) {
            $mps >= 96.0 => 'Mastered',
            $mps >= 86.0 => 'Closely Approximating Mastery',
            $mps >= 66.0 => 'Moving Towards Mastery',
            $mps >= 35.0 => 'Average',
            $mps >= 15.0 => 'Low',
            default => 'Very Low',
        };
    }

    /**
     * Compute DepEd Mean Percentage Score (MPS) across classes and subjects
     * from actual recorded assessment scores.
     *
     * @return array<string, mixed>
     */
    public function buildMpsReport(SchoolYear $schoolYear, ?GradingPeriod $period = null, ?int $gradeLevelId = null): array
    {
        $periodIds = $period
            ? [$period->id]
            : $schoolYear->gradingPeriods()->pluck('id')->all();

        $assessmentsQuery = Assessment::query()
            ->with([
                'teacherAssignment.subject',
                'teacherAssignment.section.gradeLevel',
                'teacherAssignment.teacher',
                'scores',
            ])
            ->whereIn('grading_period_id', $periodIds)
            ->whereHas('teacherAssignment.section', function ($q) use ($schoolYear, $gradeLevelId) {
                $q->where('school_year_id', $schoolYear->id);
                if ($gradeLevelId) {
                    $q->where('grade_level_id', $gradeLevelId);
                }
            });

        $assessments = $assessmentsQuery->get();

        $classBuckets = [];
        $subjectBuckets = [];
        $overallEarned = 0.0;
        $overallPossible = 0.0;

        foreach ($assessments as $assessment) {
            $assignment = $assessment->teacherAssignment;
            if (! $assignment || ! $assignment->subject || ! $assignment->section || $assessment->max_score <= 0) {
                continue;
            }

            $validScores = $assessment->scores->filter(
                fn (StudentAssessmentScore $s) => $s->status !== StudentAssessmentScore::STATUS_EXCUSED
            );

            if ($validScores->isEmpty()) {
                continue;
            }

            $earned = 0.0;
            $possible = 0.0;
            foreach ($validScores as $sc) {
                $possible += (float) $assessment->max_score;
                if ($sc->status === StudentAssessmentScore::STATUS_SCORED && $sc->raw_score !== null) {
                    $earned += (float) $sc->raw_score;
                }
            }

            if ($possible <= 0) {
                continue;
            }

            $overallEarned += $earned;
            $overallPossible += $possible;

            $classKey = $assignment->section_id . ':' . $assignment->subject_id;
            if (! isset($classBuckets[$classKey])) {
                $classBuckets[$classKey] = [
                    'section' => $assignment->section,
                    'grade_level' => $assignment->section->gradeLevel,
                    'subject' => $assignment->subject,
                    'teacher' => $assignment->teacher,
                    'assessments_count' => 0,
                    'scored_entries' => 0,
                    'earned' => 0.0,
                    'possible' => 0.0,
                ];
            }
            $classBuckets[$classKey]['assessments_count']++;
            $classBuckets[$classKey]['scored_entries'] += $validScores->count();
            $classBuckets[$classKey]['earned'] += $earned;
            $classBuckets[$classKey]['possible'] += $possible;

            $subjKey = $assignment->subject->code . '|' . $assignment->subject->name;
            if (! isset($subjectBuckets[$subjKey])) {
                $subjectBuckets[$subjKey] = [
                    'subject_code' => $assignment->subject->code,
                    'subject_name' => $assignment->subject->name,
                    'classes_count' => 0,
                    'class_keys' => [],
                    'assessments_count' => 0,
                    'earned' => 0.0,
                    'possible' => 0.0,
                ];
            }
            if (! in_array($classKey, $subjectBuckets[$subjKey]['class_keys'], true)) {
                $subjectBuckets[$subjKey]['class_keys'][] = $classKey;
                $subjectBuckets[$subjKey]['classes_count']++;
            }
            $subjectBuckets[$subjKey]['assessments_count']++;
            $subjectBuckets[$subjKey]['earned'] += $earned;
            $subjectBuckets[$subjKey]['possible'] += $possible;
        }

        $classRows = collect($classBuckets)->map(function (array $row) {
            $mps = $row['possible'] > 0 ? round(($row['earned'] / $row['possible']) * 100, 2) : 0.0;
            $row['mps'] = $mps;
            $row['mastery_level'] = self::resolveMasteryLevel($mps);
            $row['meets_target'] = $mps >= self::DEPED_MPS_TARGET;

            return $row;
        })->sortBy('mps')->values();

        $subjectRows = collect($subjectBuckets)->map(function (array $row) {
            $mps = $row['possible'] > 0 ? round(($row['earned'] / $row['possible']) * 100, 2) : 0.0;
            unset($row['class_keys']);
            $row['mps'] = $mps;
            $row['mastery_level'] = self::resolveMasteryLevel($mps);
            $row['meets_target'] = $mps >= self::DEPED_MPS_TARGET;

            return $row;
        })->sortBy('mps')->values();

        $overallMps = $overallPossible > 0 ? round(($overallEarned / $overallPossible) * 100, 2) : null;

        return [
            'overall_mps' => $overallMps,
            'overall_mastery' => $overallMps !== null ? self::resolveMasteryLevel($overallMps) : 'No Scores Yet',
            'classes_evaluated' => $classRows->count(),
            'classes_meeting_target' => $classRows->where('meets_target', true)->count(),
            'classes_below_target' => $classRows->where('meets_target', false)->count(),
            'class_rows' => $classRows,
            'subject_rows' => $subjectRows,
        ];
    }

    /**
     * Compute SARDO (Students At Risk of Dropping Out) attendance early-warning alerts
     * against DepEd's official 20% absence threshold.
     *
     * @return array<string, mixed>
     */
    public function buildSardoReport(SchoolYear $schoolYear, ?int $gradeLevelId = null): array
    {
        $enrollmentsQuery = StudentEnrollment::query()
            ->with(['student', 'gradeLevel', 'section.adviser'])
            ->where('school_year_id', $schoolYear->id)
            ->where('is_current', true);

        if ($gradeLevelId) {
            $enrollmentsQuery->where('grade_level_id', $gradeLevelId);
        }

        $enrollments = $enrollmentsQuery->get();
        $enrollmentIds = $enrollments->pluck('id')->all();

        $attendanceTotals = AttendanceRecord::query()
            ->selectRaw('student_enrollment_id, SUM(school_days) as total_school_days, SUM(days_present) as total_present, SUM(days_absent) as total_absent, SUM(days_tardy) as total_tardy')
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->groupBy('student_enrollment_id')
            ->get()
            ->keyBy('student_enrollment_id');

        $alerts = collect();
        $criticalCount = 0;
        $warningCount = 0;
        $tardyWatchCount = 0;
        $totalTracked = 0;
        $sumPresent = 0.0;
        $sumSchoolDays = 0.0;

        foreach ($enrollments as $enrollment) {
            $att = $attendanceTotals->get($enrollment->id);
            if (! $att) {
                continue;
            }

            $schoolDays = (float) $att->total_school_days;
            $present = (float) $att->total_present;
            $absent = (float) $att->total_absent;
            $tardy = (float) $att->total_tardy;
            $effectiveDays = max($schoolDays, $present + $absent);

            if ($effectiveDays <= 0) {
                continue;
            }

            $totalTracked++;
            $sumPresent += $present;
            $sumSchoolDays += $effectiveDays;

            $absenceRate = round(($absent / $effectiveDays) * 100, 2);
            $attendanceRate = round(($present / $effectiveDays) * 100, 2);

            $severity = match (true) {
                $absenceRate >= self::DEPED_SARDO_ABSENCE_THRESHOLD => 'critical',
                $absenceRate >= self::WARNING_ABSENCE_THRESHOLD => 'warning',
                $tardy >= 5 => 'tardy_watch',
                default => 'normal',
            };

            if ($severity === 'critical') {
                $criticalCount++;
            } elseif ($severity === 'warning') {
                $warningCount++;
            } elseif ($severity === 'tardy_watch') {
                $tardyWatchCount++;
            }

            if ($severity !== 'normal') {
                $alerts->push([
                    'enrollment' => $enrollment,
                    'student' => $enrollment->student,
                    'grade_level' => $enrollment->gradeLevel,
                    'section' => $enrollment->section,
                    'school_days' => $effectiveDays,
                    'days_present' => $present,
                    'days_absent' => $absent,
                    'days_tardy' => $tardy,
                    'absence_rate' => $absenceRate,
                    'attendance_rate' => $attendanceRate,
                    'severity' => $severity,
                ]);
            }
        }

        $alerts = $alerts->sortByDesc(function (array $row) {
            $weight = match ($row['severity']) {
                'critical' => 3000,
                'warning' => 2000,
                default => 1000,
            };

            return $weight + $row['absence_rate'];
        })->values();

        return [
            'learners_tracked' => $totalTracked,
            'overall_attendance_rate' => $sumSchoolDays > 0 ? round(($sumPresent / $sumSchoolDays) * 100, 2) : null,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'tardy_watch_count' => $tardyWatchCount,
            'alerts' => $alerts,
        ];
    }

    public function exportExcel(SchoolYear $schoolYear, ?GradingPeriod $period = null, ?int $gradeLevelId = null): Spreadsheet
    {
        $school = SchoolSetting::current();
        $mps = $this->buildMpsReport($schoolYear, $period, $gradeLevelId);
        $sardo = $this->buildSardoReport($schoolYear, $gradeLevelId);

        $spreadsheet = new Spreadsheet();

        // Sheet 1: DepEd MPS Summary
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('DepEd MPS Summary');

        $sheet1->setCellValue('A1', strtoupper($school->school_name));
        $sheet1->setCellValue('A2', 'DEPED MEAN PERCENTAGE SCORE (MPS) REPORT — SY ' . $schoolYear->name . ($period ? ' (' . $period->name . ')' : ' (All Periods)'));
        $sheet1->mergeCells('A1:H1');
        $sheet1->mergeCells('A2:H2');
        $sheet1->getStyle('A1:A2')->getFont()->setBold(true);

        $headers1 = ['Grade & Section', 'Subject Code', 'Learning Area', 'Subject Teacher', 'Assessments', 'MPS (%)', 'Mastery Equivalent', '75% Target'];
        $sheet1->fromArray($headers1, null, 'A4');
        $sheet1->getStyle('A4:H4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $r = 5;
        foreach ($mps['class_rows'] as $row) {
            $sheet1->fromArray([
                $row['grade_level']->name . ' - ' . $row['section']->name,
                $row['subject']->code,
                $row['subject']->name,
                $row['teacher']?->full_name ?? 'Unassigned',
                $row['assessments_count'],
                $row['mps'],
                $row['mastery_level'],
                $row['meets_target'] ? 'MET' : 'BELOW TARGET',
            ], null, 'A' . $r);
            $r++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet 2: SARDO Attendance Alerts
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('SARDO Attendance Alerts');

        $sheet2->setCellValue('A1', strtoupper($school->school_name));
        $sheet2->setCellValue('A2', 'SARDO (STUDENTS AT RISK OF DROPPING OUT) ATTENDANCE MONITORING — SY ' . $schoolYear->name);
        $sheet2->mergeCells('A1:I1');
        $sheet2->mergeCells('A2:I2');
        $sheet2->getStyle('A1:A2')->getFont()->setBold(true);

        $headers2 = ['LRN', 'Learner Name', 'Grade & Section', 'Adviser', 'School Days', 'Present', 'Absent', 'Tardy', 'Absence % / Status'];
        $sheet2->fromArray($headers2, null, 'A4');
        $sheet2->getStyle('A4:I4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9F1239']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $r2 = 5;
        foreach ($sardo['alerts'] as $a) {
            $statusLabel = match ($a['severity']) {
                'critical' => 'CRITICAL SARDO (>=20%)',
                'warning' => 'EARLY WARNING (10-19%)',
                default => 'CHRONIC TARDINESS',
            };
            $sheet2->fromArray([
                $a['student']->lrn ?? $a['student']->student_number,
                $a['student']->formal_name,
                $a['grade_level']->name . ' - ' . $a['section']->name,
                $a['section']->adviser?->full_name ?? 'Unassigned',
                $a['school_days'],
                $a['days_present'],
                $a['days_absent'],
                $a['days_tardy'],
                $a['absence_rate'] . '% — ' . $statusLabel,
            ], null, 'A' . $r2);
            $r2++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
