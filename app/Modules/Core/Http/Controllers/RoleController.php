<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Roles are named bundles of permissions. The matrix view shows exactly what each role can do. */
class RoleController extends Controller
{
    public function index()
    {
        return view('admin.users.roles', [
            'roles' => Role::withCount('users')->with('permissions:id')->orderBy('id')->get(),
            'permissions' => Permission::orderBy('module')->orderBy('id')->get()->groupBy('module'),
        ]);
    }

    public function create()
    {
        return view('admin.users.role-form', ['role' => new Role(), 'permissions' => Permission::orderBy('module')->orderBy('id')->get()->groupBy('module')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create(['slug' => Str::slug($data['name'], '_'), 'name' => $data['name'], 'description' => $data['description'] ?? null, 'home_route' => $data['home_route'] ?? 'admin.dashboard']);
        $role->permissions()->sync($data['permissions'] ?? []);
        AuditService::log('users', 'role_created', $role, $role->name);
        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
        return view('admin.users.role-form', ['role' => $role->load('permissions'), 'permissions' => Permission::orderBy('module')->orderBy('id')->get()->groupBy('module')]);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->slug === 'admin') {
            return back()->with('error', 'The Administrator role always has every permission and cannot be edited.');
        }
        $data = $this->validated($request, $role);
        $old = $role->permissions->pluck('slug')->all();
        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'home_route' => $data['home_route'] ?? $role->home_route]);
        $role->permissions()->sync($data['permissions'] ?? []);
        AuditService::log('users', 'role_permissions_changed', $role, $role->name, ['permissions' => $old], ['permissions' => $role->fresh()->permissions->pluck('slug')->all()]);
        return redirect()->route('admin.roles.index')->with('success', $role->name.' updated. Changes apply on the users\' next request.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'home_route' => ['nullable', Rule::in(['admin.dashboard', 'admin.frontdesk.index', 'pos.terminal', 'pos.kds', 'admin.housekeeping.index', 'admin.housekeeping.my', 'admin.maintenance.index', 'admin.payments.index', 'pos.inventory.items.index', 'admin.staff.my-timecard'])],
            'permissions' => ['nullable', 'array'], 'permissions.*' => ['exists:permissions,id'],
        ]);
    }
}
