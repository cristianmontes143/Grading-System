@extends('layouts.app')

@section('title', 'Grade Levels (K–12)')
@section('header', 'Grade Levels (Kindergarten to Grade 12)')
@section('subheader', 'Activate only the grade levels offered by your school.')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-6 py-3">Code</th>
                <th class="px-6 py-3">Grade Level</th>
                <th class="px-6 py-3">Key Stage</th>
                <th class="px-6 py-3">Education Level</th>
                <th class="px-6 py-3">Subjects</th>
                <th class="px-6 py-3">Sections</th>
                <th class="px-6 py-3">Status</th>
                <th class="px-6 py-3 text-right">Action</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
            @foreach($gradeLevels as $gl)
                <tr>
                    <td class="px-6 py-3 font-mono text-xs font-semibold text-slate-700">{{ $gl->code }}</td>
                    <td class="px-6 py-3 font-medium text-slate-900">{{ $gl->name }}</td>
                    <td class="px-6 py-3 text-slate-600">{{ $gl->key_stage }}</td>
                    <td class="px-6 py-3 text-slate-600 capitalize">{{ str_replace('_', ' ', $gl->education_level) }}</td>
                    <td class="px-6 py-3 text-slate-600">{{ $gl->subjects_count }}</td>
                    <td class="px-6 py-3 text-slate-600">{{ $gl->sections_count }}</td>
                    <td class="px-6 py-3">
                        @if($gl->is_active)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Active</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">Inactive</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right">
                        <form method="POST" action="{{ route('admin.grade-levels.toggle', $gl) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold {{ $gl->is_active ? 'bg-slate-100 hover:bg-rose-100 text-slate-700 hover:text-rose-700' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}">
                                {{ $gl->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
