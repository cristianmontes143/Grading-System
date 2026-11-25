<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Services\Audit\AuditLogService;
use App\Services\Reports\RegistrarCertificateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrarCertificateController extends Controller
{
    public function __construct(
        private readonly RegistrarCertificateService $certificateService,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function index(Request $request): View
    {
        $schoolYears = SchoolYear::query()->with('gradingPeriods')->orderByDesc('is_active')->orderByDesc('name')->get();
        $selectedSchoolYear = $request->filled('school_year_id')
            ? $schoolYears->firstWhere('id', (int) $request->input('school_year_id'))
            : ($schoolYears->firstWhere('is_active', true) ?? $schoolYears->first());

        $sections = $selectedSchoolYear
            ? Section::query()
                ->with('gradeLevel')
                ->where('school_year_id', $selectedSchoolYear->id)
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get()
            : collect();

        $selectedSectionId = $request->filled('section_id') ? (int) $request->input('section_id') : null;
        $search = trim((string) $request->input('search', ''));

        $enrollments = collect();
        if ($selectedSchoolYear) {
            $query = StudentEnrollment::query()
                ->with(['student', 'gradeLevel', 'section', 'schoolYear', 'shsTrack', 'shsStrand'])
                ->where('school_year_id', $selectedSchoolYear->id)
                ->where('is_current', true);

            if ($selectedSectionId) {
                $query->where('section_id', $selectedSectionId);
            }

            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->whereHas('student', function ($q) use ($like) {
                    $q->where('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like)
                        ->orWhere('lrn', 'ilike', $like)
                        ->orWhere('student_number', 'ilike', $like);
                });
            }

            $enrollments = $query->get()
                ->sortBy(fn (StudentEnrollment $e) => $e->student->last_name . ' ' . $e->student->first_name)
                ->values();
        }

        return view('admin.certificates.index', [
            'schoolYears' => $schoolYears,
            'selectedSchoolYear' => $selectedSchoolYear,
            'sections' => $sections,
            'selectedSectionId' => $selectedSectionId,
            'search' => $search,
            'enrollments' => $enrollments,
            'certificateTypes' => RegistrarCertificateService::types(),
            'periods' => $selectedSchoolYear?->gradingPeriods ?? collect(),
        ]);
    }

    public function generate(Request $request, StudentEnrollment $enrollment): Response
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(RegistrarCertificateService::types()))],
            'purpose' => ['nullable', 'string', 'max:250'],
            'grading_period_id' => ['nullable', 'integer', 'exists:grading_periods,id'],
            'control_number' => ['nullable', 'string', 'max:60'],
        ]);

        $period = ! empty($validated['grading_period_id'])
            ? GradingPeriod::find($validated['grading_period_id'])
            : null;

        $enrollment->loadMissing(['student', 'schoolYear', 'section']);

        $this->auditLog->record(
            eventType: 'registrar.certificate_issued',
            auditable: $enrollment,
            newValues: [
                'certificate_type' => $validated['type'],
                'purpose' => $validated['purpose'] ?? null,
                'control_number' => $validated['control_number'] ?? null,
            ],
            reason: "Issued {$validated['type']} certificate for {$enrollment->student->formal_name} (SY {$enrollment->schoolYear->name}).",
            context: [
                'student_id' => $enrollment->student_id,
            ]
        );

        return $this->certificateService->generatePdf(
            $enrollment,
            $validated['type'],
            $validated['purpose'] ?? null,
            $period,
            $validated['control_number'] ?? null
        );
    }
}
