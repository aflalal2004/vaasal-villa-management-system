@extends('layouts.operator')
@section('title', 'Availability & rates')
@section('content')
<x-page-header title="Availability & your rates" sub="Live availability from the same inventory the front desk uses. Prices shown are your contract rates for two adults." />
<form class="filters" method="get">
    <div class="field"><label for="arrival">Arrival</label><input id="arrival" type="date" name="arrival" value="{{ $arrival->toDateString() }}"></div>
    <div class="field"><label for="departure">Departure</label><input id="departure" type="date" name="departure" value="{{ $departure->toDateString() }}"></div>
    <button class="btn" type="submit">Check</button>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Villa type</th><th>Sleeps</th><th class="num">Available</th><th class="num">Avg / night</th><th class="num">Stay total</th><th></th></tr></thead>
        <tbody>
        @foreach ($types as $t)
            <tr>
                <td><strong>{{ $t->name }}</strong><div class="small muted">{{ $t->short_description }}</div></td>
                <td>{{ $t->max_adults }} + {{ $t->max_children }}</td>
                <td class="num"><x-badge :tone="$t->available_count ? 'success' : 'danger'" :label="$t->available_count.' free'" /></td>
                <td class="num">{{ money($t->quote['avg_nightly']) }}</td>
                <td class="num"><strong>{{ money($t->quote['grand_total']) }}</strong>@if($t->quote['errors'])<div class="small" style="color:var(--warn)">{{ implode(' ', $t->quote['errors']) }}</div>@endif</td>
                <td class="actions">@if($t->available_count)<a class="btn btn-sm btn-primary" href="{{ route('operator.bookings.create', ['arrival' => $arrival->toDateString(), 'departure' => $departure->toDateString()]) }}">Book</a>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
