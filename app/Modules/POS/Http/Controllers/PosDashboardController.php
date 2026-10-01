<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\POS\Services\PosDashboardService;

/** Restaurant POS → Dashboard. */
class PosDashboardController extends Controller
{
    public function __invoke(PosDashboardService $dashboard)
    {
        return view('pos.dashboard', ['d' => $dashboard->build()]);
    }
}
