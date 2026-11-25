<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Services\Students\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotionService)
    {
    }

    public function index(Request $request): View
    {
        $schoolYears = SchoolYear::query()->orderByDesc('start_date')->get();
        $sourceSy = $schoolYears->firstWhere('id', $request->integer('source_school_year_id'))
            ?? $schoolYears->firstWhere('is_active', true)
            ?? $schoolYears->first();

        $sourceSections = $sourceSy
            ? Section::query()
                ->with(['gradeLevel', 'adviser'])
                ->withCount('currentEnrollments')
                ->where('school_year_id', $sourceSy->id)
                ->where('is_active', true)
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get()
            : collect();

        $selectedSection = $sourceSections->firstWhere('id', $request->integer('source_section_id'))
            ?? $sourceSections->first();

        $targetSections = $sourceSy
            ? Section::query()
                ->with(['schoolYear', 'gradeLevel'])
                ->where('school_year_id', '!=', $sourceSy->id)
                ->where('is_active', true)
                ->orderByDesc('school_year_id')
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get()
            : collect();

        $evaluations = $selectedSection
            ? $this->promotionService->evaluateSection($selectedSection)
            : collect();

        return view('admin.promotions.index', [
            'schoolYears' => $schoolYears,
            'sourceSy' => $sourceSy,
            'sourceSections' => $sourceSections,
            'selectedSection' => $selectedSection,
            'targetSections' => $targetSections,
            'evaluations' => $evaluations,
        ]);
    }

    public function execute(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_section_id' => ['required', 'integer', 'exists:sections,id'],
            'action' => ['required', Rule::in([
                PromotionService::ACTION_PROMOTE,
                PromotionService::ACTION_RETAIN,
                PromotionService::ACTION_GRADUATE,
            ])],
            'target_section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'exists:student_enrollments,id'],
        ]);

        $sourceSection = Section::query()->with(['schoolYear', 'gradeLevel'])->findOrFail($validated['source_section_id']);
        $targetSection = ! empty($validated['target_section_id'])
            ? Section::query()->with(['schoolYear', 'gradeLevel'])->findOrFail($validated['target_section_id'])
            : null;

        $result = $this->promotionService->executeBatch(
            $sourceSection,
            array_map('intval', $validated['enrollment_ids']),
            $validated['action'],
            $targetSection,
            $request->user()
        );

        $msg = "Year-end rollover completed: {$result['processed']} learner record(s) updated";
        if ($result['enrolled'] > 0 && $targetSection) {
            $msg .= " and {$result['enrolled']} enrolled into SY {$targetSection->schoolYear->name} ({$targetSection->gradeLevel->name} – {$targetSection->name})";
        }
        $msg .= '.';

        $redirect = redirect()
            ->route('admin.promotions.index', [
                'source_school_year_id' => $sourceSection->school_year_id,
                'source_section_id' => $sourceSection->id,
            ])
            ->with('status', $msg);

        if (! empty($result['skipped'])) {
            $redirect->with('promotion_warnings', $result['skipped']);
        }

        return $redirect;
    }
}
