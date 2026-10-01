{{-- Drawer reconciliation + tender totals for a shift report ($r from PosShiftService::report). --}}
@php
    $variance = $r['variance'];
    $vTone = $variance === null ? '' : (abs($variance) < 1 ? 'var(--ok)' : 'var(--crit)');
@endphp
<div class="recon-grid">
    <div class="recon">
        <h3 class="recon-title"><x-icon name="drawer" /> Cash drawer</h3>
        <div class="recon-row"><span>Opening float</span><span>{{ money($r['opening_float']) }}</span></div>
        <div class="recon-row plus"><span>Cash sales</span><span>{{ money($r['cash_sales']) }}</span></div>
        <div class="recon-row plus"><span>Cash in</span><span>{{ money($r['cash_in']) }}</span></div>
        <div class="recon-row minus"><span>Cash out / paid-outs</span><span>{{ money($r['cash_out']) }}</span></div>
        <div class="recon-row minus"><span>Cash refunds</span><span>{{ money($r['cash_refunds']) }}</span></div>
        @if ($r['deposits'] > 0)<div class="recon-row minus"><span>Bank deposits / safe drops</span><span>{{ money($r['deposits']) }}</span></div>@endif
        @if (abs($r['adjustments']) > 0.009)<div class="recon-row"><span>Adjustments</span><span>{{ money($r['adjustments']) }}</span></div>@endif
        <div class="recon-row total"><span>Expected cash</span><span>{{ money($r['expected_cash']) }}</span></div>
        @if ($r['counted_cash'] !== null)
            <div class="recon-row"><span>Actual cash counted</span><span>{{ money($r['counted_cash']) }}</span></div>
            <div class="recon-row total" style="color:{{ $vTone }}"><span>Variance</span><span>{{ $variance > 0 ? '+' : '' }}{{ money($variance) }}</span></div>
        @endif
    </div>
    <div class="recon">
        <h3 class="recon-title"><x-icon name="wallet" /> Payment totals</h3>
        <div class="recon-row"><span><x-icon name="cash" /> Cash (net of refunds)</span><span>{{ money($r['by_method']['cash'] ?? 0) }}</span></div>
        <div class="recon-row"><span><x-icon name="card" /> Card</span><span>{{ money($r['card_total']) }}</span></div>
        <div class="recon-row"><span><x-icon name="bank" /> Bank transfer</span><span>{{ money($r['bank_total']) }}</span></div>
        <div class="recon-row"><span><x-icon name="qr" /> Digital / online</span><span>{{ money($r['digital_total']) }}</span></div>
        <div class="recon-row"><span><x-icon name="villa" /> Room charges</span><span>{{ money($r['room_charges']) }}</span></div>
        <div class="recon-row minus"><span>Refunds (all tenders)</span><span>{{ money($r['refunds']) }}</span></div>
        <div class="recon-row total"><span>Grand sales total</span><span>{{ money($r['grand_total']) }}</span></div>
        <div class="recon-row muted small"><span>{{ $r['orders'] }} check(s) · net sales {{ money($r['net_sales']) }}</span><span>tax {{ money($r['tax']) }}</span></div>
    </div>
</div>
