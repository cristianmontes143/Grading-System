@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('header', 'Roles & Permissions')
@section('subheader', 'Server-side role-based access control matrix. Administrator always retains full access.')

@section('content')
<div class="space-y-6">
    @foreach($roles as $role)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">{{ $role->label }}</h2>
                    <p class="text-xs text-slate-600 mt-0.5">{{ $role->description }}</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-200 text-slate-800">
                    {{ $role->name }}
                </span>
            </div>

            <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                @php $assignedIds = $role->permissions->pluck('id')->all(); @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($permissionsByModule as $module => $permissions)
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">{{ $module }}</h3>
                            <div class="space-y-2.5">
                                @foreach($permissions as $permission)
                                    <label class="flex items-start gap-2.5 text-sm text-slate-700">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="{{ $permission->id }}"
                                               @checked(in_array($permission->id, $assignedIds, true))
                                               @disabled($role->name === \App\Models\Role::ADMIN)
                                               class="mt-0.5 rounded border-slate-300 text-school-600 focus:ring-school-500 disabled:opacity-60">
                                        <span>
                                            <span class="block font-medium text-slate-800 leading-tight">{{ $permission->label }}</span>
                                            <span class="block text-xs text-slate-400">{{ $permission->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($role->name !== \App\Models\Role::ADMIN)
                    <div class="flex justify-end pt-2 border-t border-slate-100">
                        <button type="submit"
                                class="px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold shadow-sm transition">
                            Update {{ $role->label }} Permissions
                        </button>
                    </div>
                @endif
            </form>
        </div>
    @endforeach
</div>
@endsection
