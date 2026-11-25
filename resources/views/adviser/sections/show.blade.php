@extends('layouts.app')

@section('title', $section->gradeLevel->name . ' – ' . $section->name)
@section('header', 'Advisory Section: ' . $section->gradeLevel->name . ' – ' . $section->name)
@section('subheader', 'SY ' . $section->schoolYear->name . ($curriculum ? ' • Curriculum: ' . $curriculum->name : ''))

@section('content')
<div class="space-y-6">
    {{-- Top Navigation, Grading Period Selector & Quick Report Card Actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('adviser.sections.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 mr-2">&larr; My Advisory Sections</a>
            @foreach($periods as $p)
                <a href="{{ route('adviser.sections.show', ['section' => $section, 'period_id' => $p->id]) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $selectedPeriod?->id === $p->id ? 'bg-school-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    {{ $p->name }} ({{ $p->code }})
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('adviser.records.edit', ['section' => $section, 'period_id' => $selectedPeriod?->id]) }}"
               class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                Attendance &amp; Core Values
            </a>
            <a href="{{ route('adviser.sections.print-roster', $section) }}" target="_blank"
               class="px-3.5 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                Print Roster
            </a>
            @if($selectedPeriod && $collection)
                <a href="{{ route('adviser.sections.export-summary', ['section' => $section, 'period_id' => $selectedPeriod->id]) }}"
                   class="px-3.5 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                    Export Master Sheet (.xlsx)
                </a>
                <a href="{{ route('adviser.sections.export-sf5', $section) }}"
                   class="px-3.5 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                    Export DepEd SF5 (.xlsx)
                </a>
                <a href="{{ route('adviser.sections.print-summary', ['section' => $section, 'period_id' => $selectedPeriod->id]) }}" target="_blank"
                   class="px-3.5 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                    Print Honor Roll
                </a>
                @if(! empty($sectionSummary) && $sectionSummary['honor_roll']->isNotEmpty())
                    <a href="{{ route('adviser.sections.certificates', ['section' => $section, 'period_id' => $selectedPeriod->id]) }}" target="_blank"
                       class="px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold shadow-sm">
                        Honor Certificates PDF ({{ $sectionSummary['honor_roll']->count() }})
                    </a>
                @endif
                @if($collection['ready_for_report_cards'])
                    <a href="{{ route('adviser.report-cards.batch', ['section' => $section, 'period_id' => $selectedPeriod->id]) }}" target="_blank"
                       class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                        Download Official Batch PDF
                    </a>
                @else
                    <a href="{{ route('adviser.report-cards.batch', ['section' => $section, 'period_id' => $selectedPeriod->id, 'draft' => 1]) }}" target="_blank"
                       class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">
                        Preview Batch Draft PDF (Incomplete)
                    </a>
                @endif
            @endif
        </div>
    </div>

    @if($collection && $selectedPeriod)
        {{-- Automatic Grade Collection Progress Card --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Automatic Grade Collection Progress — {{ $selectedPeriod->name }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Subject teachers submit directly to the database. Only finalized/submitted grades appear below as official grades.
                    </p>
                </div>
                <div class="text-right">
                    @if($collection['ready_for_report_cards'])
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                            All {{ $collection['total_subjects'] }} Subjects Submitted — Ready for Report Cards
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                            {{ $collection['pending_subjects'] }} Pending Subject(s) &bull; {{ $collection['students_with_incomplete_grades'] }} Incomplete Learner(s)
                        </span>
                    @endif
                </div>
            </div>

            <div>
                <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                    <span>{{ $collection['submitted_subjects'] }} of {{ $collection['total_subjects'] }} Subject(s) Submitted</span>
                    <span>{{ $collection['progress_percent'] }}%</span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all {{ $collection['progress_percent'] === 100 ? 'bg-emerald-500' : 'bg-school-600' }}"
                         style="width: {{ $collection['progress_percent'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Subject Submission Status Board --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Subject Grade Submission Monitor</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Subject</th>
                        <th class="px-5 py-3">Assigned Teacher</th>
                        <th class="px-5 py-3">Graded</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Submitted At</th>
                        <th class="px-5 py-3 text-right">Access</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($collection['subject_rows'] as $row)
                        @php
                            $assignment = $row['assignment'];
                            $isOwn = $assignment && auth()->user()->teacher && $assignment->teacher_id === auth()->user()->teacher->id;
                        @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-900">{{ $row['subject']->name }}</p>
                                <p class="text-xs font-mono text-slate-400">{{ $row['subject']->code }}</p>
                            </td>
                            <td class="px-5 py-3 text-slate-700">
                                {{ $row['teacher']?->full_name ?? 'Unassigned' }}
                                @if(! empty($row['submitted_by_prior_teacher']))
                                    <p class="text-[11px] text-slate-500">Submitted by: {{ $row['submitted_by_prior_teacher'] }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600">{{ $row['graded_count'] }} / {{ $row['total_students'] }}</td>
                            <td class="px-5 py-3">
                                @if($row['status'] === 'approved')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Approved</span>
                                @elseif($row['status'] === 'submitted')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-school-100 text-school-800">Submitted</span>
                                @elseif($row['status'] === 'returned')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">Returned</span>
                                @elseif($row['status'] === 'draft')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">In Progress (Draft)</span>
                                @elseif($row['status'] === 'unassigned')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700">No Teacher Assigned</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">Pending</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">
                                {{ $row['submitted_at']?->format('M d, Y h:i A') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if($isOwn)
                                    <a href="{{ route('teacher.gradebook.show', [$assignment, $selectedPeriod]) }}"
                                       class="px-2.5 py-1 rounded bg-school-50 text-school-700 text-xs font-semibold hover:bg-school-100">
                                        Encode Own Subject &rarr;
                                    </a>
                                @elseif($assignment)
                                    <span class="text-xs text-slate-400">Read-Only (Co-Teacher)</span>
                                @else
                                    <span class="text-xs text-amber-700">Contact Admin</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-500">No graded curriculum subjects mapped for this section yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Honor Roll & Section Academic Snapshot (DO 36, s. 2016) --}}
        @if(! empty($sectionSummary))
            @php $sStats = $sectionSummary['stats']; @endphp
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="school-card p-5 space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Section Academic Summary</p>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                            <p class="text-[10px] font-semibold uppercase text-slate-500">Section Avg</p>
                            <p class="text-xl font-extrabold text-school-700 mt-0.5">{{ $sStats['section_average'] !== null ? number_format($sStats['section_average'], 2) : '—' }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                            <p class="text-[10px] font-semibold uppercase text-slate-500">Passing Rate</p>
                            <p class="text-xl font-extrabold text-emerald-700 mt-0.5">{{ $sStats['passing_rate'] !== null ? $sStats['passing_rate'] . '%' : '—' }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-50/70 border border-emerald-200/70">
                            <p class="text-[10px] font-semibold uppercase text-emerald-700">Honor Learners</p>
                            <p class="text-xl font-extrabold text-emerald-800 mt-0.5">{{ $sStats['total_honors'] }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-rose-50/70 border border-rose-200/70">
                            <p class="text-[10px] font-semibold uppercase text-rose-700">Needs Intervention</p>
                            <p class="text-xl font-extrabold text-rose-800 mt-0.5">{{ $sStats['at_risk_count'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 school-card p-5 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Official Academic Honor Roll (DepEd DO 36, s. 2016)</p>
                            <p class="text-[11px] text-slate-500">Computed strictly from learners with complete official submitted grades and no failing marks.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-bold">
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900">Highest: {{ $sStats['honor_counts']['With Highest Honors'] }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">High: {{ $sStats['honor_counts']['With High Honors'] }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-school-100 text-school-800">Honors: {{ $sStats['honor_counts']['With Honors'] }}</span>
                            @if($sectionSummary['honor_roll']->isNotEmpty())
                                <a href="{{ route('adviser.sections.certificates', ['section' => $section, 'period_id' => $selectedPeriod->id]) }}" target="_blank"
                                   class="ml-1 px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-[11px] font-bold shadow-sm transition">
                                    Download Honor Certificates PDF &nearr;
                                </a>
                            @endif
                        </div>
                    </div>

                    @if($sectionSummary['honor_roll']->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto pr-1">
                            @foreach($sectionSummary['honor_roll'] as $hr)
                                <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                                    <div class="min-w-0">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-slate-950 font-extrabold text-[10px] mr-1">#{{ $hr['rank'] }}</span>
                                        <span class="font-semibold text-slate-900 truncate">{{ $hr['student']->formal_name }}</span>
                                    </div>
                                    <div class="text-right shrink-0 flex items-center gap-2">
                                        <div>
                                            <span class="font-bold text-school-800">{{ number_format((float) $hr['average'], 2) }}</span>
                                            <span class="block text-[10px] font-bold text-emerald-700">{{ $hr['honor'] }}</span>
                                        </div>
                                        <a href="{{ route('adviser.students.certificate', ['enrollment' => $hr['enrollment'], 'period_id' => $selectedPeriod->id]) }}" target="_blank"
                                           title="Download individual Honor Certificate PDF"
                                           class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-amber-50 text-[10px] font-bold text-slate-700">
                                            Cert
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-500 py-4 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            Honor roll qualifiers (General Average &ge; 90 with complete official grades) will appear here automatically.
                        </p>
                    @endif
                </div>
            </div>
        @endif

        {{-- Consolidated Official Grades Matrix (Official/Submitted Only) --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" x-data="{ matrixSearch: '' }">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Consolidated Official Grades — {{ $selectedPeriod->name }} ({{ $enrolledStudents->count() }} Learners)</h2>
                    <p class="text-xs text-slate-500">Displays only submitted/approved official grades. Unsubmitted subjects appear as Pending.</p>
                </div>
                <input type="text" x-model="matrixSearch" placeholder="Filter learner by name or LRN..."
                       class="w-full sm:w-64 rounded-lg border-slate-300 text-xs py-1.5">
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold text-slate-600">
                    <tr>
                        <th class="px-4 py-3 sticky left-0 bg-slate-50 border-r border-slate-200 min-w-[200px]">Learner</th>
                        @foreach($collection['subject_rows'] as $row)
                            <th class="px-3 py-3 text-center">{{ $row['subject']->code }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-center bg-school-50 text-school-900">Average</th>
                        <th class="px-3 py-3 text-center bg-slate-50">Rank / Honor</th>
                        <th class="px-4 py-3 text-right bg-slate-50">Report Card</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @php
                        $summaryByEnrollment = isset($sectionSummary)
                            ? $sectionSummary['learner_rows']->keyBy(fn ($r) => $r['enrollment']->id)
                            : collect();
                    @endphp
                    @forelse($enrolledStudents as $idx => $enrollment)
                        @php
                            $numericGrades = [];
                            $missingAny = false;
                            $lSummary = $summaryByEnrollment->get($enrollment->id);
                        @endphp
                        <tr x-show="!matrixSearch.trim() || {{ Js::from(mb_strtolower($enrollment->student->formal_name . ' ' . ($enrollment->student->lrn ?? '') . ' ' . $enrollment->student->student_number)) }}.includes(matrixSearch.trim().toLowerCase())">
                            <td class="px-4 py-2.5 sticky left-0 bg-white border-r border-slate-200">
                                <a href="{{ route('adviser.students.summary', ['enrollment' => $enrollment, 'period_id' => $selectedPeriod->id]) }}"
                                   class="font-semibold text-school-700 hover:underline">
                                    {{ $idx + 1 }}. {{ $enrollment->student->formal_name }}
                                </a>
                                <p class="text-[10px] font-mono text-slate-400">LRN: {{ $enrollment->student->lrn ?? '—' }}</p>
                            </td>
                            @foreach($collection['subject_rows'] as $row)
                                @php
                                    $qg = $collection['official_grades_matrix'][$enrollment->id][$row['subject']->id] ?? null;
                                    if (! $qg) {
                                        $missingAny = true;
                                    } elseif ($qg->final_grade !== null) {
                                        $numericGrades[] = $qg->final_grade;
                                    }
                                @endphp
                                <td class="px-3 py-2.5 text-center">
                                    @if($qg)
                                        <span class="font-bold {{ ($qg->final_grade ?? 100) < 75 ? 'text-rose-700' : 'text-slate-900' }}">
                                            {{ $qg->display_grade }}
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-semibold">Pending</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-2.5 text-center font-bold bg-school-50/40 text-school-900">
                                @if(! $missingAny && count($numericGrades) > 0)
                                    {{ number_format(array_sum($numericGrades) / count($numericGrades), 2) }}
                                @elseif($missingAny)
                                    <span class="text-amber-700 font-semibold text-[11px]">Incomplete</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                @if($lSummary && $lSummary['honor'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        #{{ $lSummary['rank'] }} &bull; {{ $lSummary['honor'] }}
                                    </span>
                                @elseif($lSummary && ! empty($lSummary['failing_subjects']))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800" title="Subjects < 75: {{ implode(', ', $lSummary['failing_subjects']) }}">
                                        At-Risk ({{ count($lSummary['failing_subjects']) }})
                                    </span>
                                @elseif($lSummary && $lSummary['rank'])
                                    <span class="text-[11px] font-semibold text-slate-600">Rank #{{ $lSummary['rank'] }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @if(! $missingAny)
                                    <a href="{{ route('adviser.report-cards.individual', ['enrollment' => $enrollment, 'period_id' => $selectedPeriod->id]) }}" target="_blank"
                                       class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold">
                                        Official PDF
                                    </a>
                                @else
                                    <a href="{{ route('adviser.report-cards.individual', ['enrollment' => $enrollment, 'period_id' => $selectedPeriod->id, 'draft' => 1]) }}" target="_blank"
                                       class="px-2.5 py-1 rounded bg-amber-100 hover:bg-amber-200 text-amber-900 text-[11px] font-semibold">
                                        Draft PDF
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $collection['subject_rows']->count() + 3 }}" class="px-5 py-8 text-center text-slate-500">
                                No students enrolled in this advisory section yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl border border-slate-200 p-6 text-sm text-slate-500">
            No grading periods configured for SY {{ $section->schoolYear->name }} yet.
        </div>
    @endif
</div>
@endsection
