@extends('layouts.app')

@section('title', 'My Advisory Sections')
@section('header', 'My Advisory Sections')
@section('subheader', 'Monitor live subject submissions, attendance, honor rolls, and report cards' . ($activeSy ? ' — SY ' . $activeSy->name : ''))

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    @forelse($sectionCards as $card)
        @php
            $section = $card['section'];
            $period = $card['period'];
            $matrix = $card['matrix'];
        @endphp
        <div class="school-card p-6 flex flex-col justify-between gap-5 hover:border-school-300 hover:shadow-md transition">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-school-700">SY {{ $section->schoolYear->name }}</span>
                            @if($section->shsTrack)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">{{ $section->shsTrack->code }}</span>
                            @endif
                        </div>
                        <h2 class="mt-1 text-xl font-extrabold text-slate-900">{{ $section->gradeLevel->name }} &ndash; {{ $section->name }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Room: {{ $section->room ?? 'TBA' }}
                            &bull; {{ $section->current_enrollments_count }} Enrolled Learner(s)
                            &bull; {{ $section->activeTeacherAssignments->count() }} Assigned Subject(s)
                        </p>
                    </div>
                    @if($matrix && $matrix['ready_for_report_cards'])
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Report Cards Ready</span>
                    @elseif($matrix)
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">{{ $matrix['pending_subjects'] }} Pending</span>
                    @else
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Adviser</span>
                    @endif
                </div>

                @if($matrix && $period)
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                            <span>{{ $period->name }} ({{ $period->code }}) Grade Collection</span>
                            <span class="font-bold text-school-700">{{ $matrix['submitted_subjects'] }}/{{ $matrix['total_subjects'] }} ({{ $matrix['progress_percent'] }}%)</span>
                        </div>
                        <div class="w-full h-2.5 rounded-full bg-slate-200 overflow-hidden">
                            <div class="h-full rounded-full transition-all {{ $matrix['progress_percent'] === 100 ? 'bg-emerald-500' : 'bg-school-600' }}"
                                 style="width: {{ $matrix['progress_percent'] }}%"></div>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            Learners with incomplete grades: <strong class="{{ $matrix['students_with_incomplete_grades'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $matrix['students_with_incomplete_grades'] }}</strong>
                        </p>
                    </div>
                @endif
            </div>

            <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('adviser.sections.show', $section) }}"
                       class="px-4 py-2 rounded-xl bg-school-600 hover:bg-school-700 text-white text-xs font-semibold shadow-sm transition">
                        Collection &amp; Honor Roll &rarr;
                    </a>
                    <a href="{{ route('adviser.records.edit', ['section' => $section, 'period_id' => $period?->id]) }}"
                       class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition">
                        Attendance &amp; Values
                    </a>
                </div>
                @if($period)
                    <a href="{{ route('adviser.sections.export-summary', ['section' => $section, 'period_id' => $period->id]) }}"
                       class="text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                        Export Master Sheet (.xlsx)
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="lg:col-span-2 school-card p-10 text-center text-sm text-slate-500">
            You do not have an assigned advisory section for the active school year.
        </div>
    @endforelse
</div>
@endsection
