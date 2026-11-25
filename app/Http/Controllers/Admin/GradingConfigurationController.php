<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\GradeLevel;
use App\Models\GradingComponent;
use App\Models\GradingConfiguration;
use App\Models\PolicyReference;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\TransmutationTable;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GradingConfigurationController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request): View
    {
        $curricula = Curriculum::query()->orderBy('id')->get();
        $schoolYears = SchoolYear::query()->orderByDesc('start_date')->get();
        $gradeLevels = GradeLevel::query()->ordered()->get();
        $subjects = Subject::query()->active()->orderBy('name')->get();
        $transmutationTables = TransmutationTable::query()->where('is_active', true)->orderBy('id')->get();
        $policies = PolicyReference::query()->orderBy('document_number')->get();

        $query = GradingConfiguration::query()
            ->with(['schoolYear', 'curriculum', 'gradeLevel', 'subject', 'transmutationTable', 'policyReference', 'topLevelComponents.children', 'confirmer'])
            ->orderBy('curriculum_id')
            ->orderByDesc('school_year_id')
            ->orderBy('id');

        if ($currId = $request->integer('curriculum_id')) {
            $query->where('curriculum_id', $currId);
        }

        $configurations = $query->get();

        return view('admin.grading-policies.index', [
            'configurations' => $configurations,
            'curricula' => $curricula,
            'schoolYears' => $schoolYears,
            'gradeLevels' => $gradeLevels,
            'subjects' => $subjects,
            'transmutationTables' => $transmutationTables,
            'policies' => $policies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'school_year_id' => ['nullable', 'exists:school_years,id'],
            'curriculum_id' => ['required', 'exists:curricula,id'],
            'grade_level_id' => ['nullable', 'exists:grade_levels,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'weight_group' => ['nullable', 'string', 'max:50'],
            'grading_mode' => ['required', Rule::in([
                GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                GradingConfiguration::MODE_NUMERICAL_ZERO_BASED,
                GradingConfiguration::MODE_DESCRIPTIVE,
            ])],
            'transmutation_table_id' => ['nullable', 'exists:transmutation_tables,id'],
            'passing_grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'rounding_precision' => ['required', 'integer', 'min:0', 'max:2'],
            'policy_reference_id' => ['nullable', 'exists:policy_references,id'],
            'components' => ['nullable', 'array'],
            'components.*.code' => ['required_with:components', 'string', 'max:30'],
            'components.*.name' => ['required_with:components', 'string', 'max:100'],
            'components.*.weight_percentage' => ['required_with:components', 'numeric', 'gt:0', 'lte:100'],
        ]);

        if ($validated['grading_mode'] === GradingConfiguration::MODE_NUMERICAL_TRANSMUTED && empty($validated['transmutation_table_id'])) {
            throw ValidationException::withMessages([
                'transmutation_table_id' => 'Please select a transmutation table when using Transmuted Numerical grading mode.',
            ]);
        }

        $components = $validated['components'] ?? [];
        if ($validated['grading_mode'] !== GradingConfiguration::MODE_DESCRIPTIVE) {
            if (empty($components)) {
                throw ValidationException::withMessages([
                    'components' => 'Numerical grading configurations must define at least one weighted assessment component.',
                ]);
            }

            $weightSum = round(collect($components)->sum(fn ($c) => (float) $c['weight_percentage']), 2);
            if (abs($weightSum - 100.0) > 0.01) {
                throw ValidationException::withMessages([
                    'components' => "Component weights must total exactly 100.00% (currently {$weightSum}%).",
                ]);
            }
        }

        $config = DB::transaction(function () use ($validated, $components): GradingConfiguration {
            $cfg = GradingConfiguration::query()->create([
                'name' => trim($validated['name']),
                'school_year_id' => $validated['school_year_id'] ?? null,
                'curriculum_id' => $validated['curriculum_id'],
                'grade_level_id' => $validated['grade_level_id'] ?? null,
                'subject_id' => $validated['subject_id'] ?? null,
                'weight_group' => $validated['weight_group'] ?? null,
                'grading_mode' => $validated['grading_mode'],
                'transmutation_table_id' => $validated['grading_mode'] === GradingConfiguration::MODE_NUMERICAL_TRANSMUTED
                    ? $validated['transmutation_table_id']
                    : null,
                'passing_grade' => $validated['passing_grade'],
                'rounding_precision' => $validated['rounding_precision'],
                'policy_reference_id' => $validated['policy_reference_id'] ?? null,
                'descriptive_scale_json' => $validated['grading_mode'] === GradingConfiguration::MODE_DESCRIPTIVE
                    ? [
                        ['code' => 'CO', 'label' => 'Consistent'],
                        ['code' => 'DV', 'label' => 'Developing'],
                        ['code' => 'BG', 'label' => 'Beginning'],
                    ]
                    : null,
                'is_confirmed_by_admin' => true,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'is_active' => true,
            ]);

            foreach (array_values($components) as $idx => $comp) {
                GradingComponent::query()->create([
                    'grading_configuration_id' => $cfg->id,
                    'code' => strtoupper(trim($comp['code'])),
                    'name' => trim($comp['name']),
                    'weight_percentage' => $comp['weight_percentage'],
                    'sort_order' => ($idx + 1) * 10,
                ]);
            }

            return $cfg;
        });

        $this->auditLog->record(
            eventType: 'grading_configuration.created',
            auditable: $config,
            newValues: [
                'name' => $config->name,
                'grading_mode' => $config->grading_mode,
                'components' => $components,
            ],
            request: $request
        );

        return redirect()
            ->route('admin.grading-policies.index')
            ->with('status', "Grading configuration \"{$config->name}\" saved.");
    }

    public function confirm(Request $request, GradingConfiguration $gradingConfiguration): RedirectResponse
    {
        $gradingConfiguration->update([
            'is_confirmed_by_admin' => true,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        $this->auditLog->record(
            eventType: 'grading_configuration.confirmed',
            auditable: $gradingConfiguration,
            newValues: ['name' => $gradingConfiguration->name, 'is_confirmed_by_admin' => true],
            request: $request
        );

        return redirect()
            ->route('admin.grading-policies.index')
            ->with('status', "Grading configuration \"{$gradingConfiguration->name}\" confirmed as active school policy.");
    }

    public function toggle(Request $request, GradingConfiguration $gradingConfiguration): RedirectResponse
    {
        $old = $gradingConfiguration->is_active;
        $gradingConfiguration->update(['is_active' => ! $old]);

        $this->auditLog->record(
            eventType: 'grading_configuration.toggled',
            auditable: $gradingConfiguration,
            oldValues: ['is_active' => $old],
            newValues: ['is_active' => $gradingConfiguration->is_active],
            request: $request
        );

        return back()->with('status', "Grading configuration status updated.");
    }
}
