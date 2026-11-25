@extends('layouts.app')

@section('title', 'Transmutation Tables')
@section('header', 'Configurable Transmutation Tables')
@section('subheader', 'Review and customize DepEd or school-approved initial-grade-to-transmuted-grade brackets.')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Table Name</th>
                    <th class="px-5 py-3">Policy Ref</th>
                    <th class="px-5 py-3">Passing Threshold</th>
                    <th class="px-5 py-3">Brackets</th>
                    <th class="px-5 py-3">Admin Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($tables as $tbl)
                    <tr>
                        <td class="px-5 py-3">
                            <p class="font-semibold text-slate-900">{{ $tbl->name }}</p>
                            @if($tbl->notes)
                                <p class="text-xs text-slate-500 mt-0.5">{{ $tbl->notes }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-600">{{ $tbl->policyReference?->document_number ?? 'Custom' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-700">
                            IG {{ number_format($tbl->passing_initial_grade, 2) }} &rarr; <strong>{{ number_format($tbl->passing_transmuted_grade, 0) }}</strong>
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $tbl->rows_count }} rows</td>
                        <td class="px-5 py-3">
                            @if($tbl->is_confirmed_by_admin)
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Confirmed</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Needs Confirmation</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap space-x-2">
                            <a href="{{ route('admin.transmutation-tables.show', $tbl) }}" class="text-xs font-semibold text-school-600 hover:underline">View / Edit Brackets</a>
                            @if(! $tbl->is_confirmed_by_admin)
                                <form method="POST" action="{{ route('admin.transmutation-tables.confirm', $tbl) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded bg-school-600 text-white text-xs font-semibold">Confirm</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Create New Custom Transmutation Table</h2>
        <form method="POST" action="{{ route('admin.transmutation-tables.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Table Name <span class="text-rose-600">*</span></label>
                <input type="text" name="name" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Passing Initial Grade</label>
                <input type="number" step="0.01" name="passing_initial_grade" value="70.00" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Passing Transmuted</label>
                <input type="number" step="0.01" name="passing_transmuted_grade" value="75.00" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Policy Reference</label>
                <select name="policy_reference_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— School Custom Policy —</option>
                    @foreach($policies as $p)
                        <option value="{{ $p->id }}">{{ $p->document_number }} – {{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">
                    Create Table
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
