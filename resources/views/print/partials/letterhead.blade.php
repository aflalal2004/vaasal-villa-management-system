@php $p = property(); @endphp
<div class="doc-head">
    <div style="display:flex;gap:14px;align-items:flex-start"><x-brand-logo :size="84" :eager="true" /><div>
        <h1>{{ $p->name }}</h1>
        <div class="doc-meta">{{ $p->legal_name }}<br>{{ collect([$p->address, $p->city, $p->country])->filter()->unique()->implode(', ') }}<br>{{ $p->phone }} · {{ $p->email }}@if($p->tax_id)<br>Tax ID {{ $p->tax_id }}@endif</div></div>
    </div>
    <div style="text-align:right">
        <div class="doc-title">{{ $title }}</div>
        <div class="doc-meta">{!! $meta ?? '' !!}</div>
    </div>
</div>
