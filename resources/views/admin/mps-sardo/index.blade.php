@extends('layouts.app')

@section('title', 'DepEd MPS & SARDO Monitoring')
@section('header', 'DepEd MPS Mastery & SARDO Attendance Monitor')
@section('subheader', 'Mean Percentage Score (75% DepEd benchmark) and Students At Risk of Dropping Out (20% absence threshold)')

@section('content')
<div class="space-y-6">
    {{-- Filters & Actions --}}
    <div class="school-card p-5 flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.mps-sardo.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 flex-1 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">School Year</label>
                <select name="school_year_id" class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy->id }}" @selected($selectedSchoolYear?->id === $sy->id)>SY {{ $sy->name }} {{ $sy->is_active ? '(Active)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Grading Period (MPS)</label>
                <select name="grading_period_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All Periods (Cumulative)</option>
                    @foreach($periods as $p)
                        <option value="{{ $p->id }}" @selected($selectedPeriod?->id === $p->id)>{{ $p->name }} ({{ $p->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Grade Level</label>
                <select name="grade_level_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All Grade Levels</option>
                    @foreach($gradeLevels as $gl)
                        <option value="{{ $gl->id }}" @selected($selectedGradeLevelId === $gl->id)>{{ $gl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">Apply</button>
            </div>
        </form>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.mps-sardo.print', request()->query()) }}" target="_blank"
               class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                Print Report &nearr;
            </a>
            <a href="{{ route('admin.mps-sardo.export', request()->query()) }}"
               class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm">
                Export Excel (.xlsx)
            </a>
        </div>
    </div>

    {{-- Top KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="school-card p-4">
            <p class="text-[10px] font-bold uppercase text-slate-400">School-Wide MPS</p>
            <p class="text-2xl font-extrabold {{ ($mps['overall_mps'] ?? 0) >= 75 ? 'text-emerald-700' : 'text-amber-700' }} mt-1">
                {{ $mps['overall_mps'] !== null ? number_format($mps['overall_mps'], 2) . '%' : '—' }}
            </p>
            <p class="text-[11px] text-slate-500 mt-0.5 truncate">{{ $mps['overall_mastery'] }}</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[10px] font-bold uppercase text-emerald-600">Classes &ge; 75% MPS</p>
            <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $mps['classes_meeting_target'] }}</p>
            <p class="text-[11px] text-slate-400">of {{ $mps['classes_evaluated'] }} evaluated</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[10px] font-bold uppercase text-rose-600">Classes &lt; 75% MPS</p>
            <p class="text-2xl font-extrabold text-rose-700 mt-1">{{ $mps['classes_below_target'] }}</p>
            <p class="text-[11px] text-slate-400">needs instructional support</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[10px] font-bold uppercase text-slate-400">Attendance Rate</p>
            <p class="text-2xl font-extrabold text-school-800 mt-1">
                {{ $sardo['overall_attendance_rate'] !== null ? number_format($sardo['overall_attendance_rate'], 1) . '%' : '—' }}
            </p>
            <p class="text-[11px] text-slate-400">{{ $sardo['learners_tracked'] }} learners tracked</p>
        </div>
        <div class="school-card p-4 bg-rose-50/50 border-rose-200">
            <p class="text-[10px] font-bold uppercase text-rose-700">Critical SARDO (&ge;20%)</p>
            <p class="text-2xl font-extrabold text-rose-800 mt-1">{{ $sardo['critical_count'] }}</p>
            <p class="text-[11px] text-rose-600">DepEd 20% limit reached</p>
        </div>
        <div class="school-card p-4 bg-amber-50/50 border-amber-200">
            <p class="text-[10px] font-bold uppercase text-amber-700">Early Warning / Tardy</p>
            <p class="text-2xl font-extrabold text-amber-800 mt-1">{{ $sardo['warning_count'] + $sardo['tardy_watch_count'] }}</p>
            <p class="text-[11px] text-amber-700">{{ $sardo['warning_count'] }} warning &bull; {{ $sardo['tardy_watch_count'] }} tardy</p>
        </div>
    </div>

    {{-- Section 1: Subject-Level MPS Overview --}}
    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-base font-bold text-slate-900">DepEd Mean Percentage Score (MPS) by Learning Area</h2>
            <p class="text-xs text-slate-500">Lowest MPS areas are listed first so school heads can immediately prioritize instructional coaching and remediation.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Learning Area</th>
                    <th class="px-4 py-3 text-center">Classes</th>
                    <th class="px-4 py-3 text-center">Assessments</th>
                    <th class="px-4 py-3 w-56">MPS Progress (75% Target)</th>
                    <th class="px-4 py-3">Mastery Equivalent</th>
                    <th class="px-4 py-3 text-center">Benchmark</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($mps['subject_rows'] as $row)
                    <tr>
                        <td class="px-5 py-3 font-bold text-slate-900">
                            {{ $row['subject_name'] }}
                            <span class="font-mono text-[10px] text-slate-400 ml-1">{{ $row['subject_code'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ $row['classes_count'] }}</td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ $row['assessments_count'] }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $row['meets_target'] ? 'bg-emerald-500' : 'bg-rose-500' }}"
                                         style="width: {{ min(100, max(0, $row['mps'])) }}%"></div>
                                </div>
                                <span class="font-extrabold w-12 text-right {{ $row['meets_target'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($row['mps'], 1) }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-700">{{ $row['mastery_level'] }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($row['meets_target'])
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">MET (&ge;75%)</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[10px] font-bold">BELOW 75%</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-slate-400">No scored assessments recorded yet for this school year and period.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Section 2: Class-by-Class MPS Breakdown --}}
    @if($mps['class_rows']->isNotEmpty())
        <div class="school-card overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                <h2 class="text-base font-bold text-slate-900">Class-Level MPS Breakdown ({{ $mps['class_rows']->count() }} Classes)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-2.5">Section</th>
                        <th class="px-4 py-2.5">Subject</th>
                        <th class="px-4 py-2.5">Teacher</th>
                        <th class="px-3 py-2.5 text-center">Assessments</th>
                        <th class="px-3 py-2.5 text-center">MPS</th>
                        <th class="px-4 py-2.5">Mastery Level</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach($mps['class_rows'] as $row)
                        <tr>
                            <td class="px-5 py-2.5 font-semibold text-slate-900">{{ $row['grade_level']->name }} – {{ $row['section']->name }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row['subject']->name }} <span class="font-mono text-[10px] text-slate-400">({{ $row['subject']->code }})</span></td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row['teacher']?->full_name ?? 'Unassigned' }}</td>
                            <td class="px-3 py-2.5 text-center text-slate-600">{{ $row['assessments_count'] }}</td>
                            <td class="px-3 py-2.5 text-center font-extrabold {{ $row['meets_target'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($row['mps'], 2) }}%</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $row['mastery_level'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Section 3: SARDO Attendance Early-Warning List --}}
    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-rose-50/70 border-b border-rose-200/80 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-rose-950">SARDO (Students At Risk of Dropping Out) Attendance Alerts</h2>
                <p class="text-xs text-rose-800/80">Flags learners reaching DepEd's <strong>&ge;20% absence threshold</strong>, early-warning absences (10%–19.9%), or chronic tardiness (&ge;5 days).</p>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-white border border-rose-200 text-rose-800 text-xs font-bold">{{ $sardo['alerts']->count() }} Flagged Learner(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Learner &amp; Section</th>
                    <th class="px-4 py-3">Adviser</th>
                    <th class="px-3 py-3 text-center">School Days</th>
                    <th class="px-3 py-3 text-center">Absent</th>
                    <th class="px-3 py-3 text-center">Tardy</th>
                    <th class="px-3 py-3 text-center">Absence %</th>
                    <th class="px-4 py-3 text-center">Alert Tier</th>
                    <th class="px-4 py-3 text-right">Follow-Up</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($sardo['alerts'] as $a)
                    <tr>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.students.show', $a['student']) }}" class="font-bold text-slate-900 hover:text-school-700 hover:underline">
                                {{ $a['student']->formal_name }}
                            </a>
                            <p class="text-[11px] text-slate-500">{{ $a['grade_level']->name }} – {{ $a['section']->name }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $a['section']->adviser?->full_name ?? 'Unassigned' }}</td>
                        <td class="px-3 py-3 text-center text-slate-700">{{ $a['school_days'] }}</td>
                        <td class="px-3 py-3 text-center font-bold text-rose-700">{{ $a['days_absent'] }}</td>
                        <td class="px-3 py-3 text-center font-semibold text-amber-700">{{ $a['days_tardy'] }}</td>
                        <td class="px-3 py-3 text-center font-extrabold {{ $a['severity'] === 'critical' ? 'text-rose-700' : 'text-amber-700' }}">
                            {{ number_format($a['absence_rate'], 1) }}%
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($a['severity'] === 'critical')
                                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[10px] font-bold">CRITICAL SARDO</span>
                            @elseif($a['severity'] === 'warning')
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">EARLY WARNING</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 text-[10px] font-bold">TARDY WATCH</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('adviser.students.summary', $a['enrollment']) }}" class="text-school-600 hover:underline font-semibold">
                                Log Intervention &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-8 text-center text-emerald-700 font-medium">
                            No learners currently exceed the SARDO absence or chronic tardiness alert thresholds.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
