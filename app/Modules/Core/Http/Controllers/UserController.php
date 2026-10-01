<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['roles', 'employee', 'tourOperator'])
            ->when($request->query('type', 'staff') !== 'all', fn ($q) => $q->where('user_type', $request->query('type', 'staff')))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', $request->query('role'))))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('email', 'like', '%'.$request->query('q').'%')))
            ->orderBy('name')->paginate(30)->withQueryString();
        return view('admin.users.index', ['users' => $users, 'roles' => Role::orderBy('name')->pluck('name', 'id')]);
    }

    public function create()
    {
        return view('admin.users.form', ['u' => new User(['status' => 'active']), 'roles' => $this->roles()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = DB::transaction(function () use ($data) {
            $u = User::create(['property_id' => Property::current()->id, 'user_type' => 'staff', 'name' => $data['name'], 'email' => strtolower($data['email']), 'username' => $data['username'] ?? null,
                'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'status' => $data['status'], 'password_changed_at' => now()]);
            $u->roles()->sync($data['roles']);
            return $u;
        });
        AuditService::log('users', 'created', $user, $user->email.' roles: '.$user->roles->pluck('slug')->implode(','));
        return redirect()->route('admin.users.index')->with('success', 'User '.$user->email.' created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['u' => $user->load('roles'), 'roles' => $this->roles()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id && ! in_array(Role::where('slug', 'admin')->value('id'), $data['roles']) && $user->isSuperAdmin()) {
            return back()->with('error', 'You cannot remove the Administrator role from your own account.');
        }
        $old = ['roles' => $user->roles->pluck('slug')->all(), 'status' => $user->status];
        $user->fill(['name' => $data['name'], 'email' => strtolower($data['email']), 'username' => $data['username'] ?: $user->username, 'phone' => $data['phone'] ?? null, 'status' => $data['status']]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $user->password_changed_at = now();
        }
        $user->save();
        if ($user->user_type === 'staff') {
            $user->roles()->sync($data['roles']);
        }
        if ($data['status'] !== 'active') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        AuditService::log('users', 'updated', $user, null, $old, ['roles' => $user->fresh()->roles->pluck('slug')->all(), 'status' => $user->status]);
        return redirect()->route('admin.users.index')->with('success', 'User saved.');
    }

    public function unlock(User $user)
    {
        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
        AuditService::log('users', 'unlocked', $user);
        return back()->with('success', $user->email.' unlocked.');
    }

    public function resetPassword(User $user)
    {
        $temp = Str::password(12, symbols: false);
        $user->forceFill(['password' => $temp, 'password_changed_at' => null, 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditService::log('users', 'password_reset_by_admin', $user);
        return back()->with('success', 'Temporary password for '.$user->email.': '.$temp.' — share it securely; ask them to change it after signing in.');
    }

    private function roles()
    {
        return Role::where('slug', '!=', 'tour_operator')->orderBy('name')->get();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/i', Rule::unique('users', 'username')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$user ? 'nullable' : 'required', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'roles' => [$user?->isOperator() ? 'nullable' : 'required', 'array'], 'roles.*' => ['exists:roles,id'],
        ]);
        if (Role::whereIn('id', $data['roles'] ?? [])->where('slug', 'admin')->exists() && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only administrators can grant the Administrator role.');
        }
        $data['roles'] ??= [];
        if (! empty($data['username'])) $data['username'] = strtolower($data['username']);
        return $data;
    }
}
