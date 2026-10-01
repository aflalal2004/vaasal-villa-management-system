/* Vaasal Villa website: theme, header, hero slider, reveal, counters, lightbox, testimonials, weather, booking widget. */
(function () {
    'use strict';
    const doc = document.documentElement;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Theme (saved per browser)
    const order = ['light', 'dark', 'system'];
    document.querySelectorAll('[data-theme-toggle]').forEach(b => b.addEventListener('click', () => {
        const cur = doc.getAttribute('data-theme') || 'system';
        const next = order[(order.indexOf(cur) + 1) % 3];
        if (next === 'system') doc.removeAttribute('data-theme'); else doc.setAttribute('data-theme', next);
        try { localStorage.setItem('vv-theme', next); } catch (e) {}
        b.setAttribute('aria-label', 'Theme: ' + next);
        b.title = 'Theme: ' + next;
    }));

    // Header: transparent over hero, solid after scroll; mobile menu
    const header = document.querySelector('.site-header');
    const onScroll = () => header && !header.classList.contains('always') && header.classList.toggle('solid', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
    document.querySelector('.menu-btn')?.addEventListener('click', e => { const on = document.body.classList.toggle('menu-open'); e.currentTarget.setAttribute('aria-expanded', on ? 'true' : 'false'); });
    document.querySelectorAll('.nav-links a').forEach(a => a.addEventListener('click', () => document.body.classList.remove('menu-open')));

    // Hero slider (Ken Burns crossfade)
    const slides = [...document.querySelectorAll('.hero-slides .slide')];
    const dots = document.querySelector('.hero-dots');
    if (slides.length) {
        let i = 0;
        const show = n => { slides.forEach((s, k) => s.classList.toggle('on', k === n)); dots?.querySelectorAll('button').forEach((d, k) => d.classList.toggle('on', k === n)); i = n; };
        if (dots) slides.forEach((_, k) => { const b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', 'Slide ' + (k + 1)); b.onclick = () => show(k); dots.appendChild(b); });
        show(0);
        if (!reduce && slides.length > 1) setInterval(() => show((i + 1) % slides.length), 7000);
    }

    // ---------------------------------------------------------------- Staggered cards
    document.querySelectorAll('[data-stagger]').forEach(list => [...list.children].forEach((c, i) => { c.classList.add('reveal'); c.style.transitionDelay = Math.min(i, 8) * 70 + 'ms'; }));

    // Scroll reveal: content is visible by default; only hidden once JS confirms it can reveal it.
    const reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && !reduce && reveals.length) {
        doc.classList.add('js-reveal');
        const io = new IntersectionObserver(entries => entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12, rootMargin: '0px 0px -40px 0px' });
        reveals.forEach(el => { if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add('in'); io.observe(el); });
    }

    // Animated counters
    const counters = document.querySelectorAll('[data-count]');
    const run = el => {
        const target = parseFloat(el.dataset.count), dec = (el.dataset.count.split('.')[1] || '').length, start = performance.now(), dur = 1600;
        const step = t => { const p = Math.min(1, (t - start) / dur), v = target * (1 - Math.pow(1 - p, 3)); el.textContent = v.toLocaleString(undefined, { minimumFractionDigits: dec, maximumFractionDigits: dec }) + (el.dataset.suffix || ''); if (p < 1) requestAnimationFrame(step); };
        requestAnimationFrame(step);
    };
    if (counters.length) {
        if (reduce || !('IntersectionObserver' in window)) counters.forEach(el => el.textContent = Number(el.dataset.count).toLocaleString() + (el.dataset.suffix || ''));
        else { const co = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { run(e.target); co.unobserve(e.target); } }), { threshold: .6 }); counters.forEach(c => co.observe(c)); }
    }

    // Lightbox for [data-lightbox] groups (images and YouTube/Vimeo videos)
    const lbItems = [...document.querySelectorAll('[data-lightbox]')];
    let lb, idx = 0, group = [];
    function embed(url) {
        const yt = url.match(/(?:youtu\.be\/|v=)([\w-]{6,})/);
        if (yt) return 'https://www.youtube-nocookie.com/embed/' + yt[1] + '?autoplay=1';
        const vm = url.match(/vimeo\.com\/(\d+)/);
        return vm ? 'https://player.vimeo.com/video/' + vm[1] + '?autoplay=1' : url;
    }
    function openLb(item) {
        group = lbItems.filter(i => i.dataset.lightbox === item.dataset.lightbox && !i.closest('[hidden]'));
        idx = group.indexOf(item);
        if (!lb) {
            lb = document.createElement('div');
            lb.className = 'lightbox'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
            lb.innerHTML = '<div class="lb-stage"></div><div class="lb-cap"></div><button class="lb-close" aria-label="Close">×</button><button class="lb-prev" aria-label="Previous">‹</button><button class="lb-next" aria-label="Next">›</button>';
            document.body.appendChild(lb);
            lb.querySelector('.lb-close').onclick = closeLb;
            lb.querySelector('.lb-prev').onclick = () => showLb(idx - 1);
            lb.querySelector('.lb-next').onclick = () => showLb(idx + 1);
            lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });
            document.addEventListener('keydown', e => { if (!lb || lb.hidden) return; if (e.key === 'Escape') closeLb(); if (e.key === 'ArrowLeft') showLb(idx - 1); if (e.key === 'ArrowRight') showLb(idx + 1); });
            let x0 = null;
            lb.addEventListener('touchstart', e => x0 = e.touches[0].clientX, { passive: true });
            lb.addEventListener('touchend', e => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 50) showLb(idx + (dx < 0 ? 1 : -1)); x0 = null; });
        }
        lb.hidden = false; document.body.style.overflow = 'hidden';
        showLb(idx);
    }
    function showLb(n) {
        idx = (n + group.length) % group.length;
        const it = group[idx], stage = lb.querySelector('.lb-stage');
        stage.innerHTML = it.dataset.type === 'video' && /\.mp4(\?|$)/i.test(it.href) ? `<video src="${it.href}" controls autoplay playsinline style="max-width:100%;max-height:80vh"></video>` : it.dataset.type === 'video' ? `<iframe src="${embed(it.href)}" allow="autoplay; encrypted-media; fullscreen" allowfullscreen title="Video"></iframe>` : `<img src="${it.href}" alt="${(it.dataset.caption || '').replace(/"/g, '')}">`;
        lb.querySelector('.lb-cap').textContent = (it.dataset.caption || '') + '  ' + (idx + 1) + ' / ' + group.length;
        lb.querySelectorAll('.lb-prev,.lb-next').forEach(b => b.hidden = group.length < 2);
    }
    function closeLb() { lb.hidden = true; lb.querySelector('.lb-stage').innerHTML = ''; document.body.style.overflow = ''; }
    lbItems.forEach(a => a.addEventListener('click', e => { e.preventDefault(); openLb(a); }));

    // Gallery filters
    document.querySelectorAll('[data-filter-group]').forEach(tabs => {
        const target = document.getElementById(tabs.dataset.filterGroup);
        tabs.addEventListener('click', e => {
            const b = e.target.closest('button'); if (!b) return;
            tabs.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
            target.querySelectorAll('[data-cat]').forEach(el => el.hidden = b.dataset.filter !== 'all' && el.dataset.cat !== b.dataset.filter);
        });
    });

    // Testimonials slider
    document.querySelectorAll('.quotes').forEach(q => {
        const track = q.querySelector('.quote-track'), n = track.children.length; let i = 0;
        const go = k => { i = (k + n) % n; track.style.transform = `translateX(-${i * 100}%)`; };
        q.parentElement.querySelector('[data-q-prev]')?.addEventListener('click', () => go(i - 1));
        q.parentElement.querySelector('[data-q-next]')?.addEventListener('click', () => go(i + 1));
        if (!reduce && n > 1) setInterval(() => go(i + 1), 8000);
    });

    // YouTube facade (loads the player only on click — privacy & performance)
    document.querySelectorAll('[data-yt]').forEach(b => b.addEventListener('click', () => {
        b.outerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${b.dataset.yt}?autoplay=1&rel=0" title="Villa tour video" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>`;
    }));

    // Weather icons as inline SVG (keys from WeatherService: sun, sun-cloud, cloud, fog, rain, storm)
    const WX = { sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'sun-cloud': '<path d="M8 4v1.5M3.5 8H5M4.8 4.8l1 1M12.5 8.5a4 4 0 0 0-7 2"/><path d="M7 20h10a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 7 20z"/>',
        cloud: '<path d="M7 19h10a4.5 4.5 0 0 0 .5-9 6 6 0 0 0-11.4 2A3.5 3.5 0 0 0 7 19z"/>',
        fog: '<path d="M4 9h16M3 13h18M5 17h14"/>',
        rain: '<path d="M7 15h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.4 2A3 3 0 0 0 7 15z"/><path d="M9 18l-1 2M13 18l-1 2M17 18l-1 2"/>',
        storm: '<path d="M7 14h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.4 2A3 3 0 0 0 7 14z"/><path d="m12 15-2 4h3l-2 4"/>' };
    const wxIcon = k => '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (WX[k] || WX['sun-cloud']) + '</svg>';

    // Weather (server proxies Open-Meteo; no key)
    const w = document.querySelector('[data-weather]');
    if (w) fetch(w.dataset.weather, { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : Promise.reject()).then(d => {
        if (!d.ok) throw 0;
        w.querySelector('.temp').textContent = Math.round(d.current.temp) + '°C';
        w.querySelector('.cond').textContent = d.current.label;
        w.querySelector('.extra').textContent = 'Wind ' + Math.round(d.current.wind) + ' km/h · Sea breeze';
        w.querySelector('.forecast').innerHTML = d.daily.map(x => `<div><div class="muted">${x.day}</div><div class="wx-ic">${wxIcon(x.icon)}</div><div>${Math.round(x.max)}° / ${Math.round(x.min)}°</div></div>`).join('');
    }).catch(() => { w.querySelector('.cond').textContent = 'Weather unavailable right now'; w.querySelector('.forecast').innerHTML = ''; w.querySelector('.temp').textContent = '—'; });

    // Booking widget: keep departure after arrival, show nights
    document.querySelectorAll('form[data-booking]').forEach(f => {
        const a = f.querySelector('[name=arrival]'), d = f.querySelector('[name=departure]'), n = f.querySelector('[data-nights]');
        const sync = () => {
            if (!a.value) return;
            const min = new Date(a.value); min.setDate(min.getDate() + 1);
            const iso = min.toISOString().slice(0, 10);
            d.min = iso;
            if (!d.value || d.value <= a.value) d.value = iso;
            if (n) { const nights = Math.round((new Date(d.value) - new Date(a.value)) / 864e5); n.textContent = nights + ' night' + (nights === 1 ? '' : 's'); }
        };
        a.addEventListener('change', sync); d.addEventListener('change', sync); sync();
    });

    // Broken remote images fall back to the local placeholder
    document.querySelectorAll('img[data-fallback]').forEach(img => img.addEventListener('error', () => { if (img.src !== img.dataset.fallback) img.src = img.dataset.fallback; }, { once: true }));

    // Prevent double submit on booking/payment forms
    document.querySelectorAll('form[data-once]').forEach(f => f.addEventListener('submit', () => { const b = f.querySelector('[type=submit]'); if (b) { b.disabled = true; b.textContent = b.dataset.busy || 'Please wait…'; } }));

    // ---------------------------------------------------------------- Display currency
    // Prices are stored in LKR; every [data-price-lkr] is re-rendered in the chosen currency without a reload.
    const FX = window.VV_FX;
    const fxFormat = (lkr, code) => {
        const c = FX.currencies[code] || FX.currencies[FX.base];
        const rate = code === FX.base ? 1 : Number(FX.rates[code] || 0);
        if (!rate) return fxFormat(lkr, FX.base);
        const value = Number((lkr * rate).toFixed(c.decimals));
        const n = value.toLocaleString('en-US', { minimumFractionDigits: c.decimals, maximumFractionDigits: c.decimals });
        return (c.symbol === 'Rs' || c.symbol === 'AED') ? c.symbol + ' ' + n : c.symbol + n;
    };
    function renderPrices(code) {
        document.querySelectorAll('[data-price-lkr]').forEach(el => {
            const lkr = parseFloat(el.dataset.priceLkr);
            if (el.hasAttribute('data-approx')) { el.hidden = code === FX.base; el.textContent = '≈ ' + fxFormat(lkr, code); return; }
            el.textContent = fxFormat(lkr, code);
            el.title = code === FX.base ? '' : fxFormat(lkr, FX.base) + ' — charged in LKR';
        });
        document.querySelectorAll('[data-fx-note]').forEach(el => el.hidden = code === FX.base);
    }
    document.querySelectorAll('[data-currency-select]').forEach(box => {
        const btn = box.querySelector('.cur-btn'), menu = box.querySelector('.cur-menu');
        const options = [...menu.querySelectorAll('button[name=currency]')];
        const open = on => { menu.hidden = !on; btn.setAttribute('aria-expanded', on ? 'true' : 'false'); if (on) (options.find(o => o.classList.contains('on')) || options[0]).focus(); };
        btn.addEventListener('click', e => { e.stopPropagation(); open(menu.hidden); });
        document.addEventListener('click', e => { if (!box.contains(e.target)) open(false); });
        box.addEventListener('keydown', e => {
            if (e.key === 'Escape') { open(false); btn.focus(); }
            const i = options.indexOf(document.activeElement);
            if (i > -1 && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); options[(i + (e.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length].focus(); }
        });
        options.forEach(o => o.addEventListener('click', e => {
            if (!FX) return; // no data: let the form post normally
            e.preventDefault();
            const code = o.value;
            try { document.cookie = FX.cookie + '=' + code + ';path=/;max-age=31536000;samesite=lax'; } catch (err) {}
            FX.current = code;
            options.forEach(x => { const on = x === o; x.classList.toggle('on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
            box.querySelector('[data-cur-sym]').textContent = FX.currencies[code].symbol;
            box.querySelector('[data-cur-code]').textContent = code;
            btn.setAttribute('aria-label', 'Display currency: ' + code);
            renderPrices(code);
            document.querySelectorAll('[data-currency-mirror]').forEach(s => s.value = code);
            open(false); btn.focus();
        }));
    });

    // ---------------------------------------------------------------- Hero background video
    // Loaded only on wide screens, without reduced motion or data saver; the poster/photos show otherwise.
    document.querySelectorAll('video[data-hero-video]').forEach(v => {
        const saveData = navigator.connection && navigator.connection.saveData;
        if (reduce || saveData || window.innerWidth < 800) { v.remove(); return; }
        v.src = v.dataset.src; v.load();
        v.play().catch(() => {});
        v.addEventListener('error', () => v.remove(), { once: true });
    });


    // Booking-bar currency field drives the header selector (same cookie, same live re-render)
    document.querySelectorAll('[data-currency-mirror]').forEach(sel => sel.addEventListener('change', () => {
        const opt = document.querySelector('.cur-menu button[value="' + sel.value + '"]');
        if (opt) opt.click();
    }));
})();
