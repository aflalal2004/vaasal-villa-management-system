@props(['variant' => 'full', 'size' => 64, 'tile' => false, 'eager' => false])
{{-- Official Vaasal Villa logo (public/assets/brand/L1.png), served from pre-sized WebP/PNG copies.
     variant: "full" = emblem + VAASAL VILLA wordmark (square), "emblem" = emblem only for small spaces.
     Width and height are always equal to the source aspect ratio (1:1), so the logo is never stretched.
     tile: wraps the logo in a white rounded tile for dark surfaces (the artwork has a white ground). --}}
@php
    $name = $variant === 'emblem' ? 'vaasal-emblem' : 'vaasal-logo';
    $sizes = $variant === 'emblem' ? [32, 64, 128, 180, 256] : [96, 192, 320, 640];
    // Pick the smallest file that is at least 2x the display size (sharp on retina), else the largest.
    $pick = collect($sizes)->first(fn ($s) => $s >= $size * 2) ?? end($sizes);
    $base = asset('assets/brand/'.$name.'-'.$pick);
@endphp
<span {{ $attributes->class(['brand-logo', 'brand-logo--tile' => $tile, 'brand-logo--'.$variant]) }} style="--logo-size:{{ $size }}px">
    <picture>
        <source srcset="{{ $base }}.webp" type="image/webp">
        <img src="{{ $base }}.png" width="{{ $size }}" height="{{ $size }}" alt="Vaasal Villa" decoding="async" @unless($eager) loading="lazy" @endunless>
    </picture>
</span>
