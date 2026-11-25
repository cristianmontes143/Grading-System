@extends('layouts.app')

@section('title', 'My Teaching Load')
@section('header', 'My Assigned Subjects & Sections')
@section('subheader', ($activeSy ? 'SY ' . $activeSy->name . ' • ' : '') . 'Click any grading term pill to jump straight into your digital class record.')

@section('content')
<div class="space-y-5">
    @if($assignments->isNotEmpty())
        <div class="flex justify-end">
            <a href="{{ route('teacher.classes.print-load') }}" target="_blank"
               class="px-4 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition">
                Print Official Teaching Load Slip &nearr;
            </a>
        </div>
    @endif

    @if($assignments->isEmpty())
        <div class="school-card p-12 text-center">
            <div class="mx-auto w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-slate-900">No Active Subject Assignments Yet</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">Ask your School Administrator to assign your subjects and sections under Teacher Assignments.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($assignments as $assignment)
                @php
                    $learnerCount = (int) ($studentCountsBySection[$assignment->section_id] ?? 0);
                @endphp
                <div class="school-card p-5 flex flex-col justify-between gap-4 hover:border-school-300 hover:shadow-md transition">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-school-50 text-school-700 border border-school-100">
                                    {{ $assignment->subject->code }}
                                </span>
                                <h3 class="mt-1.5 text-base font-bold text-slate-900 leading-snug">
                                    {{ $assignment->subject->name }}
                                </h3>
                                <p class="text-xs font-semibold text-slate-600 mt-0.5">
                                    {{ $assignment->section->gradeLevel->name }} &ndash; {{ $assignment->section->name }}
                                </p>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold">
                                {{ $learnerCount }} {{ $learnerCount === 1 ? 'Learner' : 'Learners' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-[11px] text-slate-500">
                            <div>
                                <span class="block text-[10px] font-semibold uppercase text-slate-400">Adviser</span>
                                <span class="font-medium text-slate-700 truncate block">{{ $assignment->section->adviser?->full_name ?? 'Unassigned' }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-semibold uppercase text-slate-400">Schedule</span>
                                <span class="font-medium text-slate-700 truncate block">{{ $assignment->schedule_summary ?? 'Not specified' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2 border-t border-slate-100">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Open Gradebook by Term</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($assignment->schoolYear->gradingPeriods as $period)
                                    @php
                                        $subStatus = $periodStatuses[$assignment->id][$period->id] ?? null;
                                        $isOfficial = in_array($subStatus, ['submitted', 'approved'], true);
                                        $pillClass = match (true) {
                                            $isOfficial => 'bg-emerald-50 border-emerald-200 text-emerald-800 hover:bg-emerald-100',
                                            $subStatus === 'returned' => 'bg-rose-50 border-rose-200 text-rose-800 hover:bg-rose-100',
                                            $period->status === 'open' => 'bg-school-600 border-school-600 text-white hover:bg-school-700',
                                            $period->status === 'locked' => 'bg-slate-100 border-slate-200 text-slate-600 hover:bg-slate-200',
                                            default => 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50',
                                        };
                                    @endphp
                                    <a href="{{ route('teacher.gradebook.show', [$assignment, $period]) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-semibold transition {{ $pillClass }}">
                                        <span>{{ $period->code }}</span>
                                        @if($isOfficial)
                                            <span class="text-[10px]">&check;</span>
                                        @elseif($period->status === 'open')
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-300"></span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[11px] font-mono text-slate-400">{{ $assignment->subject->curriculum->code }}</span>
                            <a href="{{ route('teacher.classes.show', $assignment) }}"
                               class="text-xs font-semibold text-school-600 hover:text-school-800 hover:underline">
                                Class Overview &amp; Roster &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
