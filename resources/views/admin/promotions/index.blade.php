@extends('layouts.app')

@section('title', 'Batch Promotion & Year-End Rollover')
@section('header', 'Batch Student Promotion & Year-End Rollover')
@section('subheader', 'Evaluate full-year official standing and promote, retain, or graduate entire sections with automatic curriculum snapshotting.')

@section('content')
<div class="space-y-6" x-data="{
    action: 'promote',
    toggleAll(checked) {
        this.$el.querySelectorAll('input[name=\'enrollment_ids[]\']').forEach(cb => cb.checked = checked);
    },
    selectEligible() {
        this.$el.querySelectorAll('input[name=\'enrollment_ids[]\']').forEach(cb => {
            cb.checked = cb.dataset.eligible === '1';
        });
    }
}">
    {{-- Source Section Selector --}}
    <form method="GET" action="{{ route('admin.promotions.index') }}" class="school-card p-5 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Source School Year</label>
            <select name="source_school_year_id" onchange="this.form.submit()" class="w-full rounded-lg border-slate-300 text-sm">
                @foreach($schoolYears as $sy)
                    <option value="{{ $sy->id }}" @selected($sourceSy?->id === $sy->id)>SY {{ $sy->name }} {{ $sy->is_active ? '(Active)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Source Section</label>
            <select name="source_section_id" onchange="this.form.submit()" class="w-full rounded-lg border-slate-300 text-sm">
                @forelse($sourceSections as $sec)
                    <option value="{{ $sec->id }}" @selected($selectedSection?->id === $sec->id)>
                        {{ $sec->gradeLevel->name }} &ndash; {{ $sec->name }} ({{ $sec->current_enrollments_count }} learners)
                    </option>
                @empty
                    <option value="">No active sections in this School Year</option>
                @endforelse
            </select>
        </div>
        <div class="text-xs text-slate-500 flex flex-wrap items-center justify-between gap-2">
            @if($selectedSection)
                <div>
                    <p>Adviser: <strong class="text-slate-800">{{ $selectedSection->adviser?->full_name ?? 'Unassigned' }}</strong></p>
                    <p class="mt-0.5">Evaluating <strong>{{ $evaluations->count() }}</strong> active learner(s) using official grades.</p>
                </div>
                <a href="{{ route('adviser.sections.export-sf5', $selectedSection) }}"
                   class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                    Export DepEd SF5 (.xlsx)
                </a>
            @endif
        </div>
    </form>

    @if(session('promotion_warnings'))
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 space-y-1">
            <p class="font-bold">Rollover Notes:</p>
            <ul class="list-disc list-inside">
                @foreach(session('promotion_warnings') as $warn)
                    <li>{{ $warn }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($selectedSection)
        <form method="POST" action="{{ route('admin.promotions.execute') }}" class="space-y-5"
              onsubmit="return confirm('Execute this year-end promotion/rollover action for the selected learners? All updates are logged permanently.')">
            @csrf
            <input type="hidden" name="source_section_id" value="{{ $selectedSection->id }}">

            {{-- Rollover Action Configuration Bar --}}
            <div class="school-card p-5 grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Rollover Action <span class="text-rose-600">*</span></label>
                    <select name="action" x-model="action" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="promote">Promote Selected Learners</option>
                        <option value="retain">Retain Selected Learners</option>
                        <option value="graduate">Mark as Graduated (Grade 12)</option>
                    </select>
                </div>

                <div class="md:col-span-6" x-show="action !== 'graduate'">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Target Next School Year &amp; Section (Optional Auto-Enroll)</label>
                    <select name="target_section_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">— Only update status (enroll into next SY later) —</option>
                        @foreach($targetSections as $tSec)
                            <option value="{{ $tSec->id }}">
                                SY {{ $tSec->schoolYear->name }} &mdash; {{ $tSec->gradeLevel->name }} &ndash; {{ $tSec->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-6 text-xs text-school-800 bg-school-50 border border-school-200 rounded-lg px-3.5 py-2.5" x-show="action === 'graduate'" x-cloak>
                    Selected learners' current enrollment will be marked <strong>Completed</strong> and their student profile status updated to <strong>Graduated</strong>.
                </div>

                <div class="md:col-span-3">
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-school-600 hover:bg-school-700 text-white text-sm font-semibold shadow-sm transition">
                        Process Selected &rarr;
                    </button>
                </div>
            </div>

            {{-- Learners Evaluation Table --}}
            <div class="school-card overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">{{ $selectedSection->gradeLevel->name }} &ndash; {{ $selectedSection->name }} Learner Standing</h2>
                        <p class="text-xs text-slate-500">Recommendations are calculated from complete official full-year grades.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <button type="button" @click="selectEligible()" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-semibold border border-emerald-200">
                            Select Eligible
                        </button>
                        <button type="button" @click="toggleAll(true)" class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-semibold border border-slate-200">
                            Select All
                        </button>
                        <button type="button" @click="toggleAll(false)" class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-semibold border border-slate-200">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 w-10">Pick</th>
                            <th class="px-5 py-3">Learner</th>
                            <th class="px-4 py-3 text-center">General Avg</th>
                            <th class="px-4 py-3 text-center">Failed Subjects</th>
                            <th class="px-4 py-3">DepEd Recommendation</th>
                            <th class="px-4 py-3">Current Record Status</th>
                            <th class="px-5 py-3 text-right">Transcript</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($evaluations as $ev)
                            @php
                                $enr = $ev['enrollment'];
                                $isEligible = in_array($ev['recommended_action'], ['promote', 'graduate'], true);
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <input type="checkbox"
                                           name="enrollment_ids[]"
                                           value="{{ $enr->id }}"
                                           data-eligible="{{ $isEligible ? '1' : '0' }}"
                                           @checked($isEligible && ! $ev['already_finalized'])
                                           class="rounded border-slate-300 text-school-600 focus:ring-school-500">
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-900">{{ $ev['student']->formal_name }}</p>
                                    <p class="text-[11px] font-mono text-slate-400">{{ $ev['student']->student_number }} @if($ev['student']->lrn) &bull; LRN: {{ $ev['student']->lrn }} @endif</p>
                                </td>
                                <td class="px-4 py-3 text-center font-extrabold {{ ($ev['general_average'] ?? 100) < 75 ? 'text-rose-700' : 'text-school-800' }}">
                                    {{ $ev['general_average'] !== null ? number_format($ev['general_average'], 0) : ($ev['is_descriptive_only'] ? 'Descriptive' : '—') }}
                                </td>
                                <td class="px-4 py-3 text-center text-xs">
                                    @if(! empty($ev['failed_subjects']))
                                        <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 font-bold">{{ implode(', ', $ev['failed_subjects']) }}</span>
                                    @elseif($ev['missing_count'] > 0)
                                        <span class="text-amber-700 font-semibold">{{ $ev['missing_count'] }} pending</span>
                                    @else
                                        <span class="text-emerald-700 font-semibold">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if($ev['recommended_action'] === 'promote')
                                        <span class="px-2.5 py-0.5 rounded-full font-bold bg-emerald-100 text-emerald-800">Promoted</span>
                                    @elseif($ev['recommended_action'] === 'graduate')
                                        <span class="px-2.5 py-0.5 rounded-full font-bold bg-school-100 text-school-800">Eligible to Graduate</span>
                                    @elseif($ev['recommended_action'] === 'conditional')
                                        <span class="px-2.5 py-0.5 rounded-full font-bold bg-amber-100 text-amber-900">Conditional (Remedial)</span>
                                    @elseif($ev['recommended_action'] === 'retain')
                                        <span class="px-2.5 py-0.5 rounded-full font-bold bg-rose-100 text-rose-800">Retained</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-600">Incomplete Grades</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <span class="px-2 py-0.5 rounded-full font-semibold capitalize {{ $ev['already_finalized'] ? 'bg-school-50 text-school-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ str_replace('_', ' ', $enr->enrollment_status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.students.sf10', $ev['student']) }}" class="text-xs font-semibold text-school-600 hover:underline">SF10 &rarr;</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-slate-500">No currently enrolled learners in this section.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
