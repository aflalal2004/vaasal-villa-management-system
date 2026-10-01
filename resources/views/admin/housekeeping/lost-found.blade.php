@extends('layouts.admin')
@section('title', 'Lost & found')
@section('content')
<x-page-header title="Lost & found" sub="Items found in villas and public areas, where they are stored, and how they were returned.">
    <a class="btn btn-primary" href="{{ route('admin.lost-found.create') }}"><x-icon name="plus" /> Log item</a>
</x-page-header>
<div class="tabs">
    @foreach (['stored' => 'Stored', 'claimed' => 'Claimed', 'returned' => 'Returned', 'disposed' => 'Disposed', 'all' => 'All'] as $k => $v)
        <a href="?status={{ $k }}" @class(['active' => $status === $k])>{{ $v }}</a>
    @endforeach
</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Item</th><th></th><th>Where found</th><th>Found</th><th>Stored at</th><th>Possible owner</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($items as $i)
            <tr>
                <td class="mono small">{{ $i->item_no }}</td>
                <td>@if($i->photo_path)<img src="{{ asset('storage/'.$i->photo_path) }}" alt="" style="width:48px;height:36px;object-fit:cover;border-radius:5px;vertical-align:middle;margin-right:6px">@endif {{ $i->description }}<div class="small muted">{{ ucfirst($i->category) }}</div></td>
                <td>{{ $i->villa?->code }} {{ $i->found_location }}</td>
                <td class="small">{{ fmt_dt($i->found_at) }}<br>{{ $i->finder?->fullName() }}</td>
                <td>{{ $i->storage_location ?? '—' }}</td>
                <td>@if($i->guest)<a href="{{ route('admin.guests.show', $i->guest) }}">{{ $i->guest->fullName() }}</a>@else — @endif</td>
                <td><x-badge :status="$i->status" :label="\App\Models\LostFoundItem::STATUSES[$i->status]" /></td>
                <td class="actions"><a class="btn btn-sm btn-ghost" href="{{ route('admin.lost-found.edit', $i) }}" aria-label="Edit"><x-icon name="edit" /></a></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty title="Nothing here" icon="box" /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $items->links() }}
</div>
@endsection
