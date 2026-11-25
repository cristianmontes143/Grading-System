<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradingPeriodController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function store(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('grading_periods', 'code')->where('school_year_id', $schoolYear->id),
            ],
            'sequence' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('grading_periods', 'sequence')->where('school_year_id', $schoolYear->id),
            ],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'submission_deadline' => ['nullable', 'date'],
        ]);

        $period = $schoolYear->gradingPeriods()->create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'sequence' => $validated['sequence'],
            'semester' => $validated['semester'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'submission_deadline' => $validated['submission_deadline'] ?? null,
            'status' => GradingPeriod::STATUS_UPCOMING,
        ]);

        $this->auditLog->record(
            eventType: 'grading_period.created',
            auditable: $period,
            newValues: $period->only(['school_year_id', 'code', 'name', 'sequence', 'status']),
            request: $request
        );

        return redirect()
            ->route('admin.school-years.index')
            ->with('status', "Grading period {$period->name} added to SY {$schoolYear->name}.");
    }

    public function open(Request $request, GradingPeriod $gradingPeriod): RedirectResponse
    {
        if ($gradingPeriod->schoolYear->is_closed) {
            return redirect()
                ->route('admin.school-years.index')
                ->with('error', 'Cannot open a grading period inside a closed school year.');
        }

        if ($gradingPeriod->isLocked()) {
            return redirect()
                ->route('admin.school-years.index')
                ->with('error', 'This grading period is locked. Use Unlock with an audit reason instead.');
        }

        $oldStatus = $gradingPeriod->status;
        $gradingPeriod->update(['status' => GradingPeriod::STATUS_OPEN]);

        $this->auditLog->record(
            eventType: 'grading_period.opened',
            auditable: $gradingPeriod,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => GradingPeriod::STATUS_OPEN],
            request: $request
        );

        return redirect()
            ->route('admin.school-years.index')
            ->with('status', "{$gradingPeriod->name} is now OPEN for grade encoding and submission.");
    }

    public function lock(Request $request, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $oldStatus = $gradingPeriod->status;

        $gradingPeriod->update([
            'status' => GradingPeriod::STATUS_LOCKED,
            'locked_at' => now(),
            'locked_by' => $request->user()->id,
        ]);

        $this->auditLog->record(
            eventType: 'grading_period.locked',
            auditable: $gradingPeriod,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => GradingPeriod::STATUS_LOCKED],
            reason: $request->input('reason'),
            request: $request
        );

        return redirect()
            ->route('admin.school-years.index')
            ->with('status', "{$gradingPeriod->name} has been LOCKED. Teachers can no longer modify grades.");
    }

    public function unlock(Request $request, GradingPeriod $gradingPeriod): RedirectResponse
    {
        if ($gradingPeriod->schoolYear->is_closed) {
            return redirect()
                ->route('admin.school-years.index')
                ->with('error', 'Cannot unlock a grading period inside a closed school year.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $oldStatus = $gradingPeriod->status;

        $gradingPeriod->update([
            'status' => GradingPeriod::STATUS_OPEN,
            'locked_at' => null,
            'locked_by' => null,
        ]);

        $this->auditLog->record(
            eventType: 'grading_period.unlocked',
            auditable: $gradingPeriod,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => GradingPeriod::STATUS_OPEN],
            reason: trim($validated['reason']),
            request: $request
        );

        return redirect()
            ->route('admin.school-years.index')
            ->with('status', "{$gradingPeriod->name} has been UNLOCKED and logged in the audit trail.");
    }
}
