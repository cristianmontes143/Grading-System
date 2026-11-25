<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\Reports\ReportCardAssemblyService;
use App\Services\Reports\SectionSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentPortalController extends Controller
{
    public function __construct(private readonly ReportCardAssemblyService $assemblyService)
    {
    }

    public function index(): View
    {
        return view('portal.grades', [
            'school' => SchoolSetting::current(),
            'student' => null,
            'enrollments' => collect(),
            'selectedEnrollment' => null,
            'summary' => null,
            'honorDistinction' => null,
            'lookupError' => null,
        ]);
    }

    public function lookup(Request $request): View
    {
        $school = SchoolSetting::current();

        if (! ($school->parent_portal_enabled ?? true)) {
            return view('portal.grades', [
                'school' => $school,
                'student' => null,
                'enrollments' => collect(),
                'selectedEnrollment' => null,
                'summary' => null,
                'honorDistinction' => null,
                'lookupError' => 'The Parent & Learner Grade Portal is currently disabled by the School Administrator.',
            ]);
        }

        $validated = $request->validate([
            'student_number' => ['required', 'string', 'max:50'],
            'lrn' => ['required', 'digits:12'],
            'date_of_birth' => ['required', 'date'],
            'enrollment_id' => ['nullable', 'integer'],
        ]);

        $student = Student::query()
            ->where('student_number', trim($validated['student_number']))
            ->where('lrn', trim($validated['lrn']))
            ->whereDate('date_of_birth', $validated['date_of_birth'])
            ->where('status', '!=', Student::STATUS_ARCHIVED)
            ->first();

        if (! $student) {
            return view('portal.grades', [
                'school' => $school,
                'student' => null,
                'enrollments' => collect(),
                'selectedEnrollment' => null,
                'summary' => null,
                'honorDistinction' => null,
                'lookupError' => 'No matching learner record found. Please verify your Student ID, 12-digit DepEd LRN, and Date of Birth.',
            ]);
        }

        $enrollments = $student->enrollments()
            ->with(['schoolYear', 'gradeLevel', 'section.adviser', 'curriculum'])
            ->orderByDesc('is_current')
            ->orderByDesc('school_year_id')
            ->get();

        $selectedEnrollment = (! empty($validated['enrollment_id'])
            ? $enrollments->firstWhere('id', (int) $validated['enrollment_id'])
            : null) ?? $enrollments->first();

        $summary = $selectedEnrollment ? $this->assemblyService->assemble($selectedEnrollment) : null;
        $honorDistinction = null;

        if ($summary && ! $summary['has_missing_grades'] && $summary['general_average'] !== null) {
            $hasFailing = collect($summary['subject_rows'])
                ->contains(fn (array $r) => $r['final_grade'] !== null && (float) $r['final_grade'] < 75.0);
            $honorDistinction = SectionSummaryService::resolveHonorDistinction(
                (float) $summary['general_average'],
                $hasFailing,
                true
            );
        }

        return view('portal.grades', [
            'school' => $school,
            'student' => $student,
            'enrollments' => $enrollments,
            'selectedEnrollment' => $selectedEnrollment,
            'summary' => $summary,
            'honorDistinction' => $honorDistinction,
            'lookupError' => null,
        ]);
    }
}
