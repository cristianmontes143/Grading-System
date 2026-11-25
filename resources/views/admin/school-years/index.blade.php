@extends('layouts.app')

@section('title', 'School Years & Grading Periods')
@section('header', 'School Years & Grading Periods')
@section('subheader', 'Manage multiple school years without mixing academic periods, and control grade encoding locks.')

@section('content')
<div class="space-y-6" x-data="{ unlockModal: false, unlockUrl: '', unlockName: '' }">
    {{-- Create School Year Form --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Create New School Year</h2>
        <form method="POST" action="{{ route('admin.school-years.store') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">School Year <span class="text-rose-600">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="2026-2027" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Start Date <span class="text-rose-600">*</span></label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">End Date <span class="text-rose-600">*</span></label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Term Structure <span class="text-rose-600">*</span></label>
                <select name="term_structure" class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                    <option value="trimester" @selected(old('term_structure') === 'trimester')>Three-Term / Trimester (DO 015, s. 2026)</option>
                    <option value="quarterly" @selected(old('term_structure') === 'quarterly')>Four Quarters (Q1–Q4)</option>
                    <option value="semestral" @selected(old('term_structure') === 'semestral')>Semestral (2 Semesters / 4 Quarters)</option>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                    Create SY
                </button>
            </div>
            <div class="md:col-span-6 flex flex-wrap gap-6 pt-1">
                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox" name="set_active" value="1" checked class="rounded border-slate-300 text-school-600">
                    <span>Set as Active School Year</span>
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox" name="generate_periods" value="1" checked class="rounded border-slate-300 text-school-600">
                    <span>Auto-generate standard grading periods</span>
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox" name="open_first_period" value="1" checked class="rounded border-slate-300 text-school-600">
                    <span>Open First Term / Quarter immediately for grade encoding</span>
                </label>
            </div>
        </form>
    </div>

    {{-- School Years List --}}
    @forelse($schoolYears as $sy)
        <div class="bg-white rounded-xl border {{ $sy->is_active ? 'border-school-400 ring-1 ring-school-200' : 'border-slate-200' }} shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-base font-bold text-slate-900">SY {{ $sy->name }}</h3>
                        @if($sy->is_active)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Active</span>
                        @elseif($sy->is_closed)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-700">Closed</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Inactive</span>
                        @endif
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-school-50 text-school-700 capitalize">{{ $sy->term_structure }}</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ $sy->start_date->format('M d, Y') }} – {{ $sy->end_date->format('M d, Y') }}
                        &bull; {{ $sy->sections_count }} Section(s)
                        &bull; {{ $sy->curriculum_implementations_count }} Curriculum Mapping(s)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if(! $sy->is_active && ! $sy->is_closed)
                        <form method="POST" action="{{ route('admin.school-years.activate', $sy) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                                Set Active
                            </button>
                        </form>
                    @endif
                    @if(! $sy->is_closed)
                        <form method="POST" action="{{ route('admin.school-years.close', $sy) }}"
                              onsubmit="return confirm('Close SY {{ $sy->name }} and lock all grading periods?')">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-200 hover:bg-rose-600 hover:text-white text-slate-700 text-xs font-semibold transition">
                                Close School Year
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="p-6 space-y-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Seq</th>
                            <th class="px-4 py-2.5">Code</th>
                            <th class="px-4 py-2.5">Grading Period</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Lock Details</th>
                            <th class="px-4 py-2.5 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($sy->gradingPeriods as $period)
                            <tr>
                                <td class="px-4 py-2.5 text-slate-500">{{ $period->sequence }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs font-semibold text-slate-700">{{ $period->code }}</td>
                                <td class="px-4 py-2.5 font-medium text-slate-900">
                                    {{ $period->name }}
                                    @if($period->semester)
                                        <span class="text-xs text-slate-400">(Sem {{ $period->semester }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($period->status === 'open')
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Open</span>
                                    @elseif($period->status === 'locked')
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">Locked</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">Upcoming</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-500">
                                    @if($period->locked_at)
                                        Locked {{ $period->locked_at->format('Y-m-d H:i') }} by {{ $period->lockedByUser?->name ?? 'Admin' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap space-x-1">
                                    @if(! $sy->is_closed)
                                        @if($period->status === 'upcoming')
                                            <form method="POST" action="{{ route('admin.grading-periods.open', $period) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                                                    Open Period
                                                </button>
                                            </form>
                                        @elseif($period->status === 'open')
                                            <form method="POST" action="{{ route('admin.grading-periods.lock', $period) }}" class="inline"
                                                  onsubmit="return confirm('Lock {{ $period->name }}? Teachers will not be able to edit grades.')">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 rounded bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold">
                                                    Lock Period
                                                </button>
                                            </form>
                                        @elseif($period->status === 'locked')
                                            <button type="button"
                                                    @click="unlockModal = true; unlockUrl = '{{ route('admin.grading-periods.unlock', $period) }}'; unlockName = '{{ $period->name }} ({{ $sy->name }})'"
                                                    class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">
                                                Unlock
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-slate-500">No grading periods configured for this school year.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if(! $sy->is_closed)
                    <form method="POST" action="{{ route('admin.grading-periods.store', $sy) }}"
                          class="pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Custom Period Code</label>
                            <input type="text" name="code" placeholder="Q5 / SUM" required class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Period Name</label>
                            <input type="text" name="name" placeholder="Remedial / Summer Term" required class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Sequence</label>
                            <input type="number" name="sequence" min="1" max="12" value="{{ $sy->gradingPeriods->count() + 1 }}" required class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div>
                            <button type="submit" class="w-full px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                                + Add Period
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-500">
            No school years have been created yet. Create your school's active School Year above to begin.
        </div>
    @endforelse

    {{-- Unlock Grading Period Modal (requires audit reason) --}}
    <div x-show="unlockModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div @click.away="unlockModal = false" class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-base font-bold text-slate-900">Unlock Grading Period</h3>
            <p class="text-xs text-slate-600 mt-1">You are unlocking <strong x-text="unlockName"></strong>. Provide an official reason for the audit log.</p>
            <form method="POST" :action="unlockUrl" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Unlocking <span class="text-rose-600">*</span></label>
                    <textarea name="reason" rows="3" required minlength="5"
                              placeholder="e.g., Authorized extension for Grade 10 Science grade submission"
                              class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="unlockModal = false" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">Confirm Unlock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
