@extends('layouts.admin')
@section('title', 'Enquiries')
@section('content')
<x-page-header title="Website enquiries" sub="Messages from the contact form. Reply by email or WhatsApp, then mark as replied." />
<div class="tabs">
    @foreach (['new' => 'New', 'replied' => 'Replied', 'closed' => 'Closed', 'all' => 'All'] as $k => $v)
        <a href="{{ route('admin.enquiries.index', ['status' => $k]) }}" @class(['active' => $status === $k])>{{ $v }} @if($k !== 'all')<span class="muted">({{ $counts[$k] ?? 0 }})</span>@endif</a>
    @endforeach
</div>
<div class="stack">
@forelse ($enquiries as $e)
    <div class="card">
        <div class="card-head"><h2>{{ $e->subject ?: 'Enquiry' }} — {{ $e->name }}</h2><x-badge :status="$e->status" /></div>
        <div class="card-body grid cols-2">
            <div>
                <p style="white-space:pre-line">{{ $e->message }}</p>
                <div class="small muted">{{ fmt_dt($e->created_at) }} · {{ $e->email }} {{ $e->phone ? '· '.$e->phone : '' }}
                    @if ($e->arrival)<br>Dates: {{ fmt_date($e->arrival) }} → {{ fmt_date($e->departure) }} · {{ $e->guests }} guest(s)@endif</div>
                <div class="row" style="margin-top:10px">
                    <span class="btn btn-sm" data-copy="{{ $e->email }}">Copy email</span>
                    @if ($e->phone)<a class="btn btn-sm" target="_blank" href="{{ whatsapp_link('Hello '.$e->name.', thank you for contacting Vaasal Villa.', $e->phone) }}"><x-icon name="whatsapp" /> WhatsApp</a>@endif
                    @perm('bookings.manage')@if($e->arrival)<a class="btn btn-sm" href="{{ route('admin.bookings.create', ['arrival' => $e->arrival->toDateString(), 'departure' => $e->departure?->toDateString(), 'source' => 'email']) }}">Create booking</a>@endif @endperm
                </div>
            </div>
            <form method="post" action="{{ route('admin.enquiries.update', $e) }}" class="form-grid">
                @csrf @method('put')
                <x-select name="status" label="Status" :options="['new' => 'New', 'replied' => 'Replied', 'closed' => 'Closed']" :value="$e->status" col="f-12" :id="'st'.$e->id" />
                <x-textarea name="reply_notes" label="Reply / notes" :value="$e->reply_notes" rows="3" :id="'rn'.$e->id" />
                <div class="f-12 row between"><span class="small muted">{{ $e->handler ? 'Handled by '.$e->handler->name : '' }}</span><button class="btn btn-sm" type="submit">Save</button></div>
            </form>
        </div>
    </div>
@empty
    <div class="card"><x-empty title="No enquiries" icon="mail" /></div>
@endforelse
</div>
{{ $enquiries->links() }}
@endsection
