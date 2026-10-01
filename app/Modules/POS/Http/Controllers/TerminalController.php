<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Kot;
use App\Models\Outlet;
use App\Models\PosOrder;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function index(Request $request)
    {
        $outlets = Outlet::where('is_active', true)->orderBy('id')->get();
        $outletId = (int) ($request->query('outlet') ?: session('pos_outlet', $outlets->first()?->id));
        session(['pos_outlet' => $outletId]);
        return view('pos.terminal', ['outlets' => $outlets, 'outlet' => $outlets->firstWhere('id', $outletId) ?? $outlets->first(), 'orderId' => $request->query('order')]);
    }

    public function order(PosOrder $order)
    {
        return redirect()->route('pos.terminal', ['outlet' => $order->outlet_id, 'order' => $order->id]);
    }

    public function bill(PosOrder $order)
    {
        return view('pos.print.bill', ['o' => $order->load(['liveItems', 'outlet', 'table', 'waiter', 'booking.guest', 'villa']), 'final' => false]);
    }

    public function receipt(PosOrder $order)
    {
        return view('pos.print.bill', ['o' => $order->load(['liveItems', 'outlet', 'table', 'waiter', 'cashier', 'payments', 'booking.guest', 'villa']), 'final' => true]);
    }

    public function kot(PosOrder $order, Kot $kot)
    {
        abort_unless($kot->pos_order_id === $order->id, 404);
        return view('pos.print.kot', ['o' => $order->load(['table', 'waiter', 'villa']), 'kot' => $kot->load('items')]);
    }
}
