<?php

namespace App\Modules\Operators\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Role;
use App\Models\StayGuest;
use App\Models\TourOperator;
use App\Models\User;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Tour operator accounts: registration & approval, operator logins, rooming lists,
 * payments against city-ledger invoices, statements and commission settlement.
 */
class OperatorService
{
    public function __construct(private InvoiceService $invoices) {}

    /** Self-service registration from the portal (status pending until approved). */
    public function register(array $company, array $login): TourOperator
    {
        return DB::transaction(function () use ($company, $login) {
            $op = TourOperator::create($company + [
                'property_id' => Property::current()->id,
                'code' => DocumentNumberService::next('operator'),
                'status' => 'pending',
            ]);
            $this->createLogin($op, $login['name'], $login['email'], $login['password'], 'active');
            NotificationService::notify('operator.registered', 'New tour operator registration', $op->company_name.' is waiting for approval.',
                route('admin.operators.show', $op), 'info', 'operators.manage');
            return $op;
        });
    }

    public function createLogin(TourOperator $op, string $name, string $email, string $password, string $status = 'active'): User
    {
        $user = User::create([
            'property_id' => $op->property_id, 'user_type' => 'operator', 'tour_operator_id' => $op->id,
            'name' => $name, 'email' => strtolower($email), 'password' => $password, 'status' => $status, 'password_changed_at' => now(),
        ]);
        $user->roles()->attach(Role::where('slug', 'tour_operator')->value('id'));
        AuditService::log('operators', 'login_created', $user, "{$email} for {$op->company_name}");
        return $user;
    }

    public function approve(TourOperator $op): void
    {
        $op->update(['status' => 'active', 'approved_at' => now(), 'approved_by' => auth()->id()]);
        AuditService::log('operators', 'approved', $op, $op->company_name);
    }

    /**
     * Record an operator payment and allocate it oldest-invoice-first (or to one chosen invoice).
     */
    public function recordPayment(TourOperator $op, float $amount, string $method, ?string $reference, ?Invoice $invoice = null, ?UploadedFile $proof = null, ?string $notes = null): Payment
    {
        if ($amount <= 0) throw new BusinessRuleException('Amount must be greater than zero.');
        return DB::transaction(function () use ($op, $amount, $method, $reference, $invoice, $proof, $notes) {
            $proofPath = $proof ? \App\Modules\Core\Services\UploadService::privateDocument($proof, 'payment-proofs') : null;
            $remaining = $amount;
            $targets = $invoice ? collect([$invoice]) : $op->invoices()->where('type', 'operator_invoice')->whereIn('status', ['issued', 'partially_paid'])->orderBy('issued_at')->get();
            $first = null;
            foreach ($targets as $inv) {
                if ($remaining <= 0.009) break;
                $portion = min($remaining, (float) $inv->balance);
                if ($portion <= 0) continue;
                $p = $this->payment($op, $portion, $method, $reference, $proofPath, $notes, $inv);
                $this->invoices->applyPayment($inv, $p);
                $first ??= $p;
                $remaining = round($remaining - $portion, 2);
            }
            if ($remaining > 0.009) {
                // Unallocated: kept on account (deposit for future bookings)
                $p = $this->payment($op, $remaining, $method, $reference, $proofPath, trim(($notes ?? '').' (on account)'), null);
                $first ??= $p;
            }
            return $first;
        });
    }

    /** Deposit paid by the operator against a specific upcoming booking. */
    public function recordDeposit(Booking $booking, float $amount, string $method, ?string $reference): Payment
    {
        $folio = app(\App\Modules\Billing\Services\FolioService::class)->folioFor($booking, 'room');
        $p = app(\App\Modules\Billing\Services\FolioService::class)->recordPayment($folio, $method, $amount, 'deposit', 'Operator deposit '.($reference ?? ''));
        $this->invoices->issueReceipt($p);
        return $p;
    }

