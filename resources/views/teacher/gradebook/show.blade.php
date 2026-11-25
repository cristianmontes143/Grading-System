@extends('layouts.app')

@section('title', 'Gradebook — ' . $assignment->subject->name)
@section('header', $assignment->subject->name . ' Gradebook (' . $period->code . ')')
@section('subheader', $assignment->section->gradeLevel->name . ' – ' . $assignment->section->name . ' • SY ' . $assignment->schoolYear->name)

@section('content')
<div class="space-y-5"
     x-data="{
        showAddAssessment: false,
        showImport: false,
        showClone: false,
        studentFilter: '',
        missingOnly: false,
        editAssessmentOpen: false,
        editAssessmentUrl: '',
        editAssessment: { title: '', assessment_type: 'quiz', max_score: '', assessment_date: '' },
        openEditAssessment(url, data) {
            this.editAssessmentUrl = url;
            this.editAssessment = Object.assign({ title: '', assessment_type: 'quiz', max_score: '', assessment_date: '' }, data);
            this.editAssessmentOpen = true;
        },
        hasUnsaved: false,
        invalidCells: {},
        breakdownOpen: false,
        breakdownStudent: null,
        openBreakdown(payload) {
            this.breakdownStudent = payload;
            this.breakdownOpen = true;
        },
        validateCell(el) {
            this.hasUnsaved = true;
            const raw = (el.value || '').trim();
            const max = parseFloat(el.dataset.max || '0');
            const key = el.dataset.row + ':' + el.dataset.col;
            if (raw === '' || raw.toUpperCase() === 'M' || raw.toUpperCase() === 'EX') {
                delete this.invalidCells[key];
                el.classList.remove('border-rose-500', 'ring-2', 'ring-rose-300', 'bg-rose-50');
                return;
            }
            const num = Number(raw);
            if (Number.isNaN(num) || num < 0 || num > max) {
                this.invalidCells[key] = true;
                el.classList.add('border-rose-500', 'ring-2', 'ring-rose-300', 'bg-rose-50');
            } else {
                delete this.invalidCells[key];
                el.classList.remove('border-rose-500', 'ring-2', 'ring-rose-300', 'bg-rose-50');
            }
        },
        moveCell(e, row, col, totalRows, totalCols) {
            let nextRow = row;
            let nextCol = col;
            if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                nextRow = row + 1;
                if (nextRow >= totalRows) {
                    if (e.key === 'Enter' && col + 1 < totalCols) {
                        nextRow = 0;
                        nextCol = col + 1;
                    } else {
                        return;
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                nextRow = row - 1;
                if (nextRow < 0) return;
            } else {
                return;
            }
            const target = this.$el.querySelector('input[data-row=\'' + nextRow + '\'][data-col=\'' + nextCol + '\']');
            if (target) {
                target.focus();
                target.select();
            }
        }
     }"
     @beforeunload.window="if (hasUnsaved) { $event.preventDefault(); $event.returnValue = ''; }">
    {{-- Period Switcher & Top Actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('teacher.classes.show', $assignment) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 mr-2">&larr; Class Overview</a>
            @foreach($assignment->schoolYear->gradingPeriods as $p)
                <a href="{{ route('teacher.gradebook.show', [$assignment, $p]) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $p->id === $period->id ? 'bg-school-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    {{ $p->code }} ({{ ucfirst($p->status) }})
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($config && ! $config->isDescriptive() && ! $isLocked)
                <button type="button" @click="showAddAssessment = !showAddAssessment"
                        class="px-3.5 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                    + Add Assessment
                </button>
                @if(isset($cloneSources) && $cloneSources->isNotEmpty())
                    <button type="button" @click="showClone = !showClone"
                            class="px-3.5 py-1.5 rounded-lg bg-white border border-school-300 hover:bg-school-50 text-school-800 text-xs font-semibold">
                        Copy Assessments
                    </button>
                @endif
                <button type="button" @click="showImport = !showImport"
                        class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                    Import Excel / CSV
                </button>
            @endif
            <a href="{{ route('teacher.gradebook.export', [$assignment, $period]) }}"
               class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                Export Excel
            </a>
            <a href="{{ route('teacher.gradebook.print', [$assignment, $period]) }}" target="_blank"
               class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                Print Grade Sheet
            </a>
        </div>
    </div>

    {{-- Lock / Submission Status Banner --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <div class="text-xs">
            <div class="flex items-center gap-2">
                <span class="font-bold text-slate-800">Submission Status ({{ $period->name }}):</span>
                @php $subStatus = $submission?->status ?? 'draft'; @endphp
                @if($subStatus === 'approved')
                    <span class="px-2.5 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">Approved (Official)</span>
                @elseif($subStatus === 'submitted')
                    <span class="px-2.5 py-0.5 rounded-full font-semibold bg-school-100 text-school-800">Submitted to Adviser (Official)</span>
                @elseif($subStatus === 'returned')
                    <span class="px-2.5 py-0.5 rounded-full font-semibold bg-amber-100 text-amber-800">Returned for Revision</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-700">Draft / Pending Submission</span>
                @endif
            </div>
            @if($submission?->submitted_at)
                <p class="text-slate-500 mt-1">
                    Submitted: {{ $submission->submitted_at->format('M d, Y h:i A') }} ({{ $submission->graded_students }}/{{ $submission->total_students }} graded)
                    @if(! empty($isPriorTeacherSubmission) && $submission->teacherAssignment?->teacher)
                        &bull; <span class="font-semibold text-slate-700">Submitted by previous teacher: {{ $submission->teacherAssignment->teacher->full_name }}</span>
                    @endif
                </p>
            @endif
            @if($subStatus === 'returned' && $submission?->return_reason)
                <p class="text-amber-800 font-medium mt-1">Administrator Return Reason: {{ $submission->return_reason }}</p>
            @endif
        </div>

        @if(! $isLocked && $config && $enrollments->isNotEmpty())
            <form method="POST" action="{{ route('teacher.gradebook.submit', [$assignment, $period]) }}"
                  onsubmit="return confirm('Submit official {{ $period->name }} grades for {{ $assignment->subject->name }}? Once submitted, grades will be locked and immediately visible to the Section Adviser.')">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm">
                    Submit Official Grades &rarr;
                </button>
            </form>
        @endif
    </div>

    @if($isLocked)
        @if($period->status === 'upcoming')
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-5 py-3.5 text-xs text-amber-900 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <strong>Grading Period Not Open Yet:</strong> {{ $period->name }} is currently marked as <strong>Upcoming</strong>.
                    @if(auth()->user()->isAdmin())
                        Click <strong>Open Period</strong> in School Years &amp; Periods to allow teachers to encode scores.
                    @else
                        Please ask your School Administrator to open {{ $period->name }} under <strong>School Years &amp; Periods</strong>.
                    @endif
                </div>
                @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('admin.grading-periods.open', $period) }}">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                            Open {{ $period->name }} Now
                        </button>
                    </form>
                @endif
            </div>
        @elseif($submission && $submission->isOfficial())
            <div class="rounded-xl bg-school-50 border border-school-200 px-5 py-3 text-xs text-school-900">
                @if(! empty($isPriorTeacherSubmission))
                    <strong>Submitted &amp; Locked (Prior Teacher):</strong> Official {{ $period->name }} grades for this subject were already submitted by <strong>{{ $submission->teacherAssignment?->teacher?->full_name ?? 'the previous teacher' }}</strong> before reassignment. Ask the Administrator to <strong>Return</strong> the submission in <em>Grade Submissions Monitor</em> if you need to re-encode {{ $period->code }} grades.
                @else
                    <strong>Submitted &amp; Locked:</strong> Official grades for {{ $period->name }} have been submitted to the Section Adviser. Contact the Administrator if a revision is needed.
                @endif
            </div>
        @else
            <div class="rounded-xl bg-rose-50 border border-rose-200 px-5 py-3 text-xs text-rose-900">
                <strong>Read-Only Mode:</strong> {{ $period->name }} is currently <strong>{{ $period->status }}</strong>{{ $assignment->schoolYear->is_closed ? ' (School Year Closed)' : '' }}. Grades cannot be modified while locked.
            </div>
        @endif
    @endif

    @if(session('import_errors'))
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 space-y-1">
            <p class="font-bold">Score import warnings:</p>
            <ul class="list-disc list-inside">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Active Grading Configuration Summary --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4 text-xs">
        @if($config)
            <div>
                <p class="font-semibold text-slate-900">Grading Policy: {{ $config->name }}</p>
                <p class="text-slate-500 mt-0.5">
                    Mode: <strong class="capitalize">{{ str_replace('_', ' ', $config->grading_mode) }}</strong>
                    @if($config->transmutationTable) &bull; Table: {{ $config->transmutationTable->name }} @endif
                    @if(! $config->isDescriptive())
                        &bull; Weights:
                        @foreach($config->topLevelComponents as $comp)
                            <span class="font-semibold text-slate-700">{{ $comp->code }} {{ number_format($comp->weight_percentage, 0) }}%</span>{{ ! $loop->last ? ' / ' : '' }}
                        @endforeach
                    @endif
                </p>
            </div>
            @if($classAverage !== null)
                <div class="px-3 py-1.5 rounded-lg bg-school-50 text-school-800 font-semibold">
                    Class Average: {{ number_format($classAverage, 2) }}
                </div>
            @endif
        @else
            <p class="text-amber-800 font-semibold">No active Grading Configuration matches this subject/curriculum. Ask the Administrator to configure one in Grading Configurations.</p>
        @endif
    </div>

    {{-- Live Class Grade Analytics Strip --}}
    @if(! empty($gradeStats) && $config && ! $config->isDescriptive())
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                    <p class="text-[10px] font-bold uppercase text-slate-400">Class Average</p>
                    <p class="text-base font-extrabold text-school-800 mt-0.5">{{ number_format($classAverage, 2) }}</p>
                </div>
                <div class="p-2.5 rounded-lg bg-emerald-50/60 border border-emerald-100">
                    <p class="text-[10px] font-bold uppercase text-emerald-700">Highest Grade</p>
                    <p class="text-base font-extrabold text-emerald-800 mt-0.5">{{ number_format($gradeStats['highest'], 0) }}</p>
                </div>
                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                    <p class="text-[10px] font-bold uppercase text-slate-500">Lowest Grade</p>
                    <p class="text-base font-extrabold {{ $gradeStats['lowest'] < $config->passing_grade ? 'text-rose-700' : 'text-slate-800' }} mt-0.5">{{ number_format($gradeStats['lowest'], 0) }}</p>
                </div>
                <div class="p-2.5 rounded-lg bg-emerald-50/60 border border-emerald-100">
                    <p class="text-[10px] font-bold uppercase text-emerald-700">Passing Rate</p>
                    <p class="text-base font-extrabold text-emerald-800 mt-0.5">{{ $gradeStats['passing_rate'] }}% <span class="text-[11px] font-semibold">({{ $gradeStats['passed_count'] }}/{{ $gradeStats['graded_count'] }})</span></p>
                </div>
                <div class="p-2.5 rounded-lg {{ $gradeStats['failed_count'] > 0 ? 'bg-rose-50/70 border-rose-100' : 'bg-slate-50 border-slate-100' }} border">
                    <p class="text-[10px] font-bold uppercase {{ $gradeStats['failed_count'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">Below {{ number_format($config->passing_grade, 0) }}</p>
                    <p class="text-base font-extrabold {{ $gradeStats['failed_count'] > 0 ? 'text-rose-700' : 'text-slate-500' }} mt-0.5">{{ $gradeStats['failed_count'] }}</p>
                </div>
            </div>
            @php
                $gTot = max(1, $gradeStats['graded_count']);
                $gBands = [
                    ['90–100', $gradeStats['bands']['outstanding'], 'bg-emerald-500'],
                    ['85–89', $gradeStats['bands']['very_satisfactory'], 'bg-school-600'],
                    ['80–84', $gradeStats['bands']['satisfactory'], 'bg-sky-500'],
                    ['75–79', $gradeStats['bands']['fairly_satisfactory'], 'bg-amber-400'],
                    ['<75', $gradeStats['bands']['did_not_meet'], 'bg-rose-500'],
                ];
            @endphp
            <div class="space-y-1.5">
                <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden flex">
                    @foreach($gBands as [$lbl, $cnt, $clr])
                        @if($cnt > 0)
                            <div class="h-full {{ $clr }}" style="width: {{ round(($cnt / $gTot) * 100, 1) }}%" title="{{ $lbl }}: {{ $cnt }} learner(s)"></div>
                        @endif
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-500">
                    @foreach($gBands as [$lbl, $cnt, $clr])
                        <span class="inline-flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full {{ $clr }}"></span>
                            <span>{{ $lbl }}: <strong class="text-slate-700">{{ $cnt }}</strong></span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Add Assessment Collapsible Panel --}}
    @if($config && ! $config->isDescriptive() && ! $isLocked)
        <div x-show="showAddAssessment" x-cloak class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-bold text-slate-900 mb-3">Create New Assessment Activity</h2>
            <form method="POST" action="{{ route('teacher.gradebook.assessments.store', [$assignment, $period]) }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                @csrf
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Grading Component <span class="text-rose-600">*</span></label>
                    <select name="grading_component_id" required class="w-full rounded-lg border-slate-300 text-xs">
                        @foreach($leafComponents as $leaf)
                            <option value="{{ $leaf['id'] }}">{{ $leaf['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="assessment_type" class="w-full rounded-lg border-slate-300 text-xs">
                        <option value="quiz">Quiz</option>
                        <option value="written_activity">Written Activity</option>
                        <option value="performance_task">Performance Task</option>
                        <option value="project">Project</option>
                        <option value="summative_test">Summative Test</option>
                        <option value="examination">Examination</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Title <span class="text-rose-600">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Quiz 1" class="w-full rounded-lg border-slate-300 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Max Score <span class="text-rose-600">*</span></label>
                    <input type="number" step="0.5" min="1" name="max_score" required placeholder="20" class="w-full rounded-lg border-slate-300 text-xs">
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                        Add Activity
                    </button>
                </div>
            </form>
        </div>

        {{-- Copy Assessments From Another Class/Period Panel --}}
        @if(isset($cloneSources) && $cloneSources->isNotEmpty())
            <div x-show="showClone" x-cloak class="bg-white rounded-xl border border-school-200 shadow-sm p-5 space-y-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Copy Assessment Columns From Another Class or Term</h2>
                    <p class="text-xs text-slate-500">Quickly duplicate your quizzes, performance tasks, and exams (without copying student scores) from any of your compatible classes.</p>
                </div>
                <form method="POST" action="{{ route('teacher.gradebook.assessments.clone', [$assignment, $period]) }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Source Class</label>
                        <select name="source_assignment_id" required class="w-full rounded-lg border-slate-300 text-xs">
                            @foreach($cloneSources as $src)
                                <option value="{{ $src->id }}">
                                    {{ $src->subject->code }} &mdash; {{ $src->section->gradeLevel->name }} {{ $src->section->name }}{{ $src->id === $assignment->id ? ' (This Class)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Source Grading Period</label>
                        <select name="source_period_id" required class="w-full rounded-lg border-slate-300 text-xs">
                            @foreach($assignment->schoolYear->gradingPeriods as $srcP)
                                <option value="{{ $srcP->id }}" @selected($srcP->id === $period->id)>{{ $srcP->name }} ({{ $srcP->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                            Copy Assessments
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Bulk Score Import Collapsible Panel --}}
        <div x-show="showImport" x-cloak class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Import Scores via Excel / CSV</h2>
                    <p class="text-xs text-slate-500">Download the pre-formatted template for this class, enter scores (or <code>EX</code> for excused, blank/<code>M</code> for missing), and upload below.</p>
                </div>
                <a href="{{ route('teacher.gradebook.template', [$assignment, $period]) }}"
                   class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold">
                    Download Excel Score Template
                </a>
            </div>
            <form method="POST" action="{{ route('teacher.gradebook.import', [$assignment, $period]) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="score_file" required accept=".xlsx,.xls,.csv" class="text-xs text-slate-700">
                <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                    Upload &amp; Compute Scores
                </button>
            </form>
        </div>
    @endif

    {{-- Gradebook Grid --}}
    @if($config && $config->isDescriptive())
        {{-- Key Stage 1 Descriptive Grading Table --}}
        <form method="POST" action="{{ route('teacher.gradebook.descriptive.save', [$assignment, $period]) }}"
              class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">#</th>
                        <th class="px-5 py-3">Learner Name</th>
                        <th class="px-5 py-3">Descriptive Rating</th>
                        <th class="px-5 py-3">Descriptor / Remarks</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($enrollments as $idx => $enr)
                        @php $qg = $gradesByEnrollment->get($enr->id); @endphp
                        <tr>
                            <td class="px-5 py-3 text-slate-400">{{ $idx + 1 }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-900">{{ $enr->student->formal_name }}</p>
                                <p class="text-[11px] font-mono text-slate-400">{{ $enr->student->student_number }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <select name="ratings[{{ $enr->id }}]" @disabled($isLocked) class="rounded-lg border-slate-300 text-xs">
                                    <option value="">— Not Yet Rated —</option>
                                    @foreach($config->descriptive_scale_json ?? [] as $item)
                                        <option value="{{ $item['code'] }}" @selected($qg?->descriptive_rating === $item['code'])>
                                            {{ $item['code'] }} — {{ $item['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600">{{ $qg?->remarks ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-slate-500">No students enrolled in this section.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if(! $isLocked && $enrollments->isNotEmpty())
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                        Save Descriptive Ratings
                    </button>
                </div>
            @endif
        </form>
    @elseif($config)
        {{-- Numerical Score Sheet --}}
        <form method="POST" action="{{ route('teacher.gradebook.scores.save', [$assignment, $period]) }}"
              @submit="hasUnsaved = false"
              class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            @csrf
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" x-model="studentFilter" placeholder="Filter learner name or ID..."
                           class="w-48 rounded-lg border-slate-300 text-xs py-1">
                    <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer select-none">
                        <input type="checkbox" x-model="missingOnly" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>Missing scores only</span>
                    </label>
                    <span class="hidden xl:inline text-slate-400">&bull; <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-300 text-[10px] font-mono">Enter</kbd> / <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-300 text-[10px] font-mono">&uarr;&darr;</kbd> moves rows</span>
                </div>
                <div class="flex items-center gap-3">
                    <span x-show="hasUnsaved" x-cloak class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold text-[11px]">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        Unsaved changes
                    </span>
                    <span x-show="Object.keys(invalidCells).length > 0" x-cloak class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 font-semibold text-[11px]">
                        <span x-text="Object.keys(invalidCells).length"></span> invalid score(s)
                    </span>
                    <span>{{ $enrollments->count() }} student(s) &bull; {{ $assessments->count() }} assessment(s)</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold text-slate-600">
                    <tr>
                        <th class="px-4 py-3 sticky left-0 bg-slate-50 z-10 min-w-[200px] border-r border-slate-200">Learner</th>
                        @foreach($assessments as $assessment)
                            <th class="px-3 py-2 text-center min-w-[115px] border-r border-slate-100">
                                <span class="inline-block px-1.5 py-0.5 rounded bg-school-50 text-school-700 text-[10px] font-bold">
                                    {{ $assessment->component->code }}
                                </span>
                                <p class="font-semibold text-slate-800 mt-0.5 truncate max-w-[110px]" title="{{ $assessment->title }}">{{ $assessment->title }}</p>
                                <p class="text-[10px] text-slate-500">Max: {{ $assessment->max_score }}</p>
                            </th>
                        @endforeach
                        <th class="px-3 py-3 text-center bg-slate-100">Initial</th>
                        <th class="px-3 py-3 text-center bg-school-50 text-school-900">Grade</th>
                        <th class="px-3 py-3 text-center bg-slate-100">Missing</th>
                        <th class="px-3 py-3 bg-slate-100">Remarks</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($enrollments as $idx => $enr)
                        @php
                            $qg = $gradesByEnrollment->get($enr->id);
                            $hasMissingRow = ($qg?->missing_scores_count ?? 0) > 0 || ($assessments->isNotEmpty() && ! $qg);
                            $searchHaystack = mb_strtolower($enr->student->formal_name . ' ' . $enr->student->student_number . ' ' . ($enr->student->lrn ?? ''));
                        @endphp
                        <tr class="hover:bg-slate-50/70"
                            x-show="(!studentFilter.trim() || {{ Js::from($searchHaystack) }}.includes(studentFilter.trim().toLowerCase())) && (!missingOnly || {{ $hasMissingRow ? 'true' : 'false' }})">
                            <td class="px-4 py-2 sticky left-0 bg-white z-10 border-r border-slate-200">
                                <p class="font-medium text-slate-900">{{ $idx + 1 }}. {{ $enr->student->formal_name }}</p>
                                <p class="text-[10px] font-mono text-slate-400">{{ $enr->student->student_number }}</p>
                            </td>
                            @foreach($assessments as $colIdx => $assessment)
                                @php
                                    $sc = $scoresByKey->get($enr->id . ':' . $assessment->id);
                                    $val = '';
                                    $isMissing = true;
                                    if ($sc) {
                                        if ($sc->status === 'scored') {
                                            $val = $sc->raw_score;
                                            $isMissing = false;
                                        } elseif ($sc->status === 'excused') {
                                            $val = 'EX';
                                            $isMissing = false;
                                        }
                                    }
                                @endphp
                                <td class="px-2 py-1.5 text-center border-r border-slate-100 {{ $isMissing ? 'bg-amber-50/50' : '' }}">
                                    <input type="text"
                                           name="scores[{{ $enr->id }}][{{ $assessment->id }}]"
                                           value="{{ old("scores.{$enr->id}.{$assessment->id}", $val) }}"
                                           data-row="{{ $idx }}"
                                           data-col="{{ $colIdx }}"
                                           data-max="{{ $assessment->max_score }}"
                                           @input="validateCell($event.target)"
                                           @keydown="moveCell($event, {{ $idx }}, {{ $colIdx }}, {{ $enrollments->count() }}, {{ $assessments->count() }})"
                                           @disabled($isLocked)
                                           placeholder="M"
                                           class="w-16 text-center rounded border-slate-300 text-xs py-1 focus:border-school-500 focus:ring-school-500 disabled:bg-slate-100">
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-center font-mono text-slate-600 bg-slate-50/60">
                                {{ $qg?->initial_grade !== null ? number_format($qg->initial_grade, 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-center font-bold bg-school-50/40 {{ ($qg?->final_grade ?? 100) < $config->passing_grade ? 'text-rose-700' : 'text-school-800' }}">
                                @if($qg && ! empty($qg->component_breakdown_json))
                                    @php
                                        $breakdownPayload = [
                                            'name' => $enr->student->formal_name,
                                            'student_number' => $enr->student->student_number,
                                            'initial_grade' => $qg->initial_grade !== null ? number_format($qg->initial_grade, 2) : '—',
                                            'transmuted_grade' => $qg->transmuted_grade !== null ? number_format($qg->transmuted_grade, 2) : null,
                                            'final_grade' => $qg->display_grade ?? '—',
                                            'remarks' => $qg->remarks ?? '—',
                                            'components' => $qg->component_breakdown_json,
                                        ];
                                    @endphp
                                    <button type="button"
                                            @click='openBreakdown(@json($breakdownPayload))'
                                            title="Click to inspect weighted component breakdown"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md hover:bg-school-100 transition underline decoration-dotted underline-offset-2">
                                        <span>{{ $qg->display_grade }}</span>
                                    </button>
                                @else
                                    {{ $qg?->display_grade ?? '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if(($qg?->missing_scores_count ?? 0) > 0)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                        {{ $qg->missing_scores_count }}
                                    </span>
                                @else
                                    <span class="text-emerald-600 font-semibold">0</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $qg?->remarks ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $assessments->count() + 5 }}" class="px-5 py-8 text-center text-slate-500">No students enrolled in this class yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if(! $isLocked && $enrollments->isNotEmpty() && $assessments->isNotEmpty())
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">Click any computed <strong>Grade</strong> to view its full WW / PT / EX weighted breakdown.</p>
                    <button type="submit"
                            :disabled="Object.keys(invalidCells).length > 0"
                            :class="Object.keys(invalidCells).length > 0 ? 'opacity-50 cursor-not-allowed bg-slate-400' : (hasUnsaved ? 'bg-amber-500 hover:bg-amber-400 text-slate-950' : 'bg-school-600 hover:bg-school-700 text-white')"
                            class="px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm transition">
                        Save Scores &amp; Recompute Grades
                    </button>
                </div>
            @endif
        </form>

        {{-- Component Grade Breakdown Modal --}}
        <div x-show="breakdownOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
            <div @click.away="breakdownOpen = false" class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-xl w-full overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-amber-300">Weighted Grade Breakdown ({{ $period->code }})</p>
                        <h3 class="text-base font-bold mt-0.5" x-text="breakdownStudent?.name"></h3>
                        <p class="text-[11px] text-slate-300 font-mono" x-text="breakdownStudent?.student_number"></p>
                    </div>
                    <button type="button" @click="breakdownOpen = false" class="p-1.5 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white">&times;</button>
                </div>

                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto" x-show="breakdownStudent">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <p class="text-[10px] font-bold uppercase text-slate-500">Initial Grade</p>
                            <p class="text-lg font-extrabold font-mono text-slate-900 mt-0.5" x-text="breakdownStudent?.initial_grade"></p>
                        </div>
                        <div class="p-3 rounded-xl bg-school-50 border border-school-200">
                            <p class="text-[10px] font-bold uppercase text-school-700">Final Grade</p>
                            <p class="text-lg font-extrabold text-school-900 mt-0.5" x-text="breakdownStudent?.final_grade"></p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <p class="text-[10px] font-bold uppercase text-slate-500">Remarks</p>
                            <p class="text-xs font-bold text-slate-800 mt-1.5" x-text="breakdownStudent?.remarks"></p>
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        <template x-for="comp in (breakdownStudent?.components || [])" :key="comp.component_id">
                            <div class="rounded-xl border border-slate-200 overflow-hidden text-xs">
                                <div class="px-4 py-2.5 bg-slate-50 flex items-center justify-between gap-2">
                                    <div>
                                        <span class="font-bold text-school-700" x-text="comp.code"></span>
                                        <span class="font-semibold text-slate-800 ml-1" x-text="comp.name"></span>
                                        <span class="text-slate-500 ml-1" x-text="'(' + comp.weight_percentage + '%)'"></span>
                                    </div>
                                    <div class="text-right font-mono">
                                        <span class="text-slate-500" x-text="'PS: ' + (comp.ps !== null && comp.ps !== undefined ? comp.ps + '%' : '—')"></span>
                                        <span class="ml-2 font-bold text-slate-900" x-text="'WS: ' + (comp.ws ?? 0)"></span>
                                    </div>
                                </div>
                                <template x-if="comp.children && comp.children.length > 0">
                                    <div class="divide-y divide-slate-100 bg-white">
                                        <template x-for="child in comp.children" :key="child.component_id">
                                            <div class="px-4 py-2 flex items-center justify-between text-[11px] text-slate-600">
                                                <span>
                                                    <span class="font-semibold text-slate-800" x-text="child.code"></span>
                                                    <span x-text="' — ' + child.name + ' (' + child.weight_percentage + '% of ' + comp.code + ')'"></span>
                                                </span>
                                                <span class="font-mono">
                                                    <span x-text="child.total_raw + '/' + child.hps"></span>
                                                    <span class="ml-2 text-school-700 font-semibold" x-text="child.ps !== null && child.ps !== undefined ? child.ps + '%' : '—'"></span>
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!comp.children || comp.children.length === 0">
                                    <div class="px-4 py-2 bg-white flex items-center justify-between text-[11px] text-slate-500">
                                        <span>Raw Score / Highest Possible Score</span>
                                        <span class="font-mono font-semibold text-slate-800" x-text="(comp.total_raw ?? 0) + ' / ' + (comp.hps ?? 0)"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button type="button" @click="breakdownOpen = false" class="px-4 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">Close</button>
                </div>
            </div>
        </div>

        {{-- Manage / Delete Assessments List (outside scores form to avoid nested forms) --}}
        @if(! $isLocked && $assessments->isNotEmpty())
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Manage Created Assessments</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($assessments as $assessment)
                        @php
                            $editPayload = [
                                'title' => $assessment->title,
                                'assessment_type' => $assessment->assessment_type,
                                'max_score' => (float) $assessment->max_score,
                                'assessment_date' => $assessment->assessment_date?->format('Y-m-d') ?? '',
                            ];
                        @endphp
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                            <span class="font-semibold text-school-700">{{ $assessment->component->code }}</span>
                            <span>{{ $assessment->title }} (Max {{ $assessment->max_score }})</span>
                            <button type="button"
                                    @click='openEditAssessment("{{ route('teacher.gradebook.assessments.update', [$assignment, $period, $assessment]) }}", @json($editPayload))'
                                    class="text-school-600 hover:text-school-900 font-semibold"
                                    title="Edit assessment">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('teacher.gradebook.assessments.destroy', [$assignment, $period, $assessment]) }}"
                                  onsubmit="return confirm('Delete assessment {{ $assessment->title }} and all its scores?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 font-bold" title="Delete assessment">&times;</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Edit Assessment Modal --}}
            <div x-show="editAssessmentOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
                <div @click.away="editAssessmentOpen = false" class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full p-6">
                    <h3 class="text-base font-bold text-slate-900">Edit Assessment Details</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Grades will automatically recalculate after saving changes.</p>
                    <form method="POST" :action="editAssessmentUrl" class="mt-4 space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Title <span class="text-rose-600">*</span></label>
                            <input type="text" name="title" x-model="editAssessment.title" required maxlength="150" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Type</label>
                                <select name="assessment_type" x-model="editAssessment.assessment_type" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="quiz">Quiz</option>
                                    <option value="written_activity">Written Activity</option>
                                    <option value="performance_task">Performance Task</option>
                                    <option value="project">Project</option>
                                    <option value="summative_test">Summative Test</option>
                                    <option value="examination">Examination</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Max Score <span class="text-rose-600">*</span></label>
                                <input type="number" step="0.5" min="1" name="max_score" x-model="editAssessment.max_score" required class="w-full rounded-lg border-slate-300 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Assessment Date</label>
                            <input type="date" name="assessment_date" x-model="editAssessment.assessment_date" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="editAssessmentOpen = false" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">Save &amp; Recalculate</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
