<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;

class User extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, Notifiable, SoftDeletes;

    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token'];
    protected $attributes = ['status' => 'active', 'theme' => 'system', 'user_type' => 'staff', 'failed_attempts' => 0];

    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every account can sign in with a username as well as its email; default it from the email.
        static::creating(function (User $u) {
            $u->username = $u->username ? strtolower(trim($u->username)) : static::uniqueUsername((string) strstr((string) $u->email, '@', true));
        });
    }

    public static function uniqueUsername(string $base): string
    {
        $base = substr(preg_replace('/[^a-z0-9._-]/', '', strtolower($base)), 0, 40) ?: 'user';
        $name = $base;
        for ($i = 2; static::withTrashed()->where('username', $name)->exists(); $i++) {
            $name = $base.$i;
        }
        return $name;
    }

    /** Find an account by username or email (case-insensitive). */
    public static function findForLogin(string $login): ?self
    {
        $login = strtolower(trim($login));
        return static::where(str_contains($login, '@') ? 'email' : 'username', $login)->first();
    }

    public function roles() { return $this->belongsToMany(Role::class); }
    public function employee() { return $this->hasOne(Employee::class); }
    public function tourOperator() { return $this->belongsTo(TourOperator::class); }

    public function isOperator(): bool { return $this->user_type === 'operator'; }
    public function isStaff(): bool { return $this->user_type === 'staff'; }

    public function hasRole(string ...$slugs): bool
    {
        return $this->roles->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    public function isSuperAdmin(): bool { return $this->hasRole('admin'); }

    public function permissionSlugs(): array
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = $this->roles()->with('permissions:id,slug')->get()
                ->flatMap(fn ($r) => $r->permissions->pluck('slug'))->unique()->values()->all();
        }
        return $this->permissionCache;
    }

    /** True when the user holds ANY of the given permissions ("a|b" or array). Admin holds all. */
    public function hasPermission(string|array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $permissions = is_array($permissions) ? $permissions : explode('|', $permissions);
        return count(array_intersect($permissions, $this->permissionSlugs())) > 0;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function homeRoute(): string
    {
        if ($this->isOperator()) {
            return 'operator.dashboard';
        }
        foreach ($this->roles as $role) {
            if ($role->home_route && Route::has($role->home_route)) {
                return $role->home_route;
            }
        }
        return 'admin.dashboard';
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
