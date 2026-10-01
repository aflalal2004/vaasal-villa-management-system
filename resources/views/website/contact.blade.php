@extends('layouts.site')
@section('title', 'Contact')
@section('header_class', 'always')
@section('content')
<section class="block" style="padding-top:160px">
    <div class="wrap split" style="align-items:start">
        <div>
            <span class="eyebrow">Contact</span>
            <h1 style="font-size:clamp(36px,5vw,58px)">Tell us about your trip.</h1>
            <p class="lead" style="margin-top:16px">Questions about dates, weddings, transfers or dietary needs — we reply within a few hours.</p>
            <div style="display:grid;gap:14px;margin-top:26px">
                <a class="btn btn-primary" style="justify-self:start" href="{{ whatsapp_link('Hello Vaasal Villa!') }}" target="_blank" rel="noopener"><x-icon name="whatsapp-fill" /> WhatsApp {{ contact('whatsapp_display') }}</a>
                <div class="chips"><a class="chip" href="mailto:{{ contact('email') }}"><x-icon name="mail" /> {{ contact('email') }}</a><a class="chip" href="tel:{{ contact('phone_link') }}"><x-icon name="phone" /> {{ contact('phone') }}</a><span class="chip"><x-icon name="pin" /> {{ contact('address') }}</span></div>
                @if ($socials = \App\Models\SocialLink::for('website'))<div class="socials socials-light" aria-label="Follow us">@foreach ($socials as $s)<a href="{{ $s->url }}" target="_blank" rel="noopener" aria-label="{{ $s->name() }}"><x-icon :name="$s->icon()" /></a>@endforeach</div>@endif
            </div>
            <iframe class="map" style="margin-top:28px" loading="lazy" title="Map" referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps?q={{ urlencode(config('vaasal.site.maps_query')) }}&ll={{ property()->latitude }},{{ property()->longitude }}&z=13&output=embed"></iframe>
        </div>
        <form class="form-card" method="post" action="{{ route('site.enquiry') }}" data-once novalidate>
            @csrf
            @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
            <div class="fgrid">
                <div class="field"><label for="c-name">Your name</label><input id="c-name" name="name" value="{{ old('name') }}" required maxlength="120"></div>
                <div class="field"><label for="c-email">Email</label><input id="c-email" type="email" name="email" value="{{ old('email') }}" required></div>
                <div class="field"><label for="c-phone">Phone / WhatsApp</label><input id="c-phone" name="phone" value="{{ old('phone') }}"></div>
                <div class="field"><label for="c-subject">Subject</label><input id="c-subject" name="subject" value="{{ old('subject') }}"></div>
                <div class="field"><label for="c-arr">Arrival (optional)</label><input id="c-arr" type="date" name="arrival" value="{{ old('arrival') }}" min="{{ now()->toDateString() }}"></div>
                <div class="field"><label for="c-dep">Departure</label><input id="c-dep" type="date" name="departure" value="{{ old('departure') }}"></div>
                <div class="field"><label for="c-guests">Guests</label><input id="c-guests" type="number" min="1" max="40" name="guests" value="{{ old('guests') }}"></div>
                <div class="field full"><label for="c-msg">Message</label><textarea id="c-msg" name="message" required minlength="10">{{ old('message') }}</textarea></div>
                <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="full"><button class="btn btn-primary" type="submit" data-busy="Sending…">Send message</button></div>
            </div>
        </form>
    </div>
</section>
@endsection
