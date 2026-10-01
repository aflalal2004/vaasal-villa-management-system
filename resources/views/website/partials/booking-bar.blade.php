@php
    $a = request('arrival', now()->addDays(14)->toDateString());
    $d = request('departure', \Illuminate\Support\Carbon::parse($a)->addDays(3)->toDateString());
@endphp
<form class="booking-form" action="{{ route('book.search') }}" method="get" data-booking aria-label="Check availability">
    <div class="bf-field"><label for="bf-arrival">Check-in</label><input type="date" id="bf-arrival" name="arrival" value="{{ $a }}" min="{{ now()->toDateString() }}" required></div>
    <div class="bf-field"><label for="bf-departure">Check-out <span data-nights style="text-transform:none;letter-spacing:0;color:var(--brass)"></span></label><input type="date" id="bf-departure" name="departure" value="{{ $d }}" required></div>
    <div class="bf-field"><label for="bf-adults">Adults</label><select id="bf-adults" name="adults">@for ($i = 1; $i <= 6; $i++)<option @selected(request('adults', 2) == $i)>{{ $i }}</option>@endfor</select></div>
    <div class="bf-field"><label for="bf-children">Children</label><select id="bf-children" name="children">@for ($i = 0; $i <= 4; $i++)<option @selected(request('children', 0) == $i)>{{ $i }}</option>@endfor</select></div>
    @php $fxs = app(\App\Modules\Core\Services\CurrencyService::class); @endphp
    <div class="bf-field"><label for="bf-currency">Currency</label><select id="bf-currency" data-currency-mirror aria-label="Display currency">@foreach ($fxs->currencies() as $code => $cur)<option value="{{ $code }}" @selected($code === $fxs->current())>{{ $cur['symbol'] }} {{ $code }}</option>@endforeach</select></div>
    <button class="btn btn-primary" type="submit" style="min-height:58px;border-radius:14px"><x-icon name="search" /> Check availability</button>
    @if (! empty($promo))<input type="hidden" name="promo" value="{{ $promo }}">@endif
</form>
