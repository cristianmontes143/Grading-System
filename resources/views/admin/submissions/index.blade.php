@extends('layouts.app')

@section('title', 'Grade Submissions Monitor')
@section('header', 'School-Wide Grade Submission Monitoring')
@section('subheader', 'Track submitted, approved, returned, and pending subject grades across all sections.')

@section('content')
<div class="space-y-6" x-data="{ returnModal: false, returnUrl: '', returnTitle: '' }">
    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.submissions.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap gap-3">
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
                <option value="{{ $gl->id }}" @selected(request('grade_level_id') == $gl->id)>{{ $gl->name }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Statuses</option>
            <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
            <option value="returned" @selected(request('status') === 'returned')>Returned</option>
        </select>
        @if($selectedPeriod && ($stats['submitted'] > 0 || ($stats['pending'] + $stats['returned']) > 0))
            <div class="ml-auto flex flex-wrap gap-2">
                @if($stats['submitted'] > 0)
                    <button type="submit" form="approve-all-form"
                            class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                        Approve All Submitted ({{ $stats['submitted'] }})
                    </button>
                @endif
                @if(($stats['pending'] + $stats['returned']) > 0)
                    <button type="submit" form="remind-all-form"
                            class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold shadow-sm transition">
                        Remind All Pending ({{ $stats['pending'] + $stats['returned'] }})
                    </button>
                @endif
            </div>
        @endif
    </form>
    @if($selectedPeriod && $stats['submitted'] > 0)
        <form id="approve-all-form" method="POST" action="{{ route('admin.submissions.approve-all', $selectedPeriod) }}"
              onsubmit="return confirm('Approve all {{ $stats['submitted'] }} submitted grade class(es) for {{ $selectedPeriod->name }}?')">
            @csrf
            @if(request('grade_level_id'))
                <input type="hidden" name="grade_level_id" value="{{ request('grade_level_id') }}">
            @endif
        </form>
    @endif
    @if($selectedPeriod && ($stats['pending'] + $stats['returned']) > 0)
        <form id="remind-all-form" method="POST" action="{{ route('admin.submissions.remind-all', $selectedPeriod) }}"
              onsubmit="return confirm('Send a grade submission reminder notification to all teachers with pending or returned {{ $selectedPeriod->name }} classes?')">
            @csrf
        </form>
    @endif

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Total Classes</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-emerald-700">Approved</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $stats['approved'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-school-700">Submitted</p>
            <p class="text-2xl font-bold text-school-700 mt-1">{{ $stats['submitted'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-amber-700">Pending</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-rose-700">Returned</p>
            <p class="text-2xl font-bold text-rose-700 mt-1">{{ $stats['returned'] }}</p>
        </div>
    </div>

    {{-- Submissions Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Section</th>
                    <th class="px-5 py-3">Subject</th>
                    <th class="px-5 py-3">Assigned Teacher</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Submitted At</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($rows as $row)
                    @php
                        $ta = $row['assignment'];
                        $sub = $row['submission'];
                    @endphp
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-900">
                            {{ $ta->section->gradeLevel->name }} – {{ $ta->section->name }}
                            <p class="text-xs text-slate-400">Adviser: {{ $ta->section->adviser?->full_name ?? 'Unassigned' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-slate-900">{{ $ta->subject->name }}</p>
                            <p class="text-xs font-mono text-slate-400">{{ $ta->subject->code }}</p>
                        </td>
                        <td class="px-5 py-3 text-slate-700">
                            {{ $ta->teacher->full_name }}
                            @if(! empty($row['prior_teacher_name']))
                                <p class="text-[11px] text-amber-700 font-medium">Submitted by prior teacher: {{ $row['prior_teacher_name'] }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if($row['status'] === 'approved')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Approved</span>
                            @elseif($row['status'] === 'submitted')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-school-100 text-school-800">Submitted</span>
                            @elseif($row['status'] === 'returned')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">Returned</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Pending</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $sub?->submitted_at?->format('M d, Y h:i A') ?? '—' }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap space-x-2">
                            @if($sub)
                                <a href="{{ route('admin.submissions.show', $sub) }}" class="text-xs font-semibold text-school-600 hover:underline">Inspect / Override</a>
                                @if($sub->status === 'submitted')
                                    <form method="POST" action="{{ route('admin.submissions.approve', $sub) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">Approve</button>
                                    </form>
                                @endif
                                @if(in_array($sub->status, ['submitted', 'approved'], true))
                                    <button type="button"
                                            @click="returnModal = true; returnUrl = '{{ route('admin.submissions.return', $sub) }}'; returnTitle = '{{ $ta->subject->name }} ({{ $ta->section->name }})'"
                                            class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">
                                        Return
                                    </button>
                                @endif
                            @else
                                @if($selectedPeriod)
                                    <form method="POST" action="{{ route('admin.submissions.remind', [$ta, $selectedPeriod]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded bg-amber-100 hover:bg-amber-200 text-amber-900 text-xs font-semibold">
                                            Remind Teacher
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">Awaiting teacher</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-slate-500">No teacher assignments found for the selected filter.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Return Submission Modal --}}
    <div x-show="returnModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div @click.away="returnModal = false" class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-base font-bold text-slate-900">Return Grades for Revision</h3>
            <p class="text-xs text-slate-600 mt-1">Returning <strong x-text="returnTitle"></strong> unlocks the teacher's gradebook and removes it from official report cards until re-submitted.</p>
            <form method="POST" :action="returnUrl" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Return <span class="text-rose-600">*</span></label>
                    <textarea name="reason" rows="3" required minlength="5" placeholder="e.g., Please verify missing exam score for Student 2026-0004" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="returnModal = false" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">Return Submission</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
