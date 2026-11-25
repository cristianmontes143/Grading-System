@extends('layouts.app')

@section('title', 'Learner Interventions & Follow-Ups')
@section('header', 'School-Wide Academic Interventions & Parent Conferences')
@section('subheader', 'Track open remediation plans, parent conferences, and overdue follow-ups across all advisory sections.')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="school-card p-4 border-amber-200/80 bg-amber-50/30">
            <p class="text-[11px] font-bold uppercase text-amber-800">Open Interventions</p>
            <p class="text-2xl font-extrabold text-amber-900 mt-1">{{ $counts['open'] }}</p>
        </div>
        <div class="school-card p-4 {{ $counts['overdue'] > 0 ? 'border-rose-200 bg-rose-50/40' : '' }}">
            <p class="text-[11px] font-bold uppercase {{ $counts['overdue'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">Overdue Follow-Ups</p>
            <p class="text-2xl font-extrabold {{ $counts['overdue'] > 0 ? 'text-rose-700' : 'text-slate-700' }} mt-1">{{ $counts['overdue'] }}</p>
        </div>
        <div class="school-card p-4 border-emerald-200/80 bg-emerald-50/30">
            <p class="text-[11px] font-bold uppercase text-emerald-700">Resolved Cases</p>
            <p class="text-2xl font-extrabold text-emerald-800 mt-1">{{ $counts['resolved'] }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.interventions.index') }}" class="school-card p-4 flex flex-wrap gap-3">
        <select name="status" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Statuses</option>
            <option value="open" @selected(request('status') === 'open')>Open Only</option>
            <option value="resolved" @selected(request('status') === 'resolved')>Resolved Only</option>
        </select>
        <select name="category" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Categories</option>
            @foreach($categories as $k => $lbl)
                <option value="{{ $k }}" @selected(request('category') === $k)>{{ $lbl }}</option>
            @endforeach
        </select>
        <select name="section_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Sections</option>
            @foreach($sections as $sec)
                <option value="{{ $sec->id }}" @selected(request('section_id') == $sec->id)>{{ $sec->gradeLevel->name }} &ndash; {{ $sec->name }}</option>
            @endforeach
        </select>
        @if(request()->hasAny(['status', 'category', 'section_id']))
            <a href="{{ route('admin.interventions.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold">Reset</a>
        @endif
    </form>

    <div class="school-card overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse($interventions as $item)
                @php
                    $isOverdue = $item->status === 'open' && $item->follow_up_date && $item->follow_up_date->isPast() && ! $item->follow_up_date->isToday();
                @endphp
                <div class="p-5 flex flex-col md:flex-row md:items-start justify-between gap-4 {{ $isOverdue ? 'bg-rose-50/40' : ($item->status === 'resolved' ? 'bg-slate-50/70' : 'bg-white') }}">
                    <div class="space-y-1.5 flex-1 text-xs">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('adviser.students.summary', $item->enrollment) }}" class="text-sm font-bold text-school-700 hover:underline">
                                {{ $item->enrollment->student->formal_name }}
                            </a>
                            <span class="text-slate-400">&bull;</span>
                            <span class="font-semibold text-slate-600">{{ $item->enrollment->section->gradeLevel->name }} &ndash; {{ $item->enrollment->section->name }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-school-100 text-school-800">{{ $categories[$item->category] ?? $item->category }}</span>
                            @if($item->subject)
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[10px]">{{ $item->subject->code }}</span>
                            @endif
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $item->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : ($isOverdue ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-900') }}">
                                {{ $isOverdue ? 'Overdue' : $item->status }}
                            </span>
                        </div>
                        <p class="text-slate-800 leading-relaxed">{{ $item->notes }}</p>
                        @if($item->action_plan)
                            <p class="text-slate-600"><strong class="text-slate-700">Action Plan:</strong> {{ $item->action_plan }}</p>
                        @endif
                        <p class="text-[11px] text-slate-400">
                            Logged by {{ $item->recorder?->name ?? 'Faculty' }} on {{ $item->created_at->format('M d, Y') }}
                            @if($item->follow_up_date) &bull; Follow-up: <strong class="{{ $isOverdue ? 'text-rose-700' : 'text-slate-600' }}">{{ $item->follow_up_date->format('M d, Y') }}</strong> @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('adviser.students.summary', $item->enrollment) }}" class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold">
                            View Learner
                        </a>
                        <form method="POST" action="{{ route('adviser.students.interventions.toggle', [$item->enrollment, $item]) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $item->status === 'open' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700' }}">
                                {{ $item->status === 'open' ? 'Mark Resolved' : 'Reopen' }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">No intervention logs match the selected filters.</div>
            @endforelse
        </div>
        @if($interventions->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">{{ $interventions->links() }}</div>
        @endif
    </div>
</div>
@endsection
