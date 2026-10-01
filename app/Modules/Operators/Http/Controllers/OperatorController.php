<?php

namespace App\Modules\Operators\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\ContractRate;
use App\Models\Invoice;
use App\Models\OperatorContract;
use App\Models\Property;
use App\Models\TourOperator;
use App\Models\VillaType;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\UploadService;
use App\Modules\Operators\Http\Requests\OperatorRequest;
use App\Modules\Operators\Services\OperatorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class OperatorController extends Controller
{
    public function __construct(private OperatorService $operators) {}

    public function index(Request $request)
    {
        $ops = TourOperator::withCount(['bookings as active_bookings' => fn ($q) => $q->whereIn('status', ['confirmed', 'checked_in', 'tentative'])])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('company_name')->get();
        return view('admin.operators.index', ['operators' => $ops]);
    }

    public function create()
    {
        return view('admin.operators.form', ['op' => new TourOperator(['status' => 'active', 'payment_terms_days' => 30])]);
    }

    public function store(OperatorRequest $request)
    {
        $data = $request->validated();
        unset($data['logo']);
        $op = TourOperator::create($data + ['property_id' => Property::current()->id, 'code' => DocumentNumberService::next('operator'), 'status' => 'active',
            'approved_at' => now(), 'approved_by' => auth()->id(), 'logo_path' => $request->hasFile('logo') ? UploadService::image($request->file('logo'), 'operators') : null]);
        AuditService::log('operators', 'created', $op, $op->company_name);
        return redirect()->route('admin.operators.show', $op)->with('success', 'Tour operator created. Add a contract and a login next.');
    }

    public function show(TourOperator $operator)
    {
        $operator->load(['contracts.rates.villaType', 'users', 'approver']);
        return view('admin.operators.show', [
            'op' => $operator,
            'bookings' => $operator->bookings()->with(['guest', 'activeVillas.villa'])->latest('arrival')->limit(20)->get(),
            'invoices' => $operator->invoices()->where('type', 'operator_invoice')->latest('issued_at')->get(),
            'payments' => $operator->payments()->where('method', '!=', 'city_ledger')->latest('paid_at')->limit(15)->get(),
            'commissions' => Commission::with('booking')->where('tour_operator_id', $operator->id)->latest()->limit(30)->get(),
            'types' => VillaType::where('is_active', true)->orderBy('sort_order')->get(),
            'statement' => $this->operators->statement($operator),
        ]);
    }

    public function edit(TourOperator $operator)
    {
        return view('admin.operators.form', ['op' => $operator]);
    }

    public function update(OperatorRequest $request, TourOperator $operator)
    {
        $data = $request->validated();
        unset($data['logo']);
        if ($request->hasFile('logo')) $data['logo_path'] = UploadService::image($request->file('logo'), 'operators');
        $operator->fill($data);
        AuditService::logChanges('operators', $operator);
        $operator->save();
        return redirect()->route('admin.operators.show', $operator)->with('success', 'Company details saved.');
    }

    public function approve(TourOperator $operator)
    {
        $this->operators->approve($operator);
        return back()->with('success', $operator->company_name.' approved. Their users can now sign in to the partner portal.');
    }

    public function suspend(TourOperator $operator)
    {
        $operator->update(['status' => $operator->status === 'suspended' ? 'active' : 'suspended']);
        AuditService::log('operators', $operator->status, $operator);
        return back()->with('success', 'Operator '.($operator->status === 'suspended' ? 'suspended — portal access blocked.' : 'reactivated.'));
    }

    public function addUser(Request $request, TourOperator $operator)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::defaults()]]);
        $this->operators->createLogin($operator, $data['name'], $data['email'], $data['password']);
        return back()->with('success', 'Portal login created for '.$data['email'].'.');
    }

    public function storeContract(Request $request, TourOperator $operator)
    {
        $data = $this->contract($request);
        $c = $operator->contracts()->create($data['contract']);
        $this->saveRates($c, $data['rates']);
        AuditService::log('operators', 'contract_created', $c, $operator->company_name.' '.$c->name);
        return back()->with('success', 'Contract saved.');
    }

    public function updateContract(Request $request, OperatorContract $contract)
    {
        $data = $this->contract($request);
        $contract->fill($data['contract']);
        AuditService::logChanges('operators', $contract, 'contract_updated');
        $contract->save();
        $this->saveRates($contract, $data['rates']);
        return back()->with('success', 'Contract updated.');
    }

    public function payment(Request $request, TourOperator $operator)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(['bank_transfer', 'cheque', 'cash', 'card'])],
            'reference' => ['nullable', 'string', 'max:80'], 'invoice_id' => ['nullable', 'exists:invoices,id'],
            'proof' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')], 'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $invoice = ! empty($data['invoice_id']) ? Invoice::where('tour_operator_id', $operator->id)->findOrFail($data['invoice_id']) : null;
        $p = $this->operators->recordPayment($operator, (float) $data['amount'], $data['method'], $data['reference'] ?? null, $invoice, $request->file('proof'), $data['notes'] ?? null);
        return back()->with('success', 'Payment '.$p->reference.' of '.money($data['amount']).' recorded and allocated.');
    }

    public function settleCommissions(Request $request, TourOperator $operator)
    {
        $ids = $request->validate(['commission_ids' => ['required', 'array'], 'commission_ids.*' => ['integer']])['commission_ids'];
        $n = Commission::where('tour_operator_id', $operator->id)->whereIn('id', $ids)->where('status', 'accrued')->update(['status' => 'settled', 'settled_at' => now()]);
        AuditService::log('operators', 'commission_settled', $operator, $n.' commission line(s) settled');
        return back()->with('success', $n.' commission line(s) marked as settled.');
    }

    public function statement(TourOperator $operator, Request $request)
    {
        return view('print.operator-statement', ['op' => $operator, 's' => $this->operators->statement($operator, $request->query('from'), $request->query('to'))]);
    }

    private function contract(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'valid_from' => ['required', 'date'], 'valid_to' => ['required', 'date', 'after:valid_from'],
            'commission_pct' => ['required', 'numeric', 'min:0', 'max:50'], 'discount_pct' => ['required', 'numeric', 'min:0', 'max:60'],
            'deposit_pct' => ['required', 'numeric', 'min:0', 'max:100'], 'release_days' => ['required', 'integer', 'min:0', 'max:120'],
            'rooming_cutoff_days' => ['required', 'integer', 'min:0', 'max:60'], 'notes' => ['nullable', 'string', 'max:1000'],
            'rates' => ['nullable', 'array'], 'rates.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $rates = $data['rates'] ?? [];
        unset($data['rates']);
        return ['contract' => $data + ['is_active' => $request->boolean('is_active', true)], 'rates' => $rates];
    }

    private function saveRates(OperatorContract $c, array $rates): void
    {
        foreach ($rates as $typeId => $rate) {
            if ($rate === null || $rate === '') {
                ContractRate::where('operator_contract_id', $c->id)->where('villa_type_id', $typeId)->delete();
            } else {
                ContractRate::updateOrCreate(['operator_contract_id' => $c->id, 'villa_type_id' => $typeId], ['net_rate' => $rate]);
            }
        }
    }
}
