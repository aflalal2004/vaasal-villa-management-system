@props(['data' => [], 'height' => 220, 'suffix' => '', 'money' => false])
@php
    // Server-rendered SVG bar chart; one scale for bars, gridlines and labels.
    $data = collect($data)->map(fn ($v) => (float) $v);
    $n = max(1, $data->count());
    $max = max(1, $data->max() ?: 1);
    $step = pow(10, floor(log10($max)));
    $niceMax = ceil($max / $step) * $step;
    if ($niceMax / $step <= 2) { $step /= 5; } elseif ($niceMax / $step <= 5) { $step /= 2; }
    $niceMax = ceil($max / $step) * $step;
    $W = 720; $H = $height; $padL = 56; $padB = 46; $padT = 12; $padR = 8;
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $bw = $plotW / $n;
    $fmt = fn ($v) => $money ? number_format($v, 0) : (fmod($v, 1) ? number_format($v, 1) : number_format($v)).$suffix;
    $every = (int) ceil($n / 16);
@endphp
@if ($data->isEmpty() || $data->sum() == 0)
    <x-empty title="No data for this period" icon="chart" />
@else
<svg class="chart" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Bar chart">
    @for ($v = 0; $v <= $niceMax + 0.0001; $v += $step)
        @php $y = $padT + $plotH - ($v / $niceMax) * $plotH; @endphp
        <line class="grid-line" x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y }}" y2="{{ $y }}" />
        <text x="{{ $padL - 8 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $fmt($v) }}</text>
    @endfor
    @foreach ($data as $label => $value)
        @php
            $i = $loop->index;
            $h = ($value / $niceMax) * $plotH;
            $x = $padL + $i * $bw + $bw * 0.18;
        @endphp
        <rect class="bar-rect" x="{{ $x }}" y="{{ $padT + $plotH - $h }}" width="{{ max(2, $bw * 0.64) }}" height="{{ max(0, $h) }}" rx="3"><title>{{ $label }}: {{ $fmt($value) }}</title></rect>
        @if ($i % $every === 0)
            <text x="{{ $padL + $i * $bw + $bw / 2 }}" y="{{ $H - $padB + 16 }}" text-anchor="middle">{{ \Illuminate\Support\Str::limit((string) $label, 12, '…') }}</text>
        @endif
    @endforeach
</svg>
@endif
