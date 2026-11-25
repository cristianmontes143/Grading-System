@extends('layouts.app')

@section('title', 'School-Year Readiness & Compliance')
@section('header', 'School-Year Readiness & DepEd Compliance Checklist')
@section('subheader', 'Automated audit of configuration, curriculum mappings, teacher loads, grading policies, and backups.')

@section('content')
<div class="space-y-6 max-w-5xl">
    {{-- Score Hero Card --}}
    <div class="school-card p-6 flex flex-col sm:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            @php
                $circ = 2 * 3.14159 * 36;
                $offset = $circ * (1 - ($scorePercent / 100));
            @endphp
            <div class="relative w-24 h-24 shrink-0">
                <svg class="w-24 h-24 -rotate-90" viewBox="0 0 84 84">
                    <circle cx="42" cy="42" r="36" fill="none" stroke="#e2e8f0" stroke-width="8"/>
                    <circle cx="42" cy="42" r="36" fill="none"
                            stroke="{{ $scorePercent === 100 ? '#10b981' : ($scorePercent >= 70 ? '#1d4ed8' : '#f59e0b') }}"
                            stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-xl font-extrabold text-slate-900">{{ $scorePercent }}%</span>
                </div>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-school-700">
                    {{ $activeSy ? 'Active SY ' . $activeSy->name : 'No Active School Year' }}
                </p>
                <h2 class="text-xl font-extrabold text-slate-900 mt-0.5">
                    {{ $passedCount }} of {{ $totalChecks }} Readiness Checks Passed
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $scorePercent === 100 ? 'Your school system is 100% configured and ready for full grading operations.' : 'Resolve the highlighted items below to ensure smooth grading and SF9 report card generation.' }}
                </p>
            </div>
        </div>
        <a href="{{ route('admin.readiness.index') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold shrink-0">
            Re-Run Checks
        </a>
    </div>

    {{-- Checklist Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($checks as $check)
            <div class="school-card p-5 flex flex-col justify-between gap-4 {{ $check['passed'] ? 'border-emerald-200/80' : 'border-amber-200 bg-amber-50/20' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            @if($check['passed'])
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 font-bold text-xs">&check;</span>
                            @else
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 text-amber-800 font-bold text-xs">!</span>
                            @endif
                            <h3 class="text-sm font-bold text-slate-900">{{ $check['title'] }}</h3>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed pl-7">{{ $check['detail'] }}</p>
                    </div>
                    <span class="shrink-0 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $check['passed'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                        {{ $check['passed'] ? 'Ready' : 'Action Needed' }}
                    </span>
                </div>
                <div class="pl-7 flex justify-end">
                    <a href="{{ $check['action_url'] }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $check['passed'] ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-school-600 hover:bg-school-700 text-white' }}">
                        {{ $check['passed'] ? 'Review ' . $check['action_label'] : 'Fix in ' . $check['action_label'] . ' →' }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
