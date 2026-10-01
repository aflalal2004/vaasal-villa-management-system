/* Vaasal Villa HMS — shared UI behaviour (no build step, no dependencies). */
(function () {
  'use strict';
  const VV = (window.VV = window.VV || {});
  const doc = document.documentElement;
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  /* ---------- Theme: light / dark / system, saved per user (server) or per browser ---------- */
  const THEMES = ['light', 'dark', 'system'];
  function applyTheme(t) {
    if (t === 'light' || t === 'dark') doc.setAttribute('data-theme', t); else doc.removeAttribute('data-theme');
    document.querySelectorAll('[data-theme-label]').forEach(el => (el.textContent = t.charAt(0).toUpperCase() + t.slice(1)));
  }
  VV.setTheme = function (t) {
    applyTheme(t);
    try { localStorage.setItem('vv-theme', t); } catch (e) {}
    const url = document.body.dataset.themeUrl;
    if (url) fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ theme: t }) }).catch(() => {});
  };
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-theme-toggle]');
    if (!btn) return;
    const cur = doc.getAttribute('data-theme') || 'system';
    VV.setTheme(THEMES[(THEMES.indexOf(cur) + 1) % THEMES.length]);
  });

  /* ---------- Sidebar (mobile) ---------- */
  document.addEventListener('click', e => {
    if (e.target.closest('[data-nav-toggle]')) { document.body.classList.toggle('nav-open'); return; }
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar')) document.body.classList.remove('nav-open');
  });

  /* ---------- Dropdowns ---------- */
  document.addEventListener('click', e => {
    const trigger = e.target.closest('[data-dropdown]');
    document.querySelectorAll('.dropdown-menu:not([hidden])').forEach(m => {
      if (!trigger || m !== trigger.parentElement.querySelector('.dropdown-menu')) m.hidden = true;
    });
    if (trigger) { const m = trigger.parentElement.querySelector('.dropdown-menu'); if (m) m.hidden = !m.hidden; }
  });

  /* ---------- Modals ---------- */
  VV.openModal = sel => { const m = typeof sel === 'string' ? document.querySelector(sel) : sel; if (m) { m.hidden = false; m.querySelector('input:not([type=hidden]),select,textarea,button')?.focus(); } };
  VV.closeModal = m => { if (m) m.hidden = true; };
  document.addEventListener('click', e => {
    const open = e.target.closest('[data-modal-open]');
    if (open) {
      e.preventDefault();
      const m = document.querySelector(open.dataset.modalOpen);
      // Pass data-* values from the trigger into the modal form (e.g. line id, action URL)
      if (m && open.dataset.action) { const f = m.querySelector('form'); if (f) f.action = open.dataset.action; }
      if (m) Object.entries(open.dataset).forEach(([k, v]) => { const f = m.querySelector('[data-fill="' + k + '"]'); if (f) { if ('value' in f && f.tagName !== 'DIV' && f.tagName !== 'SPAN') f.value = v; else f.textContent = v; } });
      VV.openModal(m);
      return;
    }
    if (e.target.closest('[data-modal-close]') || e.target.classList.contains('modal')) VV.closeModal(e.target.closest('.modal'));
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal:not([hidden])').forEach(VV.closeModal); });

  /* ---------- Confirm dialogs for destructive/irreversible actions ---------- */
  let pendingForm = null;
  function confirmModal() {
    let m = document.getElementById('vv-confirm');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'modal'; m.id = 'vv-confirm'; m.hidden = true;
    m.innerHTML = '<div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="vv-confirm-title"><div class="modal-head"><h2 id="vv-confirm-title">Please confirm</h2></div>' +
      '<div class="modal-body"><p id="vv-confirm-msg"></p><div class="field" id="vv-confirm-reason-wrap" hidden><label for="vv-confirm-reason">Reason <span class="req">*</span></label><input type="text" id="vv-confirm-reason" maxlength="200"></div></div>' +
      '<div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="button" class="btn btn-primary" id="vv-confirm-ok">Confirm</button></div></div>';
    document.body.appendChild(m);
    m.querySelector('#vv-confirm-ok').addEventListener('click', () => {
      if (!pendingForm) return;
      const wrap = m.querySelector('#vv-confirm-reason-wrap');
      if (!wrap.hidden) {
        const r = m.querySelector('#vv-confirm-reason');
        if (!r.value.trim()) { r.classList.add('is-invalid'); r.focus(); return; }
        let hidden = pendingForm.querySelector('input[name="' + (pendingForm.dataset.reasonField || 'reason') + '"]');
        if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = pendingForm.dataset.reasonField || 'reason'; pendingForm.appendChild(hidden); }
        hidden.value = r.value.trim();
      }
      pendingForm.dataset.confirmed = '1';
      m.hidden = true;
      pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
    });
    return m;
  }
  document.addEventListener('submit', e => {
    const f = e.target;
    if (f.dataset.confirm && f.dataset.confirmed !== '1') {
      e.preventDefault();
      pendingForm = f;
      const m = confirmModal();
      m.querySelector('#vv-confirm-msg').textContent = f.dataset.confirm;
      const needReason = f.hasAttribute('data-reason');
      m.querySelector('#vv-confirm-reason-wrap').hidden = !needReason;
      m.querySelector('#vv-confirm-reason').value = '';
      const ok = m.querySelector('#vv-confirm-ok');
      ok.textContent = f.dataset.confirmLabel || 'Confirm';
      ok.className = 'btn ' + (f.dataset.danger !== undefined ? 'btn-danger' : 'btn-primary');
      VV.openModal(m);
      return;
    }
    // Prevent double submits
    const btn = f.querySelector('button[type=submit]:not([data-allow-repeat])');
    if (btn && !f.dataset.noLock) { setTimeout(() => (btn.disabled = true), 0); setTimeout(() => (btn.disabled = false), 4000); }
  });

  /* ---------- Toasts ---------- */
  VV.toast = function (message, type = 'success', timeout = 5000, url = null) {
    let box = document.querySelector('.toasts');
    if (!box) { box = document.createElement('div'); box.className = 'toasts'; box.setAttribute('role', 'status'); box.setAttribute('aria-live', 'polite'); document.body.appendChild(box); }
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<span class="bar"></span><div></div><button type="button" aria-label="Dismiss">&times;</button>';
    const body = t.querySelector('div');
    if (url) { const a = document.createElement('a'); a.href = url; a.textContent = message; body.appendChild(a); } else { body.textContent = message; }
    t.querySelector('button').onclick = () => t.remove();
    box.appendChild(t);
    if (timeout) setTimeout(() => t.remove(), timeout);
  };
  document.querySelectorAll('[data-flash]').forEach(el => { try { JSON.parse(el.dataset.flash).forEach(f => VV.toast(f.message, f.type, f.type === 'error' ? 9000 : 5000)); } catch (e) {} });

  /* ---------- JSON API helper ---------- */
  VV.api = async function (url, method = 'GET', body = null) {
    const opts = { method, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } };
    if (body !== null) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    const res = await fetch(url, opts);
    let data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok) {
      // 401 = the sign-in session has ended (idle timeout, signed out or signed in as someone else in another tab).
      // Show a clear message once and return to sign-in; the login page sends the user back to this screen.
      if (res.status === 401) { VV.sessionEnded(); throw new Error('Your session has ended. Please sign in again.'); }
      const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Request failed (' + res.status + ').');
      if (res.status === 419) { VV.toast('Your session expired. Reload the page and sign in again.', 'error'); }
      throw new Error(msg);
    }
    return data;
  };
  let sessionNotice = false;
  VV.sessionEnded = function () {
    if (sessionNotice) return;
    sessionNotice = true;
    VV.toast('Your session has ended (signed out, idle too long, or another account signed in on this browser). Taking you to sign-in…', 'warning', 6000);
    setTimeout(() => { location.href = (document.querySelector('meta[name="login-url"]')?.content || '/login') + '?return=' + encodeURIComponent(location.pathname + location.search); }, 2500);
  };
  VV.money = n => (window.VV_CURRENCY || '') + ' ' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  VV.esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------- Small helpers ---------- */
  document.addEventListener('change', e => { if (e.target.matches('[data-autosubmit]')) e.target.form.submit(); });
  document.addEventListener('click', async e => {
    const c = e.target.closest('[data-copy]');
    if (!c) return;
    try { await navigator.clipboard.writeText(c.dataset.copy); VV.toast('Copied to clipboard', 'info', 2000); } catch (err) { VV.toast('Copy failed — select the text manually.', 'error'); }
  });
  // Repeatable rows: <div data-repeat="tpl-id"> + button[data-repeat-add="container-id"]
  document.addEventListener('click', e => {
    const add = e.target.closest('[data-repeat-add]');
    if (add) {
      const container = document.getElementById(add.dataset.repeatAdd);
      const tpl = document.getElementById(container.dataset.template);
      const idx = Date.now();
      container.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, idx));
    }
    const rm = e.target.closest('[data-repeat-remove]');
    if (rm) rm.closest('[data-repeat-row]').remove();
  });
  // Print
  document.addEventListener('click', e => { if (e.target.closest('[data-print]')) window.print(); });

  /* ---------- Show / hide password: <button data-toggle-password="input-id"> ---------- */
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-toggle-password]');
    if (!b) return;
    const input = document.getElementById(b.dataset.togglePassword);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    b.classList.toggle('is-shown', show);
    input.focus();
  });
  /* ---------- Live notifications: poll the bell endpoint; pauses while the tab is hidden ---------- */
  const bell = document.querySelector('[data-bell]');
  if (bell && window.fetch) {
    let lastId = parseInt(bell.dataset.lastId || '0', 10);
    const every = Math.max(10, parseInt(bell.dataset.pollInterval || '30', 10)) * 1000;
    const count = bell.querySelector('[data-bell-count]');
    const tones = { danger: 'error', warning: 'warning', success: 'success', info: 'info' };
    let busy = false;
    const poll = async () => {
      if (document.hidden || busy) return;
      busy = true;
      try {
        const r = await fetch(bell.dataset.pollUrl + '?after=' + lastId, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (r.status === 401) { VV.sessionEnded(); return; }
        if (!r.ok) return;
        const d = await r.json();
        count.hidden = d.unread < 1;
        count.textContent = d.unread > 99 ? '99+' : d.unread;
        bell.setAttribute('aria-label', 'Notifications' + (d.unread ? ', ' + d.unread + ' unread' : ''));
        d.items.slice().reverse().forEach(n => VV.toast(n.title + (n.body ? ' — ' + n.body : ''), tones[n.level] || 'info', 10000, n.url));
        if (d.items.length) { bell.classList.add('ring'); setTimeout(() => bell.classList.remove('ring'), 1400); }
        lastId = Math.max(lastId, d.last_id || 0);
      } catch (e) { /* offline: try again next tick */ } finally { busy = false; }
    };
    setInterval(poll, every);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  }

  /* ---------- Sidebar: collapse to an icon rail (desktop), remembered per screen type ---------- */
  document.addEventListener('click', e => {
    if (!e.target.closest('[data-sidebar-collapse]')) return;
    const on = doc.classList.toggle('sidebar-min');
    const key = 'vv-sidebar-' + (document.body.dataset.sidebarDefault === 'min' ? 'fs' : 'std');
    try { localStorage.setItem(key, on ? 'min' : 'full'); } catch (err) {}
  });
})();
