@php
    $flashes = [];
    // Errors render as a persistent alert below; the rest appear as toasts.
    foreach (['success' => 'success', 'warning' => 'warning', 'info' => 'info'] as $key => $type) {
        if (session()->has($key)) $flashes[] = ['type' => $type, 'message' => session($key)];
    }
@endphp
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Please fix the following:</strong>
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
<div hidden data-flash="{{ json_encode($flashes) }}"></div>
