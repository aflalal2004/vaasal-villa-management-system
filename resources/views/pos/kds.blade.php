@extends('layouts.pos')
@section('title', 'Kitchen display')
@section('content')
<div class="row between mb">
    <div class="row">
        <h1 style="font-size:22px">Kitchen display</h1>
        <div class="tabs" style="margin:0;border:0">
            @foreach (['kitchen' => 'Kitchen', 'bar' => 'Bar', 'all' => 'All stations'] as $k => $v)
                <a href="?station={{ $k }}" @class(['active' => $station === $k])>{{ $v }}</a>
            @endforeach
        </div>
    </div>
    <div class="row small muted"><span id="kds-clock"></span> · <label class="check"><input type="checkbox" id="kds-sound" checked> Sound on new tickets</label> · updates every 5 s</div>
</div>
<div class="kds" id="kds" data-feed="{{ route('pos.kds.feed', ['station' => $station]) }}" data-status="{{ url('pos/kds') }}"></div>
<p class="muted small" id="kds-empty" hidden>No open tickets. New orders appear here the moment waiters send them.</p>
@endsection
@push('scripts')
<script>
(function () {
    const box = document.getElementById('kds');
    const next = { new: ['preparing', 'Start'], preparing: ['ready', 'Ready'], ready: ['served', 'Served'], served: ['ready', 'Recall'] };
    let known = new Set(), first = true;
    function beep() {
        if (!document.getElementById('kds-sound').checked) return;
        try { const c = new (window.AudioContext || window.webkitAudioContext)(); const o = c.createOscillator(); o.frequency.value = 880; o.connect(c.destination); o.start(); setTimeout(() => o.stop(), 180); } catch (e) {}
    }
    async function load() {
        try {
            const data = await VV.api(box.dataset.feed);
            document.getElementById('kds-empty').hidden = data.kots.length > 0;
            let fresh = false;
            box.innerHTML = data.kots.map(k => {
                if (!known.has(k.id)) { known.add(k.id); if (!first && k.status === 'new') fresh = true; }
                const age = k.age, cls = k.status === 'new' ? (age >= 20 ? 'age-late' : age >= 12 ? 'age-warn' : '') : k.status;
                return `<article class="kot ${cls}">
                    <div class="kot-head"><span>${VV.esc(k.where)} · <span class="small">${VV.esc(k.kot_no)}</span></span><span class="timer">${age}′</span></div>
                    <div class="small muted" style="padding:4px 14px 0">${VV.esc(k.outlet)} · ${VV.esc(k.waiter || '')} · ${VV.esc(k.station)}</div>
                    <ul>${k.items.map(i => `<li><b>${i.qty}×</b>${VV.esc(i.name)}${i.mods ? `<span class="mods">${VV.esc(i.mods)}</span>` : ''}${i.notes ? `<span class="note"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17v.5"/></svg> ${VV.esc(i.notes)}</span>` : ''}</li>`).join('')}</ul>
                    ${k.notes ? `<div class="small" style="padding:0 14px 8px;color:var(--crit)">${VV.esc(k.notes)}</div>` : ''}
                    <div class="kot-foot"><button class="btn ${k.status === 'preparing' ? 'btn-primary' : ''}" data-kot="${k.id}" data-to="${next[k.status][0]}">${next[k.status][1]}</button></div>
                </article>`;
            }).join('');
            if (fresh) beep();
            first = false;
        } catch (e) { VV.toast(e.message, 'error', 3000); }
    }
    box.addEventListener('click', async e => {
        const b = e.target.closest('[data-kot]');
        if (!b) return;
        b.disabled = true;
        try { await VV.api(box.dataset.status + '/' + b.dataset.kot + '/status', 'POST', { status: b.dataset.to }); load(); }
        catch (err) { VV.toast(err.message, 'error'); b.disabled = false; }
    });
    setInterval(() => document.getElementById('kds-clock').textContent = new Date().toTimeString().slice(0, 5), 1000);
    load();
    setInterval(load, 5000);
})();
</script>
@endpush
