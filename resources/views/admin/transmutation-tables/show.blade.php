@extends('layouts.app')

@section('title', $table->name)
@section('header', $table->name)
@section('subheader', 'Passing threshold: IG ' . number_format($table->passing_initial_grade, 2) . ' → ' . number_format($table->passing_transmuted_grade, 0))

@section('content')
<div class="space-y-4" x-data="{
    rows: {{ Js::from($table->rows->map(fn($r) => [
        'min_initial_grade' => $r->min_initial_grade,
        'max_initial_grade' => $r->max_initial_grade,
        'transmuted_grade' => $r->transmuted_grade,
    ])->values()) }},
    addRow() {
        this.rows.push({ min_initial_grade: 0, max_initial_grade: 0, transmuted_grade: 60 });
    },
    removeRow(idx) {
        if (this.rows.length > 1) this.rows.splice(idx, 1);
    }
}">
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.transmutation-tables.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to Transmutation Tables</a>
        @if(! $table->is_confirmed_by_admin)
            <form method="POST" action="{{ route('admin.transmutation-tables.confirm', $table) }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                    Confirm Table as Official
                </button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.transmutation-tables.rows.update', $table) }}" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Transmutation Brackets (<span x-text="rows.length"></span> rows)</h2>
            <button type="button" @click="addRow()" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold">+ Add Row</button>
        </div>

        <div class="overflow-x-auto max-h-[540px] overflow-y-auto border border-slate-200 rounded-lg">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 sticky top-0 text-left font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-2.5">Min Initial Grade</th>
                    <th class="px-4 py-2.5">Max Initial Grade</th>
                    <th class="px-4 py-2.5">Transmuted Grade</th>
                    <th class="px-4 py-2.5 text-right">Remove</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <template x-for="(row, idx) in rows" :key="idx">
                    <tr>
                        <td class="px-4 py-1.5">
                            <input type="number" step="0.01" min="0" max="100" :name="'rows[' + idx + '][min_initial_grade]'" x-model="row.min_initial_grade" required class="w-28 rounded border-slate-300 text-xs">
                        </td>
                        <td class="px-4 py-1.5">
                            <input type="number" step="0.01" min="0" max="100" :name="'rows[' + idx + '][max_initial_grade]'" x-model="row.max_initial_grade" required class="w-28 rounded border-slate-300 text-xs">
                        </td>
                        <td class="px-4 py-1.5">
                            <input type="number" step="0.01" min="0" max="100" :name="'rows[' + idx + '][transmuted_grade]'" x-model="row.transmuted_grade" required class="w-28 rounded border-slate-300 text-xs font-semibold">
                        </td>
                        <td class="px-4 py-1.5 text-right">
                            <button type="button" @click="removeRow(idx)" class="text-rose-600 hover:text-rose-800 font-bold">&times;</button>
                        </td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                Save Brackets
            </button>
        </div>
    </form>
</div>
@endsection
