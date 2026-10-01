@extends('layouts.admin')
@section('title', $b->reference)
@section('content')
@php
    $paid = $summaries->sum('paid');
    $charges = $summaries->sum('charges');
    $balanceVsBooking = round((float) $b->grand_total - $paid, 2);
@endphp
<x-page-header :title="$b->reference.' · '.$b->guest->fullName()" :crumbs="['Bookings' => route('admin.bookings.index')]"
    :sub="fmt_date($b->arrival, 'D d M Y').' → '.fmt_date($b->departure, 'D d M Y').' · '.$b->nights().' night(s) · '.($b->operator?->company_name ?? $b->channel->name).($b->external_ref ? ' · '.$b->external_ref : '')">
    <x-badge :status="$b->status" :label="$b->statusLabel()" style="align-self:center" />
    @if (in_array($b->status, ['confirmed', 'tentative']) && ! $b->arrival->isFuture())
        @perm('frontdesk.checkin')<a class="btn btn-primary" href="{{ route('admin.frontdesk.checkin', $b) }}"><x-icon name="login" /> Check in</a>@endperm
    @endif
    @if ($b->status === 'checked_in')
        @perm('frontdesk.checkout')<a class="btn btn-primary" href="{{ route('admin.frontdesk.checkout', $b) }}"><x-icon name="logout" /> Check out</a>@endperm
    @endif
    <a class="btn" href="{{ route('admin.bookings.voucher', $b) }}" target="_blank"><x-icon name="file" /> Voucher</a>
    <div class="dropdown">
        <button class="btn" type="button" data-dropdown>More ▾</button>
        <div class="dropdown-menu" hidden>
            <a href="{{ route('admin.bookings.confirmation', $b) }}" target="_blank"><x-icon name="printer" /> Booking confirmation</a>
            <a href="{{ route('admin.bookings.registration', $b) }}" target="_blank"><x-icon name="id" /> Registration card</a>
            @perm('bookings.manage')
                @if ($b->isLive())
                    <button type="button" data-modal-open="#dates-modal"><x-icon name="calendar" /> Change dates</button>
                    <form method="post" action="{{ route('admin.bookings.send', $b) }}">@csrf<button type="submit"><x-icon name="mail" /> Email confirmation & voucher</button></form>
                    <button type="button" data-modal-open="#link-modal"><x-icon name="link" /> Create payment link</button>
                    <form method="post" action="{{ route('admin.bookings.proforma', $b) }}">@csrf<button type="submit"><x-icon name="file" /> Issue pro-forma invoice</button></form>
                @endif
                @if (in_array($b->status, ['tentative', 'hold']))
                    <form method="post" action="{{ route('admin.bookings.confirm', $b) }}">@csrf<button type="submit"><x-icon name="check" /> Confirm booking</button></form>
                @endif
                @if ($b->status === 'confirmed' && $b->arrival->isPast())
                    <form method="post" action="{{ route('admin.bookings.no-show', $b) }}" data-confirm="Mark as no-show? Remaining nights are released and the no-show fee per policy is posted." data-danger>@csrf<button type="submit"><x-icon name="x" /> Mark no-show</button></form>
                @endif
            @endperm
            @perm('bookings.cancel')
                @if (in_array($b->status, ['hold', 'tentative', 'confirmed']))
                    <div class="sep"></div>
                    <button type="button" data-modal-open="#cancel-modal" style="color:var(--crit)"><x-icon name="trash" /> Cancel booking</button>
                @endif
            @endperm
        </div>
    </div>
</x-page-header>

@if (session('payment_link'))
    <div class="alert alert-info">Payment link: <code>{{ session('payment_link') }}</code> <button class="btn btn-sm" type="button" data-copy="{{ session('payment_link') }}">Copy</button>
        <a class="btn btn-sm" href="{{ whatsapp_link('Hello '.$b->guest->first_name.', here is your secure payment link for booking '.$b->reference.': '.session('payment_link'), $b->guest->phone) }}" target="_blank"><x-icon name="whatsapp" /> Send on WhatsApp</a></div>
@endif

