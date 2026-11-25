@extends('layouts.app')

@section('title', 'Student Performance Summary — ' . $enrollment->student->formal_name)
@section('header', 'Student Performance Summary')
@section('subheader', $enrollment->student->formal_name . ' • ' . $enrollment->gradeLevel->name . ' – ' . $enrollment->section->name . ' (SY ' . $enrollment->schoolYear->name . ')')

@section('content')
<div class="space-y-6">
    {{-- Top Navigation & PDF Actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('adviser.sections.show', $enrollment->section) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 mr-2">&larr; Back to Section</a>
            <a href="{{ route('adviser.students.summary', $enrollment) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ ! $selectedPeriod ? 'bg-school-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                Full School Year
            </a>
            @foreach($summary['all_periods'] as $p)
                <a href="{{ route('adviser.students.summary', ['enrollment' => $enrollment, 'period_id' => $p->id]) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $selectedPeriod?->id === $p->id ? 'bg-school-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Up to {{ $p->code }}
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('adviser.students.sf10', $enrollment) }}"
               class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition">
                Permanent Record (SF10)
            </a>
            @if(! $summary['has_missing_grades'])
                <a href="{{ route('adviser.report-cards.individual', ['enrollment' => $enrollment, 'period_id' => $selectedPeriod?->id]) }}" target="_blank"
                   class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm">
                    Download Official Report Card PDF
                </a>
            @else
                <a href="{{ route('adviser.report-cards.individual', ['enrollment' => $enrollment, 'period_id' => $selectedPeriod?->id, 'draft' => 1]) }}" target="_blank"
                   class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm">
                    Preview Draft PDF (Incomplete Grades)
                </a>
            @endif
        </div>
    </div>

    {{-- Learner Summary Header Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="school-card p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Learner Profile</p>
            <p class="mt-1 text-base font-bold text-slate-900">{{ $enrollment->student->formal_name }}</p>
            <p class="text-xs font-mono text-slate-500 mt-0.5">ID: {{ $enrollment->student->student_number }} &bull; LRN: {{ $enrollment->student->lrn ?? '—' }}</p>
        </div>
        <div class="school-card p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Curriculum Version</p>
            <p class="mt-1 text-sm font-bold text-school-700">{{ $enrollment->curriculum->name }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ $enrollment->curriculum->code }}</p>
        </div>
        <div class="school-card p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">General Average</p>
            <p class="mt-1 text-3xl font-extrabold {{ ($summary['general_average'] ?? 100) < 75 ? 'text-rose-700' : 'text-school-700' }}">
                {{ $summary['general_average'] !== null ? number_format($summary['general_average'], 0) : ($summary['is_descriptive_only'] ? 'Descriptive' : 'Pending') }}
            </p>
            <p class="text-xs text-slate-500 capitalize mt-0.5">Status: {{ str_replace('_', ' ', $summary['promotion_status']) }}</p>
        </div>
        <div class="school-card p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Attendance Summary</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $summary['attendance_totals']['days_present'] }} / {{ $summary['attendance_totals']['school_days'] }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ $summary['attendance_totals']['days_absent'] }} absent &bull; {{ $summary['attendance_totals']['days_tardy'] }} tardy</p>
        </div>
    </div>

    @if($summary['has_missing_grades'])
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-5 py-3 text-xs text-amber-900">
            <strong>Incomplete Official Grades:</strong> Pending submission for
            {{ collect($summary['missing_subjects'])->pluck('subject_name')->implode(', ') }}.
        </div>
    @endif

    {{-- Learning Areas Performance Table + Visual Bars --}}
    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-base font-bold text-slate-900">Official Subject Performance Breakdown</h2>
            <p class="text-xs text-slate-500">Only finalized/submitted grades from subject teachers are displayed.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Learning Area / Subject</th>
                    @foreach($summary['periods'] as $p)
                        <th class="px-4 py-3 text-center">{{ $p->code }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-center">Final Grade</th>
                    <th class="px-5 py-3">Visual Progress</th>
                    <th class="px-5 py-3">Remarks</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($summary['subject_rows'] as $row)
                    <tr>
                        <td class="px-5 py-3">
                            <p class="font-semibold text-slate-900">{{ $row['subject']->name }}</p>
                            <p class="text-xs font-mono text-slate-400">{{ $row['subject']->code }}</p>
                        </td>
                        @foreach($summary['periods'] as $p)
                            @php $val = $row['period_grades'][$p->id] ?? null; @endphp
                            <td class="px-4 py-3 text-center font-semibold">
                                @if($val !== null)
                                    <span class="{{ is_numeric($val) && (float) $val < 75 ? 'text-rose-700' : 'text-slate-900' }}">{{ $val }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-[11px]">Pending</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-center font-extrabold {{ ($row['final_grade'] ?? 100) < 75 ? 'text-rose-700' : 'text-school-800' }}">
                            {{ $row['final_grade'] !== null ? number_format($row['final_grade'], 0) : '—' }}
                        </td>
                        <td class="px-5 py-3 w-44">
                            @if($row['final_grade'] !== null)
                                <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $row['final_grade'] >= 90 ? 'bg-emerald-500' : ($row['final_grade'] >= 75 ? 'bg-school-600' : 'bg-rose-500') }}"
                                         style="width: {{ min(100, max(0, $row['final_grade'])) }}%"></div>
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-600">{{ $row['remarks'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Learner Intervention, Remediation & Parent Conference Log --}}
    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-900">Academic Intervention &amp; Parent Conference Log</h2>
                <p class="text-xs text-slate-500">Document remediation plans, parent-teacher conferences, guidance notes, or commendations for this learner.</p>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-200 text-slate-700">{{ isset($interventions) ? $interventions->count() : 0 }} Entry(s)</span>
        </div>

        <div class="p-6 border-b border-slate-100 bg-white">
            <form method="POST" action="{{ route('adviser.students.interventions.store', $enrollment) }}" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                @csrf
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Category <span class="text-rose-600">*</span></label>
                    <select name="category" required class="w-full rounded-lg border-slate-300 text-xs">
                        @foreach($interventionCategories ?? [] as $catKey => $catLabel)
                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Related Subject (Optional)</label>
                    <select name="subject_id" class="w-full rounded-lg border-slate-300 text-xs">
                        <option value="">— General / All Subjects —</option>
                        @foreach($summary['subject_rows'] as $row)
                            <option value="{{ $row['subject']->id }}">{{ $row['subject']->code }} &ndash; {{ $row['subject']->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Follow-Up Date (Optional)</label>
                    <input type="date" name="follow_up_date" class="w-full rounded-lg border-slate-300 text-xs">
                </div>
                <input type="hidden" name="grading_period_id" value="{{ $selectedPeriod?->id }}">
                <div class="md:col-span-6">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Observation / Conference Notes <span class="text-rose-600">*</span></label>
                    <textarea name="notes" rows="2" required maxlength="2000" placeholder="e.g., Met with parent regarding low Math summative scores; learner missed 3 quizzes." class="w-full rounded-lg border-slate-300 text-xs"></textarea>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Agreed Action Plan (Optional)</label>
                    <textarea name="action_plan" rows="2" maxlength="2000" placeholder="e.g., Attend Tuesday/Thursday peer tutoring & complete remedial worksheet." class="w-full rounded-lg border-slate-300 text-xs"></textarea>
                </div>
                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full px-3 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                        + Log Entry
                    </button>
                </div>
            </form>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($interventions ?? [] as $item)
                <div class="p-5 flex flex-col sm:flex-row sm:items-start justify-between gap-3 {{ $item->status === 'resolved' ? 'bg-slate-50/70' : 'bg-white' }}">
                    <div class="space-y-1 flex-1 text-xs">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $item->category === 'commendation' ? 'bg-emerald-100 text-emerald-800' : ($item->category === 'remediation' ? 'bg-rose-100 text-rose-800' : 'bg-school-100 text-school-800') }}">
                                {{ $interventionCategories[$item->category] ?? ucfirst($item->category) }}
                            </span>
                            @if($item->subject)
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[10px] font-semibold">{{ $item->subject->code }}</span>
                            @endif
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $item->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                {{ $item->status }}
                            </span>
                            @if($item->follow_up_date)
                                <span class="text-[11px] text-slate-500">Follow-up: <strong>{{ $item->follow_up_date->format('M d, Y') }}</strong></span>
                            @endif
                        </div>
                        <p class="text-slate-800 leading-relaxed">{{ $item->notes }}</p>
                        @if($item->action_plan)
                            <p class="text-slate-600"><strong class="text-slate-700">Action Plan:</strong> {{ $item->action_plan }}</p>
                        @endif
                        <p class="text-[10px] text-slate-400">
                            Logged by {{ $item->recorder?->name ?? 'Faculty' }} on {{ $item->created_at->format('M d, Y h:i A') }}
                            @if($item->resolved_at) &bull; Resolved {{ $item->resolved_at->format('M d, Y') }} @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('adviser.students.interventions.toggle', [$enrollment, $item]) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $item->status === 'open' ? 'bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                            {{ $item->status === 'open' ? 'Mark Resolved' : 'Reopen' }}
                        </button>
                    </form>
                </div>
            @empty
                <div class="p-6 text-center text-xs text-slate-400">No intervention or parent conference notes logged for this learner yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
