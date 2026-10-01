@extends('layouts.print')
@section('title', $r['title'])
@section('content')
<div class="print-page" style="max-width:1100px">
    @include('print.partials.letterhead', ['title' => $r['title'], 'meta' => fmt_date($r['from']).' – '.fmt_date($r['to']).'<br>Printed '.now()->format('d M Y H:i').' by '.e(auth()->user()->name)])
    @if (! empty($r['summary']))
        <div class="doc-grid" style="grid-template-columns:repeat(4,1fr)">@foreach ($r['summary'] as $l => $v)<div class="doc-box"><span class="doc-meta">{{ $l }}</span><br><strong>{{ $v }}</strong></div>@endforeach</div>
    @endif
    <table>
        <thead><tr>@foreach ($r['columns'] as $k => $l)<th @class(['num' => in_array($k, $r['money'])])>{{ $l }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($r['rows'] as $row)<tr>@foreach ($r['columns'] as $k => $l)<td @class(['num' => in_array($k, $r['money'])])>{{ in_array($k, $r['money']) ? money($row[$k] ?? 0, null, false) : ($row[$k] ?? '') }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
</div>
@endsection
