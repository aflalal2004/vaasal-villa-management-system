<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\NotificationRead;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = AppNotification::visibleTo($user)->with(['reads' => fn ($r) => $r->where('user_id', $user->id)])->latest('id');
        if ($request->query('filter') === 'unread') {
            $q->unreadBy($user);
        }
        if ($type = $request->query('type')) {
            $q->where('type', 'like', $type.'%');
        }
        return view('admin.notifications', ['items' => $q->paginate(25)->withQueryString()]);
    }

    /**
     * Lightweight polling endpoint for the bell and toasts (works on plain XAMPP; an SSE/WebSocket driver can
     * replace the client later without changing this contract).
     * GET ?after=<last seen id>  →  { unread, last_id, items: [{ id, title, body, level, url, time }] }
     */
    public function poll(Request $request)
    {
        $user = $request->user();
        $after = max(0, (int) $request->query('after', 0));
        $items = $after > 0
            ? AppNotification::visibleTo($user)->unreadBy($user)->where('id', '>', $after)->latest('id')->limit(5)->get()
            : collect();
        return response()->json([
            'unread' => AppNotification::visibleTo($user)->unreadBy($user)->count(),
            'last_id' => (int) (AppNotification::visibleTo($user)->max('id') ?? 0),
            'items' => $items->map(fn ($n) => ['id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'level' => $n->level,
                'url' => route('admin.notifications.open', $n), 'time' => $n->created_at?->diffForHumans()])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function open(Request $request, AppNotification $notification)
    {
        abort_unless(AppNotification::visibleTo($request->user())->whereKey($notification->id)->exists(), 404);
        NotificationRead::insertOrIgnore(['app_notification_id' => $notification->id, 'user_id' => $request->user()->id, 'read_at' => now()]);
        return redirect($notification->url ?: route('admin.notifications.index'));
    }

    public function readAll(Request $request)
    {
        $user = $request->user();
        $ids = AppNotification::visibleTo($user)->unreadBy($user)->pluck('id');
        NotificationRead::insertOrIgnore($ids->map(fn ($id) => ['app_notification_id' => $id, 'user_id' => $user->id, 'read_at' => now()])->all());
        return back()->with('success', $ids->count().' notification(s) marked as read.');
    }
}
