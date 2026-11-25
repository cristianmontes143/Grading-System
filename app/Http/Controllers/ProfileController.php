<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('roles'),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->auditLog->record(
            eventType: 'auth.password_changed',
            auditable: $user,
            actor: $user,
            request: $request
        );

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Your password has been updated.');
    }
}
