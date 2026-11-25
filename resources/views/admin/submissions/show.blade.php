@extends('layouts.app')

@section('title', 'Inspect Grade Submission')
@section('header', $submission->teacherAssignment->subject->name . ' — ' . $submission->teacherAssignment->section->gradeLevel->name . ' ' . $submission->teacherAssignment->section->name)
@section('subheader', $submission->gradingPeriod->name . ' • Teacher: ' . $submission->teacherAssignment->teacher->full_name)

@section('content')
<div class="space-y-6" x-data="{ overrideModal: false, overrideUrl: '', studentName: '', currentGrade: '', isDescriptive: false }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.submissions.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to Grade Submissions</a>
        <div class="flex items-center gap-2">
            @if($submission->status === 'submitted')
                <form method="POST" action="{{ route('admin.submissions.approve', $submission) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                        Approve Submission
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Submitted Learner Grades</h2>
            <p class="text-xs text-slate-500">Any Administrator grade override requires an official reason and is permanently recorded in the audit log.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Student ID</th>
                    <th class="px-5 py-3">Learner Name</th>
                    <th class="px-5 py-3 text-center">Initial</th>
                    <th class="px-5 py-3 text-center">Official Grade</th>
                    <th class="px-5 py-3">Remarks</th>
                    <th class="px-5 py-3 text-right">Admin Override</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($grades as $grade)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $grade->enrollment->student->student_number }}</td>
                        <td class="px-5 py-3 font-medium text-slate-900">{{ $grade->enrollment->student->formal_name }}</td>
                        <td class="px-5 py-3 text-center text-slate-600">{{ $grade->initial_grade !== null ? number_format($grade->initial_grade, 2) : '—' }}</td>
                        <td class="px-5 py-3 text-center font-bold text-school-800">{{ $grade->display_grade }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $grade->remarks ?? '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            <button type="button"
                                    @click="overrideModal = true; overrideUrl = '{{ route('admin.grades.override', $grade) }}'; studentName = '{{ addslashes($grade->enrollment->student->formal_name) }}'; currentGrade = '{{ $grade->display_grade }}'; isDescriptive = {{ $grade->gradingConfiguration->isDescriptive() ? 'true' : 'false' }}"
                                    class="px-3 py-1 rounded bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-900 text-xs font-semibold">
                                Authorize Override
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Authorized Grade Override Modal --}}
    <div x-show="overrideModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div @click.away="overrideModal = false" class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-base font-bold text-slate-900">Authorized Grade Override</h3>
            <p class="text-xs text-slate-600 mt-1">Learner: <strong x-text="studentName"></strong> (Current: <strong x-text="currentGrade"></strong>)</p>
            <form method="POST" :action="overrideUrl" class="mt-4 space-y-4">
                @csrf
                <div x-show="!isDescriptive">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Updated Final Grade (0–100) <span class="text-rose-600">*</span></label>
                    <input type="number" step="0.01" min="0" max="100" name="final_grade" :required="!isDescriptive" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div x-show="isDescriptive">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Updated Descriptive Rating <span class="text-rose-600">*</span></label>
                    <input type="text" name="descriptive_rating" :required="isDescriptive" placeholder="CO / DV / BG" class="w-full rounded-lg border-slate-300 text-sm uppercase">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Official Reason for Modification <span class="text-rose-600">*</span></label>
                    <textarea name="reason" rows="3" required minlength="5" placeholder="e.g., Approved clerical correction per Principal memo #12" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="overrideModal = false" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">Save Audited Override</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
