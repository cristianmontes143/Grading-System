<?php

namespace App\Services\Reports;

use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Services\Grading\GradeSubmissionService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SectionSummaryService
{
    public const HONOR_HIGHEST = 'With Highest Honors';
    public const HONOR_HIGH = 'With High Honors';
    public const HONOR_REGULAR = 'With Honors';

    public function __construct(
        private readonly GradeSubmissionService $submissionService,
        private readonly ReportCardAssemblyService $assemblyService
    ) {
    }

    /**
     * Resolve DepEd Order No. 36, s. 2016 honor distinction from a rounded General Average
     * and whether the learner has any failing subject (< 75).
     */
    public static function resolveHonorDistinction(?float $average, bool $hasFailingSubject, bool $isComplete): ?string
    {
        if (! $isComplete || $average === null || $hasFailingSubject) {
            return null;
        }

        $rounded = (int) round($average);

        return match (true) {
            $rounded >= 98 => self::HONOR_HIGHEST,
            $rounded >= 95 => self::HONOR_HIGH,
            $rounded >= 90 => self::HONOR_REGULAR,
            default => null,
        };
    }

    /**
     * Build complete consolidated official grade sheet, rankings, honor roll,
     * and at-risk learner analysis for a Section + Grading Period.
     *
     * @return array<string, mixed>
     */
    public function buildSectionSummary(Section $section, GradingPeriod $period): array
    {
        $section->loadMissing(['schoolYear', 'gradeLevel', 'adviser', 'shsTrack', 'shsStrand']);

        $collection = $this->submissionService->getSectionCollectionMatrix($section, $period);
        $subjectRows = $collection['subject_rows'];
        $officialMatrix = $collection['official_grades_matrix'];

        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->where('school_year_id', $section->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn (StudentEnrollment $e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $learnerRows = [];
        $honorCounts = [
            self::HONOR_HIGHEST => 0,
            self::HONOR_HIGH => 0,
            self::HONOR_REGULAR => 0,
        ];
        $passedCount = 0;
        $atRiskCount = 0;
        $incompleteCount = 0;
        $distribution = [
            'outstanding' => 0,     // 90-100
            'very_satisfactory' => 0, // 85-89
            'satisfactory' => 0,    // 80-84
            'fairly_satisfactory' => 0, // 75-79
            'did_not_meet' => 0,    // < 75
        ];

        foreach ($enrollments as $enrollment) {
            $subjectGrades = [];
            $numericGrades = [];
            $missingCount = 0;
            $failingSubjects = [];

            foreach ($subjectRows as $sRow) {
                $subject = $sRow['subject'];
                /** @var QuarterlyGrade|null $qg */
                $qg = $officialMatrix[$enrollment->id][$subject->id] ?? null;

                if (! $qg) {
                    $missingCount++;
                    $subjectGrades[$subject->id] = null;
                    continue;
                }

                $subjectGrades[$subject->id] = $qg->display_grade;
                if ($qg->final_grade !== null) {
                    $gradeVal = (float) $qg->final_grade;
                    $numericGrades[] = $gradeVal;
                    if ($gradeVal < 75.0) {
                        $failingSubjects[] = $subject->code;
                    }
                }
            }

            $isComplete = $subjectRows->isNotEmpty() && $missingCount === 0;
            $average = ! empty($numericGrades)
                ? round(array_sum($numericGrades) / count($numericGrades), 2)
                : null;
            $roundedAvg = $average !== null ? (int) round($average) : null;
            $hasFailing = ! empty($failingSubjects);

            $honor = self::resolveHonorDistinction($average, $hasFailing, $isComplete);
            if ($honor !== null) {
                $honorCounts[$honor]++;
            }

            if (! $isComplete) {
                $incompleteCount++;
                $statusLabel = 'Incomplete';
            } elseif ($hasFailing || ($roundedAvg !== null && $roundedAvg < 75)) {
                $atRiskCount++;
                $statusLabel = 'Needs Intervention';
            } else {
                $passedCount++;
                $statusLabel = $honor ?? 'Passed';
            }

            if ($isComplete && $roundedAvg !== null) {
                if ($roundedAvg >= 90) {
                    $distribution['outstanding']++;
                } elseif ($roundedAvg >= 85) {
                    $distribution['very_satisfactory']++;
                } elseif ($roundedAvg >= 80) {
                    $distribution['satisfactory']++;
                } elseif ($roundedAvg >= 75) {
                    $distribution['fairly_satisfactory']++;
                } else {
                    $distribution['did_not_meet']++;
                }
            }

            $learnerRows[] = [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'subject_grades' => $subjectGrades,
                'average' => $average,
                'rounded_average' => $roundedAvg,
                'missing_count' => $missingCount,
                'failing_subjects' => $failingSubjects,
                'is_complete' => $isComplete,
                'honor' => $honor,
                'status_label' => $statusLabel,
                'rank' => null,
            ];
        }

        // Standard competition ranking (1, 2, 2, 4) strictly for learners with complete numeric official grades
        $rankable = collect($learnerRows)
            ->filter(fn (array $r) => $r['is_complete'] && $r['average'] !== null)
            ->sortByDesc('average')
            ->values();

        $rankByEnrollment = [];
        $prevScore = null;
        $currentRank = 0;
        foreach ($rankable as $idx => $r) {
            if ($prevScore === null || $r['average'] < $prevScore) {
                $currentRank = $idx + 1;
                $prevScore = $r['average'];
            }
            $rankByEnrollment[$r['enrollment']->id] = $currentRank;
        }

        foreach ($learnerRows as &$row) {
            $row['rank'] = $rankByEnrollment[$row['enrollment']->id] ?? null;
        }
        unset($row);

        $honorRoll = collect($learnerRows)
            ->filter(fn (array $r) => $r['honor'] !== null)
            ->sortBy([
                ['average', 'desc'],
                fn (array $a, array $b) => strcmp($a['student']->last_name, $b['student']->last_name),
            ])
            ->values();

        $atRiskLearners = collect($learnerRows)
            ->filter(fn (array $r) => ! empty($r['failing_subjects']) || ($r['is_complete'] && $r['rounded_average'] !== null && $r['rounded_average'] < 75))
            ->values();

        $completeAverages = collect($learnerRows)
            ->filter(fn (array $r) => $r['is_complete'] && $r['average'] !== null)
            ->pluck('average');

        $sectionAverage = $completeAverages->isNotEmpty() ? round((float) $completeAverages->avg(), 2) : null;
        $evaluatedCount = $passedCount + $atRiskCount;
        $passingRate = $evaluatedCount > 0 ? round(($passedCount / $evaluatedCount) * 100, 1) : null;

        return [
            'section' => $section,
            'period' => $period,
            'collection' => $collection,
            'subject_rows' => $subjectRows,
            'learner_rows' => collect($learnerRows),
            'honor_roll' => $honorRoll,
            'at_risk_learners' => $atRiskLearners,
            'stats' => [
                'total_learners' => $enrollments->count(),
                'passed_count' => $passedCount,
                'at_risk_count' => $atRiskCount,
                'incomplete_count' => $incompleteCount,
                'honor_counts' => $honorCounts,
                'total_honors' => array_sum($honorCounts),
                'section_average' => $sectionAverage,
                'passing_rate' => $passingRate,
                'distribution' => $distribution,
            ],
        ];
    }

    /**
     * Build downloadable Consolidated Section Master Grade Sheet Excel (.xlsx) workbook.
     */
    public function buildMasterSheetSpreadsheet(Section $section, GradingPeriod $period): Spreadsheet
    {
        $summary = $this->buildSectionSummary($section, $period);
        $school = SchoolSetting::current();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Consolidated Master Sheet');

        $sheet->fromArray([
            [$school->school_name],
            ["Consolidated Official Section Master Grade Sheet — SY {$section->schoolYear->name} | {$period->name} ({$period->code})"],
            ["Section: {$section->gradeLevel->name} – {$section->name}", 'Class Adviser: ' . ($section->adviser?->full_name ?? 'Unassigned')],
            ['Note: Contains ONLY finalized/submitted official grades. Honor distinctions follow DepEd Order No. 36, s. 2016.'],
            [],
        ], null, 'A1');

        $headers = ['#', 'Student ID', 'LRN', 'Learner Name'];
        foreach ($summary['subject_rows'] as $sRow) {
            $headers[] = $sRow['subject']->code;
        }
        $headers = array_merge($headers, ['Average', 'Rounded', 'Rank', 'Honor / Status']);
        $sheet->fromArray([$headers], null, 'A6');

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A6:{$lastCol}6")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A6:{$lastCol}6")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
        $sheet->getStyle("A6:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNum = 7;
        foreach ($summary['learner_rows'] as $idx => $row) {
            $line = [
                $idx + 1,
                $row['student']->student_number,
                $row['student']->lrn ?? '',
                $row['student']->formal_name,
            ];
            foreach ($summary['subject_rows'] as $sRow) {
                $line[] = $row['subject_grades'][$sRow['subject']->id] ?? 'Pending';
            }
            $line[] = $row['average'] !== null ? number_format($row['average'], 2) : '—';
            $line[] = $row['rounded_average'] !== null ? $row['rounded_average'] : '—';
            $line[] = $row['rank'] !== null ? $row['rank'] : '—';
            $line[] = $row['status_label'];

            $sheet->fromArray([$line], null, "A{$rowNum}");
            $rowNum++;
        }

        // Add Honor Roll summary sheet
        $honorSheet = $spreadsheet->createSheet();
        $honorSheet->setTitle('Honor Roll');
        $honorSheet->fromArray([
            [$school->school_name . ' — Official Honor Roll'],
            ["{$section->gradeLevel->name} – {$section->name} | SY {$section->schoolYear->name} | {$period->name} ({$period->code})"],
            [],
            ['Rank', 'Student ID', 'LRN', 'Learner Name', 'General Average', 'Academic Distinction'],
        ], null, 'A1');

        $honorSheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(12);
        $honorSheet->getStyle('A4:F4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $honorSheet->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF065F46');

        $hRow = 5;
        foreach ($summary['honor_roll'] as $hr) {
            $honorSheet->fromArray([[
                $hr['rank'] ?? '—',
                $hr['student']->student_number,
                $hr['student']->lrn ?? '',
                $hr['student']->formal_name,
                number_format((float) $hr['average'], 2),
                $hr['honor'],
            ]], null, "A{$hRow}");
            $hRow++;
        }

        foreach ([$sheet, $honorSheet] as $ws) {
            foreach ($ws->getColumnIterator() as $column) {
                $ws->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Build DepEd School Form 5 (SF5) — Report on Promotion and Learning Progress & Achievement (.xlsx)
     * with Male / Female / Total promotion status and 5-band achievement distribution tables.
     */
    public function buildSf5Spreadsheet(Section $section): Spreadsheet
    {
        $section->loadMissing(['schoolYear', 'gradeLevel', 'adviser', 'shsTrack', 'shsStrand']);
        $school = SchoolSetting::current();

        $enrollments = StudentEnrollment::query()
            ->with(['student', 'curriculum'])
            ->where('section_id', $section->id)
            ->where('school_year_id', $section->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn (StudentEnrollment $e) => ($e->student->gender === 'Male' ? '0_' : '1_') . $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $promoSummary = [
            'promoted' => ['Male' => 0, 'Female' => 0],
            'conditional' => ['Male' => 0, 'Female' => 0],
            'retained' => ['Male' => 0, 'Female' => 0],
            'incomplete' => ['Male' => 0, 'Female' => 0],
        ];

        $achievementSummary = [
            'did_not_meet' => ['Male' => 0, 'Female' => 0],
            'fairly_satisfactory' => ['Male' => 0, 'Female' => 0],
            'satisfactory' => ['Male' => 0, 'Female' => 0],
            'very_satisfactory' => ['Male' => 0, 'Female' => 0],
            'outstanding' => ['Male' => 0, 'Female' => 0],
        ];

        $learnerLines = [];

        foreach ($enrollments as $enr) {
            $assembled = $this->assemblyService->assemble($enr);
            $sexKey = $enr->student->gender === 'Male' ? 'Male' : 'Female';
            $status = $assembled['promotion_status'];
            $avg = $assembled['general_average'];

            if (isset($promoSummary[$status])) {
                $promoSummary[$status][$sexKey]++;
            } else {
                $promoSummary['incomplete'][$sexKey]++;
            }

            if ($avg !== null && ! $assembled['has_missing_grades']) {
                $rounded = (int) round((float) $avg);
                $band = match (true) {
                    $rounded >= 90 => 'outstanding',
                    $rounded >= 85 => 'very_satisfactory',
                    $rounded >= 80 => 'satisfactory',
                    $rounded >= 75 => 'fairly_satisfactory',
                    default => 'did_not_meet',
                };
                $achievementSummary[$band][$sexKey]++;
            }

            $failedCodes = collect($assembled['subject_rows'])
                ->filter(fn (array $r) => $r['final_grade'] !== null && (float) $r['final_grade'] < 75.0)
                ->map(fn (array $r) => $r['subject']->code)
                ->implode(', ');

            $learnerLines[] = [
                $enr->student->lrn ?? $enr->student->student_number,
                $enr->student->formal_name,
                $sexKey,
                $avg !== null ? number_format((float) $avg, 0) : ($assembled['is_descriptive_only'] ? 'Descriptive' : '—'),
                strtoupper(str_replace('_', ' ', $status)),
                $failedCodes ?: 'None',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DepEd SF5 Roster');

        $sheet->fromArray([
            [$school->school_name . ' — School Form 5 (SF5) Report on Promotion & Level of Proficiency'],
            ["School Year: {$section->schoolYear->name} | Grade & Section: {$section->gradeLevel->name} – {$section->name} | Adviser: " . ($section->adviser?->full_name ?? 'Unassigned')],
            ['School ID: ' . ($school->school_id_code ?? 'N/A') . ' | Region/Division: ' . (collect([$school->region, $school->division])->filter()->implode(' / ') ?: 'N/A')],
            [],
            ['LRN / Student ID', 'Learner Name', 'Sex', 'General Average', 'Action Taken', 'Did Not Meet Expectations (Subjects)'],
        ], null, 'A1');

        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A5:F5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A5:F5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');

        $r = 6;
        foreach ($learnerLines as $line) {
            $sheet->fromArray([$line], null, "A{$r}");
            $r++;
        }

        // Sheet 2: Official SF5 Summary Tables (Promotion Status & Level of Progress by Sex)
        $sumSheet = $spreadsheet->createSheet();
        $sumSheet->setTitle('SF5 Summary Tables');
        $sumSheet->fromArray([
            ['SUMMARY TABLE — PROMOTION STATUS (DepEd SF5)'],
            ['Status', 'Male', 'Female', 'Total'],
            ['PROMOTED', $promoSummary['promoted']['Male'], $promoSummary['promoted']['Female'], $promoSummary['promoted']['Male'] + $promoSummary['promoted']['Female']],
            ['CONDITIONAL', $promoSummary['conditional']['Male'], $promoSummary['conditional']['Female'], $promoSummary['conditional']['Male'] + $promoSummary['conditional']['Female']],
            ['RETAINED', $promoSummary['retained']['Male'], $promoSummary['retained']['Female'], $promoSummary['retained']['Male'] + $promoSummary['retained']['Female']],
            ['INCOMPLETE (PENDING GRADES)', $promoSummary['incomplete']['Male'], $promoSummary['incomplete']['Female'], $promoSummary['incomplete']['Male'] + $promoSummary['incomplete']['Female']],
            [],
            ['LEVEL OF PROGRESS AND ACHIEVEMENT (Based on General Average)'],
            ['Descriptor & Grading Scale', 'Male', 'Female', 'Total'],
            ['Did Not Meet Expectations (74 and below)', $achievementSummary['did_not_meet']['Male'], $achievementSummary['did_not_meet']['Female'], $achievementSummary['did_not_meet']['Male'] + $achievementSummary['did_not_meet']['Female']],
            ['Fairly Satisfactory (75–79)', $achievementSummary['fairly_satisfactory']['Male'], $achievementSummary['fairly_satisfactory']['Female'], $achievementSummary['fairly_satisfactory']['Male'] + $achievementSummary['fairly_satisfactory']['Female']],
            ['Satisfactory (80–84)', $achievementSummary['satisfactory']['Male'], $achievementSummary['satisfactory']['Female'], $achievementSummary['satisfactory']['Male'] + $achievementSummary['satisfactory']['Female']],
            ['Very Satisfactory (85–89)', $achievementSummary['very_satisfactory']['Male'], $achievementSummary['very_satisfactory']['Female'], $achievementSummary['very_satisfactory']['Male'] + $achievementSummary['very_satisfactory']['Female']],
            ['Outstanding (90–100)', $achievementSummary['outstanding']['Male'], $achievementSummary['outstanding']['Female'], $achievementSummary['outstanding']['Male'] + $achievementSummary['outstanding']['Female']],
        ], null, 'A1');

        $sumSheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sumSheet->getStyle('A2:D2')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sumSheet->getStyle('A2:D2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
        $sumSheet->getStyle('A8:D8')->getFont()->setBold(true);
        $sumSheet->getStyle('A9:D9')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sumSheet->getStyle('A9:D9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF065F46');

        foreach ([$sheet, $sumSheet] as $ws) {
            foreach ($ws->getColumnIterator() as $column) {
                $ws->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
