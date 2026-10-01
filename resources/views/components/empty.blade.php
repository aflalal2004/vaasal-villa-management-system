@props(['title' => 'Nothing here yet', 'icon' => 'sparkles'])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    <x-icon :name="$icon" />
    <h3>{{ $title }}</h3>
    <div>{{ $slot }}</div>
</div>
