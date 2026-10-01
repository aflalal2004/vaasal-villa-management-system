@extends('layouts.pos')
@section('title', 'Day-end closing')
@section('content')
<x-page-header title="Day-end closing" sub="Count the drawer, confirm the variance and lock the shift. The closing report can be printed straight after." />
@include('pos.partials.cash-tabs')

<ol class="wizard-steps" aria-label="Closing steps">
    @foreach (['Select shift', 'Expected amounts', 'Count drawer', 'Counted cash', 'Variance', 'Notes', 'Confirm', 'Print report', 'Manager review'] as $i => $step)
        <li @class(['done' => $shift && $i === 0, 'now' => (! $shift && $i === 0) || ($shift && $i === 1)])><span>{{ $i + 1 }}</span>{{ $step }}</li>
    @endforeach
</ol>

@if (! $shift)
    <div class="card">
        <div class="card-head"><h2>1 · Select the shift to close</h2></div>
        <div class="card-body">
            @forelse ($candidates as $c)
                <a class="shift-pick" href="{{ route('pos.day-end', ['shift' => $c->id]) }}">
                    <x-icon name="drawer" /><span><strong>{{ $c->outlet->name }}</strong> · {{ $c->user->name }}<br><span class="small muted">Opened {{ fmt_dt($c->opened_at) }} · float {{ money($c->opening_float) }}</span></span>
                    <x-icon name="arrow-right" />
                </a>
            @empty
                <x-empty title="No open shift to close" icon="drawer">Open a shift under <a href="{{ route('pos.shifts.index') }}">Shifts</a> first.</x-empty>
            @endforelse
        </div>
    </div>
@else
<form method="post" action="{{ route('pos.shifts.close', $shift) }}" id="dayend" class="stack" data-expected="{{ $r['expected_cash'] }}">
    @csrf
    <div class="card">
        <div class="card-head"><h2>1 · Shift</h2><a class="small" href="{{ route('pos.day-end') }}">Change</a></div>
        <div class="card-body row" style="gap:18px">
            <span><span class="muted small">Cashier</span><br><strong>{{ $shift->user->name }}</strong></span>
            <span><span class="muted small">Outlet</span><br><strong>{{ $shift->outlet->name }}</strong></span>
            <span><span class="muted small">Opened</span><br><strong>{{ fmt_dt($shift->opened_at) }}</strong></span>
            <span><span class="muted small">Closing at</span><br><strong>{{ now()->format('d M Y, H:i') }}</strong></span>
            @if ($billedOpen)<span class="badge tone-warning">{{ $billedOpen }} billed check(s) still unpaid — settle them first if possible</span>@endif
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>2 · System expected amounts</h2></div>
        <div class="card-body">@include('pos.partials.shift-reconciliation')</div>
    </div>

    <div class="grid cols-2">
        <div class="card">
            <div class="card-head"><h2>3 · Count the drawer</h2><span class="small muted">optional, by denomination</span></div>
            <div class="card-body">
                <div class="denoms">
                    @foreach ($denominations as $d)
                        <label class="denom"><span class="denom-v">{{ number_format($d) }}</span><span class="muted">×</span>
                            <input type="number" name="denominations[{{ $d }}]" min="0" max="100000" step="1" inputmode="numeric" data-denom="{{ $d }}" value="{{ old('denominations.'.$d) }}" aria-label="Number of {{ $d }} notes or coins">
                            <span class="denom-sub" data-sub>0.00</span></label>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-ghost" data-clear-denoms><x-icon name="x" /> Clear count</button>
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card-head"><h2>4 · Physical cash counted</h2></div>
                <div class="card-body">
                    <x-input name="counted_cash" type="number" step="0.01" min="0" label="Counted cash (LKR)" required col="f-12" id="counted" help="Filled from the denomination count; type it directly if you counted another way." />
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h2>5 · Variance</h2></div>
                <div class="card-body">
                    <div class="variance-box" data-variance-box>
                        <div><span class="muted small">Expected</span><strong>{{ money($r['expected_cash']) }}</strong></div>
                        <div><span class="muted small">Counted</span><strong data-counted-out>—</strong></div>
                        <div><span class="muted small">Variance</span><strong data-variance-out>—</strong></div>
                    </div>
                    <p class="small muted" data-variance-hint style="margin:8px 0 0">Enter the count to see the variance.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>6 · Closing notes</h2></div>
        <div class="card-body form-grid"><x-textarea name="notes" label="Notes for the manager" rows="2" help="Explain any variance, missing slips or unpaid checks." /></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>7 · Confirm and lock</h2></div>
        <div class="card-body">
            <label class="check"><input type="checkbox" name="confirm" value="1" required> I have counted the drawer and the amount above is correct. The shift will be locked.</label>
            @error('confirm')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="card-foot"><a class="btn" href="{{ route('pos.shifts.show', $shift) }}">X report</a><button class="btn btn-danger btn-lg" type="submit"><x-icon name="lock" /> Close shift</button></div>
    </div>
    <p class="small muted">8 · After closing you can print the A4 or thermal closing report. 9 · A manager then approves or flags the shift from its report page.</p>
</form>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('dayend'), expected = parseFloat(form.dataset.expected), counted = document.getElementById('counted');
    const fmt = n => 'LKR ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const denoms = [...form.querySelectorAll('[data-denom]')];
    function fromDenoms() {
        let total = 0, any = false;
        denoms.forEach(i => { const n = parseInt(i.value || '0', 10) || 0; if (i.value !== '') any = true; const sub = n * parseFloat(i.dataset.denom); total += sub; i.parentElement.querySelector('[data-sub]').textContent = sub.toLocaleString('en-US', { minimumFractionDigits: 2 }); });
        if (any) counted.value = total.toFixed(2);
        variance();
    }
    function variance() {
        const box = form.querySelector('[data-variance-box]'), hint = form.querySelector('[data-variance-hint]');
        if (counted.value === '') { form.querySelector('[data-counted-out]').textContent = '—'; form.querySelector('[data-variance-out]').textContent = '—'; box.className = 'variance-box'; return; }
        const c = parseFloat(counted.value), v = Math.round((c - expected) * 100) / 100;
        form.querySelector('[data-counted-out]').textContent = fmt(c);
        form.querySelector('[data-variance-out]').textContent = (v > 0 ? '+' : '') + fmt(v);
        box.className = 'variance-box ' + (Math.abs(v) < 1 ? 'ok' : v < 0 ? 'short' : 'over');
        hint.textContent = Math.abs(v) < 1 ? 'Drawer balances.' : v < 0 ? 'Drawer is SHORT. Recount, then explain in the notes.' : 'Drawer is OVER. Recount, then explain in the notes.';
    }
    denoms.forEach(i => i.addEventListener('input', fromDenoms));
    counted.addEventListener('input', variance);
    form.querySelector('[data-clear-denoms]').addEventListener('click', () => { denoms.forEach(i => i.value = ''); counted.value = ''; fromDenoms(); });
    fromDenoms();
})();
</script>
@endpush
@endif
@endsection
