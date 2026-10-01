@props(['status' => null, 'label' => null, 'tone' => null])
<span {{ $attributes->merge(['class' => 'badge tone-'.($tone ?? status_tone($status))]) }}>{{ $label ?? label($status) }}</span>
