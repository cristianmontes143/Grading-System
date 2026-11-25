<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RemedialClass;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Services\Audit\AuditLogService;
use App\Services\Reports\ReportCardAssemblyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RemedialClassController extends Controller
{
    public function __construct(
        private readonly ReportCardAssemblyService $assemblyService,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function index(Request $request): View
    {
        $schoolYears = SchoolYear::query()->orderByDesc('is_active')->orderByDesc('name')->get();
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

        $candidates = collect();
        $recordedRemedials = collect();
        $enrollmentsForManual = collect();

        if ($selectedSchoolYear) {
            $enrollmentQuery = StudentEnrollment::query()
                ->with(['student', 'gradeLevel', 'section', 'curriculum', 'remedialClasses.subject'])
                ->where('school_year_id', $selectedSchoolYear->id)
                ->where('is_current', true);

            if ($selectedSectionId) {
                $enrollmentQuery->where('section_id', $selectedSectionId);
            }

            if ($search !== '') {
                $like = '%' . $search . '%';
                $enrollmentQuery->whereHas('student', function ($q) use ($like) {
                    $q->where('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like)
                        ->orWhere('lrn', 'ilike', $like)
                        ->orWhere('student_number', 'ilike', $like);
                });
            }

            $enrollments = $enrollmentQuery->get()
                ->sortBy(fn (StudentEnrollment $e) => $e->student->last_name . ' ' . $e->student->first_name)
                ->values();

            $enrollmentsForManual = $enrollments;

            foreach ($enrollments as $enrollment) {
                $assembled = $this->assemblyService->assemble($enrollment);
                $existingBySubject = $enrollment->remedialClasses->keyBy('subject_id');

                foreach ($assembled['subject_rows'] as $row) {
                    if ($row['final_grade'] !== null && (float) $row['final_grade'] < 75.0) {
                        $candidates->push([
                            'enrollment' => $enrollment,
                            'subject' => $row['subject'],
                            'final_rating' => (float) $row['final_grade'],
                            'remedial' => $existingBySubject->get($row['subject']->id),
                        ]);
                    }
                }
            }

            $recordedQuery = RemedialClass::query()
                ->with(['enrollment.student', 'enrollment.gradeLevel', 'enrollment.section', 'subject', 'recorder'])
                ->whereHas('enrollment', function ($q) use ($selectedSchoolYear, $selectedSectionId, $search) {
                    $q->where('school_year_id', $selectedSchoolYear->id);
                    if ($selectedSectionId) {
                        $q->where('section_id', $selectedSectionId);
                    }
                    if ($search !== '') {
                        $like = '%' . $search . '%';
                        $q->whereHas('student', function ($sq) use ($like) {
                            $sq->where('first_name', 'ilike', $like)
                                ->orWhere('last_name', 'ilike', $like)
                                ->orWhere('lrn', 'ilike', $like)
                                ->orWhere('student_number', 'ilike', $like);
                        });
                    }
                })
                ->orderByDesc('updated_at');

            $recordedRemedials = $recordedQuery->get();
        }

        $subjects = Subject::query()
            ->where('is_active', true)
            ->where('is_graded', true)
            ->whereNull('parent_subject_id')
            ->orderBy('name')
            ->get();

        $summary = [
            'candidates_count' => $candidates->count(),
            'pending_count' => $candidates->whereNull('remedial')->count(),
            'recorded_count' => $recordedRemedials->count(),
            'passed_count' => $recordedRemedials->where('remarks', RemedialClass::REMARKS_PASSED)->count(),
            'failed_count' => $recordedRemedials->where('remarks', RemedialClass::REMARKS_FAILED)->count(),
        ];

        return view('admin.remedials.index', [
            'school' => SchoolSetting::current(),
            'schoolYears' => $schoolYears,
            'selectedSchoolYear' => $selectedSchoolYear,
            'sections' => $sections,
            'selectedSectionId' => $selectedSectionId,
            'search' => $search,
            'candidates' => $candidates,
            'recordedRemedials' => $recordedRemedials,
            'enrollmentsForManual' => $enrollmentsForManual,
            'subjects' => $subjects,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_enrollment_id' => ['required', 'integer', 'exists:student_enrollments,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'final_rating' => ['required', 'numeric', 'min:50', 'max:74.99'],
            'remedial_class_mark' => ['required', 'numeric', 'min:60', 'max:100'],
            'conducted_from' => ['nullable', 'date'],
            'conducted_to' => ['nullable', 'date', 'after_or_equal:conducted_from'],
            'conducted_by' => ['nullable', 'string', 'max:150'],
            'school_name' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $enrollment = StudentEnrollment::with(['student', 'section'])->findOrFail($validated['student_enrollment_id']);
        $subject = Subject::findOrFail($validated['subject_id']);

        $finalRating = round((float) $validated['final_rating'], 2);
        $remedialMark = round((float) $validated['remedial_class_mark'], 2);
        $recomputed = RemedialClass::computeRecomputedGrade($finalRating, $remedialMark);
        $remarks = $recomputed >= 75.0 ? RemedialClass::REMARKS_PASSED : RemedialClass::REMARKS_FAILED;

        $remedial = RemedialClass::query()->updateOrCreate(
            [
                'student_enrollment_id' => $enrollment->id,
                'subject_id' => $subject->id,
            ],
            [
                'final_rating' => $finalRating,
                'remedial_class_mark' => $remedialMark,
                'recomputed_final_grade' => $recomputed,
                'remarks' => $remarks,
                'conducted_from' => $validated['conducted_from'] ?? null,
                'conducted_to' => $validated['conducted_to'] ?? null,
                'conducted_by' => $validated['conducted_by'] ?? null,
                'school_name' => ($validated['school_name'] ?? null) ?: SchoolSetting::current()->school_name,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]
        );

        $this->auditLog->record(
            eventType: 'remedial.recorded',
            auditable: $remedial,
            newValues: [
                'final_rating' => $finalRating,
                'remedial_class_mark' => $remedialMark,
                'recomputed_final_grade' => $recomputed,
                'remarks' => $remarks,
            ],
            reason: "Recorded remedial class for {$enrollment->student->formal_name} in {$subject->name}: Final {$finalRating}, Remedial {$remedialMark}, Recomputed {$recomputed} ({$remarks}).",
            context: [
                'student_id' => $enrollment->student_id,
                'subject_id' => $subject->id,
                'previous_grade' => $finalRating,
                'updated_grade' => $recomputed,
            ]
        );

        return back()->with('status', "Remedial record saved for {$enrollment->student->formal_name} ({$subject->code}) — Recomputed Final Grade: " . number_format($recomputed, 0) . " ({$remarks}).");
    }

    public function destroy(RemedialClass $remedialClass): RedirectResponse
    {
        $remedialClass->loadMissing(['enrollment.student', 'subject']);
        $desc = "{$remedialClass->enrollment->student->formal_name} — {$remedialClass->subject->name}";
        $remedialClass->delete();

        $this->auditLog->record(
            eventType: 'remedial.deleted',
            auditable: $remedialClass,
            oldValues: $remedialClass->only(['final_rating', 'remedial_class_mark', 'recomputed_final_grade', 'remarks']),
            reason: "Deleted remedial class record for {$desc}.",
            context: [
                'student_id' => $remedialClass->enrollment->student_id,
                'subject_id' => $remedialClass->subject_id,
            ]
        );

        return back()->with('status', "Remedial class record removed for {$desc}.");
    }

    public function certificatePdf(StudentEnrollment $enrollment): Response
    {
        $enrollment->loadMissing([
            'student',
            'schoolYear',
            'gradeLevel',
            'section.adviser',
            'curriculum',
            'remedialClasses.subject',
        ]);

        abort_if($enrollment->remedialClasses->isEmpty(), 404, 'No remedial class records exist for this enrollment.');

        $school = SchoolSetting::current();

        $pdf = Pdf::loadView('reports.remedial-certificate-pdf', [
            'school' => $school,
            'enrollment' => $enrollment,
            'remedials' => $enrollment->remedialClasses,
            'issuedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'portrait');

        $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', $enrollment->student->student_number);

        return $pdf->stream("Remedial_Certificate_{$safeId}.pdf");
    }
}
