{{-- Report filters, summary, chart and table. $reportRoute = named route taking the report type (admin or POS). --}}
<form class="filters" method="get">
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ $r['from']->toDateString() }}"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ $r['to']->toDateString() }}"></div>
    <div class="field"><label for="quick">Quick range</label>
        <select id="quick" onchange="if(this.value){const [f,t]=this.value.split('|');document.getElementById('from').value=f;document.getElementById('to').value=t;this.form.submit();}">
            <option value="">—</option>
            <option value="{{ now()->toDateString() }}|{{ now()->toDateString() }}">Today</option>
            <option value="{{ now()->subDays(6)->toDateString() }}|{{ now()->toDateString() }}">Last 7 days</option>
            <option value="{{ now()->startOfMonth()->toDateString() }}|{{ now()->toDateString() }}">This month</option>
            <option value="{{ now()->subMonth()->startOfMonth()->toDateString() }}|{{ now()->subMonth()->endOfMonth()->toDateString() }}">Last month</option>
            <option value="{{ now()->startOfYear()->toDateString() }}|{{ now()->toDateString() }}">Year to date</option>
            <option value="{{ now()->toDateString() }}|{{ now()->addDays(30)->toDateString() }}">Next 30 days</option>
        </select></div>
    <button class="btn" type="submit">Run report</button>
    <div class="field" style="margin-left:auto"><label for="other">Other reports</label>
        <select id="other" onchange="location.href=this.value">@foreach ($all as $k => $v)<option value="{{ route($reportRoute ?? 'admin.reports.show', $k) }}?from={{ $r['from']->toDateString() }}&to={{ $r['to']->toDateString() }}" @selected($k === $r['type'])>{{ $v[0] }}</option>@endforeach</select></div>
</form>

@if (! empty($r['summary']))
<div class="stats">@foreach ($r['summary'] as $label => $value)<x-stat :label="$label" :value="$value" />@endforeach</div>
@endif

@if (! empty($r['chart']))
<div class="card mb">
    <div class="card-body">
        @if (count($r['chart']) > 10)
            <x-bar-chart :data="$r['chart']" :suffix="$r['type'] === 'occupancy' ? '%' : ''" :money="! in_array($r['type'], ['occupancy', 'bookings', 'guests', 'housekeeping', 'attendance', 'maintenance', 'keycards', 'pos_sales'])" />
        @else
            <x-hbars :data="$r['chart']" :money="in_array($r['type'], ['revenue', 'payments', 'channels', 'operators', 'pos_items', 'inventory'])" />
        @endif
    </div>
</div>
@endif

<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr>@foreach ($r['columns'] as $k => $label)<th @class(['num' => in_array($k, $r['money']) || is_numeric($r['rows'][0][$k] ?? null)])>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($r['rows'] as $row)
            <tr>@foreach ($r['columns'] as $k => $label)
                @php $v = $row[$k] ?? ''; @endphp
                <td @class(['num' => in_array($k, $r['money']) || is_numeric($v)])>{{ in_array($k, $r['money']) ? money($v, null, false) : $v }}</td>
            @endforeach</tr>
        @empty
            <tr><td colspan="{{ count($r['columns']) }}"><x-empty title="No data for this period" icon="chart" /></td></tr>
        @endforelse
        </tbody>
        @if (count($r['rows']) && count($r['money']))
            <tfoot><tr>@foreach ($r['columns'] as $k => $label)<td @class(['num' => in_array($k, $r['money'])])>{{ $loop->first ? 'Total' : (in_array($k, $r['money']) ? money(collect($r['rows'])->sum($k), null, false) : '') }}</td>@endforeach</tr></tfoot>
        @endif
    </table></div>
</div>
