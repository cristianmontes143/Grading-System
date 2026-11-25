@extends('layouts.app')

@section('title', 'Subjects & Learning Areas')
@section('header', 'DepEd Learning Areas & Subjects')
@section('subheader', 'Subjects are strictly scoped to their Curriculum Version and Grade Level.')

@section('content')
<div class="space-y-6">
    {{-- Add Subject Form --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Add Learning Area / Subject</h2>
        <form method="POST" action="{{ route('admin.subjects.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Curriculum <span class="text-rose-600">*</span></label>
                <select name="curriculum_id" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($curricula as $c)
                        <option value="{{ $c->id }}" @selected(request('curriculum_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Grade Level <span class="text-rose-600">*</span></label>
                <select name="grade_level_id" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($gradeLevels as $gl)
                        <option value="{{ $gl->id }}" @selected(request('grade_level_id') == $gl->id)>{{ $gl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Subject Code <span class="text-rose-600">*</span></label>
                <input type="text" name="code" required placeholder="e.g. PROG12" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Subject Name <span class="text-rose-600">*</span></label>
                <input type="text" name="name" required placeholder="e.g. Computer Programming" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                <select name="subject_category" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="learning_area">Learning Area (K–10)</option>
                    <option value="core">SHS Core Subject</option>
                    <option value="applied">SHS Applied Subject</option>
                    <option value="specialized">SHS Specialized Subject</option>
                    <option value="elective">SHS Elective</option>
                    <option value="doorway">SHS Doorway Option</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Grading Weight Group</label>
                <select name="weight_group" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="languages_ap_gmrc">Languages / AP / GMRC / VE</option>
                    <option value="math_science">Mathematics &amp; Science</option>
                    <option value="mapeh_epp_tle">MAPEH / EPP / TLE</option>
                    <option value="descriptive_ks1">Key Stage 1 Descriptive</option>
                    <option value="shs_core">SHS Core / Academic Elective</option>
                    <option value="shs_techpro">SHS TechPro / TVL</option>
                    <option value="shs_research">SHS Research / Innovation</option>
                    <option value="shs_immersion">SHS Work Immersion</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">SHS Track (Optional)</label>
                <select name="shs_track_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— All / Not SHS Track-Specific —</option>
                    @foreach($tracks as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                    + Add Subject
                </button>
            </div>
        </form>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.subjects.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap gap-3">
        <select name="curriculum_id" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Curricula</option>
            @foreach($curricula as $c)
                <option value="{{ $c->id }}" @selected(request('curriculum_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="grade_level_id" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Grade Levels</option>
            @foreach($gradeLevels as $gl)
                <option value="{{ $gl->id }}" @selected(request('grade_level_id') == $gl->id)>{{ $gl->name }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search code or subject name..."
               class="flex-1 min-w-[200px] rounded-lg border-slate-300 text-sm">
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold">Filter</button>
        @if(request()->hasAny(['curriculum_id', 'grade_level_id', 'search']))
            <a href="{{ route('admin.subjects.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium">Reset</a>
        @endif
    </form>

    {{-- Subjects Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Subject / Learning Area</th>
                    <th class="px-5 py-3">Grade</th>
                    <th class="px-5 py-3">Curriculum</th>
                    <th class="px-5 py-3">Weight Group</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($subjects as $subject)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-700">{{ $subject->code }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-slate-900">
                                @if($subject->parent)
                                    <span class="text-slate-400">&lfloor;</span>
                                @endif
                                {{ $subject->name }}
                            </p>
                            <p class="text-xs text-slate-500 capitalize">
                                {{ str_replace('_', ' ', $subject->subject_category) }}
                                @if($subject->shsTrack) &bull; {{ $subject->shsTrack->name }} @endif
                            </p>
                        </td>
                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $subject->gradeLevel->name }}</td>
                        <td class="px-5 py-3 text-xs text-slate-600">{{ $subject->curriculum->code }}</td>
                        <td class="px-5 py-3 text-xs font-mono text-slate-600">{{ $subject->weight_group }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $subject->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $subject->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.subjects.toggle', $subject) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-school-600 hover:underline">
                                    {{ $subject->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-slate-500">No subjects match your filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($subjects->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">{{ $subjects->links() }}</div>
        @endif
    </div>
</div>
@endsection
