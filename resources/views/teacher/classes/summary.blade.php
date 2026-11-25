@extends('layouts.app')

@section('title', 'Full-Year Subject Summary — ' . $assignment->subject->name)
@section('header', 'Full-Year Cumulative Subject Summary')
@section('subheader', $assignment->subject->name . ' (' . $assignment->subject->code . ') • ' . $assignment->section->gradeLevel->name . ' – ' . $assignment->section->name . ' • SY ' . $assignment->schoolYear->name)

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('teacher.classes.show', $assignment) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to Class Overview</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('teacher.classes.summary.export', $assignment) }}"
               class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm">
                Export Full-Year Summary (.xlsx)
            </a>
            <button type="button" onclick="window.print()"
                    class="px-3.5 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                Print Summary
            </button>
        </div>
    </div>

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="school-card p-4">
            <p class="text-[10px] font-bold uppercase text-slate-400">Enrolled Learners</p>
            <p class="text-2xl font-extrabold text-slate-900 mt-1">{{ $stats['total_students'] }}</p>
            <p class="text-[11px] text-slate-400">{{ $stats['completed_count'] }} with final grade</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[10px] font-bold uppercase text-school-700">Year-End Average</p>
            <p class="text-2xl font-extrabold text-school-800 mt-1">{{ $stats['average'] !== null ? number_format($stats['average'], 2) : '—' }}</p>
        </div>
        <div class="school-card p-4 border-emerald-200/80 bg-emerald-50/30">
            <p class="text-[10px] font-bold uppercase text-emerald-700">Passing Rate</p>
            <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $stats['passing_rate'] !== null ? $stats['passing_rate'] . '%' : '—' }}</p>
            <p class="text-[11px] text-emerald-600/80">{{ $stats['passed_count'] }} passed</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[10px] font-bold uppercase text-slate-500">Highest / Lowest</p>
            <p class="text-2xl font-extrabold text-slate-900 mt-1">
                {{ $stats['highest'] !== null ? number_format($stats['highest'], 0) : '—' }}
                <span class="text-slate-400 text-base">/</span>
                <span class="{{ ($stats['lowest'] ?? 100) < $passingGrade ? 'text-rose-700' : 'text-slate-700' }}">{{ $stats['lowest'] !== null ? number_format($stats['lowest'], 0) : '—' }}</span>
            </p>
        </div>
        <div class="school-card p-4 {{ $stats['failed_count'] > 0 ? 'border-rose-200 bg-rose-50/30' : '' }}">
            <p class="text-[10px] font-bold uppercase {{ $stats['failed_count'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">Failed (&lt; {{ number_format($passingGrade, 0) }})</p>
            <p class="text-2xl font-extrabold {{ $stats['failed_count'] > 0 ? 'text-rose-700' : 'text-slate-500' }} mt-1">{{ $stats['failed_count'] }}</p>
        </div>
    </div>

    {{-- Cumulative Table --}}
    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-base font-bold text-slate-900">All Grading Periods &amp; Computed Final Subject Grade</h2>
            <p class="text-xs text-slate-500">Green dot indicates an officially submitted term grade; amber indicates a draft term grade.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">#</th>
                    <th class="px-5 py-3">Learner</th>
                    @foreach($periods as $p)
                        <th class="px-4 py-3 text-center">
                            <a href="{{ route('teacher.gradebook.show', [$assignment, $p]) }}" class="text-school-700 hover:underline">{{ $p->code }}</a>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-center bg-school-50 text-school-900">Final Grade</th>
                    <th class="px-5 py-3">Remarks</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($rows as $idx => $row)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-2.5 text-slate-400 text-xs">{{ $idx + 1 }}</td>
                        <td class="px-5 py-2.5">
                            <p class="font-semibold text-slate-900">{{ $row['student']->formal_name }}</p>
                            <p class="text-[11px] font-mono text-slate-400">{{ $row['student']->student_number }}</p>
                        </td>
                        @foreach($periods as $p)
                            @php $pg = $row['period_grades'][$p->id]; @endphp
                            <td class="px-4 py-2.5 text-center font-semibold">
                                @if($pg['display'] !== null)
                                    <span class="inline-flex items-center gap-1 {{ ($pg['numeric'] ?? 100) < $passingGrade ? 'text-rose-700' : 'text-slate-900' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $pg['is_official'] ? 'bg-emerald-500' : 'bg-amber-400' }}" title="{{ $pg['is_official'] ? 'Official Submitted' : 'Draft' }}"></span>
                                        {{ $pg['display'] }}
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-2.5 text-center font-extrabold bg-school-50/40 {{ ($row['final_grade'] ?? 100) < $passingGrade ? 'text-rose-700' : 'text-school-800' }}">
                            {{ $row['final_grade'] !== null ? number_format($row['final_grade'], 0) : '—' }}
                        </td>
                        <td class="px-5 py-2.5 text-xs">
                            @if($row['remarks'] === 'Passed')
                                <span class="px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">Passed</span>
                            @elseif($row['remarks'] === 'Did Not Meet Expectations')
                                <span class="px-2 py-0.5 rounded-full font-semibold bg-rose-100 text-rose-800">Did Not Meet Expectations</span>
                            @else
                                <span class="text-slate-500">{{ $row['remarks'] }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $periods->count() + 4 }}" class="px-5 py-8 text-center text-slate-500">No enrolled learners in this class.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
