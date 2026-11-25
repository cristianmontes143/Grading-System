<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\GradingPeriod;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Services\Curriculum\CurriculumResolverService;
use App\Services\Grading\GradeSubmissionService;
use App\Services\Reports\CertificatePdfService;
use App\Services\Reports\ReportCardAssemblyService;
use App\Services\Reports\SectionSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvisoryController extends Controller
{
    public function __construct(
        private readonly CurriculumResolverService $curriculumResolver,
        private readonly GradeSubmissionService $submissionService,
        private readonly SectionSummaryService $sectionSummaryService,
        private readonly CertificatePdfService $certificatePdfService
    ) {
    }

    public function index(Request $request): View
    {
        $teacher = $request->user()->teacher;
        $activeSy = SchoolYear::current();
        $activePeriods = $activeSy ? $activeSy->gradingPeriods()->get() : collect();
        $currentPeriod = $activePeriods->firstWhere('status', GradingPeriod::STATUS_OPEN)
            ?? $activePeriods->first();

        $sections = $teacher
            ? Section::query()
                ->with(['schoolYear.gradingPeriods', 'gradeLevel', 'shsTrack', 'shsStrand', 'activeTeacherAssignments.subject', 'activeTeacherAssignments.teacher'])
                ->withCount('currentEnrollments')
                ->where('adviser_id', $teacher->id)
                ->when($activeSy, fn ($q) => $q->where('school_year_id', $activeSy->id))
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get()
            : collect();

        $sectionCards = $sections->map(function (Section $section) use ($currentPeriod) {
            $periodForSection = $currentPeriod && (int) $currentPeriod->school_year_id === (int) $section->school_year_id
                ? $currentPeriod
                : ($section->schoolYear->gradingPeriods->firstWhere('status', GradingPeriod::STATUS_OPEN)
                    ?? $section->schoolYear->gradingPeriods->first());

            $matrix = $periodForSection
                ? $this->submissionService->getSectionCollectionMatrix($section, $periodForSection)
                : null;

            return [
                'section' => $section,
                'period' => $periodForSection,
                'matrix' => $matrix,
            ];
        });

        return view('adviser.sections.index', [
            'sections' => $sections,
            'sectionCards' => $sectionCards,
            'activeSy' => $activeSy,
            'currentPeriod' => $currentPeriod,
        ]);
    }

    public function show(Request $request, Section $section): View
    {
        $this->authorize('viewAdvisory', $section);

        $section->load([
            'schoolYear.gradingPeriods',
            'gradeLevel',
            'shsTrack',
            'shsStrand',
            'adviser',
            'activeTeacherAssignments.subject',
            'activeTeacherAssignments.teacher',
            'currentEnrollments.student',
        ]);

        $curriculum = $this->curriculumResolver->resolveFor($section->school_year_id, $section->grade_level_id);
        $periods = $section->schoolYear->gradingPeriods;

        $selectedPeriod = $periods->firstWhere('id', $request->integer('period_id'))
            ?? $periods->firstWhere('status', GradingPeriod::STATUS_OPEN)
            ?? $periods->first();

        $sectionSummary = $selectedPeriod
            ? $this->sectionSummaryService->buildSectionSummary($section, $selectedPeriod)
            : null;

        $collection = $sectionSummary ? $sectionSummary['collection'] : null;

        $enrolledStudents = $section->currentEnrollments
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        return view('adviser.sections.show', [
            'section' => $section,
            'curriculum' => $curriculum,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'collection' => $collection,
            'sectionSummary' => $sectionSummary,
            'enrolledStudents' => $enrolledStudents,
        ]);
    }

    public function exportMasterSheet(Request $request, Section $section): StreamedResponse
    {
        $this->authorize('viewAdvisory', $section);

        $period = $this->resolveSectionPeriod($section, $request->integer('period_id'));
        $spreadsheet = $this->sectionSummaryService->buildMasterSheetSpreadsheet($section, $period);
        $safeSection = preg_replace('/[^A-Za-z0-9_-]+/', '_', $section->gradeLevel->code . '_' . $section->name);
        $filename = "master_grade_sheet_{$safeSection}_{$period->code}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function printMasterSheet(Request $request, Section $section): View
    {
        $this->authorize('viewAdvisory', $section);

        $period = $this->resolveSectionPeriod($section, $request->integer('period_id'));
        $summary = $this->sectionSummaryService->buildSectionSummary($section, $period);

        return view('adviser.sections.print-summary', [
            'school' => SchoolSetting::current(),
            'summary' => $summary,
        ]);
    }

    public function exportSf5(Section $section): StreamedResponse
    {
        $this->authorize('viewAdvisory', $section);
        $section->loadMissing(['schoolYear', 'gradeLevel']);

        $spreadsheet = $this->sectionSummaryService->buildSf5Spreadsheet($section);
        $safeSection = preg_replace('/[^A-Za-z0-9_-]+/', '_', $section->gradeLevel->code . '_' . $section->name);
        $filename = "DepEd_SF5_{$safeSection}_SY_{$section->schoolYear->name}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function honorCertificates(Request $request, Section $section): Response|RedirectResponse
    {
        $this->authorize('viewAdvisory', $section);
        $period = $this->resolveSectionPeriod($section, $request->integer('period_id'));

        $response = $this->certificatePdfService->generateSectionHonorCertificates($section, $period);
        if (! $response) {
            return redirect()
                ->route('adviser.sections.show', ['section' => $section, 'period_id' => $period->id])
                ->with('error', "No learners in this section currently qualify for {$period->name} Academic Honors (requires complete official grades with General Average ≥ 90 and no failing marks).");
        }

        return $response;
    }

    public function studentHonorCertificate(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        $enrollment->loadMissing(['section', 'schoolYear']);
        $this->authorize('viewAdvisory', $enrollment->section);
        $period = $this->resolveSectionPeriod($enrollment->section, $request->integer('period_id'));

        $response = $this->certificatePdfService->generateIndividualHonorCertificate($enrollment, $period);
        if (! $response) {
            return back()->with('error', "This learner does not currently qualify for a {$period->name} Academic Honor Certificate.");
        }

        return $response;
    }

    public function printRoster(Section $section): View
    {
        $this->authorize('viewAdvisory', $section);
        $section->load(['schoolYear', 'gradeLevel', 'adviser', 'currentEnrollments.student']);

        $enrollments = $section->currentEnrollments
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        return view('reports.class-roster-print', [
            'school' => SchoolSetting::current(),
            'section' => $section,
            'enrollments' => $enrollments,
            'subjectLabel' => null,
            'teacherName' => null,
        ]);
    }

    private function resolveSectionPeriod(Section $section, ?int $periodId): GradingPeriod
    {
        $periods = $section->schoolYear->gradingPeriods()->get();
        $period = ($periodId ? $periods->firstWhere('id', $periodId) : null)
            ?? $periods->firstWhere('status', GradingPeriod::STATUS_OPEN)
            ?? $periods->first();

        abort_unless($period !== null, 404, 'No grading period found for this section.');

        return $period;
    }
}
