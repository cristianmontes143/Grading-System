@extends('layouts.app')

@section('title', 'Curricula & MATATAG Transition')
@section('header', 'Curriculum Management & Phased Implementation')
@section('subheader', 'Configure MATATAG K–10, Legacy K–12, Strengthened SHS, and Legacy SHS by Grade Level and School Year.')

@section('content')
<div class="space-y-6">
    {{-- Phased Implementation Matrix --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Phased Implementation Matrix (Curriculum per Grade Level)</h2>
                <p class="text-xs text-slate-500">Historical grades remain bound to the curriculum active when they were earned.</p>
            </div>
            <form method="GET" action="{{ route('admin.curricula.index') }}" class="flex items-center gap-2">
                <select name="school_year_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
                    @forelse($schoolYears as $sy)
                        <option value="{{ $sy->id }}" @selected($selectedSyId === $sy->id)>SY {{ $sy->name }} {{ $sy->is_active ? '(Active)' : '' }}</option>
                    @empty
                        <option value="">No School Years Yet</option>
                    @endforelse
                </select>
            </form>
        </div>

        @if($selectedSyId)
            <form method="POST" action="{{ route('admin.curricula.implementations.save') }}" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="school_year_id" value="{{ $selectedSyId }}">

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Grade Level</th>
                            <th class="px-4 py-2.5">Key Stage</th>
                            <th class="px-4 py-2.5">Assigned Curriculum Version</th>
                            <th class="px-4 py-2.5">Implementation Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach($gradeLevels as $gl)
                            @php $impl = $implementations->get($gl->id); @endphp
                            <tr class="{{ $gl->is_active ? '' : 'opacity-50 bg-slate-50' }}">
                                <td class="px-4 py-2.5 font-medium text-slate-900">
                                    {{ $gl->name }}
                                    @if(! $gl->is_active)
                                        <span class="text-xs text-slate-400">(Inactive)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-600">{{ $gl->key_stage }}</td>
                                <td class="px-4 py-2.5">
                                    <select name="mappings[{{ $gl->id }}][curriculum_id]" class="w-full rounded-lg border-slate-300 text-sm">
                                        <option value="">— Not Assigned —</option>
                                        @foreach($curricula as $curr)
                                            <option value="{{ $curr->id }}" @selected($impl?->curriculum_id === $curr->id)>
                                                {{ $curr->name }} (v{{ $curr->version }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2.5">
                                    <select name="mappings[{{ $gl->id }}][implementation_status]" class="rounded-lg border-slate-300 text-sm">
                                        <option value="active" @selected(($impl?->implementation_status ?? 'active') === 'active')>Active</option>
                                        <option value="pilot" @selected($impl?->implementation_status === 'pilot')>Pilot</option>
                                        <option value="phasing_out" @selected($impl?->implementation_status === 'phasing_out')>Phasing Out</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                        Save Curriculum Implementation Matrix
                    </button>
                </div>
            </form>
        @else
            <div class="p-6 text-sm text-slate-500">
                Create a School Year first in <a href="{{ route('admin.school-years.index') }}" class="text-school-600 font-semibold underline">School Years &amp; Periods</a> to configure grade-level curriculum implementations.
            </div>
        @endif
    </div>

    {{-- Curriculum Versions & SHS Tracks --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($curricula as $curr)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="font-mono text-xs text-school-700 font-semibold">{{ $curr->code }}</span>
                        <h3 class="text-base font-bold text-slate-900">{{ $curr->name }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Version {{ $curr->version }} &bull; {{ $curr->subjects_count }} Subject(s)
                            @if($curr->policyReference)
                                &bull; Ref: {{ $curr->policyReference->document_number }}
                            @endif
                        </p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize {{ $curr->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $curr->status }}
                    </span>
                </div>
                @if($curr->description)
                    <p class="text-xs text-slate-600">{{ $curr->description }}</p>
                @endif

                @if($curr->education_stage === 'senior_high')
                    <div class="pt-3 border-t border-slate-100 space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">SHS Tracks &amp; Strands / Elective Clusters</p>
                        @foreach($curr->shsTracks as $track)
                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 space-y-2">
                                <p class="text-xs font-bold text-slate-900">{{ $track->code }} — {{ $track->name }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($track->strands as $strand)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-white border border-slate-200 text-xs text-slate-700">
                                            {{ $strand->code }}: {{ $strand->name }}
                                        </span>
                                    @endforeach
                                </div>
                                <form method="POST" action="{{ route('admin.curricula.strands.store', $track) }}" class="flex flex-wrap gap-2 pt-1">
                                    @csrf
                                    <input type="text" name="code" placeholder="Code" required class="w-24 rounded border-slate-300 text-xs">
                                    <input type="text" name="name" placeholder="Add Strand or Cluster" required class="flex-1 rounded border-slate-300 text-xs">
                                    <select name="type" class="rounded border-slate-300 text-xs">
                                        <option value="elective_cluster">Elective Cluster</option>
                                        <option value="strand">Strand</option>
                                    </select>
                                    <button type="submit" class="px-2.5 py-1 rounded bg-slate-800 text-white text-xs font-semibold">Add</button>
                                </form>
                            </div>
                        @endforeach

                        <form method="POST" action="{{ route('admin.curricula.tracks.store', $curr) }}" class="flex gap-2">
                            @csrf
                            <input type="text" name="code" placeholder="Track Code" required class="w-28 rounded-lg border-slate-300 text-xs">
                            <input type="text" name="name" placeholder="New SHS Track Name" required class="flex-1 rounded-lg border-slate-300 text-xs">
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-school-600 text-white text-xs font-semibold">+ Track</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Create New Curriculum Version --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Add Custom Curriculum Version</h2>
        <form method="POST" action="{{ route('admin.curricula.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Code <span class="text-rose-600">*</span></label>
                <input type="text" name="code" required placeholder="e.g. MATATAG-K10-2027" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Curriculum Name <span class="text-rose-600">*</span></label>
                <input type="text" name="name" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Version <span class="text-rose-600">*</span></label>
                <input type="text" name="version" required value="1.0" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Education Stage</label>
                <select name="education_stage" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="k_to_10">K to 10</option>
                    <option value="kindergarten">Kindergarten</option>
                    <option value="elementary">Elementary (G1–G6)</option>
                    <option value="junior_high">Junior High School (G7–G10)</option>
                    <option value="senior_high">Senior High School (G11–G12)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">DepEd Policy Reference</label>
                <select name="policy_reference_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— Optional —</option>
                    @foreach($policies as $p)
                        <option value="{{ $p->id }}">{{ $p->document_number }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="active">Active</option>
                    <option value="transitioning">Transitioning</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="px-5 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">
                    Create Curriculum Version
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
