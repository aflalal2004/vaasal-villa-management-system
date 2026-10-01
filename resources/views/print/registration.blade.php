@extends('layouts.print')
@section('title', 'Registration card '.$b->reference)
@section('content')
<div class="print-page">
    @include('print.partials.letterhead', ['title' => 'Guest Registration Card', 'meta' => 'Ref <strong>'.$b->reference.'</strong>'])
    <div class="doc-grid">
        <div class="doc-box">
            <strong>Lead guest</strong><br>{{ $b->guest->fullName() }}<br>Nationality: {{ $b->guest->nationality ?? '________' }}<br>
            {{ ucfirst($b->guest->id_type ?? 'ID') }} no.: {{ $b->guest->maskedId() ?? '________________' }}<br>Expiry: {{ fmt_date($b->guest->id_expiry) }}<br>
            Phone: {{ $b->guest->phone ?? '________' }}<br>Email: {{ $b->guest->email ?? '________' }}
        </div>
        <div class="doc-box">
            <strong>Stay</strong><br>{{ fmt_date($b->arrival) }} → {{ fmt_date($b->departure) }} ({{ $b->nights() }} nights)<br>
            Villa(s): {{ $b->villas->where('status', 'active')->map(fn ($bv) => $bv->villa->code)->implode(', ') }}<br>Rate plan: {{ $b->ratePlan?->name }}
        </div>
    </div>
    <table><thead><tr><th>Accompanying guests</th><th>Nationality</th><th>Passport</th></tr></thead><tbody>
        @forelse ($b->villas->flatMap->guests as $sg)<tr><td>{{ $sg->fullName() }}</td><td>{{ $sg->nationality }}</td><td>{{ $sg->passport_no ? '••••'.substr($sg->passport_no, -4) : '' }}</td></tr>
        @empty<tr><td colspan="3">&nbsp;</td></tr><tr><td colspan="3">&nbsp;</td></tr>@endforelse
    </tbody></table>
    <p class="doc-meta" style="margin-top:18px">I agree to settle all charges incurred during my stay. Valuables should be kept in the in-villa safe. The property is not liable for items left unattended. Smoking is not permitted indoors.</p>
    <div class="doc-grid" style="margin-top:40px"><div>Guest signature: ______________________</div><div>Received by: ______________________</div></div>
</div>
@endsection
