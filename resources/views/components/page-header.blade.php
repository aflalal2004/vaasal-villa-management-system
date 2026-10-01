@props(['title', 'eyebrow' => null, 'sub' => null, 'crumbs' => []])
<div class="page-head">
    <div class="titles">
        @if ($crumbs)
            <div class="breadcrumb">
                @foreach ($crumbs as $label => $url)
                    <a href="{{ $url }}">{{ $label }}</a> /
                @endforeach
            </div>
        @endif
        @if ($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endif
        <h1>{{ $title }}</h1>
        @if ($sub)<div class="sub">{{ $sub }}</div>@endif
    </div>
    @if (trim($slot))
        <div class="page-actions">{{ $slot }}</div>
    @endif
</div>
