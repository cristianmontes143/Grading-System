<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\StudentIntervention;
use App\Services\Reports\PermanentRecordService;
use App\Services\Reports\ReportCardAssemblyService;
use App\Services\Reports\ReportCardPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function __construct(
        private readonly ReportCardAssemblyService $assembler,
        private readonly ReportCardPdfService $pdfService
    ) {
    }

    public function summary(Request $request, StudentEnrollment $enrollment): View
    {
        $enrollment->loadMissing(['section', 'schoolYear.gradingPeriods', 'student', 'curriculum', 'gradeLevel']);
        $this->authorize('viewAdvisory', $enrollment->section);

        $period = $request->integer('period_id')
            ? GradingPeriod::query()->where('school_year_id', $enrollment->school_year_id)->findOrFail($request->integer('period_id'))
            : null;

        $summary = $this->assembler->assemble($enrollment, $period);
        $interventions = StudentIntervention::query()
            ->with(['subject', 'gradingPeriod', 'recorder'])
            ->where('student_enrollment_id', $enrollment->id)
            ->latest()
            ->get();

        return view('adviser.students.summary', [
            'summary' => $summary,
            'enrollment' => $enrollment,
            'selectedPeriod' => $period,
            'interventions' => $interventions,
            'interventionCategories' => StudentIntervention::categoryLabels(),
        ]);
    }

    public function individual(Request $request, StudentEnrollment $enrollment): Response
    {
        $enrollment->loadMissing(['section', 'schoolYear', 'student']);
        $this->authorize('viewAdvisory', $enrollment->section);

        $period = $request->integer('period_id')
            ? GradingPeriod::query()->where('school_year_id', $enrollment->school_year_id)->findOrFail($request->integer('period_id'))
            : null;

        $allowDraft = $request->boolean('draft', false);

        $pdf = $this->pdfService->generateIndividualPdf($enrollment, $period, $request->user(), $allowDraft);

        $suffix = $period ? $period->code : 'FULL';
        $prefix = $allowDraft ? 'DRAFT_' : '';
        $filename = "{$prefix}ReportCard_{$enrollment->student->student_number}_{$enrollment->schoolYear->name}_{$suffix}.pdf";

        return $pdf->stream($filename);
    }

    public function sectionBatch(Request $request, Section $section): Response
    {
        $this->authorize('viewAdvisory', $section);
        $section->loadMissing(['schoolYear', 'gradeLevel']);

        $period = $request->integer('period_id')
            ? GradingPeriod::query()->where('school_year_id', $section->school_year_id)->findOrFail($request->integer('period_id'))
            : null;

        $allowDraft = $request->boolean('draft', false);

        $pdf = $this->pdfService->generateSectionBatchPdf($section, $period, $request->user(), $allowDraft);

        $suffix = $period ? $period->code : 'FULL';
        $prefix = $allowDraft ? 'DRAFT_' : '';
        $filename = "{$prefix}BatchReportCards_{$section->gradeLevel->code}_{$section->name}_{$section->schoolYear->name}_{$suffix}.pdf";

        return $pdf->stream($filename);
    }

    public function sf10(Request $request, StudentEnrollment $enrollment, PermanentRecordService $permanentRecordService): View|Response
    {
        $enrollment->loadMissing(['section', 'student']);
        $this->authorize('viewAdvisory', $enrollment->section);

        if ($request->boolean('pdf')) {
            return $permanentRecordService->generatePdf($enrollment->student);
        }

        return view('reports.sf10', $permanentRecordService->assembleForStudent($enrollment->student));
    }
}
