@extends('layouts.app')

@section('title', 'Report Card Templates')
@section('header', 'DepEd-Compatible Report Card Templates')
@section('subheader', 'Customize headers, paper size, visibility options, and school disclaimer labels.')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Create Custom Report Card Template</h2>
        <form method="POST" action="{{ route('admin.report-card-templates.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Template Name <span class="text-rose-600">*</span></label>
                <input type="text" name="name" required placeholder="e.g. MATATAG JHS Standard A4" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Curriculum Scope (Optional)</label>
                <select name="curriculum_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All Curricula (Global Default)</option>
                    @foreach($curricula as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Header Title <span class="text-rose-600">*</span></label>
                <input type="text" name="header_subtitle" value="Learner Progress Report Card" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Form Compatibility / Disclaimer Label <span class="text-rose-600">*</span></label>
                <input type="text" name="disclaimer_label" value="DepEd-Compatible Learner Progress Report Card (School-Issued)" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Paper Size</label>
                    <select name="paper_size" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="a4">A4</option>
                        <option value="letter">Letter</option>
                        <option value="legal">Legal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Orientation</label>
                    <select name="orientation" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </select>
                </div>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Footer Note (Optional)</label>
                <input type="text" name="footer_note" placeholder="e.g. Valid without erasures; signed by the Class Adviser and School Head." class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-3 flex flex-wrap gap-6 text-xs text-slate-700">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_lrn" value="1" checked class="rounded border-slate-300 text-school-600"> Show LRN</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_attendance" value="1" checked class="rounded border-slate-300 text-school-600"> Show Attendance</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="show_observed_values" value="1" checked class="rounded border-slate-300 text-school-600"> Show Observed Values</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-school-600"> Set as Default Template</label>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="px-5 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                    Save Template
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-5 py-3">Template Name</th>
                <th class="px-5 py-3">Curriculum</th>
                <th class="px-5 py-3">Paper</th>
                <th class="px-5 py-3">Sections Shown</th>
                <th class="px-5 py-3 text-right">Status</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
            @forelse($templates as $tpl)
                <tr>
                    <td class="px-5 py-3">
                        <p class="font-semibold text-slate-900">{{ $tpl->name }}</p>
                        <p class="text-xs text-slate-500">{{ $tpl->disclaimer_label }}</p>
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-600">{{ $tpl->curriculum?->code ?? 'All Curricula' }}</td>
                    <td class="px-5 py-3 text-xs uppercase text-slate-600">{{ $tpl->paper_size }} / {{ $tpl->orientation }}</td>
                    <td class="px-5 py-3 text-xs text-slate-600">
                        {{ $tpl->show_lrn ? 'LRN' : '' }} {{ $tpl->show_attendance ? '• Attendance' : '' }} {{ $tpl->show_observed_values ? '• Core Values' : '' }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        @if($tpl->is_default)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Default</span>
                        @else
                            <form method="POST" action="{{ route('admin.report-card-templates.default', $tpl) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-school-600 hover:underline">Set Default</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-6 text-center text-slate-500">Using built-in DepEd-Compatible SF9 Default Layout. Create a custom template above if needed.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
