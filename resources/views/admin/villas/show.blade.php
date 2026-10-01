@extends('layouts.admin')
@section('title', $villa->code.' '.$villa->name)
@section('content')
<x-page-header :title="$villa->code.' · '.$villa->name" :crumbs="['Villas' => route('admin.villas.index')]" :sub="$villa->type->name.' · '.$villa->zone.' · lock '.($villa->lock_ref ?? 'not mapped')">
    @perm('bookings.manage')<a class="btn btn-primary" href="{{ route('admin.bookings.create', ['villa' => $villa->id]) }}"><x-icon name="plus" /> Book this villa</a>@endperm
    @perm('villas.manage')<a class="btn" href="{{ route('admin.villas.edit', $villa) }}"><x-icon name="edit" /> Edit</a>@endperm
    @perm('maintenance.report')<a class="btn" href="{{ route('admin.maintenance.create', ['villa_id' => $villa->id]) }}"><x-icon name="wrench" /> Report issue</a>@endperm
</x-page-header>
<div class="stats">
    <x-stat label="Occupancy" :value="label($villa->occupancy_status)" :hint="$villa->currentStay ? $villa->currentStay->booking->guest->fullName() : 'No guest in-house'" />
    <x-stat label="Housekeeping" :value="\App\Models\Villa::HK_LABELS[$villa->hk_status]" />
    <x-stat label="Maintenance" :value="label($villa->maintenance_status)" :hint="$villa->tickets->whereNotIn('status', ['resolved', 'closed'])->count().' open ticket(s)'" />
    <x-stat label="Occupancy year to date" :value="$occupancyYtd.'%'" :pct="$occupancyYtd" />
    <x-stat label="Rate" :value="money($villa->rate_override ?? $villa->type->base_rate)" :hint="$villa->rate_override ? 'Villa override' : 'Type base rate (seasons apply)'" />
</div>
<div class="grid cols-main">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Bookings (last 60 days & upcoming)</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Reference</th><th>Guest</th><th>Stay</th><th>Channel</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    <tr><td><a class="mono" href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a></td><td>{{ $b->guest->fullName() }}</td>
                        <td class="nowrap">{{ fmt_date($b->arrival, 'd M') }} → {{ fmt_date($b->departure, 'd M') }}</td><td>{{ $b->channel->name }}</td><td><x-badge :status="$b->status" :label="$b->statusLabel()" /></td></tr>
                @empty
                    <tr><td colspan="5" class="muted">No bookings.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="grid cols-2">
            <div class="card">
                <div class="card-head"><h2>Housekeeping tasks</h2></div>
                <ul class="list">@forelse ($villa->hkTasks as $t)<li><x-badge :status="$t->status" :label="\App\Models\HkTask::STATUSES[$t->status]" /> <a href="{{ route('admin.housekeeping.tasks.show', $t) }}" class="small">{{ \App\Models\HkTask::TYPES[$t->type] }} · {{ fmt_date($t->scheduled_date, 'd M') }}</a></li>@empty<li class="muted small">None</li>@endforelse</ul>
            </div>
            <div class="card">
                <div class="card-head"><h2>Maintenance</h2></div>
                <ul class="list">@forelse ($villa->tickets as $t)<li><x-badge :status="$t->status" /> <a href="{{ route('admin.maintenance.show', $t) }}" class="small">{{ $t->title }}</a></li>@empty<li class="muted small">No tickets</li>@endforelse</ul>
            </div>
        </div>
    </div>
    <div class="stack" style="align-content:start">
        <div class="card">
            <img src="{{ $villa->coverUrl() }}" alt="{{ $villa->name }}" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px 10px 0 0;display:block">
            <div class="card-body">
                <p class="small">{{ $villa->description ?: $villa->type->short_description }}</p>
                <div class="row">@foreach ($villa->type->facilities as $f)<span class="chip">{{ $f->name }}</span>@endforeach</div>
            </div>
        </div>
        @perm('villas.manage')
        <div class="card">
            <div class="card-head"><h2>Villa-specific photos</h2></div>
            <form class="card-body stack" method="post" action="{{ route('admin.villas.media', $villa) }}" enctype="multipart/form-data">
                @csrf
                <div class="row">@foreach ($villa->media as $m)<img src="{{ $m->url() }}" alt="" style="width:70px;height:52px;object-fit:cover;border-radius:6px">@endforeach</div>
                <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" aria-label="Photos">
                <button class="btn btn-sm" type="submit"><x-icon name="upload" /> Upload</button>
            </form>
        </div>
        @endperm
    </div>
</div>
@endsection
