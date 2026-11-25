<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Services\Reports\MpsAndSardoService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MpsSardoController extends Controller
{
    public function __construct(private readonly MpsAndSardoService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.mps-sardo.index', $this->buildViewData($request));
    }

    public function printReport(Request $request): View
    {
        return view('admin.mps-sardo.print', $this->buildViewData($request));
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $data = $this->buildViewData($request);
        /** @var SchoolYear|null $schoolYear */
        $schoolYear = $data['selectedSchoolYear'];
        abort_if(! $schoolYear, 404, 'No school year selected.');

        /** @var GradingPeriod|null $period */
        $period = $data['selectedPeriod'];
        $gradeLevelId = $data['selectedGradeLevelId'];

        $spreadsheet = $this->service->exportExcel($schoolYear, $period, $gradeLevelId);
        $safeSy = preg_replace('/[^A-Za-z0-9_-]+/', '_', $schoolYear->name);
        $suffix = $period ? '_' . $period->code : '_FullYear';
        $filename = "DepEd_MPS_SARDO_{$safeSy}{$suffix}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(Request $request): array
    {
        $schoolYears = SchoolYear::query()->with('gradingPeriods')->orderByDesc('is_active')->orderByDesc('name')->get();
        $selectedSchoolYear = $request->filled('school_year_id')
            ? $schoolYears->firstWhere('id', (int) $request->input('school_year_id'))
            : ($schoolYears->firstWhere('is_active', true) ?? $schoolYears->first());

        $periods = $selectedSchoolYear?->gradingPeriods ?? collect();
        $selectedPeriod = $request->filled('grading_period_id')
            ? $periods->firstWhere('id', (int) $request->input('grading_period_id'))
            : null;

        $gradeLevels = GradeLevel::query()->active()->orderBy('sort_order')->get();
        $selectedGradeLevelId = $request->filled('grade_level_id') ? (int) $request->input('grade_level_id') : null;

        $mpsReport = $selectedSchoolYear
            ? $this->service->buildMpsReport($selectedSchoolYear, $selectedPeriod, $selectedGradeLevelId)
            : ['overall_mps' => null, 'overall_mastery' => 'N/A', 'classes_evaluated' => 0, 'classes_meeting_target' => 0, 'classes_below_target' => 0, 'class_rows' => collect(), 'subject_rows' => collect()];

        $sardoReport = $selectedSchoolYear
            ? $this->service->buildSardoReport($selectedSchoolYear, $selectedGradeLevelId)
            : ['learners_tracked' => 0, 'overall_attendance_rate' => null, 'critical_count' => 0, 'warning_count' => 0, 'tardy_watch_count' => 0, 'alerts' => collect()];

        return [
            'school' => SchoolSetting::current(),
            'schoolYears' => $schoolYears,
            'selectedSchoolYear' => $selectedSchoolYear,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'gradeLevels' => $gradeLevels,
            'selectedGradeLevelId' => $selectedGradeLevelId,
            'mps' => $mpsReport,
            'sardo' => $sardoReport,
            'generatedAt' => now(),
        ];
    }
}
