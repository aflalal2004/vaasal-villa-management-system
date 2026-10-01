<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->query('module')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->query('user')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('description', 'like', '%'.$request->query('q').'%')->orWhere('action', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->query('date')))
            ->latest('id')->paginate(50)->withQueryString();
        return view('admin.audit.index', ['logs' => $logs, 'modules' => AuditLog::distinct()->orderBy('module')->pluck('module'), 'users' => User::orderBy('name')->pluck('name', 'id')]);
    }

    public function logins(Request $request)
    {
        $logs = LoginLog::with('user')
            ->when($request->query('result') === 'failed', fn ($q) => $q->where('success', false))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', '%'.$request->query('q').'%')->orWhere('ip_address', 'like', '%'.$request->query('q').'%')))
            ->latest('id')->paginate(50)->withQueryString();
        return view('admin.audit.logins', ['logs' => $logs, 'locked' => User::whereNotNull('locked_until')->where('locked_until', '>', now())->get(),
            'failed24' => LoginLog::where('success', false)->where('created_at', '>=', now()->subDay())->count()]);
    }
}
