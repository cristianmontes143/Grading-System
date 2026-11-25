<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolSettingController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'setting' => SchoolSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:200'],
            'school_id_code' => ['nullable', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'principal_name' => ['nullable', 'string', 'max:150'],
            'principal_title' => ['required', 'string', 'max:100'],
            'attendance_enabled' => ['nullable', 'boolean'],
            'observed_values_enabled' => ['nullable', 'boolean'],
            'parent_portal_enabled' => ['nullable', 'boolean'],
            'default_calendar_mode' => ['required', Rule::in(['trimester', 'quarterly', 'semestral'])],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $setting = SchoolSetting::query()->first() ?? new SchoolSetting();
        $oldValues = $setting->exists ? $setting->only([
            'school_name', 'school_id_code', 'region', 'division', 'district',
            'principal_name', 'principal_title', 'attendance_enabled',
            'observed_values_enabled', 'parent_portal_enabled', 'default_calendar_mode',
        ]) : null;

        $setting->fill([
            'school_name' => $validated['school_name'],
            'school_id_code' => $validated['school_id_code'] ?? null,
            'region' => $validated['region'] ?? null,
            'division' => $validated['division'] ?? null,
            'district' => $validated['district'] ?? null,
            'address' => $validated['address'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'principal_name' => $validated['principal_name'] ?? null,
            'principal_title' => $validated['principal_title'],
            'attendance_enabled' => $request->boolean('attendance_enabled'),
            'observed_values_enabled' => $request->boolean('observed_values_enabled'),
            'parent_portal_enabled' => $request->boolean('parent_portal_enabled'),
            'default_calendar_mode' => $validated['default_calendar_mode'],
        ]);

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $setting->logo_path = $request->file('logo')->store('school', 'public');
        }

        $setting->save();

        $this->auditLog->record(
            eventType: 'school_settings.updated',
            auditable: $setting,
            oldValues: $oldValues,
            newValues: $setting->only([
                'school_name', 'school_id_code', 'region', 'division', 'district',
                'principal_name', 'principal_title', 'attendance_enabled',
                'observed_values_enabled', 'default_calendar_mode',
            ]),
            request: $request
        );

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'School settings have been updated successfully.');
    }
}
