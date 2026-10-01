@extends('layouts.admin')
@section('title', 'Availability calendar')
@section('content')
<x-page-header title="Availability calendar" sub="One ledger for every channel. Each cell is one villa-night; a villa can hold only one booking or block per night.">
    <form method="get" class="row">
        <a class="btn" href="{{ route('admin.calendar', ['from' => $from->copy()->subDays($days)->toDateString(), 'days' => $days]) }}" aria-label="Previous period"><x-icon name="arrow-left" /></a>
        <input type="date" name="from" value="{{ $from->toDateString() }}" data-autosubmit aria-label="Start date" style="width:auto">
        <select name="days" data-autosubmit aria-label="Days shown" style="width:auto">@foreach ([14, 21, 31, 45] as $d)<option value="{{ $d }}" @selected($days === $d)>{{ $d }} days</option>@endforeach</select>
        <a class="btn" href="{{ route('admin.calendar', ['from' => $from->copy()->addDays($days)->toDateString(), 'days' => $days]) }}" aria-label="Next period"><x-icon name="arrow-right" /></a>
        <a class="btn" href="{{ route('admin.calendar') }}">Today</a>
    </form>
    @perm('villas.manage|bookings.manage')<button class="btn" type="button" data-modal-open="#block-modal"><x-icon name="lock" /> Block villa</button>@endperm
</x-page-header>

<div class="legend mb">
    <span style="--c:#0E6B63">Confirmed</span><span style="--c:#1F5F9E">In-house</span><span style="--c:#7B8A85">Checked out</span>
    <span style="--c:#B07A1E">Hold / tentative</span><span style="--c:#9B3A33">Blocked / out of order</span><span>Click an empty night to start a booking</span>
</div>

<div class="cal-wrap">
    <table class="cal">
        <thead>
            <tr>
                <th class="villa-col">Villa</th>
                @foreach ($dates as $d)
                    <th @class(['today' => $d->isToday(), 'weekend' => $d->isWeekend()])>{{ $d->format('D') }}<br><strong>{{ $d->format('d') }}</strong><br><span class="small">{{ $d->format('M') }}</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
        @foreach ($villas as $v)
            <tr>
                <th class="villa-col" scope="row">
                    <a href="{{ route('admin.villas.show', $v) }}" style="color:var(--ink)"><strong>{{ $v->code }}</strong> {{ \Illuminate\Support\Str::before($v->name, ' ') }}</a>
                    <div class="small muted">{{ $v->type->name }} @if($v->maintenance_status === 'out_of_order')<x-badge status="out_of_order" label="OOO" />@endif</div>
                </th>
                @php $i = 0; @endphp
                @while ($i < $days)
                    @php
                        $d = $dates[$i];
                        $cell = $grid[$v->id][$d->toDateString()] ?? null;
                    @endphp
                    @if (! $cell)
                        <td class="free" title="{{ $v->code }} {{ $d->format('d M') }} — free" @perm('bookings.manage') onclick="location.href='{{ route('admin.bookings.create', ['villa' => $v->id, 'arrival' => $d->toDateString(), 'departure' => $d->copy()->addDays(2)->toDateString()]) }}'" @endperm></td>
                        @php $i++; @endphp
                    @else
                        @php
                            // merge consecutive nights of the same booking line / block into one bar
                            $key = $cell->booking_villa_id ? 'b'.$cell->booking_villa_id : 'k'.$cell->villa_block_id;
                            $span = 1;
                            while ($i + $span < $days) {
                                $next = $grid[$v->id][$dates[$i + $span]->toDateString()] ?? null;
                                if (! $next || ($next->booking_villa_id ? 'b'.$next->booking_villa_id : 'k'.$next->villa_block_id) !== $key) break;
                                $span++;
                            }
                            $startsHere = $i === 0 ? false : true;
                        @endphp
                        <td colspan="{{ $span }}">
                            @if ($cell->bookingVilla)
                                @php $bk = $cell->bookingVilla->booking; @endphp
                                <a class="seg start end s-{{ $bk->status }}" href="{{ route('admin.bookings.show', $bk) }}"
                                   title="{{ $bk->reference }} · {{ $bk->guest->fullName() }} · {{ $bk->channel->name }} · {{ fmt_date($bk->arrival, 'd M') }}–{{ fmt_date($bk->departure, 'd M') }}">
                                    {{ $bk->guest->last_name }} · {{ $bk->channel->code === 'tour_operator' ? 'TO' : \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($bk->channel->name, 0, 3)) }}
                                </a>
                            @else
                                <span class="seg start end s-block" title="Blocked: {{ $cell->block?->reason }} {{ $cell->block?->notes }}">
                                    {{ ucfirst($cell->block?->reason ?? 'Blocked') }}
                                    @perm('villas.manage|bookings.manage')
                                    <form method="post" action="{{ route('admin.calendar.unblock', $cell->villa_block_id) }}" style="display:inline" data-confirm="Remove this block and reopen the nights for sale?">@csrf @method('delete')<button type="submit" style="background:none;border:0;color:#fff;cursor:pointer" aria-label="Remove block">×</button></form>
                                    @endperm
                                </span>
                            @endif
                        </td>
                        @php $i += $span; @endphp
                    @endif
                @endwhile
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            @foreach ($types as $t)
                <tr>
                    <th class="villa-col small muted" style="font-weight:500">Free · {{ $t->name }}</th>
                    @foreach ($dates as $d)
                        @php $n = $matrix[$t->id][$d->toDateString()] ?? 0; @endphp
                        <td class="small" style="color:{{ $n === 0 ? 'var(--crit)' : 'var(--muted)' }};font-weight:{{ $n === 0 ? 700 : 400 }}">{{ $n }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tfoot>
    </table>
</div>

<x-modal id="block-modal" title="Block a villa">
    <form method="post" action="{{ route('admin.calendar.block') }}">
        @csrf
        <div class="modal-body form-grid">
            <x-select name="villa_id" label="Villa" :options="$villas->mapWithKeys(fn ($v) => [$v->id => $v->code.' — '.$v->name])" required col="f-12" />
            <x-input name="start_date" type="date" label="From (first night)" required :value="now()" />
            <x-input name="end_date" type="date" label="Until (exclusive)" required :value="now()->addDays(2)" />
            <x-select name="reason" label="Reason" :options="['maintenance' => 'Maintenance', 'owner' => 'Owner use', 'other' => 'Other']" required col="f-6" />
            <x-input name="notes" label="Notes" col="f-6" />
            <p class="small muted f-12" style="margin:0">Blocks fail if any night already has a booking. Channels are updated automatically.</p>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Block</button></div>
    </form>
</x-modal>
@endsection
