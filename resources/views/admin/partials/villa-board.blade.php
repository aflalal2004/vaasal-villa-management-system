{{-- Villa status board. $board from DashboardService::villaBoard(). --}}
<div class="villa-board">
    @foreach ($board as $row)
        @php [$label, $icon, $tone] = \App\Models\Villa::BOARD[$row['status']]; $v = $row['villa']; @endphp
        <a class="vb-tile vb-{{ $row['status'] }}" href="{{ auth()->user()->hasPermission('villas.view') ? route('admin.villas.show', $v) : route('admin.housekeeping.index') }}"
           title="{{ $v->code }} · {{ $label }}{{ $row['guest'] ? ' · '.$row['guest'] : '' }}">
            <span class="vb-top"><strong>{{ $v->code }}</strong><x-icon :name="$icon" /></span>
            <span class="vb-status">{{ $label }}</span>
            <span class="vb-sub">{{ $row['guest'] ?? $v->type?->name }}</span>
            @if ($row['task'])<span class="vb-sub"><x-icon name="broom" /> {{ $row['task']->assignee?->first_name ?? 'Unassigned' }} · {{ \App\Models\HkTask::STATUSES[$row['task']->status] }}</span>@endif
        </a>
    @endforeach
</div>
<div class="vb-legend small muted">@foreach (\App\Models\Villa::BOARD as $k => [$l, $i, $t])<span><i class="vb-dot vb-{{ $k }}"></i>{{ $l }}</span>@endforeach</div>
