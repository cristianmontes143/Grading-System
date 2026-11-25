@extends('layouts.app')

@section('title', 'Registrar Certificates Hub')
@section('header', 'Registrar Official Certificates Hub')
@section('subheader', 'Issue official Certificates of Enrollment, Good Moral Character, and GWA / Class Ranking PDFs')

@section('content')
<div class="space-y-6">
    {{-- Filter Bar --}}
    <div class="school-card p-5">
        <form method="GET" action="{{ route('admin.certificates.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">School Year</label>
                <select name="school_year_id" class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy->id }}" @selected($selectedSchoolYear?->id === $sy->id)>SY {{ $sy->name }} {{ $sy->is_active ? '(Active)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Section</label>
                <select name="section_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All Sections</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}" @selected($selectedSectionId === $sec->id)>{{ $sec->gradeLevel->name }} – {{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Search Learner</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Name, LRN, or Student ID..." class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">Filter</button>
                <a href="{{ route('admin.certificates.index') }}" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-medium">Reset</a>
            </div>
        </form>
    </div>

    {{-- Custom Certificate Generator Card --}}
    <div class="school-card p-6" x-data="{
        enrollmentId: '{{ $enrollments->first()?->id ?? '' }}',
        type: 'enrollment',
        purpose: '',
        controlNumber: '',
        periodId: '{{ $periods->last()?->id ?? '' }}',
        baseUrl: '{{ url('/admin/certificates/enrollments') }}'
    }">
        <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-slate-100 mb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Custom Official Certificate Issuance</h2>
                <p class="text-xs text-slate-500">Select a learner, certificate type, and optional purpose/control number to generate a signed-ready A4 PDF.</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-school-50 text-school-700 border border-school-200">Audited Registrar Issuance</span>
        </div>

        <form method="GET"
              :action="baseUrl + '/' + enrollmentId + '/generate'"
              target="_blank"
              class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Learner</label>
                <select x-model="enrollmentId" required class="w-full rounded-lg border-slate-300 text-sm">
                    @forelse($enrollments as $enr)
                        <option value="{{ $enr->id }}">{{ $enr->student->formal_name }} — {{ $enr->gradeLevel->code }} {{ $enr->section->name }}</option>
                    @empty
                        <option value="">No enrolled learners found</option>
                    @endforelse
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Certificate Type</label>
                <select name="type" x-model="type" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($certificateTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Purpose (Optional)</label>
                <input type="text" name="purpose" x-model="purpose" placeholder="e.g. Scholarship Application" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <button type="submit" :disabled="!enrollmentId"
                        class="w-full px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 disabled:opacity-40 text-white text-sm font-semibold shadow-sm">
                    Generate PDF &nearr;
                </button>
            </div>

            <div class="md:col-span-2 lg:col-span-2" x-show="type === 'gwa_ranking'">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Grading Period (for Rank Reference)</label>
                <select name="grading_period_id" x-model="periodId" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Full School Year / Latest Period</option>
                    @foreach($periods as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Control No. (Optional)</label>
                <input type="text" name="control_number" x-model="controlNumber" placeholder="Auto-generated if blank" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
        </form>
    </div>

    {{-- Quick 1-Click Learner Directory Table --}}
    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">Quick 1-Click Certificates ({{ $enrollments->count() }} Learners)</h2>
                <p class="text-xs text-slate-500">Directly issue standard Registrar PDFs for any enrolled learner in SY {{ $selectedSchoolYear?->name ?? '—' }}.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Learner</th>
                    <th class="px-4 py-3">LRN / Student ID</th>
                    <th class="px-4 py-3">Grade &amp; Section</th>
                    <th class="px-5 py-3 text-right">Instant Certificates (PDF)</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($enrollments as $enr)
                    <tr>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.students.show', $enr->student) }}" class="font-bold text-slate-900 hover:text-school-700 hover:underline">
                                {{ $enr->student->formal_name }}
                            </a>
                            <span class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 capitalize">{{ str_replace('_', ' ', $enr->enrollment_status) }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-slate-600">
                            {{ $enr->student->lrn ?? 'No LRN' }}
                            <span class="block text-[10px] text-slate-400">{{ $enr->student->student_number }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-700">
                            {{ $enr->gradeLevel->name }} &ndash; {{ $enr->section->name }}
                            @if($enr->shsStrand)
                                <span class="text-[10px] text-slate-400 block">{{ $enr->shsTrack?->name }} ({{ $enr->shsStrand->code }})</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap space-x-1.5">
                            <a href="{{ route('admin.certificates.generate', ['enrollment' => $enr, 'type' => 'enrollment']) }}" target="_blank"
                               class="inline-flex px-2.5 py-1 rounded-lg bg-school-50 text-school-700 hover:bg-school-100 font-semibold">Enrollment</a>
                            <a href="{{ route('admin.certificates.generate', ['enrollment' => $enr, 'type' => 'good_moral']) }}" target="_blank"
                               class="inline-flex px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-semibold">Good Moral</a>
                            <a href="{{ route('admin.certificates.generate', ['enrollment' => $enr, 'type' => 'gwa_ranking']) }}" target="_blank"
                               class="inline-flex px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 hover:bg-amber-100 font-semibold">GWA &amp; Rank</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-slate-400">No enrolled learners match your current filter.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
