@extends('layouts.app')

@section('title', 'School Settings')
@section('header', 'School Profile & Settings')
@section('subheader', 'Official school details printed on DepEd-compatible report cards and grade sheets.')

@section('content')
<div class="max-w-4xl">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-200">
        @csrf
        @method('PUT')

        <div class="p-6 space-y-5">
            <h2 class="text-base font-semibold text-slate-900">School Identification</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="school_name" class="block text-sm font-medium text-slate-700 mb-1">School Name <span class="text-rose-600">*</span></label>
                    <input id="school_name" name="school_name" type="text" required
                           value="{{ old('school_name', $setting->school_name) }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div>
                    <label for="school_id_code" class="block text-sm font-medium text-slate-700 mb-1">DepEd School ID</label>
                    <input id="school_id_code" name="school_id_code" type="text"
                           value="{{ old('school_id_code', $setting->school_id_code) }}"
                           placeholder="e.g. 301234"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div>
                    <label for="region" class="block text-sm font-medium text-slate-700 mb-1">Region</label>
                    <input id="region" name="region" type="text"
                           value="{{ old('region', $setting->region) }}"
                           placeholder="e.g. Region IV-A (CALABARZON)"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div>
                    <label for="division" class="block text-sm font-medium text-slate-700 mb-1">Schools Division</label>
                    <input id="division" name="division" type="text"
                           value="{{ old('division', $setting->division) }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div>
                    <label for="district" class="block text-sm font-medium text-slate-700 mb-1">District</label>
                    <input id="district" name="district" type="text"
                           value="{{ old('district', $setting->district) }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Complete School Address</label>
                    <textarea id="address" name="address" rows="2"
                              class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">{{ old('address', $setting->address) }}</textarea>
                </div>
                <div>
                    <label for="contact_number" class="block text-sm font-medium text-slate-700 mb-1">Contact Number</label>
                    <input id="contact_number" name="contact_number" type="text"
                           value="{{ old('contact_number', $setting->contact_number) }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Official Email</label>
                    <input id="email" name="email" type="email"
                           value="{{ old('email', $setting->email) }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
            </div>
        </div>

        <div class="p-6 space-y-5">
            <h2 class="text-base font-semibold text-slate-900">Signatory &amp; Report Card Options</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="principal_name" class="block text-sm font-medium text-slate-700 mb-1">Principal / School Head Name</label>
                    <input id="principal_name" name="principal_name" type="text"
                           value="{{ old('principal_name', $setting->principal_name) }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div>
                    <label for="principal_title" class="block text-sm font-medium text-slate-700 mb-1">Signatory Title <span class="text-rose-600">*</span></label>
                    <input id="principal_title" name="principal_title" type="text" required
                           value="{{ old('principal_title', $setting->principal_title) }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                </div>
                <div>
                    <label for="default_calendar_mode" class="block text-sm font-medium text-slate-700 mb-1">Default Academic Term Structure <span class="text-rose-600">*</span></label>
                    <select id="default_calendar_mode" name="default_calendar_mode"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
                        <option value="trimester" @selected(old('default_calendar_mode', $setting->default_calendar_mode) === 'trimester')>Three-Term / Trimester (DO 015, s. 2026)</option>
                        <option value="quarterly" @selected(old('default_calendar_mode', $setting->default_calendar_mode) === 'quarterly')>Four Quarters (Traditional / DO 8, s. 2015)</option>
                        <option value="semestral" @selected(old('default_calendar_mode', $setting->default_calendar_mode) === 'semestral')>Semestral (Senior High School)</option>
                    </select>
                </div>
                <div>
                    <label for="logo" class="block text-sm font-medium text-slate-700 mb-1">School Logo (PNG/JPG/WebP, max 2MB)</label>
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                           class="w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-school-50 file:text-school-700 hover:file:bg-school-100">
                </div>
            </div>

            <div class="flex flex-col sm:flex-row flex-wrap gap-6 pt-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="attendance_enabled" value="1"
                           @checked(old('attendance_enabled', $setting->attendance_enabled))
                           class="rounded border-slate-300 text-school-600 focus:ring-school-500">
                    <span>Include Attendance Records on Report Cards</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="observed_values_enabled" value="1"
                           @checked(old('observed_values_enabled', $setting->observed_values_enabled))
                           class="rounded border-slate-300 text-school-600 focus:ring-school-500">
                    <span>Include DepEd Core Values on Report Cards</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="parent_portal_enabled" value="1"
                           @checked(old('parent_portal_enabled', $setting->parent_portal_enabled ?? true))
                           class="rounded border-slate-300 text-school-600 focus:ring-school-500">
                    <span>Enable Public Parent &amp; Learner Official Grade Portal (Read-Only)</span>
                </label>
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold shadow-sm transition">
                Save School Settings
            </button>
        </div>
    </form>
</div>
@endsection
