@extends('layouts.app')

@section('title', 'Grading Configurations')
@section('header', 'Configurable Grading Policies & Component Weights')
@section('subheader', 'No grading weights are hardcoded. Review, customize, and confirm grading rules per curriculum, grade level, or subject.')

@section('content')
<div class="space-y-6" x-data="{
    mode: 'numerical_transmuted',
    components: [
        { code: 'WW', name: 'Written Work', weight_percentage: 30 },
        { code: 'PT', name: 'Performance Tasks', weight_percentage: 50 },
        { code: 'QA', name: 'Quarterly Assessment', weight_percentage: 20 }
    ],
    get totalWeight() {
        return this.components.reduce((sum, c) => sum + (parseFloat(c.weight_percentage) || 0), 0);
    },
    addComponent() {
        this.components.push({ code: '', name: '', weight_percentage: 0 });
    },
    removeComponent(idx) {
        if (this.components.length > 1) this.components.splice(idx, 1);
    }
}">
    {{-- Create Custom Grading Configuration --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Create Custom Grading Configuration</h2>
        <form method="POST" action="{{ route('admin.grading-policies.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Configuration Name <span class="text-rose-600">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. Grade 12 Programming Custom Policy (20/60/20)" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Grading Mode <span class="text-rose-600">*</span></label>
                    <select name="grading_mode" x-model="mode" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="numerical_transmuted">Numerical + Transmutation Table</option>
                        <option value="numerical_zero_based">Numerical Zero-Based (DO 015 SY 2027-28+)</option>
                        <option value="descriptive">Descriptive Non-Numeric (Key Stage 1)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Curriculum <span class="text-rose-600">*</span></label>
                    <select name="curriculum_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($curricula as $curr)
                            <option value="{{ $curr->id }}">{{ $curr->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">School Year Scope (Optional)</label>
                    <select name="school_year_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All School Years (Template)</option>
                        @foreach($schoolYears as $sy)
                            <option value="{{ $sy->id }}">SY {{ $sy->name }} Only</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Grade Level Scope (Optional)</label>
                    <select name="grade_level_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">All Applicable Grades</option>
                        @foreach($gradeLevels as $gl)
                            <option value="{{ $gl->id }}">{{ $gl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Weight Group (Optional)</label>
                    <select name="weight_group" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">— Any / Specific Subject —</option>
                        <option value="languages_ap_gmrc">Languages / AP / GMRC / VE</option>
                        <option value="math_science">Mathematics &amp; Science</option>
                        <option value="mapeh_epp_tle">MAPEH / EPP / TLE</option>
                        <option value="shs_core">SHS Core / Academic</option>
                        <option value="shs_techpro">SHS TechPro / TVL</option>
                        <option value="shs_research">SHS Research / Innovation</option>
                        <option value="shs_immersion">SHS Work Immersion</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Specific Subject Override (Optional)</label>
                    <select name="subject_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">— None (Apply to Group/Grade) —</option>
                        @foreach($subjects as $sub)
                            <option value="{{ $sub->id }}">{{ $sub->code }} – {{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="mode === 'numerical_transmuted'">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Transmutation Table</label>
                    <select name="transmutation_table_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">— Select Table —</option>
                        @foreach($transmutationTables as $tbl)
                            <option value="{{ $tbl->id }}">{{ $tbl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Passing Grade</label>
                    <input type="number" step="0.01" name="passing_grade" value="75" required class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Rounding Decimal Places</label>
                    <select name="rounding_precision" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="0">Whole Number (DepEd Standard)</option>
                        <option value="1">1 Decimal Place</option>
                        <option value="2">2 Decimal Places</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Policy Reference</label>
                    <select name="policy_reference_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">— Optional —</option>
                        @foreach($policies as $p)
                            <option value="{{ $p->id }}">{{ $p->document_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Dynamic Weighted Components --}}
            <div x-show="mode !== 'descriptive'" class="pt-4 border-t border-slate-100 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Weighted Assessment Components</h3>
                        <p class="text-xs text-slate-500">Component percentages must sum to exactly 100.00%.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                              :class="Math.abs(totalWeight - 100) < 0.01 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                              x-text="'Total: ' + totalWeight.toFixed(2) + '%'"></span>
                        <button type="button" @click="addComponent()" class="px-3 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold">
                            + Add Component
                        </button>
                    </div>
                </div>

                <template x-for="(comp, idx) in components" :key="idx">
                    <div class="grid grid-cols-12 gap-3 items-center">
                        <div class="col-span-3 sm:col-span-2">
                            <input type="text" :name="'components[' + idx + '][code]'" x-model="comp.code" placeholder="WW" required class="w-full rounded-lg border-slate-300 text-xs uppercase">
                        </div>
                        <div class="col-span-5 sm:col-span-6">
                            <input type="text" :name="'components[' + idx + '][name]'" x-model="comp.name" placeholder="Component Name" required class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div class="col-span-3">
                            <input type="number" step="0.01" min="0.01" max="100" :name="'components[' + idx + '][weight_percentage]'" x-model="comp.weight_percentage" required class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div class="col-span-1 text-right">
                            <button type="button" @click="removeComponent(idx)" class="text-rose-600 hover:text-rose-800 text-xs font-bold">&times;</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                    Save Grading Configuration
                </button>
            </div>
        </form>
    </div>

    {{-- Filter by Curriculum --}}
    <form method="GET" action="{{ route('admin.grading-policies.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap items-center gap-3">
        <select name="curriculum_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Curricula</option>
            @foreach($curricula as $curr)
                <option value="{{ $curr->id }}" @selected(request('curriculum_id') == $curr->id)>{{ $curr->name }}</option>
            @endforeach
        </select>
        <a href="{{ route('admin.transmutation-tables.index') }}" class="ml-auto text-xs font-semibold text-school-600 hover:underline">
            Manage Transmutation Tables &rarr;
        </a>
    </form>

    {{-- Configured Grading Policies --}}
    <div class="space-y-4">
        @foreach($configurations as $cfg)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900">{{ $cfg->name }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-school-50 text-school-700">
                                {{ str_replace('_', ' ', $cfg->grading_mode) }}
                            </span>
                            @if($cfg->is_confirmed_by_admin)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">Confirmed</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">Needs Admin Confirmation</span>
                            @endif
                            @if(! $cfg->is_active)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-200 text-slate-600">Inactive</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Curriculum: <strong>{{ $cfg->curriculum->code }}</strong>
                            @if($cfg->schoolYear) &bull; SY {{ $cfg->schoolYear->name }} @else &bull; All School Years @endif
                            @if($cfg->gradeLevel) &bull; {{ $cfg->gradeLevel->name }} @endif
                            @if($cfg->weight_group) &bull; Group: <code>{{ $cfg->weight_group }}</code> @endif
                            @if($cfg->subject) &bull; Subject: {{ $cfg->subject->code }} @endif
                            @if($cfg->transmutationTable) &bull; Table: {{ $cfg->transmutationTable->name }} @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(! $cfg->is_confirmed_by_admin)
                            <form method="POST" action="{{ route('admin.grading-policies.confirm', $cfg) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                                    Confirm Policy
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.grading-policies.toggle', $cfg) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                                {{ $cfg->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </div>
                </div>

                @if($cfg->isDescriptive())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($cfg->descriptive_scale_json ?? [] as $item)
                            <span class="px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700">
                                <strong>{{ $item['code'] }}</strong>: {{ $item['label'] }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        @foreach($cfg->topLevelComponents as $comp)
                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs">
                                <div class="flex justify-between font-semibold text-slate-800">
                                    <span>{{ $comp->code }} – {{ $comp->name }}</span>
                                    <span class="text-school-700">{{ number_format($comp->weight_percentage, 0) }}%</span>
                                </div>
                                @if($comp->children->isNotEmpty())
                                    <div class="mt-1.5 pt-1.5 border-t border-slate-200 space-y-0.5 text-[11px] text-slate-500">
                                        @foreach($comp->children as $child)
                                            <div class="flex justify-between">
                                                <span>&lfloor; {{ $child->code }} ({{ $child->name }})</span>
                                                <span>{{ number_format($child->weight_percentage, 0) }}%</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
