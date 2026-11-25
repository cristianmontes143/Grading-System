<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\PolicyReference;
use App\Models\SchoolYear;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurriculumController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request): View
    {
        $curricula = Curriculum::query()
            ->with(['policyReference', 'shsTracks.strands'])
            ->withCount('subjects')
            ->orderBy('id')
            ->get();

        $policies = PolicyReference::query()->orderBy('document_number')->get();
        $schoolYears = SchoolYear::query()->orderByDesc('start_date')->get();
        $selectedSyId = $request->integer('school_year_id') ?: ($schoolYears->firstWhere('is_active', true)?->id ?? $schoolYears->first()?->id);

        $gradeLevels = GradeLevel::query()->ordered()->get();
        $implementations = $selectedSyId
            ? CurriculumGradeImplementation::query()
                ->where('school_year_id', $selectedSyId)
                ->get()
                ->keyBy('grade_level_id')
            : collect();

        return view('admin.curricula.index', [
            'curricula' => $curricula,
            'policies' => $policies,
            'schoolYears' => $schoolYears,
            'selectedSyId' => $selectedSyId,
            'gradeLevels' => $gradeLevels,
            'implementations' => $implementations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', 'unique:curricula,code'],
            'name' => ['required', 'string', 'max:150'],
            'version' => ['required', 'string', 'max:40'],
            'education_stage' => ['required', Rule::in(['kindergarten', 'elementary', 'junior_high', 'senior_high', 'k_to_10'])],
            'policy_reference_id' => ['nullable', 'exists:policy_references,id'],
            'status' => ['required', Rule::in(['draft', 'active', 'transitioning', 'archived'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $curriculum = Curriculum::query()->create([
            ...$validated,
            'code' => strtoupper(trim($validated['code'])),
        ]);

        $this->auditLog->record(
            eventType: 'curriculum.created',
            auditable: $curriculum,
            newValues: $curriculum->only(['code', 'name', 'version', 'education_stage', 'status']),
            request: $request
        );

        return redirect()
            ->route('admin.curricula.index')
            ->with('status', "Curriculum {$curriculum->name} ({$curriculum->version}) created.");
    }

    public function saveImplementations(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_year_id' => ['required', 'exists:school_years,id'],
            'mappings' => ['required', 'array'],
            'mappings.*.curriculum_id' => ['nullable', 'exists:curricula,id'],
            'mappings.*.implementation_status' => ['nullable', Rule::in(['active', 'pilot', 'phasing_out'])],
        ]);

        $schoolYear = SchoolYear::query()->findOrFail($validated['school_year_id']);

        if ($schoolYear->is_closed) {
            return redirect()
                ->route('admin.curricula.index', ['school_year_id' => $schoolYear->id])
                ->with('error', 'Cannot alter curriculum implementations for a closed school year.');
        }

        DB::transaction(function () use ($validated, $schoolYear): void {
            foreach ($validated['mappings'] as $gradeLevelId => $row) {
                if (empty($row['curriculum_id'])) {
                    continue;
                }

                CurriculumGradeImplementation::query()->updateOrCreate(
                    [
                        'school_year_id' => $schoolYear->id,
                        'grade_level_id' => (int) $gradeLevelId,
                    ],
                    [
                        'curriculum_id' => (int) $row['curriculum_id'],
                        'implementation_status' => $row['implementation_status'] ?? 'active',
                    ]
                );
            }
        });

        $this->auditLog->record(
            eventType: 'curriculum_implementations.updated',
            auditable: $schoolYear,
            newValues: ['school_year' => $schoolYear->name, 'mappings_count' => count($validated['mappings'])],
            request: $request
        );

        return redirect()
            ->route('admin.curricula.index', ['school_year_id' => $schoolYear->id])
            ->with('status', "Curriculum phased implementation matrix saved for SY {$schoolYear->name}.");
    }

    public function storeTrack(Request $request, Curriculum $curriculum): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('shs_tracks', 'code')->where('curriculum_id', $curriculum->id),
            ],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $track = $curriculum->shsTracks()->create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'is_active' => true,
        ]);

        $this->auditLog->record(
            eventType: 'shs_track.created',
            auditable: $track,
            newValues: $track->only(['curriculum_id', 'code', 'name']),
            request: $request
        );

        return redirect()
            ->route('admin.curricula.index')
            ->with('status', "SHS Track {$track->name} added to {$curriculum->name}.");
    }

    public function storeStrand(Request $request, ShsTrack $shsTrack): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('shs_strands', 'code')->where('shs_track_id', $shsTrack->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['strand', 'elective_cluster'])],
        ]);

        $strand = $shsTrack->strands()->create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'is_active' => true,
        ]);

        $this->auditLog->record(
            eventType: 'shs_strand.created',
            auditable: $strand,
            newValues: $strand->only(['shs_track_id', 'code', 'name', 'type']),
            request: $request
        );

        return redirect()
            ->route('admin.curricula.index')
            ->with('status', "SHS {$strand->type} {$strand->name} added to {$shsTrack->name}.");
    }
}
