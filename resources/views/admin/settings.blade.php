@extends('layouts.admin')
@section('title', 'Settings')
@section('content')
<x-page-header title="Property settings" sub="Used on documents, prices and the website. API keys and secrets live in the .env file, never in the database." />
<div class="grid cols-main">
    <form method="post" action="{{ route('admin.settings.update') }}" class="card">
        @csrf @method('put')
        <div class="card-body form-grid">
            <x-input name="name" label="Property name" :value="$p->name" required col="f-6" />
            <x-input name="legal_name" label="Legal name" :value="$p->legal_name" col="f-6" />
            <x-input name="email" type="email" label="Email" :value="$p->email" col="f-6" />
            <x-input name="phone" label="Phone" :value="$p->phone" col="f-6" />
            <x-input name="address" label="Address" :value="$p->address" col="f-6" />
            <x-input name="city" label="City" :value="$p->city" col="f-3" />
            <x-input name="country" label="Country" :value="$p->country" col="f-3" />
            <x-input name="tax_id" label="Tax / VAT registration" :value="$p->tax_id" col="f-4" />
            <x-input name="tax_pct" type="number" step="0.01" label="Tax % (rooms & services)" :value="$p->tax_pct" required col="f-4" />
            <x-input name="service_charge_pct" type="number" step="0.01" label="Service charge % (rooms)" :value="$p->service_charge_pct" required col="f-4" />
            <x-input name="check_in_time" type="time" label="Check-in from" :value="substr($p->check_in_time, 0, 5)" required col="f-3" />
            <x-input name="check_out_time" type="time" label="Check-out by" :value="substr($p->check_out_time, 0, 5)" required col="f-3" />
            <x-input name="latitude" label="Latitude" :value="$p->latitude" col="f-3" help="Map & weather" />
            <x-input name="longitude" label="Longitude" :value="$p->longitude" col="f-3" />
            <div class="f-12 small muted">Currency: <strong>{{ $p->currency }}</strong> (set VV_CURRENCY in .env before first use) · Timezone: {{ config('app.timezone') }}</div>
        </div>
        <div class="card-foot"><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>
    <div class="card" style="align-self:start">
        <div class="card-head"><h2>Integrations</h2></div>
        <div class="card-body"><dl class="dl">@foreach ($integrations as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach</dl>
            <p class="small muted" style="margin-top:12px">Change drivers and keys in <code>.env</code> (see docs/SETUP.md → Integrations), then run <code>php artisan config:clear</code>.</p></div>
    </div>
</div>
<form method="post" action="{{ route('admin.settings.currencies') }}" class="card mt" id="currencies">
    @csrf @method('put')
    <div class="card-head"><h2><x-icon name="currency" /> Display currencies</h2>
        <span class="small muted">Source: <strong>{{ ['manual' => 'Manual override', 'api' => 'Live API', 'static' => 'Static fallback'][$fx['source']] }}</strong>{{ $fx['updated_at'] ? ' · '.\Illuminate\Support\Carbon::parse($fx['updated_at'])->diffForHumans() : '' }}</span></div>
    <div class="card-body">
        <p class="small muted">Everything is stored, charged and invoiced in <strong>LKR</strong>. These rates only convert prices on the website's currency selector. Leave a field empty to use the live API (when <code>EXCHANGE_RATE_API_KEY</code> is set) or the built-in fallback.</p>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Currency</th><th class="num">Rate in use (per 1 LKR)</th><th class="num">LKR 10,000 =</th><th>Manual rate</th></tr></thead>
            <tbody>@foreach ($currencies as $code => $cur)
                @continue($code === 'LKR')
                <tr><td><strong>{{ $code }}</strong> <span class="muted small">{{ $cur['symbol'] }} · {{ $cur['name'] }}</span></td>
                    <td class="num mono">{{ rtrim(rtrim(number_format($fx['rates'][$code] ?? 0, 6), '0'), '.') }}</td>
                    <td class="num">{{ price(10000, $code) }}</td>
                    <td><input type="number" step="0.000001" min="0" name="rates[{{ $code }}]" value="{{ $override[$code] ?? '' }}" placeholder="auto" aria-label="Manual rate for {{ $code }}" style="max-width:160px"></td></tr>
            @endforeach</tbody>
        </table></div>
    </div>
    <div class="card-foot">
        <button class="btn" type="submit" form="refresh-rates"><x-icon name="refresh" /> Refresh live rates</button>
        <button class="btn btn-primary" type="submit">Save rates</button>
    </div>
</form>
<form method="post" action="{{ route('admin.settings.currencies.refresh') }}" id="refresh-rates">@csrf</form>
@endsection
