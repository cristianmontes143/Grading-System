@extends('layouts.app')

@section('title', 'Sections')
@section('header', 'Class Sections Management')
@section('subheader', 'Create and organize sections by School Year, Grade Level, and SHS Track/Cluster.')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Create Section</h2>
        @if($schoolYears->isEmpty())
            <p class="text-sm text-amber-800">Please create a School Year in <a href="{{ route('admin.school-years.index') }}" class="underline font-semibold">School Years &amp; Periods</a> first.</p>
        @else
            <form method="POST" action="{{ route('admin.sections.store') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">School Year <span class="text-rose-600">*</span></label>
                    <select name="school_year_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($schoolYears as $sy)
                            <option value="{{ $sy->id }}" @selected($selectedSyId === $sy->id)>SY {{ $sy->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Grade Level <span class="text-rose-600">*</span></label>
                    <select name="grade_level_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($gradeLevels as $gl)
                            <option value="{{ $gl->id }}">{{ $gl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Section Name <span class="text-rose-600">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. Section A" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Room</label>
                    <input type="text" name="room" placeholder="e.g. Room 201" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Capacity</label>
                    <input type="number" name="capacity" min="1" max="200" value="45" required class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                        + Create Section
                    </button>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">SHS Track (for Grade 11–12 only)</label>
                    <select name="shs_track_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">— Not Applicable (K–10) —</option>
                        @foreach($tracks as $track)
                            <option value="{{ $track->id }}">{{ $track->curriculum->code }}: {{ $track->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">SHS Strand / Elective Cluster (Optional)</label>
                    <select name="shs_strand_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">— Optional —</option>
                        @foreach($tracks as $track)
                            @foreach($track->strands as $strand)
                                <option value="{{ $strand->id }}">{{ $track->code }} – {{ $strand->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </form>
        @endif
    </div>

    @if($schoolYears->count() >= 2)
        <div class="bg-white rounded-xl border border-school-200 shadow-sm p-5">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-slate-900">Quick Setup: Copy Sections From Previous School Year</h2>
                <p class="text-xs text-slate-500">Clone all active sections (Grade Level, Name, Room, Capacity, SHS Track/Strand) into a new School Year while skipping duplicates.</p>
            </div>
            <form method="POST" action="{{ route('admin.sections.clone') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end"
                  onsubmit="return confirm('Copy all active sections from the selected source School Year into the target School Year?')">
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
                        Copy Sections &rarr;
                    </button>
                </div>
            </form>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.sections.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap gap-3">
        <select name="school_year_id" class="rounded-lg border-slate-300 text-sm">
            @foreach($schoolYears as $sy)
                <option value="{{ $sy->id }}" @selected($selectedSyId === $sy->id)>SY {{ $sy->name }}</option>
            @endforeach
        </select>
        <select name="grade_level_id" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Grade Levels</option>
            @foreach($gradeLevels as $gl)
                <option value="{{ $gl->id }}" @selected(request('grade_level_id') == $gl->id)>{{ $gl->name }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search section or room..." class="flex-1 min-w-[180px] rounded-lg border-slate-300 text-sm">
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold">Filter</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">School Year</th>
                    <th class="px-5 py-3">Grade Level</th>
                    <th class="px-5 py-3">Section</th>
                    <th class="px-5 py-3">Adviser</th>
                    <th class="px-5 py-3">SHS Track / Cluster</th>
                    <th class="px-5 py-3">Enrolled / Cap</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Grades &amp; Reports</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($sections as $section)
                    <tr>
                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $section->schoolYear->name }}</td>
                        <td class="px-5 py-3 font-medium text-slate-900 whitespace-nowrap">{{ $section->gradeLevel->name }}</td>
                        <td class="px-5 py-3 font-semibold text-school-700">{{ $section->name }}</td>
                        <td class="px-5 py-3 text-xs text-slate-700">{{ $section->adviser?->full_name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-600">
                            @if($section->shsTrack)
                                {{ $section->shsTrack->name }}
                                @if($section->shsStrand)
                                    <span class="block text-slate-400">{{ $section->shsStrand->name }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $section->current_enrollments_count }} / {{ $section->capacity }} <span class="text-xs text-slate-400">({{ $section->room ?? 'TBA' }})</span></td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $section->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $section->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('adviser.sections.show', $section) }}" class="text-xs font-semibold text-school-600 hover:underline">
                                View Grades &amp; Cards &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-8 text-center text-slate-500">No sections found for this school year.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($sections->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">{{ $sections->links() }}</div>
        @endif
    </div>
</div>
@endsection
