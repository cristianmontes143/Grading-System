<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use App\Services\Audit\AuditLogService;
use App\Services\Curriculum\CurriculumResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolYearController extends Controller
{
    public function __construct(
        private readonly CurriculumResolverService $curriculumResolver,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function index(): View
    {
        $schoolYears = SchoolYear::query()
            ->with(['gradingPeriods.lockedByUser'])
            ->withCount(['sections', 'curriculumImplementations'])
            ->orderByDesc('start_date')
            ->get();

        return view('admin.school-years.index', [
            'schoolYears' => $schoolYears,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:30', 'unique:school_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'term_structure' => ['required', Rule::in([
                SchoolYear::TERM_TRIMESTER,
                SchoolYear::TERM_QUARTERLY,
                SchoolYear::TERM_SEMESTRAL,
            ])],
            'set_active' => ['nullable', 'boolean'],
            'generate_periods' => ['nullable', 'boolean'],
            'open_first_period' => ['nullable', 'boolean'],
        ]);

        $schoolYear = DB::transaction(function () use ($validated, $request): SchoolYear {
            $sy = SchoolYear::query()->create([
                'name' => trim($validated['name']),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'term_structure' => $validated['term_structure'],
                'is_active' => false,
                'is_closed' => false,
            ]);

            if ($request->boolean('set_active')) {
                $this->curriculumResolver->activateSchoolYear($sy);
            }

            if ($request->boolean('generate_periods', true)) {
                $this->curriculumResolver->seedStandardPeriodsForSchoolYear($sy);

                if ($request->boolean('open_first_period')) {
                    $sy->gradingPeriods()->orderBy('sequence')->first()?->update([
                        'status' => \App\Models\GradingPeriod::STATUS_OPEN,
                    ]);
                }
            }

            return $sy->fresh();
        });

        $this->auditLog->record(
            eventType: 'school_year.created',
            auditable: $schoolYear,
            newValues: $schoolYear->only(['name', 'start_date', 'end_date', 'term_structure', 'is_active']),
            request: $request
        );

        return redirect()
            ->route('admin.school-years.index')
            ->with('status', "School Year {$schoolYear->name} created successfully.");
    }

    public function activate(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        if ($schoolYear->is_closed) {
            return redirect()
                ->route('admin.school-years.index')
                ->with('error', "Closed School Year {$schoolYear->name} cannot be activated.");
        }

        $this->curriculumResolver->activateSchoolYear($schoolYear);

        $this->auditLog->record(
            eventType: 'school_year.activated',
            auditable: $schoolYear,
            newValues: ['name' => $schoolYear->name, 'is_active' => true],
            request: $request
        );

        return redirect()
            ->route('admin.school-years.index')
            ->with('status', "School Year {$schoolYear->name} is now the active school year.");
    }

    public function close(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        DB::transaction(function () use ($schoolYear): void {
            $schoolYear->gradingPeriods()->update([
                'status' => 'locked',
                'locked_at' => now(),
                'locked_by' => auth()->id(),
            ]);

            $schoolYear->update([
                'is_closed' => true,
                'is_active' => false,
            ]);
        });

        $this->auditLog->record(
            eventType: 'school_year.closed',
            auditable: $schoolYear,
            newValues: ['name' => $schoolYear->name, 'is_closed' => true],
            request: $request
        );

        return redirect()
            ->route('admin.school-years.index')
            ->with('status', "School Year {$schoolYear->name} has been closed and all grading periods locked.");
    }
}
