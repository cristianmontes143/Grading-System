@extends('layouts.app')

@section('title', 'Teachers')
@section('header', 'Teacher Accounts Management')
@section('subheader', 'Register advisers and subject teachers. Advisers can also be assigned to teach multiple subjects across sections.')

@section('content')
<div class="space-y-6" x-data="{ editModal: false, editTeacher: {} }">
    {{-- Create Teacher Form --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Register New Teacher</h2>
        <form method="POST" action="{{ route('admin.teachers.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Employee No. <span class="text-rose-600">*</span></label>
                <input type="text" name="employee_number" value="{{ old('employee_number') }}" required placeholder="e.g. TCH-2026-001"
                       class="w-full rounded-lg border-slate-300 text-sm">
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
                <label class="block text-xs font-semibold text-slate-600 mb-1">Email (Login) <span class="text-rose-600">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Initial Password <span class="text-rose-600">*</span></label>
                <input type="password" name="password" required placeholder="Min 8 chars (letters+numbers)" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Role <span class="text-rose-600">*</span></label>
                <select name="role_name" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($teacherRoles as $role)
                        <option value="{{ $role->name }}" @selected(old('role_name') === $role->name)>{{ $role->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Specialization</label>
                <input type="text" name="specialization" value="{{ old('specialization') }}" placeholder="e.g. Mathematics / ICT" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Gender</label>
                <select name="gender" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— Optional —</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Contact Number</label>
                <input type="text" name="contact_number" value="{{ old('contact_number') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2 flex items-end justify-end">
                <button type="submit" class="px-5 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                    + Create Teacher Account
                </button>
            </div>
        </form>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('admin.teachers.index') }}" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search employee no., name, email, specialization..."
               class="flex-1 min-w-[220px] rounded-lg border-slate-300 text-sm">
        <select name="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">All Statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold">Filter</button>
        <a href="{{ route('admin.teachers.export', request()->query()) }}"
           class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
            Export Faculty &amp; Loads (.xlsx)
        </a>
        @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('admin.teachers.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium">Reset</a>
        @endif
    </form>

    {{-- Teachers Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Employee No.</th>
                    <th class="px-5 py-3">Teacher Name &amp; Email</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Load ({{ $activeSy ? 'SY ' . $activeSy->name : 'All' }})</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($teachers as $teacher)
                    @php
                        $primaryRole = $teacher->user->isAdviser() ? 'adviser' : 'subject_teacher';
                    @endphp
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-700">{{ $teacher->employee_number }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-slate-900">{{ $teacher->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $teacher->user->email }} @if($teacher->specialization) &bull; {{ $teacher->specialization }} @endif</p>
                        </td>
                        <td class="px-5 py-3 text-xs">
                            @if($teacher->user->isAdviser())
                                <span class="px-2 py-0.5 rounded-full font-semibold bg-school-50 text-school-700 border border-school-200">Adviser &amp; Subject Teacher</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-700">Subject Teacher</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-600">
                            <span>{{ $teacher->advisory_sections_count }} Advisory</span> &bull;
                            <span>{{ $teacher->active_assignments_count }} Subject(s)</span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $teacher->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                {{ $teacher->is_active ? 'Active' : 'Deactivated' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap space-x-2">
                            <button type="button"
                                    @click="editModal = true; editTeacher = {{ Js::from([
                                        'url' => route('admin.teachers.update', $teacher),
                                        'employee_number' => $teacher->employee_number,
                                        'first_name' => $teacher->first_name,
                                        'middle_name' => $teacher->middle_name ?? '',
                                        'last_name' => $teacher->last_name,
                                        'specialization' => $teacher->specialization ?? '',
                                        'contact_number' => $teacher->contact_number ?? '',
                                        'email' => $teacher->user->email,
                                        'role_name' => $primaryRole,
                                    ]) }}"
                                    class="text-xs font-semibold text-school-600 hover:underline">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.teachers.toggle', $teacher) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-semibold {{ $teacher->is_active ? 'text-rose-600' : 'text-emerald-600' }} hover:underline">
                                    {{ $teacher->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-slate-500">No teacher accounts registered yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($teachers->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">{{ $teachers->links() }}</div>
        @endif
    </div>

    {{-- Edit Teacher Modal --}}
    <div x-show="editModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div @click.away="editModal = false" class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
            <h3 class="text-base font-bold text-slate-900 mb-4">Edit Teacher Account</h3>
            <form method="POST" :action="editTeacher.url" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Employee No.</label>
                        <input type="text" name="employee_number" x-model="editTeacher.employee_number" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Role</label>
                        <select name="role_name" x-model="editTeacher.role_name" class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach($teacherRoles as $role)
                                <option value="{{ $role->name }}">{{ $role->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">First Name</label>
                        <input type="text" name="first_name" x-model="editTeacher.first_name" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Last Name</label>
                        <input type="text" name="last_name" x-model="editTeacher.last_name" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Middle Name</label>
                        <input type="text" name="middle_name" x-model="editTeacher.middle_name" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Specialization</label>
                        <input type="text" name="specialization" x-model="editTeacher.specialization" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Email Address</label>
                        <input type="email" name="email" x-model="editTeacher.email" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Reset Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editModal = false" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
