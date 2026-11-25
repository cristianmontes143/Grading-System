@extends('layouts.app')

@section('title', 'DepEd Policy Registry')
@section('header', 'Official DepEd Policy Reference Registry')
@section('subheader', 'Every implemented curriculum and grading rule tracks its DepEd Order number, publication date, and admin confirmation.')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">Registered Policy References</h2>
            <a href="https://www.deped.gov.ph/" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-school-600 hover:underline">
                Official DepEd Portal (deped.gov.ph) &nearr;
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Document No.</th>
                    <th class="px-5 py-3">Policy Title &amp; Scope</th>
                    <th class="px-5 py-3">Publication Date</th>
                    <th class="px-5 py-3">Implementation SY</th>
                    <th class="px-5 py-3">Admin Status</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($policies as $policy)
                    <tr>
                        <td class="px-5 py-3 font-semibold text-slate-900 whitespace-nowrap">{{ $policy->document_number }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-slate-900">{{ $policy->title }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">Grades: {{ $policy->applicable_grade_levels }}</p>
                            @if($policy->notes)
                                <p class="text-xs text-slate-500 mt-1">{{ $policy->notes }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $policy->publication_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $policy->implementation_school_year }}</td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            @if($policy->is_confirmed_by_admin)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Confirmed</span>
                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $policy->confirmer?->name }} ({{ $policy->confirmed_at?->format('Y-m-d') }})</p>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Needs Confirmation</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @if(! $policy->is_confirmed_by_admin)
                                <form method="POST" action="{{ route('admin.policies.confirm', $policy) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                                        Confirm Policy
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-emerald-700 font-medium">Verified &amp; Active</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Add Division / School Policy Reference</h2>
        <form method="POST" action="{{ route('admin.policies.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Document Number <span class="text-rose-600">*</span></label>
                <input type="text" name="document_number" required placeholder="e.g. DM 045, s. 2026" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Official Title <span class="text-rose-600">*</span></label>
                <input type="text" name="title" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Publication Date</label>
                <input type="date" name="publication_date" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Applicable Grade Levels <span class="text-rose-600">*</span></label>
                <input type="text" name="applicable_grade_levels" required placeholder="e.g. Grades 1 to 10" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Implementation SY <span class="text-rose-600">*</span></label>
                <input type="text" name="implementation_school_year" required placeholder="e.g. SY 2026-2027" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Official Source URL</label>
                <input type="url" name="source_url" placeholder="https://www.deped.gov.ph/..." class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">
                    Register Policy
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
