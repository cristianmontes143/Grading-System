<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyReference;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PolicyReferenceController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(): View
    {
        $policies = PolicyReference::query()
            ->with('confirmer')
            ->orderByDesc('publication_date')
            ->get();

        return view('admin.policies.index', [
            'policies' => $policies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:80', 'unique:policy_references,document_number'],
            'title' => ['required', 'string', 'max:255'],
            'publication_date' => ['nullable', 'date'],
            'applicable_grade_levels' => ['required', 'string', 'max:150'],
            'implementation_school_year' => ['required', 'string', 'max:100'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $policy = PolicyReference::query()->create([
            ...$validated,
            'is_verified' => false,
            'is_confirmed_by_admin' => false,
        ]);

        $this->auditLog->record(
            eventType: 'policy_reference.created',
            auditable: $policy,
            newValues: $policy->only(['document_number', 'title', 'applicable_grade_levels']),
            request: $request
        );

        return redirect()
            ->route('admin.policies.index')
            ->with('status', "Policy reference {$policy->document_number} added. Please confirm it once verified.");
    }

    public function confirm(Request $request, PolicyReference $policyReference): RedirectResponse
    {
        $policyReference->update([
            'is_confirmed_by_admin' => true,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        $this->auditLog->record(
            eventType: 'policy_reference.confirmed',
            auditable: $policyReference,
            newValues: [
                'document_number' => $policyReference->document_number,
                'is_confirmed_by_admin' => true,
            ],
            reason: $request->input('reason', 'Administrator confirmed policy applicability for this school.'),
            request: $request
        );

        return redirect()
            ->route('admin.policies.index')
            ->with('status', "{$policyReference->document_number} has been confirmed as an active school reference.");
    }
}
