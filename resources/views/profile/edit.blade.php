@extends('layouts.app')

@section('title', 'Profile & Password')
@section('header', 'Account Profile & Security')
@section('subheader', 'Review your assigned roles and update your account password.')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Account Information</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-slate-500">Full Name</dt>
                <dd class="font-medium text-slate-900 mt-0.5">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Email Address</dt>
                <dd class="font-medium text-slate-900 mt-0.5">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Assigned Role(s)</dt>
                <dd class="font-medium text-school-700 mt-0.5">{{ $user->role_labels }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Last Sign-In</dt>
                <dd class="font-medium text-slate-900 mt-0.5">{{ $user->last_login_at?->format('Y-m-d H:i:s') ?? 'First session' }}</dd>
            </div>
        </dl>
    </div>

    <form method="POST" action="{{ route('profile.password.update') }}"
          class="bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-200">
        @csrf
        @method('PUT')

        <div class="p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900">Change Password</h2>
            <div>
                <label for="current_password" class="block text-sm font-medium text-slate-700 mb-1">Current Password</label>
                <input id="current_password" name="current_password" type="password" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">New Password (min 8 chars, letters &amp; numbers)</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
            </div>
        </div>
        <div class="px-6 py-4 bg-slate-50 flex justify-end">
            <button type="submit" class="px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
                Update Password
            </button>
        </div>
    </form>
</div>
@endsection
