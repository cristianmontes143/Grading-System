@extends('layouts.app')

@section('title', 'SF10 Permanent Record — ' . $student->formal_name)
@section('header', 'Learner Permanent Academic Record (SF10 / Form 137)')
@section('subheader', $student->formal_name . ' • Student ID: ' . $student->student_number . ($student->lrn ? ' • LRN: ' . $student->lrn : ''))

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.students.show', $student) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to Student Profile</a>
            @else
                <a href="{{ route('adviser.sections.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to Advisory Sections</a>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ request()->fullUrlWithQuery(['pdf' => 1]) }}" target="_blank"
               class="px-4 py-2 rounded-xl bg-school-600 hover:bg-school-700 text-white text-xs font-semibold shadow-sm transition">
                Download / Print Official SF10 PDF &nearr;
            </a>
        </div>
    </div>

    {{-- Learner Personal Profile Card --}}
    <div class="school-card p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-school-700">DepEd School Form 10 (SF10) — Permanent Academic Record</p>
                <h2 class="text-xl font-extrabold text-slate-900 mt-0.5">{{ $student->formal_name }}</h2>
            </div>
            <span class="self-start px-3 py-1 rounded-full text-xs font-bold uppercase {{ $student->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($student->status === 'graduated' ? 'bg-school-100 text-school-800' : 'bg-slate-200 text-slate-700') }}">
                {{ str_replace('_', ' ', $student->status) }}
            </span>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 text-xs">
            <div>
                <dt class="text-slate-400 font-semibold uppercase text-[10px]">Learner Reference Number (LRN)</dt>
                <dd class="font-mono font-bold text-slate-900 mt-0.5">{{ $student->lrn ?? 'Not Assigned' }}</dd>
            </div>
            <div>
                <dt class="text-slate-400 font-semibold uppercase text-[10px]">Student ID</dt>
                <dd class="font-mono font-bold text-slate-900 mt-0.5">{{ $student->student_number }}</dd>
            </div>
            <div>
                <dt class="text-slate-400 font-semibold uppercase text-[10px]">Sex &amp; Date of Birth</dt>
                <dd class="font-semibold text-slate-900 mt-0.5">{{ $student->gender }} &bull; {{ $student->date_of_birth->format('M d, Y') }}</dd>
            </div>
            <div>
                <dt class="text-slate-400 font-semibold uppercase text-[10px]">Recorded School Years</dt>
                <dd class="font-semibold text-school-700 mt-0.5">{{ $records->count() }} Enrollment Record(s)</dd>
            </div>
        </dl>
    </div>

    {{-- Multi-Year Scholastic Records --}}
    @forelse($records as $item)
        @php
            $enr = $item['enrollment'];
            $sum = $item['summary'];
        @endphp
        <div class="school-card overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-md bg-school-600 text-white text-xs font-bold">SY {{ $enr->schoolYear->name }}</span>
                        <h3 class="text-base font-bold text-slate-900">{{ $enr->gradeLevel->name }} &ndash; {{ $enr->section->name }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-200 text-slate-700">{{ $enr->curriculum->name }}</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        Adviser: <strong>{{ $enr->section->adviser?->full_name ?? 'Unassigned' }}</strong>
                        &bull; Status: <strong class="capitalize">{{ str_replace('_', ' ', $enr->enrollment_status) }}</strong>
                        @if($enr->shsTrack) &bull; Track: <strong>{{ $enr->shsTrack->name }}</strong> @endif
                        @if($enr->shsStrand) ({{ $enr->shsStrand->code }}) @endif
                    </p>
                </div>

                <div class="text-right">
                    <p class="text-[10px] font-bold uppercase text-slate-400">General Average</p>
                    <p class="text-xl font-extrabold {{ ($sum['general_average'] ?? 100) < 75 ? 'text-rose-700' : 'text-school-800' }}">
                        {{ $sum['general_average'] !== null ? number_format($sum['general_average'], 0) : ($sum['is_descriptive_only'] ? 'Descriptive' : 'Incomplete') }}
                    </p>
                    <p class="text-[11px] font-semibold capitalize text-slate-500">{{ str_replace('_', ' ', $sum['promotion_status']) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-2.5">Learning Area / Subject</th>
                        @foreach($sum['periods'] as $p)
                            <th class="px-3 py-2.5 text-center">{{ $p->code }}</th>
                        @endforeach
                        <th class="px-4 py-2.5 text-center bg-school-50 text-school-900">Final Rating</th>
                        <th class="px-5 py-2.5">Remarks</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($sum['subject_rows'] as $row)
                        <tr>
                            <td class="px-5 py-2.5 font-medium text-slate-900">
                                {{ $row['subject']->name }}
                                <span class="text-[10px] font-mono text-slate-400 ml-1">{{ $row['subject']->code }}</span>
                            </td>
                            @foreach($sum['periods'] as $p)
                                @php $val = $row['period_grades'][$p->id] ?? null; @endphp
                                <td class="px-3 py-2.5 text-center font-semibold {{ is_numeric($val) && (float) $val < 75 ? 'text-rose-700' : 'text-slate-700' }}">
                                    {{ $val ?? '—' }}
                                </td>
                            @endforeach
                            <td class="px-4 py-2.5 text-center font-extrabold bg-school-50/40 {{ ($row['final_grade'] ?? 100) < 75 ? 'text-rose-700' : 'text-school-900' }}">
                                {{ $row['final_grade'] !== null ? number_format($row['final_grade'], 0) : '—' }}
                            </td>
                            <td class="px-5 py-2.5 text-slate-600">{{ $row['remarks'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $sum['periods']->count() + 3 }}" class="px-5 py-4 text-center text-slate-400">No subjects mapped for this historical curriculum.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($enr->remedialClasses->isNotEmpty())
                <div class="px-6 py-4 bg-amber-50/50 border-t border-amber-200/70">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-amber-800 mb-2">Remedial Classes / Recomputed Final Grades</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-200/70 text-xs">
                            <thead class="text-left text-[10px] font-bold uppercase text-amber-900">
                            <tr>
                                <th class="py-1.5 pr-4">Learning Area</th>
                                <th class="py-1.5 px-3 text-center">Final Rating</th>
                                <th class="py-1.5 px-3 text-center">Remedial Mark</th>
                                <th class="py-1.5 px-3 text-center">Recomputed Final</th>
                                <th class="py-1.5 pl-3">Remarks</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100">
                            @foreach($enr->remedialClasses as $rem)
                                <tr>
                                    <td class="py-1.5 pr-4 font-semibold text-slate-900">{{ $rem->subject->name }}</td>
                                    <td class="py-1.5 px-3 text-center text-rose-700 font-semibold">{{ number_format($rem->final_rating, 0) }}</td>
                                    <td class="py-1.5 px-3 text-center text-slate-800 font-semibold">{{ number_format($rem->remedial_class_mark, 0) }}</td>
                                    <td class="py-1.5 px-3 text-center font-extrabold text-school-900">{{ number_format($rem->recomputed_final_grade, 0) }}</td>
                                    <td class="py-1.5 pl-3 font-bold {{ $rem->isPassed() ? 'text-emerald-700' : 'text-rose-700' }}">{{ $rem->remarks }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="px-6 py-3 bg-slate-50/70 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
                <span>
                    Attendance Totals: <strong>{{ $sum['attendance_totals']['days_present'] }}</strong> present /
                    <strong>{{ $sum['attendance_totals']['school_days'] }}</strong> school days
                    ({{ $sum['attendance_totals']['days_absent'] }} absent, {{ $sum['attendance_totals']['days_tardy'] }} tardy)
                </span>
                <a href="{{ route('adviser.report-cards.individual', ['enrollment' => $enr, 'draft' => $sum['has_missing_grades'] ? 1 : 0]) }}" target="_blank"
                   class="font-semibold text-school-600 hover:underline">
                    Open SY {{ $enr->schoolYear->name }} SF9 Report Card PDF &nearr;
                </a>
            </div>
        </div>
    @empty
        <div class="school-card p-10 text-center text-sm text-slate-500">
            This student has no recorded enrollments yet.
        </div>
    @endforelse
</div>
@endsection
