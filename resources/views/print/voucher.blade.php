@extends('layouts.print')
@section('title', 'Voucher '.$b->reference)
@section('content')
<div class="print-page">
    @include('print.partials.letterhead', ['title' => 'Accommodation Voucher', 'meta' => 'Ref <strong>'.$b->reference.'</strong><br>Issued '.now()->format('d M Y')])
    <div class="doc-grid">
        <div class="doc-box"><strong>Guest</strong><br>{{ $b->guest->fullName() }}<br>{{ $b->guest->email }}<br>{{ $b->guest->phone }}</div>
        <div class="doc-box"><strong>Stay</strong><br>Arrival {{ fmt_date($b->arrival, 'D d M Y') }} (from {{ substr(property()->check_in_time, 0, 5) }})<br>
            Departure {{ fmt_date($b->departure, 'D d M Y') }} (by {{ substr(property()->check_out_time, 0, 5) }})<br>{{ $b->nights() }} night(s) · {{ $b->ratePlan?->name }}</div>
    </div>
    @if ($b->operator)<p class="doc-meta">Booked through <strong>{{ $b->operator->company_name }}</strong>{{ $b->group_name ? ' · '.$b->group_name : '' }}. Accommodation {{ $b->ratePlan?->mealPlanLabel() }} is prepaid by the operator; extras are settled by the guest.</p>@endif
    <table>
        <thead><tr><th>Villa</th><th>Type</th><th>Guests</th><th>Occupants</th></tr></thead>
        <tbody>
        @foreach ($b->villas->where('status', 'active') as $bv)
            <tr><td><strong>{{ $bv->villa->code }}</strong> {{ $bv->villa->name }}</td><td>{{ $bv->villa->type->name }}</td>
                <td>{{ $bv->adults }} adult(s){{ $bv->children ? ', '.$bv->children.' child(ren)' : '' }}</td>
                <td>{{ $bv->guests->map->fullName()->implode(', ') ?: $b->guest->fullName() }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @if ($b->special_requests)<p style="margin-top:14px"><strong>Requests:</strong> {{ $b->special_requests }}</p>@endif
    <div class="doc-foot">
        Please present this voucher and a valid passport or national ID at check-in. Airport transfers, dining and spa can be arranged on WhatsApp {{ setting('contact_phone') }}.
        Cancellation policy: {{ $b->ratePlan?->description }}
    </div>
</div>
@endsection
