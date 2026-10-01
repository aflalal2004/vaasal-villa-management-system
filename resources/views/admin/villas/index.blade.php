@extends('layouts.admin')
@section('title', 'Villas')
@section('content')
<x-page-header title="Villas" sub="Occupancy, housekeeping and maintenance are tracked separately — each answers a different question.">
    @perm('villas.manage')<a class="btn btn-primary" href="{{ route('admin.villas.create') }}"><x-icon name="plus" /> New villa</a>@endperm
</x-page-header>
<form class="filters" method="get">
    <div class="field"><label for="type">Type</label><select id="type" name="type" data-autosubmit><option value="">All types</option>@foreach ($types as $id => $n)<option value="{{ $id }}" @selected(request('type') == $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="field"><label for="hk">Housekeeping</label><select id="hk" name="hk" data-autosubmit><option value="">Any</option>@foreach (\App\Models\Villa::HK_LABELS as $k => $v)<option value="{{ $k }}" @selected(request('hk') === $k)>{{ $v }}</option>@endforeach</select></div>
</form>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Villa</th><th>Type</th><th>Zone</th><th>Occupancy</th><th>Housekeeping</th><th>Maintenance</th><th>In-house / next arrival</th><th>Lock</th><th></th></tr></thead>
        <tbody>
        @foreach ($villas as $v)
            <tr @class(['strike' => ! $v->is_active])>
                <td><a href="{{ route('admin.villas.show', $v) }}"><strong>{{ $v->code }}</strong> {{ $v->name }}</a></td>
                <td>{{ $v->type->name }}</td><td>{{ $v->zone }}</td>
                <td><x-badge :status="$v->occupancy_status" /></td>
                <td><x-badge :status="$v->hk_status" :label="\App\Models\Villa::HK_LABELS[$v->hk_status]" /></td>
                <td><x-badge :status="$v->maintenance_status" :label="label($v->maintenance_status)" /></td>
                <td class="small">
                    @if ($v->currentStay)<a href="{{ route('admin.bookings.show', $v->currentStay->booking) }}">{{ $v->currentStay->booking->guest->fullName() }}</a> · until {{ fmt_date($v->currentStay->departure, 'd M') }}
                    @elseif ($n = $nextArrivals->get($v->id))<span class="muted">Next: {{ $n->guest->fullName() }} {{ fmt_date($n->arrival, 'd M') }}</span>
                    @else <span class="muted">—</span>@endif
                </td>
                <td class="mono small">{{ $v->lock_ref ?? '—' }}</td>
                <td class="actions">@perm('villas.manage')<a class="btn btn-sm btn-ghost" href="{{ route('admin.villas.edit', $v) }}" aria-label="Edit"><x-icon name="edit" /></a>@endperm</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
