<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyReference;
use App\Models\TransmutationTable;
use App\Models\TransmutationTableRow;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransmutationTableController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(): View
    {
        $tables = TransmutationTable::query()
            ->with(['policyReference', 'confirmer'])
            ->withCount('rows')
            ->orderBy('id')
            ->get();

        $policies = PolicyReference::query()->orderBy('document_number')->get();

        return view('admin.transmutation-tables.index', [
            'tables' => $tables,
            'policies' => $policies,
        ]);
    }

    public function show(TransmutationTable $transmutationTable): View
    {
        $transmutationTable->load(['rows', 'policyReference', 'confirmer']);

        return view('admin.transmutation-tables.show', [
            'table' => $transmutationTable,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:transmutation_tables,name'],
            'policy_reference_id' => ['nullable', 'exists:policy_references,id'],
            'passing_initial_grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'passing_transmuted_grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $table = TransmutationTable::query()->create([
            ...$validated,
            'is_confirmed_by_admin' => false,
            'is_active' => true,
        ]);

        $this->auditLog->record(
            eventType: 'transmutation_table.created',
            auditable: $table,
            newValues: $table->only(['name', 'passing_initial_grade', 'passing_transmuted_grade']),
            request: $request
        );

        return redirect()
            ->route('admin.transmutation-tables.show', $table)
            ->with('status', "Transmutation table \"{$table->name}\" created. Configure its score brackets below.");
    }

    public function updateRows(Request $request, TransmutationTable $transmutationTable): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.min_initial_grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'rows.*.max_initial_grade' => ['required', 'numeric', 'min:0', 'max:100', 'gte:rows.*.min_initial_grade'],
            'rows.*.transmuted_grade' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($transmutationTable, $validated): void {
            $transmutationTable->rows()->delete();

            $insert = array_map(fn ($r) => [
                'transmutation_table_id' => $transmutationTable->id,
                'min_initial_grade' => round((float) $r['min_initial_grade'], 2),
                'max_initial_grade' => round((float) $r['max_initial_grade'], 2),
                'transmuted_grade' => round((float) $r['transmuted_grade'], 2),
            ], $validated['rows']);

            TransmutationTableRow::query()->insert($insert);
        });

        $this->auditLog->record(
            eventType: 'transmutation_table.rows_updated',
            auditable: $transmutationTable,
            newValues: ['rows_count' => count($validated['rows'])],
            request: $request
        );

        return redirect()
            ->route('admin.transmutation-tables.show', $transmutationTable)
            ->with('status', 'Transmutation brackets updated successfully.');
    }

    public function confirm(Request $request, TransmutationTable $transmutationTable): RedirectResponse
    {
        $transmutationTable->update([
            'is_confirmed_by_admin' => true,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        $this->auditLog->record(
            eventType: 'transmutation_table.confirmed',
            auditable: $transmutationTable,
            newValues: ['name' => $transmutationTable->name, 'is_confirmed_by_admin' => true],
            request: $request
        );

        return back()->with('status', "Transmutation table \"{$transmutationTable->name}\" confirmed.");
    }
}
