<?php

namespace App\Models;

class AppNotification extends BaseModel
{
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];

    public function reads() { return $this->hasMany(NotificationRead::class); }

    /**
     * Visible to a user: addressed to them, or broadcast to a permission they hold (or to everyone).
     * `permission` may list alternatives separated by "|" (e.g. "housekeeping.work|housekeeping.view").
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $q, User $user)
    {
        $perms = $user->isSuperAdmin() ? null : $user->permissionSlugs();
        return $q->where(function ($w) use ($user, $perms) {
            $w->where('user_id', $user->id)->orWhere(function ($b) use ($perms) {
                $b->whereNull('user_id');
                if ($perms !== null) {
                    $b->where(function ($p) use ($perms) {
                        $p->whereNull('permission')->orWhereIn('permission', $perms);
                        foreach ($perms as $perm) {
                            $p->orWhere('permission', 'like', $perm.'|%')->orWhere('permission', 'like', '%|'.$perm)->orWhere('permission', 'like', '%|'.$perm.'|%');
                        }
                    });
                }
            });
        });
    }

    public function scopeUnreadSince(\Illuminate\Database\Eloquent\Builder $q, int $afterId)
    {
        return $q->where('id', '>', $afterId);
    }

    public function scopeUnreadBy(\Illuminate\Database\Eloquent\Builder $q, User $user)
    {
        return $q->whereDoesntHave('reads', fn ($r) => $r->where('user_id', $user->id));
    }
}
