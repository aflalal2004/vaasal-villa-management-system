@props(['amount', 'mode' => 'display'])
{{-- A price stored in LKR.
     mode="display": shown in the visitor's currency (browsing pages).
     mode="charge":  LKR stays the authoritative amount, with an "≈ USD 41.63" hint when another currency is selected
                     (booking totals, payments, confirmations — the guest is always charged in LKR).
     site.js re-renders every [data-price-lkr] instantly when the currency selector changes. --}}
@php
    $fx = app(\App\Modules\Core\Services\CurrencyService::class);
    $cur = $fx->current();
    $lkr = round((float) $amount, 2);
    $isBase = $cur === $fx->base();
@endphp
@if ($mode === 'charge')
<span {{ $attributes->merge(['class' => 'vv-price vv-price-charge']) }}>{{ money($lkr) }}<span class="price-approx" data-price-lkr="{{ $lkr }}" data-approx @if($isBase) hidden @endif>≈ {{ $fx->format($lkr, $cur) }}</span></span>
@else
<span {{ $attributes->merge(['class' => 'vv-price']) }} data-price-lkr="{{ $lkr }}" @unless($isBase) title="{{ money($lkr) }} — charged in LKR" @endunless>{{ $fx->format($lkr, $cur) }}</span>
@endif
