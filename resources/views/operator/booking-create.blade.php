@extends('layouts.operator')
@section('title', 'New group booking')
@section('content')
<x-page-header title="New group booking" sub="Choose dates, then how many villas of each type. Villas are allocated automatically from live inventory at your contract rate." />
<form method="get" class="filters">
    <div class="field"><label for="a">Arrival</label><input id="a" type="date" name="arrival" value="{{ $arrival }}" min="{{ now()->toDateString() }}"></div>
    <div class="field"><label for="d">Departure</label><input id="d" type="date" name="departure" value="{{ $departure }}"></div>
    <button class="btn" type="submit"><x-icon name="refresh" /> Update availability</button>
</form>
<form method="post" action="{{ route('operator.bookings.store') }}" class="grid cols-main">
    @csrf
    <input type="hidden" name="arrival" value="{{ $arrival }}"><input type="hidden" name="departure" value="{{ $departure }}">
    <div class="card">
        <div class="card-head"><h2>Villas · {{ fmt_date($arrival) }} → {{ fmt_date($departure) }}</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Villa type</th><th class="num">Free</th><th class="num">Villas</th><th class="num">Adults each</th></tr></thead>
            <tbody>
            @foreach ($types as $t)
                <tr><td><strong>{{ $t->name }}</strong><div class="small muted">Sleeps {{ $t->max_adults }} adults + {{ $t->max_children }} children</div></td>
                    <td class="num">{{ $t->available_count }}</td>
                    <td class="num"><input type="number" name="rooms[{{ $t->id }}][qty]" min="0" max="{{ $t->available_count }}" value="{{ old('rooms.'.$t->id.'.qty', 0) }}" style="width:80px" @disabled(! $t->available_count) aria-label="{{ $t->name }} quantity"></td>
                    <td class="num"><input type="number" name="rooms[{{ $t->id }}][adults]" min="1" max="{{ $t->max_adults }}" value="{{ min(2, $t->max_adults) }}" style="width:80px" aria-label="Adults"></td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="card" style="align-self:start">
        <div class="card-head"><h2>Group details</h2></div>
        <div class="card-body form-grid">
            <x-input name="group_name" label="Group / tour name" col="f-12" />
            <x-input name="lead_first_name" label="Lead guest first name" required col="f-6" />
            <x-input name="lead_last_name" label="Last name" required col="f-6" />
            <x-input name="lead_email" type="email" label="Lead guest email" col="f-6" />
            <x-input name="lead_nationality" label="Nationality" col="f-6" />
            <x-select name="rate_plan_id" label="Board basis" :options="$plans->pluck('name', 'id')" col="f-12" />
            <x-textarea name="special_requests" label="Requests (dietary, arrival flights, transfers)" rows="3" />
            <p class="small muted f-12" style="margin:0">Rooming list can be added after booking, until the cut-off in your contract.</p>
            <div class="f-12"><button class="btn btn-primary btn-lg btn-block" type="submit" @disabled($op->status !== 'active')>Confirm booking</button></div>
        </div>
    </div>
</form>
@endsection
