<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Modules\Reports\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        return view('admin.dashboard', [
            'd' => $dashboard->build(),
            'alerts' => AppNotification::visibleTo($request->user())->unreadBy($request->user())->latest('id')->limit(6)->get(),
        ]);
    }
}
