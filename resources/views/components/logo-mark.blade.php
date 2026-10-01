@props(['size' => 40, 'mono' => false, 'variant' => 'emblem'])
{{-- Compact Vaasal Villa brand mark used in sidebars, headers and documents.
     Renders the official logo (public/assets/brand/L1.png) on a white tile; see <x-brand-logo> and docs/BRAND.md. --}}
<x-brand-logo :variant="$variant" :size="$size" :tile="true" :eager="true" {{ $attributes }} />
