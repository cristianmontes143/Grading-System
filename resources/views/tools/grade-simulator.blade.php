@extends('layouts.app')

@section('title', 'DepEd Grade Calculator & What-If Simulator')
@section('header', 'DepEd Grade Calculator & What-If Simulator')
@section('subheader', 'Preview exact PS%, WS%, Initial Grade, and Transmuted/Zero-Based ratings using live school policies — zero impact on real records.')

@section('content')
<div class="space-y-6 max-w-5xl">
    {{-- Policy Picker --}}
    <form method="GET" action="{{ route('tools.grade-simulator') }}" class="school-card p-5 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[260px]">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Select Active Grading Policy</label>
            <select name="config_id" onchange="this.form.submit()" class="w-full rounded-lg border-slate-300 text-sm">
                @forelse($configs as $cfg)
                    <option value="{{ $cfg->id }}" @selected($selectedConfig?->id === $cfg->id)>
                        {{ $cfg->name }} ({{ str_replace('_', ' ', $cfg->grading_mode) }})
                    </option>
                @empty
                    <option value="">No numeric grading configurations found</option>
                @endforelse
            </select>
        </div>
        @if($selectedConfig)
            <div class="text-xs text-slate-500">
                <p>Mode: <strong class="capitalize text-slate-800">{{ str_replace('_', ' ', $selectedConfig->grading_mode) }}</strong></p>
                <p>Passing Grade: <strong class="text-slate-800">{{ number_format($selectedConfig->passing_grade, 0) }}</strong>
                    @if($selectedConfig->transmutationTable) &bull; Table: <strong>{{ $selectedConfig->transmutationTable->name }}</strong> @endif
                </p>
            </div>
        @endif
    </form>

    @if($selectedConfig)
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Input Form --}}
            <form method="POST" action="{{ route('tools.grade-simulator.compute') }}" class="lg:col-span-7 school-card overflow-hidden">
                @csrf
                <input type="hidden" name="config_id" value="{{ $selectedConfig->id }}">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-base font-bold text-slate-900">Enter Sample Component Scores</h2>
                    <p class="text-xs text-slate-500">Enter Total Raw Score and Highest Possible Score (HPS) for each component.</p>
                </div>

                <div class="p-6 space-y-4">
                    @foreach($selectedConfig->topLevelComponents as $comp)
                        @if($comp->hasChildren())
                            <div class="rounded-xl border border-slate-200 p-4 space-y-3 bg-slate-50/40">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-900">{{ $comp->code }} &mdash; {{ $comp->name }}</span>
                                    <span class="px-2 py-0.5 rounded-full bg-school-100 text-school-800 font-bold">{{ number_format($comp->weight_percentage, 0) }}%</span>
                                </div>
                                @foreach($comp->children as $child)
                                    @php
                                        $rawVal = $submittedInputs[$child->id]['raw'] ?? 40;
                                        $maxVal = $submittedInputs[$child->id]['max'] ?? 50;
                                    @endphp
                                    <div class="grid grid-cols-12 gap-2 items-center text-xs bg-white p-2.5 rounded-lg border border-slate-200/80">
                                        <div class="col-span-6">
                                            <p class="font-semibold text-slate-800">{{ $child->code }} &ndash; {{ $child->name }}</p>
                                            <p class="text-[10px] text-slate-400">{{ number_format($child->weight_percentage, 0) }}% of {{ $comp->code }}</p>
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-[10px] text-slate-400">Raw Score</label>
                                            <input type="number" step="0.5" min="0" name="raw[{{ $child->id }}]" value="{{ $rawVal }}" class="w-full rounded border-slate-300 text-xs py-1">
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-[10px] text-slate-400">HPS (Max)</label>
                                            <input type="number" step="0.5" min="1" name="max[{{ $child->id }}]" value="{{ $maxVal }}" class="w-full rounded border-slate-300 text-xs py-1">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            @php
                                $rawVal = $submittedInputs[$comp->id]['raw'] ?? 42;
                                $maxVal = $submittedInputs[$comp->id]['max'] ?? 50;
                            @endphp
                            <div class="grid grid-cols-12 gap-3 items-center p-4 rounded-xl border border-slate-200 bg-white text-xs">
                                <div class="col-span-6">
                                    <span class="font-bold text-school-700">{{ $comp->code }}</span>
                                    <span class="font-semibold text-slate-900 ml-1">{{ $comp->name }}</span>
                                    <p class="text-[10px] text-slate-400">Weight: {{ number_format($comp->weight_percentage, 0) }}%</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] text-slate-400">Raw Score</label>
                                    <input type="number" step="0.5" min="0" name="raw[{{ $comp->id }}]" value="{{ $rawVal }}" class="w-full rounded border-slate-300 text-xs py-1">
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] text-slate-400">HPS (Max)</label>
                                    <input type="number" step="0.5" min="1" name="max[{{ $comp->id }}]" value="{{ $maxVal }}" class="w-full rounded border-slate-300 text-xs py-1">
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button type="submit" class="school-btn-primary text-xs">
                        Compute Simulated Grade &rarr;
                    </button>
                </div>
            </form>

            {{-- Live Result Panel --}}
            <div class="lg:col-span-5 space-y-4">
                @if($result && $result['has_scores'])
                    <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-school-900 to-school-700 text-white p-6 shadow-md space-y-4">
                        <p class="text-[11px] font-bold uppercase tracking-widest text-amber-300">Simulated Official Result</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3.5 rounded-xl bg-white/10 border border-white/15">
                                <p class="text-[10px] uppercase text-school-200 font-semibold">Initial Grade</p>
                                <p class="text-2xl font-extrabold font-mono mt-0.5">{{ number_format((float) $result['initial_grade'], 2) }}</p>
                            </div>
                            <div class="p-3.5 rounded-xl bg-white/15 border border-amber-300/40">
                                <p class="text-[10px] uppercase text-amber-200 font-semibold">Final Term Grade</p>
                                <p class="text-3xl font-extrabold mt-0.5">{{ number_format((float) $result['final_grade'], 0) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs pt-2 border-t border-white/15">
                            <span class="text-school-200">DepEd Descriptor</span>
                            <span class="px-2.5 py-0.5 rounded-full font-bold {{ (float) $result['final_grade'] >= $selectedConfig->passing_grade ? 'bg-emerald-400/25 text-emerald-200' : 'bg-rose-400/25 text-rose-200' }}">
                                {{ $result['remarks'] }}
                            </span>
                        </div>
                    </div>

                    <div class="school-card p-5 space-y-2.5 text-xs">
                        <h3 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">Weighted Component Breakdown</h3>
                        @foreach($result['component_breakdown'] as $cb)
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800">{{ $cb['code'] }} ({{ number_format($cb['weight_percentage'], 0) }}%)</span>
                                    <span class="font-mono font-bold text-school-800">PS: {{ $cb['ps'] !== null ? $cb['ps'] . '%' : '—' }} &bull; WS: {{ $cb['ws'] }}</span>
                                </div>
                                @if(! empty($cb['children']))
                                    <div class="pl-3 pt-1 border-t border-slate-200/70 space-y-0.5 text-[11px] text-slate-600">
                                        @foreach($cb['children'] as $ch)
                                            <div class="flex justify-between">
                                                <span>{{ $ch['code'] }} ({{ $ch['total_raw'] }}/{{ $ch['hps'] }})</span>
                                                <span class="font-mono">{{ $ch['ps'] !== null ? $ch['ps'] . '%' : '—' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="school-card p-8 text-center space-y-2">
                        <p class="text-sm font-bold text-slate-800">Ready to Simulate</p>
                        <p class="text-xs text-slate-500">Enter sample raw &amp; max scores on the left and click <strong>Compute Simulated Grade</strong> to see the exact DepEd weighted score and transmutation output.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
