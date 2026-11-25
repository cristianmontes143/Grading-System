<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\StudentEnrollment;
use App\Models\StudentIntervention;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentInterventionController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function store(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $enrollment->loadMissing(['section', 'student']);
        $this->authorize('viewAdvisory', $enrollment->section);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(StudentIntervention::categoryLabels()))],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'grading_period_id' => ['nullable', 'integer', 'exists:grading_periods,id'],
            'notes' => ['required', 'string', 'max:2000'],
            'action_plan' => ['nullable', 'string', 'max:2000'],
            'follow_up_date' => ['nullable', 'date'],
        ]);

        $intervention = StudentIntervention::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'subject_id' => $validated['subject_id'] ?? null,
            'grading_period_id' => $validated['grading_period_id'] ?? null,
            'category' => $validated['category'],
            'notes' => trim($validated['notes']),
            'action_plan' => ! empty($validated['action_plan']) ? trim($validated['action_plan']) : null,
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'status' => StudentIntervention::STATUS_OPEN,
            'recorded_by' => $request->user()->id,
        ]);

        $this->auditLog->record(
            eventType: 'student_intervention.created',
            auditable: $intervention,
            newValues: $intervention->only(['category', 'subject_id', 'grading_period_id', 'status']),
            actor: $request->user(),
            context: ['student_id' => $enrollment->student_id, 'subject_id' => $intervention->subject_id]
        );

        return back()->with('status', 'Learner intervention / conference note recorded.');
    }

    public function toggleStatus(Request $request, StudentEnrollment $enrollment, StudentIntervention $intervention): RedirectResponse
    {
        $enrollment->loadMissing('section');
        $this->authorize('viewAdvisory', $enrollment->section);
        abort_unless((int) $intervention->student_enrollment_id === (int) $enrollment->id, 404);

        $oldStatus = $intervention->status;
        $newStatus = $oldStatus === StudentIntervention::STATUS_OPEN
            ? StudentIntervention::STATUS_RESOLVED
            : StudentIntervention::STATUS_OPEN;

        $intervention->update([
            'status' => $newStatus,
            'resolved_at' => $newStatus === StudentIntervention::STATUS_RESOLVED ? now() : null,
        ]);

        $this->auditLog->record(
            eventType: 'student_intervention.status_updated',
            auditable: $intervention,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
            actor: $request->user(),
            context: ['student_id' => $enrollment->student_id]
        );

        return back()->with('status', $newStatus === StudentIntervention::STATUS_RESOLVED ? 'Intervention marked as resolved.' : 'Intervention reopened.');
    }
}
