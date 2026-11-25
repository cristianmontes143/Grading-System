<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeLevelController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(): View
    {
        $gradeLevels = GradeLevel::query()
            ->withCount(['sections', 'subjects'])
            ->ordered()
            ->get();

        return view('admin.grade-levels.index', [
            'gradeLevels' => $gradeLevels,
        ]);
    }

    public function toggle(Request $request, GradeLevel $gradeLevel): RedirectResponse
    {
        $oldState = $gradeLevel->is_active;
        $gradeLevel->update(['is_active' => ! $oldState]);

        $this->auditLog->record(
            eventType: 'grade_level.toggled',
            auditable: $gradeLevel,
            oldValues: ['is_active' => $oldState],
            newValues: ['is_active' => $gradeLevel->is_active],
            request: $request
        );

        $label = $gradeLevel->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('admin.grade-levels.index')
            ->with('status', "{$gradeLevel->name} has been {$label}.");
    }
}
