@extends('layouts.admin')
@section('title', 'Card '.$card->uid)
@section('content')
<x-page-header :title="'Card '.$card->uid" :crumbs="['Key cards' => route('admin.keycards.index')]" :sub="ucfirst($card->type).' card'.($card->card_number ? ' · '.$card->card_number : '').($card->notes ? ' · '.$card->notes : '')">
    <x-badge :status="$card->status" />
    @perm('keycards.issue')
        @if ($card->status === 'active')
            <button class="btn" type="button" data-modal-open="#lost-modal">Report lost</button>
        @endif
    @endperm
    @perm('keycards.manage')
        @if (in_array($card->status, ['available', 'active']))
            <form method="post" action="{{ route('admin.keycards.block', $card) }}" data-confirm="Block card {{ $card->uid }}? Any active access is revoked." data-reason data-danger>@csrf<button class="btn btn-danger" type="submit">Block</button></form>
        @elseif (in_array($card->status, ['blocked', 'lost', 'damaged']))
            <form method="post" action="{{ route('admin.keycards.unblock', $card) }}" data-confirm="Return this card to available stock?">@csrf<button class="btn" type="submit">Return to stock</button></form>
        @endif
    @endperm
</x-page-header>
<div class="grid cols-main">
    <div class="card">
        <div class="card-head"><h2>Assignments</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Holder</th><th>Access</th><th>Valid</th><th>Status</th><th>Encoder jobs</th><th></th></tr></thead>
            <tbody>
            @forelse ($card->assignments as $a)
                <tr>
                    <td>{{ $a->holderName() }}@if($a->booking)<div class="small"><a class="mono" href="{{ route('admin.bookings.show', $a->booking) }}">{{ $a->booking->reference }}</a></div>@endif</td>
                    <td class="small">{{ $a->villa?->code ?? ucfirst($a->access_level) }}{{ $a->zone ? ' · '.$a->zone : '' }}<br><span class="muted">{{ implode(', ', $a->lock_refs ?? []) }} · {{ $a->issue_type }}</span></td>
                    <td class="small nowrap">{{ fmt_dt($a->valid_from) }}<br>→ {{ fmt_dt($a->valid_to) }}</td>
                    <td><x-badge :status="$a->status" />@if($a->revoke_reason)<div class="small muted">{{ $a->revoke_reason }}</div>@endif</td>
                    <td class="small">@foreach ($a->jobs as $j)<div>{{ label($j->action) }}: <x-badge :status="$j->status" />
                        @if ($j->status === 'failed')@perm('keycards.issue')<form method="post" action="{{ route('admin.keycards.retry', $j) }}" style="display:inline">@csrf<button class="btn btn-sm btn-ghost" type="submit">Retry</button></form>@endperm @endif</div>@endforeach</td>
                    <td class="actions">
                        @if ($a->status === 'active')
                            @perm('keycards.issue')
                            <button class="btn btn-sm" type="button" data-modal-open="#extend-modal" data-action="{{ route('admin.keycards.extend', $a) }}">Extend</button>
                            <form method="post" action="{{ route('admin.keycards.revoke', $a) }}" data-confirm="Revoke this card now?" data-danger style="display:inline">@csrf<button class="btn btn-sm" type="submit">Revoke</button></form>
                            @endperm
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Never issued.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Access history</h2></div>
        <ul class="list">
            @forelse ($logs as $l)
                <li><x-badge :tone="in_array($l->event, ['open', 'issued']) ? 'success' : (in_array($l->event, ['denied', 'blocked_card', 'expired_card']) ? 'danger' : 'neutral')" :label="label($l->event)" />
                    <span class="small">{{ $l->villa?->code ?? $l->lock_ref }} · {{ fmt_dt($l->occurred_at) }}<br><span class="muted">{{ $l->source }}</span></span></li>
            @empty
                <li class="muted small">No events recorded.</li>
            @endforelse
        </ul>
    </div>
</div>

<x-modal id="lost-modal" title="Report card lost">
    <form method="post" action="{{ route('admin.keycards.lost', $card) }}">
        @csrf
        <div class="modal-body form-grid">
            <p class="f-12" style="margin:0">The card is blocked immediately. Issuing a replacement as a <strong>new key</strong> also makes the lost card fail at offline locks.</p>
            <x-input name="replacement_uid" label="Replacement card UID (optional)" col="f-12" autocomplete="off" />
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-danger" type="submit">Block card</button></div>
    </form>
</x-modal>
<x-modal id="extend-modal" title="Extend card validity">
    <form method="post" action="#">
        @csrf
        <div class="modal-body form-grid"><x-input name="valid_to" type="datetime-local" label="Valid until" :value="now()->addDay()->setTime(12, 0)" required col="f-12" /></div>
        <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Extend</button></div>
    </form>
</x-modal>
@endsection
