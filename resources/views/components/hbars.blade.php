@props(['data' => [], 'money' => false])
@php $max = max(1, collect($data)->max() ?: 1); @endphp
@if (collect($data)->isEmpty())
    <x-empty title="No data yet" icon="chart" />
@else
<div class="hbars">
    @foreach ($data as $label => $value)
        <div class="hbar">
            <span class="nowrap" style="overflow:hidden;text-overflow:ellipsis">{{ $label }}</span>
            <span class="track"><span class="fill" style="width: {{ round($value / $max * 100, 1) }}%"></span></span>
            <span class="val">{{ $money ? money($value) : $value }}</span>
        </div>
    @endforeach
</div>
@endif
