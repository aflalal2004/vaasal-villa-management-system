@props(['label', 'value', 'hint' => null, 'pct' => null, 'href' => null])
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" style="color:inherit;text-decoration:none" @endif {{ $attributes->merge(['class' => 'stat']) }}>
    <span class="label">{{ $label }}</span>
    <span class="value">{{ $value }}</span>
    @if ($pct !== null)<span class="bar"><span style="width: {{ max(0, min(100, $pct)) }}%"></span></span>@endif
    @if ($hint)<span class="hint">{{ $hint }}</span>@endif
</{{ $href ? 'a' : 'div' }}>
