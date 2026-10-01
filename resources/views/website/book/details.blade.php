@extends('layouts.site')
@section('title', 'Your details')
@section('header_class', 'always')
@section('content')
<section class="block" style="padding-top:150px">
    <div class="wrap">
        <div class="steps"><span>1 · Choose villa</span><span class="on">2 · Your details</span><span>3 · Secure payment</span><span>4 · Confirmation</span></div>
        <div class="detail-layout">
            <form class="form-card" method="post" action="{{ route('book.hold') }}" data-once>
                @csrf
                @foreach (['type', 'plan', 'arrival', 'departure', 'adults', 'children', 'promo'] as $k)<input type="hidden" name="{{ $k }}" value="{{ $data[$k] ?? '' }}">@endforeach
                <h2 style="font-size:30px;margin-bottom:18px">Who's staying?</h2>
                @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
                <div class="fgrid">
                    <div class="field"><label for="fn">First name</label><input id="fn" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name"></div>
                    <div class="field"><label for="ln">Last name</label><input id="ln" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name"></div>
                    <div class="field"><label for="em">Email</label><input id="em" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
                    <div class="field"><label for="ph">Mobile / WhatsApp</label><input id="ph" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel"></div>
                    <div class="field"><label for="co">Country of residence</label><input id="co" name="country" value="{{ old('country') }}" required autocomplete="country-name"></div>
                    <div class="field"><label for="at">Estimated arrival time</label><select id="at" name="arrival_time"><option value="">Not sure yet</option>@foreach (['12:00', '14:00', '16:00', '18:00', '20:00', '22:00', 'After 22:00'] as $h)<option @selected(old('arrival_time') === $h)>{{ $h }}</option>@endforeach</select></div>
                    <div class="field full"><label for="sr">Special requests</label><textarea id="sr" name="special_requests" placeholder="Dietary needs, celebrations, baby cot, late arrival…">{{ old('special_requests') }}</textarea></div>
                </div>
                @if ($extras->isNotEmpty())
                    <h3 style="margin:26px 0 10px;font-size:20px">Add to your stay <span class="muted small" style="font-family:var(--sans)">(pay at the villa)</span></h3>
                    <div style="display:grid;gap:8px">@foreach ($extras as $x)
                        <label class="plan" style="grid-template-columns:auto 1fr auto;cursor:pointer"><input type="checkbox" name="extras[]" value="{{ $x->id }}" @checked(in_array($x->id, old('extras', [])))><span>{{ $x->name }}</span><strong><x-price :amount="$x->price" /></strong></label>
                    @endforeach</div>
                @endif
                <h3 style="margin:26px 0 10px;font-size:20px">Payment</h3>
                <div style="display:grid;gap:8px">
                    @if ($plan->is_refundable)
                        <label class="plan" style="grid-template-columns:auto 1fr auto;cursor:pointer"><input type="radio" name="pay" value="deposit" checked><span>Pay a {{ (float) $plan->deposit_pct }}% deposit now, balance at the villa</span><strong><x-price :amount="$quote['deposit_due']" mode="charge" /></strong></label>
                    @endif
                    <label class="plan" style="grid-template-columns:auto 1fr auto;cursor:pointer"><input type="radio" name="pay" value="full" @checked(! $plan->is_refundable)><span>Pay in full now</span><strong><x-price :amount="$quote['grand_total']" mode="charge" /></strong></label>
                </div>
                <p class="small muted secure-note" style="margin-top:12px"><x-icon name="lock" /> You'll be taken to our payment provider's secure page. Card details are never entered on or stored by Vaasal Villa. Your villa is held for {{ config('vaasal.hold_minutes') }} minutes while you pay.</p>
                <label style="display:flex;gap:10px;margin-top:10px"><input type="checkbox" name="marketing_consent" value="1"> <span class="small">Send me occasional offers (you can unsubscribe any time).</span></label>
                <label style="display:flex;gap:10px;margin-top:8px"><input type="checkbox" name="terms" value="1" required> <span class="small">I accept the booking conditions: {{ $plan->description }}</span></label>
                <button class="btn btn-primary" type="submit" style="width:100%;margin-top:20px;min-height:56px;font-size:17px" data-busy="Holding your villa…">Continue to secure payment</button>
            </form>
            <aside class="sticky-book">
                <img src="{{ $type->coverUrl() }}" alt="{{ $type->name }}" style="border-radius:10px;aspect-ratio:16/10;object-fit:cover;width:100%" data-fallback="{{ asset('assets/img/placeholder-villa.svg') }}">
                <div><h3>{{ $type->name }}</h3><div class="small muted">{{ $plan->name }} · {{ $data['adults'] }} adult(s){{ ! empty($data['children']) ? ', '.$data['children'].' child(ren)' : '' }}</div></div>
                <div class="small">{{ fmt_date($data['arrival'], 'D d M Y') }} → {{ fmt_date($data['departure'], 'D d M Y') }} · {{ $quote['nights'] }} nights</div>
                <div class="summary small">
                    @foreach ($quote['nightly'] as $d => $r)<span class="muted">{{ fmt_date($d, 'D d M') }}</span><span>{{ money($r) }}</span>@endforeach
                    @if ($quote['discount'] > 0)<span>{{ $offer?->title }}</span><span>− {{ money($quote['discount']) }}</span>@endif
                    <span class="muted">Service charge</span><span>{{ money($quote['service']) }}</span>
                    <span class="muted">Tax</span><span>{{ money($quote['tax']) }}</span>
                    <span class="total">Total</span><span class="total"><x-price :amount="$quote['grand_total']" mode="charge" /></span>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
