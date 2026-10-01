@extends('layouts.admin')
@section('title', 'Offers')
@section('content')
<x-page-header title="Offers & promo codes" sub="Featured offers appear on the website home and Offers pages; codes apply in the booking engine and admin bookings.">
    <a class="btn btn-primary" href="{{ route('admin.offers.create') }}"><x-icon name="plus" /> New offer</a>
</x-page-header>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Offer</th><th>Code</th><th>Discount</th><th>Min nights</th><th>Valid</th><th class="num">Used</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($offers as $o)
            <tr>
                <td><strong>{{ $o->title }}</strong> @if($o->is_featured)<x-badge tone="accent" label="Featured" />@endif<div class="small muted">{{ $o->summary }}</div></td>
                <td class="mono">{{ $o->promo_code ?? '—' }}</td>
                <td>{{ $o->label() }}</td><td>{{ $o->min_nights }}</td>
                <td class="small nowrap">{{ fmt_date($o->valid_from) }} – {{ fmt_date($o->valid_to) }}</td>
                <td class="num">{{ $o->used_count }}{{ $o->max_uses ? ' / '.$o->max_uses : '' }}</td>
                <td><x-badge :status="$o->is_active ? 'active' : 'inactive'" /></td>
                <td class="actions"><a class="btn btn-sm btn-ghost" href="{{ route('admin.offers.edit', $o) }}" aria-label="Edit"><x-icon name="edit" /></a>
                    <form method="post" action="{{ route('admin.offers.destroy', $o) }}" style="display:inline" data-confirm="Delete this offer?" data-danger>@csrf @method('delete')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Delete"><x-icon name="trash" /></button></form></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty title="No offers yet" icon="star" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