<div class="grid cols-main">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Villas & rooming list</h2></div>
            @foreach ($b->villas as $bv)
                <div class="card-body" style="border-bottom:1px solid var(--line-2)">
                    <div class="row between">
                        <div>
                            <strong style="font-family:var(--serif);font-size:18px">{{ $bv->villa->code }} · {{ $bv->villa->name }}</strong>
                            @if ($bv->status === 'cancelled')<x-badge status="cancelled" />@endif
                            <div class="small muted">{{ $bv->villaType->name }} · {{ $bv->adults }} adult(s){{ $bv->children ? ', '.$bv->children.' child(ren)' : '' }} ·
                                {{ fmt_date($bv->arrival, 'd M') }} → {{ fmt_date($bv->departure, 'd M') }} · room total {{ money($bv->total) }}</div>
                        </div>
                        <div class="row">
                            <x-badge :status="$bv->villa->hk_status" :label="'HK: '.\App\Models\Villa::HK_LABELS[$bv->villa->hk_status]" />
                            @if ($bv->status === 'active' && $b->isLive())
                                @perm('bookings.manage')<button class="btn btn-sm" type="button" data-modal-open="#move-modal-{{ $bv->id }}"><x-icon name="transfer" /> Move</button>@endperm
                            @endif
                        </div>
                    </div>
                    <details style="margin-top:8px"><summary class="small muted" style="cursor:pointer">Nightly rates</summary>
                        <div class="row small" style="margin-top:6px">@foreach ($bv->nightly_rates as $d => $r)<span class="chip">{{ fmt_date($d, 'd M') }} · {{ money($r, null, false) }}</span>@endforeach</div>
                    </details>
                    <div class="table-wrap" style="margin-top:10px">
                        <table class="table">
                            <thead><tr><th>Guest</th><th>Nationality</th><th>Passport</th><th>Flight</th><th></th></tr></thead>
                            <tbody>
                            @forelse ($bv->guests as $sg)
                                <tr><td>{{ $sg->fullName() }} @if($sg->is_primary)<x-badge tone="accent" label="Lead" />@endif @if($sg->is_child)<x-badge tone="neutral" label="Child" />@endif</td>
                                    <td>{{ $sg->nationality ?? '—' }}</td><td class="mono small">{{ $sg->passport_no ? '••••'.substr($sg->passport_no, -4) : '—' }}</td><td class="small">{{ $sg->flight_details ?? '—' }}</td>
                                    <td class="actions">@perm('bookings.manage')<form method="post" action="{{ route('admin.bookings.stay-guests.destroy', [$b, $sg]) }}" data-confirm="Remove {{ $sg->fullName() }} from the rooming list?">@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit"><x-icon name="x" /></button></form>@endperm</td></tr>
                            @empty
                                <tr><td colspan="5" class="muted small">No additional guests registered.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @perm('bookings.manage')
                    <form method="post" action="{{ route('admin.bookings.stay-guests', $b) }}" class="filters" style="margin:10px 0 0">
                        @csrf <input type="hidden" name="booking_villa_id" value="{{ $bv->id }}">
                        <div class="field"><label>First name</label><input name="first_name" required maxlength="80"></div>
                        <div class="field"><label>Last name</label><input name="last_name" required maxlength="80"></div>
                        <div class="field"><label>Nationality</label><input name="nationality" maxlength="80"></div>
                        <div class="field"><label>Passport</label><input name="passport_no" maxlength="40"></div>
                        <label class="check"><input type="hidden" name="is_child" value="0"><input type="checkbox" name="is_child" value="1"> Child</label>
                        <button class="btn btn-sm" type="submit"><x-icon name="plus" /> Add guest</button>
                    </form>
                    @endperm
                </div>

                @if ($bv->status === 'active')
                <x-modal :id="'move-modal-'.$bv->id" :title="'Move '.$bv->villa->code.' to another villa'">
                    <form method="post" action="{{ route('admin.bookings.move', [$b, $bv]) }}">
                        @csrf
                        <div class="modal-body form-grid">
                            @if (empty($moveOptions[$bv->id]))
                                <p class="f-12 muted">No other villa is free for {{ $b->status === 'checked_in' ? 'the remaining nights' : 'these dates' }}.</p>
                            @else
                                <x-select name="villa_id" label="New villa" :options="$moveOptions[$bv->id]" required col="f-12" />
                                <x-input name="reason" label="Reason" col="f-12" placeholder="e.g. Guest request, AC fault" />
                                @if ($b->status === 'checked_in')<p class="small muted f-12" style="margin:0">The current villa becomes Dirty with a housekeeping task. Remember to issue new key cards.</p>@endif
                            @endif
                        </div>
                        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button>@if(! empty($moveOptions[$bv->id]))<button class="btn btn-primary" type="submit">Move villa</button>@endif</div>
                    </form>
                </x-modal>
                @endif
            @endforeach
        </div>

        @include('admin.partials.folios')

        @if ($posOrders->isNotEmpty())
        <div class="card">
            <div class="card-head"><h2>Restaurant checks linked to this stay</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Check</th><th>Outlet</th><th>Opened</th><th>Status</th><th class="num">Total</th></tr></thead>
                <tbody>@foreach ($posOrders as $o)
                    <tr><td class="mono">{{ $o->order_no }}</td><td>{{ $o->outlet->name }}</td><td>{{ fmt_dt($o->opened_at) }}</td><td><x-badge :status="$o->status" :label="$o->statusLabel()" /></td><td class="num">{{ money($o->total) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </div>
        @endif

        <div class="card">
            <div class="card-head"><h2>Key cards</h2>
                @if (in_array($b->status, ['confirmed', 'checked_in']))
                    @perm('keycards.issue')<button class="btn btn-sm" type="button" data-modal-open="#card-modal"><x-icon name="key" /> Issue card</button>@endperm
                @endif
            </div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Card</th><th>Villa</th><th>Type</th><th>Valid</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($b->cardAssignments->sortByDesc('id') as $a)
                    <tr>
                        <td><a class="mono" href="{{ route('admin.keycards.show', $a->card) }}">{{ $a->card->uid }}</a><div class="small muted">{{ $a->card->card_number }}</div></td>
                        <td>{{ $a->villa?->code }}</td><td>{{ ucfirst($a->issue_type) }}</td>
                        <td class="small nowrap">{{ fmt_dt($a->valid_from) }}<br>→ {{ fmt_dt($a->valid_to) }}</td>
                        <td><x-badge :status="$a->status" />@if($a->revoke_reason)<div class="small muted">{{ $a->revoke_reason }}</div>@endif</td>
                        <td class="actions">
                            @if ($a->status === 'active')
                                @perm('keycards.issue')
                                <form method="post" action="{{ route('admin.keycards.revoke', $a) }}" data-confirm="Revoke card {{ $a->card->uid }}? It will stop opening the door." data-danger style="display:inline">@csrf<button class="btn btn-sm" type="submit">Revoke</button></form>
                                <form method="post" action="{{ route('admin.keycards.lost', $a->card) }}" data-confirm="Report card {{ $a->card->uid }} lost? It is blocked immediately." data-danger style="display:inline">@csrf<button class="btn btn-sm" type="submit">Lost</button></form>
                                @endperm
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted small">No cards issued.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="stack" style="align-content:start">
        <div class="card">
            <div class="card-head"><h2>Guest</h2>@perm('guests.view')<a class="small" href="{{ route('admin.guests.show', $b->guest) }}">Profile</a>@endperm</div>
            <div class="card-body">
                <dl class="dl">
                    <dt>Name</dt><dd>{{ $b->guest->fullName() }} @if($b->guest->is_vip)<x-badge tone="accent" label="VIP" />@endif @if($b->guest->is_blacklisted)<x-badge tone="danger" label="Blacklisted" />@endif</dd>
                    <dt>Email</dt><dd>{{ $b->guest->email ?? '—' }}</dd>
                    <dt>Phone</dt><dd>{{ $b->guest->phone ?? '—' }} @if($b->guest->phone)<a href="{{ whatsapp_link('Hello '.$b->guest->first_name.' — Vaasal Villa reservations here regarding booking '.$b->reference.'.', $b->guest->phone) }}" target="_blank" title="WhatsApp"><x-icon name="whatsapp" style="width:16px;height:16px;vertical-align:-3px" /></a>@endif</dd>
                    <dt>Country</dt><dd>{{ $b->guest->country ?? '—' }}</dd>
                    <dt>ID</dt><dd>{{ $b->guest->id_type ? ucfirst($b->guest->id_type).' '.$b->guest->maskedId() : 'Not captured' }}</dd>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Charges & payments</h2></div>
            <div class="card-body">
                <div class="totals">
                    <span class="muted">Room total</span><span>{{ money($b->room_total) }}</span>
                    @if ($b->discount_total > 0)<span class="muted">Discount {{ $b->offer ? '('.$b->offer->promo_code.')' : '' }}</span><span>− {{ money($b->discount_total) }}</span>@endif
                    <span class="muted">Service charge</span><span>{{ money($b->service_total) }}</span>
                    <span class="muted">Tax</span><span>{{ money($b->tax_total) }}</span>
                    <span class="grand">Booking total</span><span class="grand">{{ money($b->grand_total) }}</span>
                    <span class="muted">Posted to folios</span><span>{{ money($charges) }}</span>
                    <span class="muted">Paid</span><span style="color:var(--ok)">{{ money($paid) }}</span>
                    <span class="muted">Deposit due</span><span>{{ money($b->deposit_due) }}</span>
                    <span><strong>Balance vs booking</strong></span><span><strong>{{ money(max(0, $balanceVsBooking)) }}</strong></span>
                    @if ($b->commission_pct > 0)<span class="muted">Commission ({{ $b->commission_pct }}%)</span><span>{{ money($b->commission_amount) }}</span>@endif
                    @if ($b->cancellation_fee > 0)<span class="muted">Cancellation fee</span><span>{{ money($b->cancellation_fee) }}</span>@endif
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Details</h2></div>
            <div class="card-body">
                <dl class="dl" style="margin-bottom:12px">
                    <dt>Rate plan</dt><dd>{{ $b->ratePlan?->name ?? '—' }}</dd>
                    <dt>Payment</dt><dd>{{ label($b->payment_mode) }}</dd>
                    <dt>Created</dt><dd>{{ fmt_dt($b->created_at) }}{{ $b->creator ? ' by '.$b->creator->name : '' }}</dd>
                    @if ($b->contract)<dt>Contract</dt><dd>{{ $b->contract->name }}</dd>@endif
                    @if ($b->cancel_reason)<dt>Cancelled</dt><dd>{{ $b->cancel_reason }}</dd>@endif
                </dl>
                @perm('bookings.manage')
                <form method="post" action="{{ route('admin.bookings.notes', $b) }}" class="form-grid">
                    @csrf
                    <x-input name="group_name" label="Group name" :value="$b->group_name" col="f-6" />
                    <x-input name="arrival_time" label="Arrival time" :value="$b->arrival_time" col="f-6" />
                    <x-textarea name="special_requests" label="Special requests" :value="$b->special_requests" rows="3" />
                    <div class="f-12"><button class="btn btn-sm" type="submit">Save details</button></div>
                </form>
                @else
                    <p class="small">{{ $b->special_requests ?: 'No special requests.' }}</p>
                @endperm
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>History</h2></div>
            <div class="card-body">
                <ul class="timeline">
                    @foreach ($b->events as $e)
                        <li><div><strong>{{ label($e->event) }}</strong> · <span class="muted">{{ fmt_dt($e->created_at) }}{{ $e->user ? ' · '.$e->user->name : '' }}</span><br>{{ $e->description }}</div></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<x-modal id="dates-modal" title="Change stay dates">
    <form method="post" action="{{ route('admin.bookings.dates', $b) }}">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="arrival" type="date" label="Arrival" :value="$b->arrival" required :readonly="$b->status === 'checked_in'" />
            <x-input name="departure" type="date" label="Departure" :value="$b->departure" required />
            <x-input name="reason" label="Reason" col="f-12" />
            <p class="small muted f-12" style="margin:0">The same villa is kept; if it is not free for the new dates you'll be told and nothing changes. The stay is re-priced.</p>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Update dates</button></div>
    </form>
</x-modal>

<x-modal id="cancel-modal" title="Cancel booking {{ $b->reference }}">
    <form method="post" action="{{ route('admin.bookings.cancel', $b) }}">
        @csrf
        <div class="modal-body form-grid">
            <p class="f-12" style="margin:0">Policy fee for cancelling today: <strong>{{ money($cancelFee) }}</strong> ({{ $b->ratePlan?->is_refundable ? 'free until '.$b->ratePlan->free_cancel_days.' days before arrival' : 'non-refundable rate' }}). You can adjust it.</p>
            <x-input name="fee" type="number" step="0.01" min="0" label="Cancellation fee" :value="$cancelFee" col="f-6" />
            <x-input name="reason" label="Reason" required col="f-12" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Keep booking</button><button class="btn btn-danger" type="submit">Cancel booking</button></div>
    </form>
</x-modal>

<x-modal id="link-modal" title="Create a secure payment link">
    <form method="post" action="{{ route('admin.bookings.payment-link', $b) }}">
        @csrf
        <div class="modal-body form-grid">
            <x-input name="amount" type="number" step="0.01" min="1" label="Amount" :value="max(0, $balanceVsBooking)" required col="f-12"
                help="The guest pays on the payment provider's hosted page; card details never reach our server." />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Create link</button></div>
    </form>
</x-modal>

<x-modal id="card-modal" title="Issue key card">
    <form method="post" action="{{ route('admin.keycards.issue', $b) }}">
        @csrf
        <div class="modal-body form-grid">
            <x-select name="booking_villa_id" label="Villa" :options="$b->villas->where('status', 'active')->mapWithKeys(fn ($bv) => [$bv->id => $bv->villa->code.' — '.$bv->villa->name])" required col="f-12" />
            <x-input name="uid" label="Card UID" required col="f-8" help="Place the card on the encoder and scan, or type the UID printed on the card." autocomplete="off" />
            <x-select name="issue_type" label="Type" :options="['new' => 'New key (cancels earlier cards)', 'duplicate' => 'Duplicate (additional card)']" col="f-4" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><x-icon name="key" /> Encode card</button></div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
    // Pre-fill max on refund modal
    document.addEventListener('click', e => { const b = e.target.closest('[data-max]'); if (b) document.getElementById('ref_amount').max = b.dataset.max; });
</script>
@endpush
