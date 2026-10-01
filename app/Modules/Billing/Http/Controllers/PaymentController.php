<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $q = Payment::with(['booking.guest', 'operator', 'receiver', 'invoice'])
            ->when($request->filled('method'), fn ($w) => $w->where('method', $request->query('method')))
            ->when($request->filled('type'), fn ($w) => $w->where('type', $request->query('type')))
            ->when($request->filled('from'), fn ($w) => $w->where('paid_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($w) => $w->where('paid_at', '<=', $request->query('to').' 23:59:59'))
            ->when($request->filled('q'), fn ($w) => $w->where(fn ($s) => $s->where('reference', 'like', '%'.$request->query('q').'%')
                ->orWhere('gateway_ref', 'like', '%'.$request->query('q').'%')
                ->orWhereHas('booking', fn ($b) => $b->where('reference', 'like', '%'.$request->query('q').'%'))));

        $totals = (clone $q)->where('method', '!=', 'city_ledger')->selectRaw('method, SUM(amount) total, COUNT(*) n')->groupBy('method')->get();

        return view('admin.billing.payments', ['payments' => $q->latest('paid_at')->paginate(25)->withQueryString(), 'totals' => $totals]);
    }

    /** Payment proof (bank slip) is stored privately and streamed only to authorised users. */
    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);
        return Storage::disk('local')->response($payment->proof_path);
    }
}
