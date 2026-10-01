<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Modules\POS\Services\PosBillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderHistoryController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $orders = PosOrder::with(['outlet', 'table', 'waiter', 'cashier', 'villa'])
            ->when($request->filled('outlet'), fn ($q) => $q->where('outlet_id', $request->query('outlet')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('order_no', 'like', '%'.$request->query('q').'%')->orWhere('invoice_no', 'like', '%'.$request->query('q').'%')))
            ->when(! $request->filled('q'), fn ($q) => $q->whereDate('opened_at', $date))
            ->latest('opened_at')->paginate(30)->withQueryString();
        return view('pos.orders', ['orders' => $orders, 'date' => $date, 'outlets' => Outlet::pluck('name', 'id')]);
    }

    public function refund(Request $request, PosOrder $order, PosBillingService $billing)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(array_keys(\App\Models\PosPayment::METHODS))], 'reason' => ['required', 'string', 'max:200']]);
        $billing->refund($order, (float) $data['amount'], $data['method'], $data['reason']);
        return back()->with('success', 'Refund of '.money($data['amount']).' recorded on '.$order->invoice_no.'.');
    }
}
