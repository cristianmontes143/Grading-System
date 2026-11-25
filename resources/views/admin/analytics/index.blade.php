@extends('layouts.app')

@section('title', 'Academic Analytics & Honor Roll')
@section('header', 'School-Wide Academic Analytics & Honor Roll')
@section('subheader', 'Computed strictly from finalized/submitted official grades under DepEd Order No. 36, s. 2016.')

@section('content')
<div class="space-y-6">
    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.analytics.index') }}" class="school-card p-4 flex flex-wrap items-center gap-3">
        <select name="school_year_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            @foreach($schoolYears as $sy)
                <option value="{{ $sy->id }}" @selected($selectedSy?->id === $sy->id)>SY {{ $sy->name }}</option>
            @endforeach
        </select>
        <select name="grading_period_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            @foreach($periods as $p)
                <option value="{{ $p->id }}" @selected($selectedPeriod?->id === $p->id)>{{ $p->name }} ({{ $p->code }})</option>
            @endforeach
        </select>
        <select name="grade_level_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Grade Levels</option>
            @foreach($gradeLevels as $gl)
                <option value="{{ $gl->id }}" @selected($selectedGradeLevelId === $gl->id)>{{ $gl->name }}</option>
            @endforeach
        </select>
        <div class="ml-auto">
            <a href="{{ route('admin.analytics.print', request()->query()) }}" target="_blank"
               class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-sm transition">
                Print Executive Report &nearr;
            </a>
        </div>
    </form>

    {{-- Top KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="school-card p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Evaluated Learners</p>
            <p class="mt-1.5 text-2xl font-extrabold text-slate-900">{{ $totals['learners'] }}</p>
            <p class="text-[11px] text-slate-400">{{ $totals['incomplete'] }} incomplete</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-school-700">School Average</p>
            <p class="mt-1.5 text-2xl font-extrabold text-school-800">{{ $totals['overall_average'] !== null ? number_format($totals['overall_average'], 2) : '—' }}</p>
            <p class="text-[11px] text-slate-400">Official complete grades</p>
        </div>
        <div class="school-card p-4 border-emerald-200/80 bg-emerald-50/30">
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Passing Rate</p>
            <p class="mt-1.5 text-2xl font-extrabold text-emerald-700">{{ $totals['passing_rate'] !== null ? $totals['passing_rate'] . '%' : '—' }}</p>
            <p class="text-[11px] text-emerald-600/80">{{ $totals['passed'] }} passed</p>
        </div>
        <div class="school-card p-4 border-amber-200/80 bg-amber-50/30">
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Academic Honors</p>
            <p class="mt-1.5 text-2xl font-extrabold text-amber-800">{{ $totals['total_honors'] }}</p>
            <p class="text-[11px] text-amber-700/90">{{ $totals['highest_honors'] }} Highest &bull; {{ $totals['high_honors'] }} High &bull; {{ $totals['with_honors'] }} Honors</p>
        </div>
        <div class="school-card p-4 border-rose-200/80 bg-rose-50/30">
            <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Needs Intervention</p>
            <p class="mt-1.5 text-2xl font-extrabold text-rose-700">{{ $totals['at_risk'] }}</p>
            <p class="text-[11px] text-rose-600/80">With subject grade &lt; 75</p>
        </div>
    </div>

    {{-- Grade Distribution + Section Comparison --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="school-card p-6 space-y-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">DepEd Grade Distribution</h2>
                <p class="text-xs text-slate-500">Breakdown of learners with complete official grades.</p>
            </div>
            @php
                $distTotal = max(1, array_sum($totals['distribution']));
                $bands = [
                    ['Outstanding (90–100)', $totals['distribution']['outstanding'], 'bg-emerald-500'],
                    ['Very Satisfactory (85–89)', $totals['distribution']['very_satisfactory'], 'bg-school-600'],
                    ['Satisfactory (80–84)', $totals['distribution']['satisfactory'], 'bg-sky-500'],
                    ['Fairly Satisfactory (75–79)', $totals['distribution']['fairly_satisfactory'], 'bg-amber-400'],
                    ['Did Not Meet (< 75)', $totals['distribution']['did_not_meet'], 'bg-rose-500'],
                ];
            @endphp
            <div class="space-y-3 text-xs">
                @foreach($bands as [$label, $count, $color])
                    @php $pct = array_sum($totals['distribution']) > 0 ? round(($count / $distTotal) * 100) : 0; @endphp
                    <div>
                        <div class="flex justify-between font-semibold text-slate-700 mb-1">
                            <span>{{ $label }}</span>
                            <span>{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div class="w-full h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $color }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="lg:col-span-2 school-card overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                <h2 class="text-base font-bold text-slate-900">Section-by-Section Performance Comparison</h2>
                <p class="text-xs text-slate-500">Click any section to open its Consolidated Master Sheet or export to Excel.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Section</th>
                        <th class="px-4 py-3 text-center">Learners</th>
                        <th class="px-4 py-3 text-center">Submitted</th>
                        <th class="px-4 py-3 text-center">Avg</th>
                        <th class="px-4 py-3 text-center">Honors</th>
                        <th class="px-4 py-3 text-center">At-Risk</th>
                        <th class="px-5 py-3 text-right">Master Sheet</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($sectionMetrics as $row)
                        @php
                            $sec = $row['section'];
                            $st = $row['stats'];
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <p class="font-bold text-slate-900">{{ $sec->gradeLevel->name }} &ndash; {{ $sec->name }}</p>
                                <p class="text-[11px] text-slate-400">Adviser: {{ $sec->adviser?->full_name ?? 'Unassigned' }}</p>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ $st['total_learners'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full font-bold {{ $row['progress_percent'] === 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $row['progress_percent'] }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-bold text-school-800">{{ $st['section_average'] !== null ? number_format($st['section_average'], 2) : '—' }}</td>
                            <td class="px-4 py-3 text-center font-bold text-emerald-700">{{ $st['total_honors'] }}</td>
                            <td class="px-4 py-3 text-center font-bold {{ $st['at_risk_count'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ $st['at_risk_count'] }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap space-x-2">
                                <a href="{{ route('adviser.sections.show', ['section' => $sec, 'period_id' => $selectedPeriod?->id]) }}" class="font-semibold text-school-600 hover:underline">View</a>
                                @if($selectedPeriod)
                                    <a href="{{ route('adviser.sections.export-summary', ['section' => $sec, 'period_id' => $selectedPeriod->id]) }}" class="font-semibold text-emerald-700 hover:underline">.xlsx</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">No sections found for the selected filter.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Top Honor Roll & At-Risk Intervention Lists --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="school-card overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">School-Wide Honor Roll</h2>
                    <p class="text-xs text-slate-500">Top qualifiers under DepEd Order No. 36, s. 2016</p>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900">{{ $topHonors->count() }} Listed</span>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-2.5">Learner</th>
                        <th class="px-4 py-2.5">Section</th>
                        <th class="px-4 py-2.5 text-center">Avg</th>
                        <th class="px-5 py-2.5 text-right">Distinction</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($topHonors as $hr)
                        <tr>
                            <td class="px-5 py-2.5 font-semibold text-slate-900">{{ $hr['student']->formal_name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $hr['section']->gradeLevel->code }} &ndash; {{ $hr['section']->name }}</td>
                            <td class="px-4 py-2.5 text-center font-bold text-school-800">{{ number_format((float) $hr['average'], 2) }}</td>
                            <td class="px-5 py-2.5 text-right">
                                <span class="px-2 py-0.5 rounded-full font-bold text-[10px] {{ $hr['honor'] === 'With Highest Honors' ? 'bg-amber-100 text-amber-900' : ($hr['honor'] === 'With High Honors' ? 'bg-emerald-100 text-emerald-800' : 'bg-school-100 text-school-800') }}">
                                    {{ $hr['honor'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No honor roll qualifiers with complete official grades yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="school-card overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Learners Needing Academic Intervention</h2>
                    <p class="text-xs text-slate-500">Learners with one or more official subject grades below 75</p>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">{{ $atRiskList->count() }} Flagged</span>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-2.5">Learner</th>
                        <th class="px-4 py-2.5">Section</th>
                        <th class="px-5 py-2.5">Subjects &lt; 75</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($atRiskList as $ar)
                        <tr>
                            <td class="px-5 py-2.5 font-semibold text-slate-900">
                                <a href="{{ route('adviser.students.summary', ['enrollment' => $ar['enrollment'], 'period_id' => $selectedPeriod?->id]) }}" class="text-school-700 hover:underline">
                                    {{ $ar['student']->formal_name }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $ar['section']->gradeLevel->code }} &ndash; {{ $ar['section']->name }}</td>
                            <td class="px-5 py-2.5">
                                @foreach($ar['failing_subjects'] as $subCode)
                                    <span class="inline-block px-2 py-0.5 mr-1 rounded bg-rose-100 text-rose-800 font-bold text-[10px]">{{ $subCode }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-emerald-700 font-medium">Zero learners flagged with grades below 75!</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
