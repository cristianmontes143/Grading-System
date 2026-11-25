<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(): View
    {
        $roles = Role::query()->with('permissions')->orderBy('id')->get();
        $permissionsByModule = Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissionsByModule' => $permissionsByModule,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === Role::ADMIN) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Administrator role always retains full system permissions.');
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $newPermissionIds = $validated['permissions'] ?? [];
        $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($role, $newPermissionIds): void {
            $role->permissions()->sync($newPermissionIds);
        });

        $newPermissions = $role->permissions()->pluck('name')->sort()->values()->all();

        $this->auditLog->record(
            eventType: 'role_permissions.updated',
            auditable: $role,
            oldValues: ['role' => $role->name, 'permissions' => $oldPermissions],
            newValues: ['role' => $role->name, 'permissions' => $newPermissions],
            request: $request
        );

        return redirect()
            ->route('admin.roles.index')
            ->with('status', "Permissions for {$role->label} updated successfully.");
    }
}
