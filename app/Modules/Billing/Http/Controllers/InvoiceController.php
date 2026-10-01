<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\DocumentMail;
use App\Models\Invoice;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['booking.guest', 'operator'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', '%'.$request->query('q').'%')->orWhere('bill_to_name', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('from'), fn ($q) => $q->where('issued_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('issued_at', '<=', $request->query('to').' 23:59:59'))
            ->latest('issued_at')->paginate(25)->withQueryString();
        return view('admin.billing.invoices', ['invoices' => $invoices]);
    }

    public function show(Invoice $invoice)
    {
        return view('print.invoice', ['inv' => $invoice->load(['booking.guest', 'operator', 'payments', 'issuer']), 'copy' => $invoice->created_at->lt(now()->subMinutes(2))]);
    }

    public function email(Request $request, Invoice $invoice)
    {
        $to = $request->validate(['email' => ['required', 'email']])['email'];
        Mail::to($to)->send(new DocumentMail($invoice->typeLabel().' '.$invoice->number, 'print.partials.invoice-body', ['inv' => $invoice->load(['booking.guest', 'operator', 'payments']), 'copy' => true]));
        AuditService::log('invoices', 'emailed', $invoice, 'to '.$to);
        return back()->with('success', $invoice->number.' emailed to '.$to.'.');
    }
}
