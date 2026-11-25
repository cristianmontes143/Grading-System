@extends('layouts.app')

@section('title', $student->full_name)
@section('header', $student->formal_name)
@section('subheader', 'Student ID: ' . $student->student_number . ($student->lrn ? ' • LRN: ' . $student->lrn : ''))

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <a href="{{ route('admin.students.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to Student Directory</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.students.sf10', $student) }}"
               class="px-3.5 py-2 rounded-xl bg-school-600 hover:bg-school-700 text-white text-xs font-semibold shadow-sm transition">
                Permanent Record (SF10 / Form 137) &rarr;
            </a>
            <a href="{{ route('admin.students.sf10', ['student' => $student, 'pdf' => 1]) }}" target="_blank"
               class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition">
                Download SF10 PDF &nearr;
            </a>
            @if($currentEnrollment)
                <a href="{{ route('admin.certificates.generate', ['enrollment' => $currentEnrollment, 'type' => 'enrollment']) }}" target="_blank"
                   class="px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold transition">
                    Cert. of Enrollment &nearr;
                </a>
                <a href="{{ route('admin.certificates.generate', ['enrollment' => $currentEnrollment, 'type' => 'good_moral']) }}" target="_blank"
                   class="px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 hover:bg-amber-100 text-amber-800 text-xs font-semibold transition">
                    Good Moral &nearr;
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Profile & Edit Form --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center gap-4 mb-5">
                @if($student->photo_path)
                    <img src="{{ asset('storage/' . $student->photo_path) }}" alt="{{ $student->full_name }}" class="h-16 w-16 rounded-xl object-cover border border-slate-200">
                @else
                    <div class="h-16 w-16 rounded-xl bg-school-100 text-school-700 flex items-center justify-center font-bold text-lg">
                        {{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h2 class="text-base font-bold text-slate-900">{{ $student->full_name }}</h2>
                    <p class="text-xs text-slate-500">{{ $student->gender }} &bull; Born {{ $student->date_of_birth->format('F d, Y') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.students.update', $student) }}" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Student ID Number</label>
                    <input type="text" name="student_number" value="{{ old('student_number', $student->student_number) }}" required class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">DepEd LRN (12 Digits)</label>
                    <input type="text" name="lrn" value="{{ old('lrn', $student->lrn) }}" maxlength="12" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}" required class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name', $student->middle_name) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}" required class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Suffix</label>
                    <input type="text" name="suffix" value="{{ old('suffix', $student->suffix) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Gender</label>
                    <select name="gender" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="Male" @selected(old('gender', $student->gender) === 'Male')>Male</option>
                        <option value="Female" @selected(old('gender', $student->gender) === 'Female')>Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $student->date_of_birth->format('Y-m-d')) }}" required class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Enrollment Status</label>
                    <select name="status" class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach(['active', 'transferred_out', 'graduated', 'dropped', 'archived'] as $st)
                            <option value="{{ $st }}" @selected(old('status', $student->status) === $st)>{{ ucwords(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Update Photo</label>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-slate-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Guardian Name</label>
                    <input type="text" name="guardian_name" value="{{ old('guardian_name', $student->guardian_name) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Guardian Contact</label>
                    <input type="text" name="guardian_contact" value="{{ old('guardian_contact', $student->guardian_contact) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Address</label>
                    <input type="text" name="address" value="{{ old('address', $student->address) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                        Update Student Profile
                    </button>
                </div>
            </form>
        </div>

        {{-- Right Column: Section Transfer & New School Year Enrollment --}}
        <div class="space-y-6">
            @if($currentEnrollment)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Transfer Section (Preserve Records)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Current: <strong>{{ $currentEnrollment->gradeLevel->name }} – {{ $currentEnrollment->section->name }}</strong> (SY {{ $currentEnrollment->schoolYear->name }})
                        </p>
                    </div>
                    @if($transferTargetSections->isNotEmpty())
                        <form method="POST" action="{{ route('admin.students.transfer', [$student, $currentEnrollment]) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Target Section</label>
                                <select name="target_section_id" required class="w-full rounded-lg border-slate-300 text-sm">
                                    @foreach($transferTargetSections as $sec)
                                        <option value="{{ $sec->id }}">{{ $sec->gradeLevel->name }} – {{ $sec->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Transfer Reason <span class="text-rose-600">*</span></label>
                                <textarea name="transfer_reason" rows="2" required minlength="5" placeholder="e.g. Parent request / strand realignment" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">
                                Transfer Student Section
                            </button>
                        </form>
                    @else
                        <p class="text-xs text-slate-500">No other active sections exist in SY {{ $currentEnrollment->schoolYear->name }}.</p>
                    @endif
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                <h3 class="text-sm font-bold text-slate-900">Enroll in a School Year / Section</h3>
                <form method="POST" action="{{ route('admin.students.enroll', $student) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Section</label>
                        <select name="section_id" required class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach($allActiveSections as $sec)
                                <option value="{{ $sec->id }}">SY {{ $sec->schoolYear->name }} — {{ $sec->gradeLevel->name }} – {{ $sec->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                        Enroll Student
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Academic & Enrollment History Timeline --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Academic &amp; Enrollment History</h2>
            <p class="text-xs text-slate-500">Every enrollment retains its original curriculum version snapshot and section transfer trail.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">School Year</th>
                    <th class="px-5 py-3">Grade &amp; Section</th>
                    <th class="px-5 py-3">Curriculum Snapshot</th>
                    <th class="px-5 py-3">Adviser</th>
                    <th class="px-5 py-3">Status &amp; Notes</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($student->enrollments as $enr)
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-900 whitespace-nowrap">
                            SY {{ $enr->schoolYear->name }}
                            <p class="text-[11px] text-slate-400">Enrolled {{ $enr->enrolled_at->format('Y-m-d') }}</p>
                        </td>
                        <td class="px-5 py-3 font-semibold text-school-700">
                            {{ $enr->gradeLevel->name }} – {{ $enr->section->name }}
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-600">
                            {{ $enr->curriculum->name }} ({{ $enr->curriculum->code }})
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $enr->section->adviser?->full_name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-3 text-xs">
                            <span class="px-2 py-0.5 rounded-full font-semibold capitalize {{ $enr->is_current ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ str_replace('_', ' ', $enr->enrollment_status) }}{{ $enr->is_current ? ' (Current)' : '' }}
                            </span>
                            @if($enr->transferredFrom)
                                <p class="text-slate-500 mt-1">Transferred from {{ $enr->transferredFrom->section->name }}: {{ $enr->transfer_reason }}</p>
                            @elseif($enr->transfer_reason)
                                <p class="text-slate-500 mt-1">{{ $enr->transfer_reason }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-3">
                                <a href="{{ route('adviser.students.summary', $enr) }}"
                                   class="text-[11px] font-semibold text-emerald-700 hover:underline">
                                    Performance Summary &rarr;
                                </a>
                                <a href="{{ route('adviser.report-cards.individual', ['enrollment' => $enr, 'draft' => 1]) }}" target="_blank"
                                   class="text-[11px] font-semibold text-school-600 hover:underline">
                                    Historical Report Card PDF ({{ $enr->curriculum->code }}) &nearr;
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-slate-500">No enrollment history recorded yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
