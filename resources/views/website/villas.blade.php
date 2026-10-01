@extends('layouts.site')
@section('title', 'Villas')
@section('description', 'Garden, pool, ocean and family villas with private pools, daily housekeeping and in-villa dining.')
@section('content')
<section class="page-hero" style="background-image:url('https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=2000&q=70')">
    <div class="wrap"><span class="eyebrow" style="color:#E9C98A">Villas</span><h1>Your own pool, your own garden.</h1></div>
</section>
<div class="wrap booking-bar">@include('website.partials.booking-bar')</div>
<section class="block">
    <div class="wrap">
        <div class="cards">@foreach ($types as $t)@include('website.partials.villa-card', ['t' => $t, 'delay' => 'd'.($loop->index % 3)])@endforeach</div>
        <div class="form-card reveal" style="margin-top:48px">
            <h3>Compare at a glance</h3>
            <div style="overflow-x:auto;margin-top:14px">
                <table style="width:100%;border-collapse:collapse;font-size:15px;min-width:640px">
                    <thead><tr style="text-align:left;color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:.08em"><th style="padding:10px">Villa</th><th>Sleeps</th><th>Bedrooms</th><th>Size</th><th>Private pool</th><th>From / night</th></tr></thead>
                    <tbody>@foreach ($types as $t)
                        <tr style="border-top:1px solid var(--line)"><td style="padding:12px 10px"><a href="{{ route('site.villa', $t) }}"><strong>{{ $t->name }}</strong></a></td><td>{{ $t->max_adults }} + {{ $t->max_children }}</td><td>{{ $t->bedrooms }}</td><td>{{ $t->size_sqm }} m²</td>
                            <td>{{ $t->facilities->contains('name', 'Private pool') ? 'Yes' : 'Plunge / shared' }}</td><td><strong><x-price :amount="$t->base_rate" /></strong></td></tr>
                    @endforeach</tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