    /** Import rooming list rows: [villa_code|booking_villa_id, first_name, last_name, nationality, passport_no, flight_details, is_child]. */
    public function importRoomingList(Booking $booking, array $rows): int
    {
        $cutoff = $booking->contract?->rooming_cutoff_days ?? 3;
        if (auth()->user()?->isOperator() && now()->startOfDay()->diffInDays($booking->arrival, false) < $cutoff) {
            throw new BusinessRuleException("The rooming list locked {$cutoff} days before arrival. Contact reservations for changes.");
        }
        $villas = $booking->activeVillas()->with('villa')->get();
        $n = 0;
        DB::transaction(function () use ($rows, $villas, &$n) {
            foreach ($rows as $row) {
                $bv = $villas->first(fn ($v) => strcasecmp($v->villa->code, (string) ($row['villa'] ?? '')) === 0 || (string) $v->id === (string) ($row['villa'] ?? ''))
                    ?? $villas->first();
                if (! $bv || empty($row['first_name'])) continue;
                StayGuest::create([
                    'booking_villa_id' => $bv->id, 'first_name' => $row['first_name'], 'last_name' => $row['last_name'] ?? '',
                    'nationality' => $row['nationality'] ?? null, 'passport_no' => $row['passport_no'] ?? null,
                    'flight_details' => $row['flight_details'] ?? null, 'is_child' => filter_var($row['is_child'] ?? false, FILTER_VALIDATE_BOOL),
                    'notes' => $row['notes'] ?? null,
                ]);
                $n++;
            }
        });
        return $n;
    }

    public function statement(TourOperator $op, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->subMonths(3)->startOfMonth()->toDateString();
        $to ??= now()->toDateString();
        $invoices = $op->invoices()->where('type', 'operator_invoice')->whereBetween('issued_at', [$from, $to.' 23:59:59'])->get()
            ->map(fn ($i) => ['date' => $i->issued_at, 'ref' => $i->number, 'type' => 'Invoice', 'debit' => (float) $i->grand_total, 'credit' => 0]);
        $payments = Payment::where('tour_operator_id', $op->id)->where('method', '!=', 'city_ledger')->whereBetween('paid_at', [$from, $to.' 23:59:59'])->get()
            ->map(fn ($p) => ['date' => $p->paid_at, 'ref' => $p->reference, 'type' => 'Payment ('.$p->methodLabel().')', 'debit' => 0, 'credit' => (float) $p->amount]);
        $lines = $invoices->concat($payments)->sortBy('date')->values();
        $running = 0;
        $lines = $lines->map(function ($l) use (&$running) {
            $running += $l['debit'] - $l['credit'];
            return $l + ['balance' => round($running, 2)];
        });

        $ageing = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
        foreach ($op->invoices()->where('type', 'operator_invoice')->whereIn('status', ['issued', 'partially_paid'])->get() as $inv) {
            $days = $inv->due_date ? max(0, (int) $inv->due_date->diffInDays(now(), false)) : 0;
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
            $ageing[$bucket] += (float) $inv->balance;
        }
        return ['from' => $from, 'to' => $to, 'lines' => $lines, 'outstanding' => $op->outstanding(), 'ageing' => $ageing];
    }

    private function payment(TourOperator $op, float $amount, string $method, ?string $reference, ?string $proofPath, ?string $notes, ?Invoice $invoice): Payment
    {
        $p = Payment::create([
            'reference' => DocumentNumberService::next('payment'), 'tour_operator_id' => $op->id, 'invoice_id' => $invoice?->id,
            'booking_id' => $invoice?->booking_id, 'type' => 'payment', 'method' => $method, 'amount' => round($amount, 2), 'currency' => config('vaasal.currency'),
            'gateway_ref' => null, 'status' => 'completed', 'proof_path' => $proofPath,
            'notes' => trim(($reference ? 'Ref '.$reference.' ' : '').($notes ?? '')), 'received_by' => auth()->id(), 'paid_at' => now(),
        ]);
        AuditService::log('operators', 'payment', $p, "{$op->company_name} ".money($amount).($invoice ? ' → '.$invoice->number : ''));
        return $p;
    }
}
