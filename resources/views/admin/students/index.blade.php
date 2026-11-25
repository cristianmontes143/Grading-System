@extends('layouts.app')

@section('title', 'Students & Enrollment')
@section('header', 'Student Records & Bulk Enrollment')
@section('subheader', 'Manage learner profiles with DepEd LRN, bulk CSV/Excel import, section transfers, and historical academic records.')

@section('content')
<div class="space-y-6" x-data="{ tab: '{{ session('import_errors') ? 'import' : 'list' }}' }">
    {{-- Import Row Errors Banner --}}
    @if(session('import_errors'))
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 space-y-1">
            <p class="font-bold">Some rows were skipped during import:</p>
            <ul class="list-disc list-inside space-y-0.5 max-h-40 overflow-y-auto">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Action Tabs --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="tab = 'list'"
                    :class="tab === 'list' ? 'bg-school-600 text-white' : 'bg-white text-slate-700 border border-slate-200'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition">
                Student Directory
            </button>
            <button type="button" @click="tab = 'add'"
                    :class="tab === 'add' ? 'bg-school-600 text-white' : 'bg-white text-slate-700 border border-slate-200'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition">
                + Add Single Student
            </button>
            <button type="button" @click="tab = 'import'"
                    :class="tab === 'import' ? 'bg-school-600 text-white' : 'bg-white text-slate-700 border border-slate-200'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition">
                Bulk Excel / CSV Import
            </button>
        </div>
        <a href="{{ route('admin.students.export', request()->query()) }}"
           class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition">
            Export Master List (.xlsx)
        </a>
    </div>

    {{-- Add Student Form --}}
    <div x-show="tab === 'add'" x-cloak class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Register New Student</h2>
        <form method="POST" action="{{ route('admin.students.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Student ID Number <span class="text-rose-600">*</span></label>
                <input type="text" name="student_number" value="{{ old('student_number') }}" required placeholder="e.g. 2026-0001" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">DepEd LRN (12 Digits)</label>
                <input type="text" name="lrn" value="{{ old('lrn') }}" maxlength="12" placeholder="109876543210" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Gender <span class="text-rose-600">*</span></label>
                <select name="gender" required class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                    <option value="Female" @selected(old('gender') === 'Female')>Female</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Date of Birth <span class="text-rose-600">*</span></label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">First Name <span class="text-rose-600">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Middle Name</label>
                <input type="text" name="middle_name" value="{{ old('middle_name') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Last Name <span class="text-rose-600">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Suffix</label>
                <input type="text" name="suffix" value="{{ old('suffix') }}" placeholder="Jr., III" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Enroll Immediately in Section (Optional)</label>
                <select name="section_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— Enroll Later —</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->gradeLevel->name }} – {{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Guardian Name</label>
                <input type="text" name="guardian_name" value="{{ old('guardian_name') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Guardian Contact</label>
                <input type="text" name="guardian_contact" value="{{ old('guardian_contact') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Address</label>
                <input type="text" name="address" value="{{ old('address') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Student Photo (Optional, JPG/PNG/WebP max 2MB)</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-slate-600">
            </div>
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                    Save Student Record
                </button>
            </div>
        </form>
    </div>

    {{-- Bulk Import Panel --}}
    <div x-show="tab === 'import'" x-cloak class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Bulk Import Students (Excel .xlsx/.xls or CSV)</h2>
                <p class="text-xs text-slate-500 mt-0.5">Required columns: <code>student_number</code>, <code>first_name</code>, <code>last_name</code>, <code>gender</code> (Male/Female), <code>date_of_birth</code> (YYYY-MM-DD). Optional: <code>lrn</code> (12 digits), <code>middle_name</code>, <code>suffix</code>, <code>address</code>, <code>guardian_name</code>, <code>guardian_contact</code>.</p>
            </div>
            <a href="{{ route('admin.students.import.template') }}" class="px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold">
                Download CSV Template
            </a>
        </div>

        <form method="POST" action="{{ route('admin.students.import') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Upload Excel / CSV File <span class="text-rose-600">*</span></label>
                <input type="file" name="import_file" required accept=".csv,.xlsx,.xls" class="w-full text-sm text-slate-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Enroll Imported Students Into Section (Optional)</label>
                <select name="section_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— Import Profiles Only —</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->gradeLevel->name }} – {{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                    Import Students
                </button>
            </div>
        </form>
    </div>

    {{-- Search & Filter --}}
    <div x-show="tab === 'list'" class="space-y-4">
        <form method="GET" action="{{ route('admin.students.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Student ID, LRN, or name..."
                   class="flex-1 min-w-[200px] rounded-lg border-slate-300 text-sm">
            <select name="section_id" class="rounded-lg border-slate-300 text-sm">
                <option value="">All Sections</option>
                @foreach($sections as $sec)
                    <option value="{{ $sec->id }}" @selected(request('section_id') == $sec->id)>{{ $sec->gradeLevel->name }} – {{ $sec->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border-slate-300 text-sm">
                <option value="">Active &amp; Enrolled</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="transferred_out" @selected(request('status') === 'transferred_out')>Transferred Out</option>
                <option value="graduated" @selected(request('status') === 'graduated')>Graduated</option>
                <option value="dropped" @selected(request('status') === 'dropped')>Dropped</option>
                <option value="archived" @selected(request('status') === 'archived')>Archived</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold">Filter</button>
            @if(request()->hasAny(['search', 'section_id', 'status']))
                <a href="{{ route('admin.students.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium">Reset</a>
            @endif
        </form>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Student ID / LRN</th>
                        <th class="px-5 py-3">Learner Name</th>
                        <th class="px-5 py-3">Current Placement</th>
                        <th class="px-5 py-3">Curriculum</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($students as $student)
                        @php $curr = $student->currentEnrollment; @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-mono text-xs font-semibold text-slate-800">{{ $student->student_number }}</p>
                                <p class="font-mono text-[11px] text-slate-400">LRN: {{ $student->lrn ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-900">{{ $student->formal_name }}</p>
                                <p class="text-xs text-slate-500">{{ $student->gender }} &bull; DOB: {{ $student->date_of_birth->format('Y-m-d') }}</p>
                            </td>
                            <td class="px-5 py-3 text-slate-700">
                                @if($curr)
                                    <p class="font-medium">{{ $curr->section->gradeLevel->name }} – {{ $curr->section->name }}</p>
                                    <p class="text-xs text-slate-400">SY {{ $curr->schoolYear->name }}</p>
                                @else
                                    <span class="text-xs text-amber-700">Not currently enrolled</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600">{{ $curr?->curriculum?->code ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize {{ $student->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ str_replace('_', ' ', $student->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap space-x-2">
                                <a href="{{ route('admin.students.show', $student) }}" class="text-xs font-semibold text-school-600 hover:underline">Profile &amp; History</a>
                                <form method="POST" action="{{ route('admin.students.archive', $student) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-rose-600">
                                        {{ $student->status === 'archived' ? 'Restore' : 'Archive' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-500">No students found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($students->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">{{ $students->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
