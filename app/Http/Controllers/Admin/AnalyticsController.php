<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Services\Reports\SectionSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(private readonly SectionSummaryService $sectionSummaryService)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.analytics.index', $this->buildReportData($request));
    }

    public function printReport(Request $request): View
    {
        return view('admin.analytics.print', [
            ...$this->buildReportData($request),
            'school' => SchoolSetting::current(),
            'generatedAt' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportData(Request $request): array
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
        $selectedGradeLevelId = $request->integer('grade_level_id') ?: null;

        $sectionMetrics = collect();
        $topHonors = collect();
        $atRiskList = collect();

        $totals = [
            'learners' => 0,
            'passed' => 0,
            'at_risk' => 0,
            'incomplete' => 0,
            'highest_honors' => 0,
            'high_honors' => 0,
            'with_honors' => 0,
            'total_honors' => 0,
            'passing_rate' => null,
            'overall_average' => null,
            'distribution' => [
                'outstanding' => 0,
                'very_satisfactory' => 0,
                'satisfactory' => 0,
                'fairly_satisfactory' => 0,
                'did_not_meet' => 0,
            ],
        ];

        if ($selectedSy && $selectedPeriod) {
            $sections = Section::query()
                ->with(['schoolYear', 'gradeLevel', 'adviser', 'shsTrack', 'shsStrand'])
                ->where('school_year_id', $selectedSy->id)
                ->where('is_active', true)
                ->when($selectedGradeLevelId, fn ($q) => $q->where('grade_level_id', $selectedGradeLevelId))
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get();

            $allCompleteAverages = collect();

            foreach ($sections as $section) {
                $summary = $this->sectionSummaryService->buildSectionSummary($section, $selectedPeriod);
                $st = $summary['stats'];

                $totals['learners'] += $st['total_learners'];
                $totals['passed'] += $st['passed_count'];
                $totals['at_risk'] += $st['at_risk_count'];
                $totals['incomplete'] += $st['incomplete_count'];
                $totals['highest_honors'] += $st['honor_counts'][SectionSummaryService::HONOR_HIGHEST];
                $totals['high_honors'] += $st['honor_counts'][SectionSummaryService::HONOR_HIGH];
                $totals['with_honors'] += $st['honor_counts'][SectionSummaryService::HONOR_REGULAR];
                $totals['total_honors'] += $st['total_honors'];

                foreach ($totals['distribution'] as $band => $count) {
                    $totals['distribution'][$band] += $st['distribution'][$band];
                }

                foreach ($summary['learner_rows'] as $lr) {
                    if ($lr['is_complete'] && $lr['average'] !== null) {
                        $allCompleteAverages->push($lr['average']);
                    }
                }

                foreach ($summary['honor_roll'] as $hr) {
                    $topHonors->push([
                        'section' => $section,
                        'student' => $hr['student'],
                        'enrollment' => $hr['enrollment'],
                        'average' => $hr['average'],
                        'honor' => $hr['honor'],
                    ]);
                }

                foreach ($summary['at_risk_learners'] as $ar) {
                    $atRiskList->push([
                        'section' => $section,
                        'student' => $ar['student'],
                        'enrollment' => $ar['enrollment'],
                        'average' => $ar['average'],
                        'failing_subjects' => $ar['failing_subjects'],
                    ]);
                }

                $sectionMetrics->push([
                    'section' => $section,
                    'stats' => $st,
                    'progress_percent' => $summary['collection']['progress_percent'],
                ]);
            }

            $evaluated = $totals['passed'] + $totals['at_risk'];
            $totals['passing_rate'] = $evaluated > 0 ? round(($totals['passed'] / $evaluated) * 100, 1) : null;
            $totals['overall_average'] = $allCompleteAverages->isNotEmpty()
                ? round((float) $allCompleteAverages->avg(), 2)
                : null;

            $topHonors = $topHonors
                ->sortByDesc('average')
                ->values()
                ->take(30);
        }

        return [
            'schoolYears' => $schoolYears,
            'selectedSy' => $selectedSy,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'gradeLevels' => $gradeLevels,
            'selectedGradeLevelId' => $selectedGradeLevelId,
            'totals' => $totals,
            'sectionMetrics' => $sectionMetrics,
            'topHonors' => $topHonors,
            'atRiskList' => $atRiskList,
        ];
    }
}
