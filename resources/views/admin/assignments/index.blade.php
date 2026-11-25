@extends('layouts.app')

@section('title', 'Teacher & Subject Assignments')
@section('header', 'Flexible Teacher & Subject Assignment Matrix')
@section('subheader', 'Assign section advisers and subject teachers. A teacher can advise one section while teaching subjects across multiple sections.')

@section('content')
<div class="space-y-6">
    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.assignments.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap gap-3 items-center">
        <select name="school_year_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            @forelse($schoolYears as $sy)
                <option value="{{ $sy->id }}" @selected($selectedSyId === $sy->id)>SY {{ $sy->name }} {{ $sy->is_active ? '(Active)' : '' }}</option>
            @empty
                <option value="">No School Years Yet</option>
            @endforelse
        </select>
        <select name="grade_level_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Grade Levels</option>
            @foreach($gradeLevels as $gl)
                <option value="{{ $gl->id }}" @selected(request('grade_level_id') == $gl->id)>{{ $gl->name }}</option>
            @endforeach
        </select>
        <span class="text-xs text-slate-500 ml-auto">{{ $teachers->count() }} active teacher(s) available</span>
    </form>

    @if($schoolYears->count() >= 2)
        <div class="bg-white rounded-xl border border-school-200 shadow-sm p-5">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-slate-900">Quick Setup: Copy Advisers &amp; Subject Loads From Previous School Year</h2>
                <p class="text-xs text-slate-500">Matches sections by Grade Level &amp; Name and copies active Advisers and curriculum-compatible Subject Teachers into unassigned slots.</p>
            </div>
            <form method="POST" action="{{ route('admin.assignments.clone') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end"
                  onsubmit="return confirm('Copy unassigned Advisers and compatible Subject Teacher loads from the selected source School Year?')">
                @csrf
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Copy From (Source SY)</label>
                    <select name="source_school_year_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($schoolYears as $sy)
                            <option value="{{ $sy->id }}">SY {{ $sy->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Copy Into (Target SY)</label>
                    <select name="target_school_year_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($schoolYears as $sy)
                            <option value="{{ $sy->id }}" @selected($selectedSyId === $sy->id)>SY {{ $sy->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                        Copy Loads &rarr;
                    </button>
                </div>
            </form>
        </div>
    @endif

    @forelse($sectionMatrices as $matrix)
        @php
            $section = $matrix['section'];
            $curriculum = $matrix['curriculum'];
            $offerings = $matrix['offerings'];
            $assignmentsBySubject = $matrix['assignmentsBySubject'];
        @endphp
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            {{-- Section Header & Adviser Assignment --}}
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        {{ $section->gradeLevel->name }} – {{ $section->name }}
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Curriculum:
                        @if($curriculum)
                            <span class="font-semibold text-school-700">{{ $curriculum->name }} (v{{ $curriculum->version }})</span>
                        @else
                            <span class="font-semibold text-amber-700">Not mapped in Curriculum Phased Implementation Matrix</span>
                        @endif
                        @if($section->shsTrack) &bull; {{ $section->shsTrack->name }} @endif
                        @if($section->shsStrand) ({{ $section->shsStrand->code }}) @endif
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.assignments.adviser', $section) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <label class="text-xs font-semibold text-slate-600">Adviser:</label>
                    <select name="adviser_id" class="rounded-lg border-slate-300 text-sm min-w-[220px]">
                        <option value="">— Unassigned —</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" @selected($section->adviser_id === $t->id)>
                                {{ $t->formal_name }} ({{ $t->employee_number }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3.5 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                        Save Adviser
                    </button>
                </form>
            </div>

            {{-- Curriculum Subjects & Subject Teacher Assignments --}}
            <div class="p-6">
                @if(! $curriculum)
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900">
                        Assign a curriculum to <strong>{{ $section->gradeLevel->name }}</strong> in
                        <a href="{{ route('admin.curricula.index', ['school_year_id' => $section->school_year_id]) }}" class="underline font-semibold">Curricula &amp; MATATAG</a>
                        before assigning subject teachers to this section.
                    </div>
                @elseif($offerings->isEmpty())
                    <p class="text-sm text-slate-500">No active subjects found for {{ $curriculum->name }} ({{ $section->gradeLevel->name }}). Add subjects in <a href="{{ route('admin.subjects.index') }}" class="text-school-600 underline">Subjects</a>.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Code</th>
                                <th class="px-4 py-2.5">Subject / Learning Area</th>
                                <th class="px-4 py-2.5">Assigned Subject Teacher</th>
                                <th class="px-4 py-2.5">Schedule (Optional)</th>
                                <th class="px-4 py-2.5 text-right">Action</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @foreach($offerings as $subject)
                                @php $current = $assignmentsBySubject->get($subject->id); @endphp
                                <tr>
                                    <td class="px-4 py-2.5 font-mono text-xs font-semibold text-slate-700">{{ $subject->code }}</td>
                                    <td class="px-4 py-2.5">
                                        <p class="font-medium text-slate-900">{{ $subject->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $subject->weight_group }}</p>
                                    </td>
                                    <td class="px-4 py-2.5" colspan="3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <form method="POST" action="{{ route('admin.assignments.subject', $section) }}" class="flex-1 flex flex-wrap items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                                <select name="teacher_id" required class="flex-1 min-w-[200px] rounded-lg border-slate-300 text-xs">
                                                    <option value="">— Select Teacher —</option>
                                                    @foreach($teachers as $t)
                                                        <option value="{{ $t->id }}" @selected($current?->teacher_id === $t->id)>
                                                            {{ $t->formal_name }} @if($section->adviser_id === $t->id) [Section Adviser] @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="schedule_summary" value="{{ $current?->schedule_summary }}"
                                                       placeholder="e.g. MWF 8:00-9:00"
                                                       class="w-40 rounded-lg border-slate-300 text-xs">
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-school-700 text-white text-xs font-semibold">
                                                    {{ $current ? 'Update' : 'Assign' }}
                                                </button>
                                            </form>
                                            @if($current)
                                                <form method="POST" action="{{ route('admin.assignments.deactivate', $current) }}"
                                                      onsubmit="return confirm('Remove this subject assignment?')">
                                                    @csrf
                                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold">
                                                        Remove
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-500">
            No active sections found for the selected school year. Create sections in <a href="{{ route('admin.sections.index') }}" class="text-school-600 font-semibold underline">Sections</a>.
        </div>
    @endforelse
</div>
@endsection
