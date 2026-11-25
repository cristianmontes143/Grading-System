@extends('layouts.app')

@section('title', 'Remedial Classes & Recomputed Grades')
@section('header', 'Remedial Classes & Recomputed Final Grades')
@section('subheader', 'DepEd DO 13, s. 2018 & DO 015, s. 2026 learning recovery records and SF10 recomputed ratings')

@section('content')
<div class="space-y-6">
    {{-- Filter Bar --}}
    <div class="school-card p-5">
        <form method="GET" action="{{ route('admin.remedials.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">School Year</label>
                <select name="school_year_id" class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy->id }}" @selected($selectedSchoolYear?->id === $sy->id)>SY {{ $sy->name }} {{ $sy->is_active ? '(Active)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Section</label>
                <select name="section_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All Sections</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}" @selected($selectedSectionId === $sec->id)>{{ $sec->gradeLevel->name }} – {{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Search Learner</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Name, LRN, or Student ID..." class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">Apply</button>
                <a href="{{ route('admin.remedials.index') }}" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-medium">Reset</a>
            </div>
        </form>
    </div>

    {{-- KPI Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="school-card p-4">
            <p class="text-[11px] font-bold uppercase text-slate-400">Detected Failing Areas</p>
            <p class="text-2xl font-extrabold text-slate-900 mt-1">{{ $summary['candidates_count'] }}</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[11px] font-bold uppercase text-amber-600">Pending Remedial Mark</p>
            <p class="text-2xl font-extrabold text-amber-700 mt-1">{{ $summary['pending_count'] }}</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[11px] font-bold uppercase text-school-600">Recorded Remedials</p>
            <p class="text-2xl font-extrabold text-school-800 mt-1">{{ $summary['recorded_count'] }}</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[11px] font-bold uppercase text-emerald-600">Recomputed Passed</p>
            <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $summary['passed_count'] }}</p>
        </div>
        <div class="school-card p-4">
            <p class="text-[11px] font-bold uppercase text-rose-600">Still Failed (&lt;75)</p>
            <p class="text-2xl font-extrabold text-rose-700 mt-1">{{ $summary['failed_count'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Left: Auto-detected Learners Needing Remedial + Quick Record --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="school-card overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Detected Learners with Failing Final Ratings (&lt; 75)</h2>
                        <p class="text-xs text-slate-500">Automatically scanned from completed official subject grades. Formula: <strong>Recomputed = Round((Final + Remedial) / 2)</strong>.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Learner &amp; Section</th>
                            <th class="px-4 py-3">Learning Area</th>
                            <th class="px-3 py-3 text-center">Final Rating</th>
                            <th class="px-4 py-3">Encode Remedial Mark</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($candidates as $c)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-bold text-slate-900">{{ $c['enrollment']->student->formal_name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $c['enrollment']->gradeLevel->name }} – {{ $c['enrollment']->section->name }}</p>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-800">
                                    {{ $c['subject']->name }}
                                    <span class="font-mono text-[10px] text-slate-400 ml-1">{{ $c['subject']->code }}</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold">{{ number_format($c['final_rating'], 0) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.remedials.store') }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="student_enrollment_id" value="{{ $c['enrollment']->id }}">
                                        <input type="hidden" name="subject_id" value="{{ $c['subject']->id }}">
                                        <input type="hidden" name="final_rating" value="{{ $c['final_rating'] }}">
                                        <input type="number" name="remedial_class_mark" step="0.01" min="60" max="100" required
                                               value="{{ $c['remedial']?->remedial_class_mark }}"
                                               placeholder="Mark (60-100)"
                                               class="w-28 rounded-lg border-slate-300 text-xs py-1.5">
                                        <input type="text" name="conducted_by" value="{{ $c['remedial']?->conducted_by }}" placeholder="Remedial Teacher"
                                               class="w-36 rounded-lg border-slate-300 text-xs py-1.5">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white font-semibold text-xs">
                                            {{ $c['remedial'] ? 'Update' : 'Save' }}
                                        </button>
                                    </form>
                                    @if($c['remedial'])
                                        <p class="text-[11px] mt-1 font-semibold {{ $c['remedial']->isPassed() ? 'text-emerald-700' : 'text-rose-700' }}">
                                            Recomputed: {{ number_format($c['remedial']->recomputed_final_grade, 0) }} ({{ $c['remedial']->remarks }})
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-slate-400">
                                    No failing completed final subject grades (&lt; 75) detected for this filter. You can still encode a manual remedial record on the right.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recorded Remedial Classes Registry --}}
            <div class="school-card overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-base font-bold text-slate-900">Recorded Remedial Classes (Synced to SF10)</h2>
                    <p class="text-xs text-slate-500">All recorded remedial classes automatically appear on the learner's SF10 Permanent Academic Record.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Learner</th>
                            <th class="px-4 py-3">Learning Area</th>
                            <th class="px-3 py-3 text-center">Final</th>
                            <th class="px-3 py-3 text-center">Remedial</th>
                            <th class="px-3 py-3 text-center">Recomputed</th>
                            <th class="px-3 py-3 text-center">Remarks</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($recordedRemedials as $rec)
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.students.sf10', $rec->enrollment->student) }}" class="font-bold text-school-700 hover:underline">
                                        {{ $rec->enrollment->student->formal_name }}
                                    </a>
                                    <p class="text-[11px] text-slate-500">{{ $rec->enrollment->gradeLevel->name }} – {{ $rec->enrollment->section->name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-800">{{ $rec->subject->name }}</p>
                                    @if($rec->conducted_from || $rec->conducted_to)
                                        <p class="text-[10px] text-slate-400">
                                            {{ $rec->conducted_from?->format('M d, Y') ?? '—' }} to {{ $rec->conducted_to?->format('M d, Y') ?? '—' }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center font-semibold text-rose-700">{{ number_format($rec->final_rating, 0) }}</td>
                                <td class="px-3 py-3 text-center font-semibold text-slate-800">{{ number_format($rec->remedial_class_mark, 0) }}</td>
                                <td class="px-3 py-3 text-center font-extrabold text-school-900">{{ number_format($rec->recomputed_final_grade, 0) }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $rec->isPassed() ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $rec->remarks }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                    <a href="{{ route('admin.remedials.certificate', $rec->enrollment) }}" target="_blank"
                                       class="text-school-600 hover:underline font-semibold">Certificate PDF &nearr;</a>
                                    <form method="POST" action="{{ route('admin.remedials.destroy', $rec) }}" class="inline"
                                          onsubmit="return confirm('Remove this remedial record?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:underline font-semibold">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-slate-400">No remedial class records encoded yet for this school year.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right: Manual Remedial Encoding Form --}}
        <div class="school-card p-6 h-fit">
            <h3 class="text-sm font-bold text-slate-900 mb-1">Encode Remedial Class Record</h3>
            <p class="text-xs text-slate-500 mb-4">Record summer or learning recovery remedial marks manually (including transferees or completed terms).</p>

            <form method="POST" action="{{ route('admin.remedials.store') }}" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Learner Enrollment</label>
                    <select name="student_enrollment_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Select Learner...</option>
                        @foreach($enrollmentsForManual as $enr)
                            <option value="{{ $enr->id }}">{{ $enr->student->formal_name }} ({{ $enr->gradeLevel->code }} – {{ $enr->section->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Learning Area / Subject</label>
                    <select name="subject_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Select Subject...</option>
                        @foreach($subjects as $subj)
                            <option value="{{ $subj->id }}">{{ $subj->name }} ({{ $subj->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Original Final (&lt;75)</label>
                        <input type="number" name="final_rating" step="0.01" min="50" max="74.99" required placeholder="e.g. 72" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Remedial Mark</label>
                        <input type="number" name="remedial_class_mark" step="0.01" min="60" max="100" required placeholder="e.g. 82" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Conducted From</label>
                        <input type="date" name="conducted_from" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Conducted To</label>
                        <input type="date" name="conducted_to" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Conducted By (Teacher)</label>
                    <input type="text" name="conducted_by" placeholder="e.g. Mr. Juan Dela Cruz" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">School Where Conducted</label>
                    <input type="text" name="school_name" value="{{ $school->school_name }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional learning recovery remarks..." class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                </div>
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-school-600 hover:bg-school-700 text-white text-sm font-semibold shadow-sm">
                    Compute &amp; Save Remedial Record
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
