<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\ShsTrack;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request): View
    {
        $schoolYears = SchoolYear::query()->orderByDesc('start_date')->get();
        $gradeLevels = GradeLevel::query()->active()->ordered()->get();
        $tracks = ShsTrack::query()->with(['curriculum', 'strands'])->where('is_active', true)->orderBy('name')->get();

        $selectedSyId = $request->integer('school_year_id')
            ?: ($schoolYears->firstWhere('is_active', true)?->id ?? $schoolYears->first()?->id);

        $query = Section::query()
            ->with(['schoolYear', 'gradeLevel', 'shsTrack', 'shsStrand', 'adviser'])
            ->withCount('currentEnrollments')
            ->orderBy('grade_level_id')
            ->orderBy('name');

        if ($selectedSyId) {
            $query->where('school_year_id', $selectedSyId);
        }

        if ($gradeLevelId = $request->integer('grade_level_id')) {
            $query->where('grade_level_id', $gradeLevelId);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('room', 'ilike', "%{$search}%");
            });
        }

        $sections = $query->paginate(20)->withQueryString();

        return view('admin.sections.index', [
            'sections' => $sections,
            'schoolYears' => $schoolYears,
            'selectedSyId' => $selectedSyId,
            'gradeLevels' => $gradeLevels,
            'tracks' => $tracks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_year_id' => ['required', 'exists:school_years,id'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'shs_track_id' => ['nullable', 'exists:shs_tracks,id'],
            'shs_strand_id' => ['nullable', 'exists:shs_strands,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sections', 'name')
                    ->where('school_year_id', $request->input('school_year_id'))
                    ->where('grade_level_id', $request->input('grade_level_id')),
            ],
            'room' => ['nullable', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $section = Section::query()->create([
            ...$validated,
            'name' => trim($validated['name']),
            'is_active' => true,
        ]);

        $this->auditLog->record(
            eventType: 'section.created',
            auditable: $section,
            newValues: $section->only(['school_year_id', 'grade_level_id', 'name', 'room', 'capacity']),
            request: $request
        );

        return redirect()
            ->route('admin.sections.index', ['school_year_id' => $section->school_year_id])
            ->with('status', "Section {$section->name} created successfully.");
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sections', 'name')
                    ->where('school_year_id', $section->school_year_id)
                    ->where('grade_level_id', $section->grade_level_id)
                    ->ignore($section->id),
            ],
            'room' => ['nullable', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldValues = $section->only(['name', 'room', 'capacity', 'is_active']);

        $section->update([
            'name' => trim($validated['name']),
            'room' => $validated['room'] ?? null,
            'capacity' => $validated['capacity'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditLog->record(
            eventType: 'section.updated',
            auditable: $section,
            oldValues: $oldValues,
            newValues: $section->only(['name', 'room', 'capacity', 'is_active']),
            request: $request
        );

        return redirect()
            ->route('admin.sections.index', ['school_year_id' => $section->school_year_id])
            ->with('status', "Section {$section->name} updated.");
    }

    public function cloneFromSchoolYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'target_school_year_id' => ['required', 'integer', 'exists:school_years,id', 'different:source_school_year_id'],
        ]);

        $sourceSy = SchoolYear::query()->findOrFail($validated['source_school_year_id']);
        $targetSy = SchoolYear::query()->findOrFail($validated['target_school_year_id']);

        $sourceSections = Section::query()
            ->where('school_year_id', $sourceSy->id)
            ->where('is_active', true)
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();

        if ($sourceSections->isEmpty()) {
            return back()->with('error', "SY {$sourceSy->name} has no active sections to copy.");
        }

        $existingKeys = Section::query()
            ->where('school_year_id', $targetSy->id)
            ->get()
            ->map(fn (Section $s) => $s->grade_level_id . ':' . mb_strtolower(trim($s->name)))
            ->all();

        $createdCount = 0;

        DB::transaction(function () use ($sourceSections, $targetSy, $existingKeys, &$createdCount): void {
            foreach ($sourceSections as $src) {
                $key = $src->grade_level_id . ':' . mb_strtolower(trim($src->name));
                if (in_array($key, $existingKeys, true)) {
                    continue;
                }

                Section::query()->create([
                    'school_year_id' => $targetSy->id,
                    'grade_level_id' => $src->grade_level_id,
                    'shs_track_id' => $src->shs_track_id,
                    'shs_strand_id' => $src->shs_strand_id,
                    'name' => $src->name,
                    'room' => $src->room,
                    'capacity' => $src->capacity,
                    'is_active' => true,
                ]);
                $createdCount++;
            }
        });

        if ($createdCount > 0) {
            $this->auditLog->record(
                eventType: 'section.cloned_from_school_year',
                auditable: $targetSy,
                newValues: [
                    'source_school_year_id' => $sourceSy->id,
                    'target_school_year_id' => $targetSy->id,
                    'cloned_count' => $createdCount,
                ],
                request: $request
            );
        }

        $msg = $createdCount > 0
            ? "Copied {$createdCount} section(s) from SY {$sourceSy->name} into SY {$targetSy->name}."
            : "All active sections from SY {$sourceSy->name} already exist in SY {$targetSy->name}.";

        return redirect()
            ->route('admin.sections.index', ['school_year_id' => $targetSy->id])
            ->with('status', $msg);
    }
}
