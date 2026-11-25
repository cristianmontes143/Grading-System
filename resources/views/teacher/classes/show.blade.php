@extends('layouts.app')

@section('title', $assignment->subject->name)
@section('header', $assignment->subject->name . ' — ' . $assignment->section->gradeLevel->name . ' – ' . $assignment->section->name)
@section('subheader', 'SY ' . $assignment->schoolYear->name . ' • Curriculum: ' . $assignment->subject->curriculum->name)

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm flex-1">
                <div>
                    <dt class="text-slate-500 text-xs uppercase font-semibold">Assigned Teacher</dt>
                    <dd class="font-semibold text-slate-900 mt-1">{{ $assignment->teacher->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 text-xs uppercase font-semibold">Section Adviser</dt>
                    <dd class="font-semibold text-slate-900 mt-1">{{ $assignment->section->adviser?->full_name ?? 'Unassigned' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 text-xs uppercase font-semibold">Schedule</dt>
                    <dd class="font-semibold text-slate-900 mt-1">{{ $assignment->schedule_summary ?? 'Not specified' }}</dd>
                </div>
            </dl>
            <a href="{{ route('teacher.classes.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to My Load</a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900 mb-3">Grading Periods (SY {{ $assignment->schoolYear->name }})</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach($assignment->schoolYear->gradingPeriods as $period)
                <div class="rounded-lg border border-slate-200 p-4 flex flex-col justify-between gap-3">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-sm text-slate-900">{{ $period->name }}</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $period->status === 'open' ? 'bg-emerald-100 text-emerald-800' : ($period->status === 'locked' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600') }}">
                                {{ ucfirst($period->status) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Code: {{ $period->code }}</p>
                    </div>
                    <a href="{{ route('teacher.gradebook.show', [$assignment, $period]) }}"
                       class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                        Open Gradebook &rarr;
                    </a>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">Enrolled Students ({{ $enrolledStudents->count() }})</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('teacher.classes.summary', $assignment) }}"
                   class="px-3 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                    Full-Year Subject Summary &rarr;
                </a>
                <a href="{{ route('teacher.classes.print-roster', $assignment) }}" target="_blank"
                   class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-semibold">
                    Print Classroom Roster &nearr;
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">#</th>
                    <th class="px-5 py-3">Student ID</th>
                    <th class="px-5 py-3">LRN</th>
                    <th class="px-5 py-3">Learner Name</th>
                    <th class="px-5 py-3">Gender</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($enrolledStudents as $idx => $enrollment)
                    <tr>
                        <td class="px-5 py-2.5 text-slate-400">{{ $idx + 1 }}</td>
                        <td class="px-5 py-2.5 font-mono text-xs text-slate-700">{{ $enrollment->student->student_number }}</td>
                        <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{{ $enrollment->student->lrn ?? '—' }}</td>
                        <td class="px-5 py-2.5 font-medium text-slate-900">{{ $enrollment->student->formal_name }}</td>
                        <td class="px-5 py-2.5 text-slate-600">{{ $enrollment->student->gender }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-6 text-center text-slate-500">No students enrolled in this class yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
