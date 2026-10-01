/* Vaasal Villa POS terminal — tables, menu, ticket, KOT, split/merge/transfer, discounts, split tender, charge to villa. */
(function () {
    'use strict';
    const root = document.getElementById('pos');
    if (!root) return;
    const API = root.dataset.api;
    const outletId = Number(root.dataset.outlet);
    const can = { void: root.dataset.canVoid === '1', discount: root.dataset.canDiscount === '1', pay: root.dataset.canPay === '1' };
    const $ = s => document.querySelector(s);
    const esc = VV.esc, money = VV.money;
    const api = (path, method = 'GET', body = null) => VV.api(API + path, method, body);

    let order = null, menu = [], currentCat = null, pendingItem = null, pendingTable = null, tenders = [], focusInput = null, inHouse = null;

    // ------------------------------------------------------------------ tables
    async function loadTables() {
        try {
            const data = await api('/tables?outlet=' + outletId);
            const grid = $('#tables-grid');
            const areas = [...new Set(data.tables.map(t => t.area))];
            grid.innerHTML = '';
            data.tables.forEach(t => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'ptable' + (t.order ? (t.order.status === 'billed' ? ' billed' : ' busy') : (t.reservation ? ' reserved' : ''));
                b.innerHTML = `<span class="name">${esc(t.name)}</span><span class="meta">${t.order ? money(t.order.total) + ' · ' + t.order.covers + ' pax' : t.seats + ' seats · ' + esc(t.area)}</span>` +
                    (t.order ? `<span class="meta">${esc(t.order.waiter || '')} · ${t.order.since}</span>` : '') +
                    (!t.order && t.reservation ? `<span class="res-tag">Reserved ${t.reservation.time} · ${esc(t.reservation.guest)} (${t.reservation.party})</span>` : '');
                b.onclick = () => t.order ? loadOrder(t.order.id) : openTable(t);
                grid.appendChild(b);
            });
            const other = $('#other-orders');
            other.innerHTML = data.other.length ? '' : '<span class="small muted">None</span>';
            data.other.forEach(o => {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'btn';
                // Inline SVG icons (room service bell / takeaway bag) instead of emoji
                const ic = o.type === 'room_service' ? '<path d="M3 17h18M5 17a7 7 0 0 1 14 0M12 8V6M10 6h4M4 20h16"/>' : '<path d="M5 8h14l-1.3 12.2a1 1 0 0 1-1 .8H7.3a1 1 0 0 1-1-.8zM9 8V6a3 3 0 0 1 6 0v2"/>';
                b.innerHTML = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ic + '</svg>';
                b.append(' ' + (o.type === 'room_service' ? 'Room · ' : 'Takeaway · ') + (o.guest || o.order_no) + ' · ' + money(o.total));
                b.onclick = () => loadOrder(o.id);
                other.appendChild(b);
            });
            return data;
        } catch (e) { VV.toast(e.message, 'error'); }
    }
    function showTables() { $('#tables-view').hidden = false; $('#menu-view').hidden = true; $('#menu-search').hidden = true; loadTables(); }
    function showMenu() { $('#tables-view').hidden = true; $('#menu-view').hidden = false; $('#menu-search').hidden = false; }

    function openTable(t) {
        pendingTable = t;
        $('#covers').value = Math.min(2, t.seats);
        $('#guest-name').value = '';
        VV.openModal('#m-covers');
    }
    $('#covers-ok').onclick = async () => {
        VV.closeModal($('#m-covers'));
        await createOrder({ type: 'dine_in', pos_table_id: pendingTable.id, covers: Number($('#covers').value || 1), guest_name: $('#guest-name').value || null });
    };
    $('#btn-tables').onclick = () => { setOrder(null); showTables(); };
    $('#btn-takeaway').onclick = () => createOrder({ type: 'takeaway', covers: 1 });
    $('#btn-room').onclick = async () => {
        const data = await api('/in-house');
        inHouse = data.guests;
        const list = $('#room-list');
        list.innerHTML = data.guests.length ? '' : '<p class="muted">No guests in-house.</p>';
        data.guests.forEach(g => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'btn'; b.style.justifyContent = 'flex-start';
            b.disabled = g.blocked;
            b.innerHTML = `<strong>${esc(g.villas)}</strong>&nbsp;· ${esc(g.guest)} <span class="muted small">&nbsp;${esc(g.reference)}</span>`;
            b.onclick = async () => { VV.closeModal($('#m-room')); await createOrder({ type: 'room_service', booking_id: g.booking_id }); };
            list.appendChild(b);
        });
        VV.openModal('#m-room');
    };
    async function createOrder(body) {
        try { setOrder(await api('/orders', 'POST', Object.assign({ outlet_id: outletId }, body))); }
        catch (e) { VV.toast(e.message, 'error'); }
    }

    // ------------------------------------------------------------------ menu
    async function loadMenu() {
        const data = await api('/menu?outlet=' + outletId);
        menu = data.categories;
        const cats = $('#menu-cats');
        cats.innerHTML = '';
        menu.forEach((c, i) => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = c.name; b.setAttribute('role', 'tab');
            b.onclick = () => { currentCat = c.id; renderMenu(); };
            cats.appendChild(b);
            if (i === 0 && !currentCat) currentCat = c.id;
        });
        renderMenu();
    }
    function renderMenu() {
        const q = ($('#menu-search').value || '').toLowerCase();
        document.querySelectorAll('#menu-cats button').forEach((b, i) => b.classList.toggle('active', !q && menu[i].id === currentCat));
        const items = q ? menu.flatMap(c => c.items.map(i => Object.assign({ color: c.color }, i))).filter(i => i.name.toLowerCase().includes(q))
            : (menu.find(c => c.id === currentCat)?.items || []).map(i => Object.assign({ color: menu.find(c => c.id === currentCat).color }, i));
        const grid = $('#menu-grid');
        grid.innerHTML = '';
        items.forEach(it => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'mitem' + (it.available ? '' : ' off');
            b.style.setProperty('--c', it.color);
            b.innerHTML = (it.image ? `<span class="mimg"><img src="${esc(it.image)}" alt="" loading="lazy" onerror="this.parentNode.remove()"></span>` : '') +
                `<span class="n">${esc(it.name)}</span><span class="p">${money(it.price)}${it.available ? '' : ' · sold out'}</span>`;
            b.onclick = () => pickItem(it);
            grid.appendChild(b);
        });
    }
    $('#menu-search').addEventListener('input', renderMenu);

    function pickItem(it) {
        if (!order || !order.editable) { VV.toast('Open a check first.', 'info'); return; }
        if (!it.groups.length) { addItem(it.id, []); return; }
        pendingItem = it;
        $('#m-modifiers-title').textContent = it.name;
        $('#mod-notes').value = '';
        $('#mod-body').innerHTML = it.groups.map(g => `<fieldset style="border:0;padding:0;margin:0 0 14px"><legend class="label">${esc(g.name)} <span class="muted small">${g.min ? 'required' : 'optional'}${g.max > 1 ? ', up to ' + g.max : ''}</span></legend>
            <div class="row" style="margin-top:6px">${g.options.map(o => `<label class="check chip" style="padding:8px 12px"><input type="${g.max === 1 ? 'radio' : 'checkbox'}" name="g${g.id}" value="${o.id}" data-max="${g.max}">
            ${esc(o.name)}${o.price ? ' +' + money(o.price) : ''}</label>`).join('')}</div></fieldset>`).join('');
        VV.openModal('#m-modifiers');
    }
    $('#mod-add').onclick = () => {
        const it = pendingItem;
        for (const g of it.groups) {
            const n = document.querySelectorAll(`#mod-body input[name="g${g.id}"]:checked`).length;
            if (n < g.min) { VV.toast('Choose ' + g.name + '.', 'error'); return; }
            if (g.max && n > g.max) { VV.toast('Too many options for ' + g.name + '.', 'error'); return; }
        }
        const mods = [...document.querySelectorAll('#mod-body input:checked')].map(i => Number(i.value));
        VV.closeModal($('#m-modifiers'));
        addItem(it.id, mods, $('#mod-notes').value);
    };
    async function addItem(id, modifiers, notes = null) {
        try { setOrder(await api(`/orders/${order.id}/items`, 'POST', { menu_item_id: id, qty: 1, modifiers, notes })); }
        catch (e) { VV.toast(e.message, 'error'); }
    }

    // ------------------------------------------------------------------ ticket
    async function loadOrder(id) {
        try { setOrder(await api('/orders/' + id)); } catch (e) { VV.toast(e.message, 'error'); }
    }
    function setOrder(o) {
        order = o;
        const lines = $('#t-lines');
        if (!o) {
            $('#t-title').textContent = 'No check selected';
            $('#t-status').innerHTML = '';
            $('#t-sub').textContent = 'Choose a table, takeaway or room service to start.';
            lines.innerHTML = ''; $('#t-totals').innerHTML = ''; $('#t-actions').hidden = true;
            history.replaceState(null, '', '?outlet=' + outletId);
            return;
        }
        history.replaceState(null, '', '?outlet=' + outletId + '&order=' + o.id);
        $('#t-title').textContent = (o.table ? 'Table ' + o.table.name : (o.type === 'room_service' ? 'Room service' : 'Takeaway')) + ' · ' + o.order_no;
        $('#t-status').innerHTML = `<span class="badge tone-${o.status === 'open' ? 'info' : (o.status === 'billed' ? 'warning' : (['paid', 'charged_to_room'].includes(o.status) ? 'success' : 'danger'))}">${esc(o.status_label)}</span>`;
        $('#t-sub').textContent = [o.booking ? 'Villa ' + (o.booking.villa || '') + ' · ' + o.booking.guest : (o.guest_name || ''), o.covers + ' cover(s)', o.waiter ? 'by ' + o.waiter : '', 'opened ' + o.opened_at, o.notes ? '“' + o.notes + '”' : ''].filter(Boolean).join(' · ');
        lines.innerHTML = o.items.length ? '' : '<p class="muted small" style="padding:14px">Tap menu items to add them.</p>';
        o.items.forEach(i => {
            const div = document.createElement('div');
            div.className = 'tline' + (i.status !== 'pending' ? ' fired' : '') + (i.status === 'void' ? ' void' : '');
            const mods = (i.modifiers || []).map(m => m.name + (m.price ? ' +' + m.price : '')).join(', ');
            const editable = o.editable && i.status === 'pending';
            div.innerHTML = `<span class="q">${editable ? `<button type="button" data-q="-1" aria-label="Less">−</button>` : ''}<strong>${i.qty}</strong>${editable ? `<button type="button" data-q="1" aria-label="More">+</button>` : ''}</span>
                <span>${esc(i.name)}${mods ? `<span class="mods"> · ${esc(mods)}</span>` : ''}${i.notes ? `<div class="mods" style="color:var(--crit)">${esc(i.notes)}</div>` : ''}
                <div class="mods">${i.status === 'pending' ? 'not sent' : esc(i.kot || '') + ' · ' + esc(i.kot_status || i.status)}</div></span>
                <span class="num">${money(i.line_total)}${o.editable && i.status !== 'void' && (editable || can.void) ? `<br><button type="button" class="btn btn-sm btn-ghost" data-rm aria-label="Remove">×</button>` : ''}</span>`;
            div.querySelectorAll('[data-q]').forEach(b => b.onclick = () => changeQty(i, Number(b.dataset.q)));
            const rm = div.querySelector('[data-rm]');
            if (rm) rm.onclick = () => removeLine(i);
            lines.appendChild(div);
        });
        $('#t-totals').innerHTML = `<span class="muted">Subtotal</span><span>${money(o.subtotal)}</span>` +
            (o.discount ? `<span class="muted">Discount${o.discount_type === 'percent' ? ' ' + o.discount_value + '%' : ''}</span><span>− ${money(o.discount)}</span>` : '') +
            `<span class="muted">Service ${o.outlet.service_pct}%</span><span>${money(o.service)}</span><span class="muted">Tax ${o.outlet.tax_pct}%</span><span>${money(o.tax)}</span>` +
            `<span class="grand">Total</span><span class="grand">${money(o.total)}</span>` + (o.paid ? `<span class="muted">Paid</span><span>${money(o.paid)}</span><span><strong>Balance</strong></span><span><strong>${money(o.balance)}</strong></span>` : '');
        const acts = $('#t-actions');
        acts.hidden = !o.editable;
        acts.querySelector('[data-act=pay]').disabled = !can.pay;
        acts.querySelector('[data-act=discount]').disabled = !can.discount;
        acts.querySelector('[data-act=void]').disabled = !can.void;
        acts.querySelector('[data-act=fire]').disabled = !o.items.some(i => i.status === 'pending');
        if (o.editable) { showMenu(); if (!menu.length) loadMenu(); }
        else if (['paid', 'charged_to_room'].includes(o.status)) {
            lines.insertAdjacentHTML('afterbegin', `<div class="alert alert-success" style="margin:10px">Check closed ${o.invoice_no ? '· invoice ' + esc(o.invoice_no) : ''}. <a href="${API.replace('/api', '')}/order/${o.id}/receipt" target="_blank">Print receipt</a></div>`);
        }
    }
    async function changeQty(i, d) {
        try { setOrder(await api(`/orders/${order.id}/items/${i.id}`, 'PATCH', { qty: Math.max(0, i.qty + d) })); } catch (e) { VV.toast(e.message, 'error'); }
    }
    async function removeLine(i) {
        let reason = null;
        if (i.status !== 'pending') { reason = await ask('Void “' + i.name + '” — reason'); if (!reason) return; }
        try { setOrder(await VV.api(API + `/orders/${order.id}/items/${i.id}`, 'DELETE', { reason })); } catch (e) { VV.toast(e.message, 'error'); }
    }

    // Prompt dialog (window.prompt is avoided for touch terminals)
    function ask(label, value = '') {
        return new Promise(resolve => {
            $('#m-prompt-title').textContent = label; $('#prompt-label').textContent = label;
            const inp = $('#prompt-input'); inp.value = value;
            const m = $('#m-prompt'); VV.openModal(m); inp.focus();
            const done = v => { VV.closeModal(m); $('#prompt-ok').onclick = null; resolve(v); };
            $('#prompt-ok').onclick = () => done(inp.value.trim());
            m.querySelector('[data-modal-close]').onclick = () => done(null);
            inp.onkeydown = e => { if (e.key === 'Enter') done(inp.value.trim()); };
        });
    }

    // ------------------------------------------------------------------ actions
    $('#t-actions').addEventListener('click', async e => {
        const b = e.target.closest('[data-act]');
        if (!b || !order) return;
        const act = b.dataset.act;
        try {
            if (act === 'fire') {
                const r = await api(`/orders/${order.id}/fire`, 'POST');
                setOrder(r);
                VV.toast('Sent to ' + r.kots.map(k => k.station + ' (' + k.kot_no + ')').join(', '), 'success');
            } else if (act === 'bill') {
                const r = await api(`/orders/${order.id}/bill`, 'POST');
                setOrder(r); window.open(r.print_url, '_blank');
            } else if (act === 'pay') {
                openPay();
            } else if (act === 'discount') {
                $('#disc-type').value = order.discount_type || 'percent'; $('#disc-value').value = order.discount_value || ''; $('#disc-reason').value = '';
                VV.openModal('#m-discount');
            } else if (act === 'transfer' || act === 'merge') {
                const data = await api('/tables?outlet=' + outletId);
                const pick = $('#pick-tables'); pick.innerHTML = '';
                const list = data.tables.filter(t => act === 'transfer' ? !t.order : (t.order && t.order.id !== order.id));
                if (!list.length) pick.innerHTML = '<p class="muted">' + (act === 'transfer' ? 'No free tables.' : 'No other open checks to merge.') + '</p>';
                list.forEach(t => {
                    const btn = document.createElement('button');
                    btn.type = 'button'; btn.className = 'ptable' + (t.order ? ' busy' : '');
                    btn.innerHTML = `<span class="name">${esc(t.name)}</span><span class="meta">${t.order ? money(t.order.total) : t.seats + ' seats'}</span>`;
                    btn.onclick = async () => {
                        VV.closeModal($('#m-tables'));
                        try {
                            setOrder(act === 'transfer' ? await api(`/orders/${order.id}/transfer`, 'POST', { pos_table_id: t.id })
                                : await api(`/orders/${order.id}/merge`, 'POST', { source_order_id: t.order.id }));
                            VV.toast(act === 'transfer' ? 'Moved to table ' + t.name : 'Table ' + t.name + ' merged into this check', 'success');
                        } catch (err) { VV.toast(err.message, 'error'); }
                    };
                    pick.appendChild(btn);
                });
                $('#m-tables-title').textContent = act === 'transfer' ? 'Move check to table' : 'Merge another table into this check';
                VV.openModal('#m-tables');
            } else if (act === 'split') {
                $('#split-lines').innerHTML = order.items.filter(i => i.status !== 'void').map(i =>
                    `<label class="row between chip" style="padding:8px 12px"><span>${esc(i.name)} <span class="muted">(${i.qty})</span></span>
                     <input type="number" min="0" max="${i.qty}" step="1" value="0" data-line="${i.id}" style="width:80px" aria-label="Move quantity"></label>`).join('');
                VV.openModal('#m-split');
            } else if (act === 'notes') {
                const n = await ask('Check notes', order.notes || '');
                if (n !== null) setOrder(await api(`/orders/${order.id}/notes`, 'POST', { notes: n }));
            } else if (act === 'void') {
                const reason = await ask('Void the whole check — reason');
                if (reason) { setOrder(await api(`/orders/${order.id}/void`, 'POST', { reason })); VV.toast('Check voided', 'success'); setTimeout(() => { setOrder(null); showTables(); }, 800); }
            }
        } catch (err) { VV.toast(err.message, 'error'); }
    });

    $('#split-ok').onclick = async () => {
        const lines = {};
        document.querySelectorAll('#split-lines [data-line]').forEach(i => { if (Number(i.value) > 0) lines[i.dataset.line] = Number(i.value); });
        try {
            const r = await api(`/orders/${order.id}/split`, 'POST', { lines });
            VV.closeModal($('#m-split'));
            setOrder(r);
            VV.toast('New check ' + r.new_order_no + ' created on the same table.', 'success');
        } catch (e) { VV.toast(e.message, 'error'); }
    };
    $('#disc-ok').onclick = async () => {
        try {
            setOrder(await api(`/orders/${order.id}/discount`, 'POST', { type: $('#disc-type').value, value: Number($('#disc-value').value || 0), reason: $('#disc-reason').value }));
            VV.closeModal($('#m-discount'));
        } catch (e) { VV.toast(e.message, 'error'); }
    };
    $('#disc-clear').onclick = async () => {
        try { setOrder(await api(`/orders/${order.id}/discount`, 'POST', { type: 'percent', value: 0 })); VV.closeModal($('#m-discount')); } catch (e) { VV.toast(e.message, 'error'); }
    };

    // ------------------------------------------------------------------ payment
    function openPay() {
        tenders = [];
        addTender(order.booking ? 'room_charge' : 'cash', order.balance);
        VV.openModal('#m-pay');
        refreshPay();
    }
    async function addTender(method, amount) {
        if (method === 'room_charge' && !inHouse) inHouse = (await api('/in-house')).guests;
        const remaining = Math.max(0, order.balance - tenders.reduce((s, t) => s + Number(t.amount || 0), 0));
        tenders.push({ method, amount: Number((amount ?? remaining).toFixed(2)), tendered: null, reference: '', booking_id: order.booking ? order.booking.id : (inHouse && inHouse[0] ? inHouse[0].booking_id : null) });
        renderTenders();
    }
    function renderTenders() {
        const box = $('#tenders');
        const label = { cash: 'Cash', card: 'Card', bank_transfer: 'Bank transfer', digital: 'Digital wallet / QR', online: 'Online payment', room_charge: 'Charge to villa' };
        box.innerHTML = '';
        tenders.forEach((t, idx) => {
            const row = document.createElement('div');
            row.className = 'card'; row.style.padding = '10px';
            row.innerHTML = `<div class="row between"><strong>${label[t.method]}</strong><button type="button" class="btn btn-sm btn-ghost" data-del aria-label="Remove">×</button></div>
                <div class="form-grid" style="margin-top:6px">
                  <div class="field f-6"><label>Amount</label><input type="number" step="0.01" min="0" data-f="amount" value="${t.amount}"></div>
                  ${t.method === 'cash' ? `<div class="field f-6"><label>Tendered</label><input type="number" step="0.01" min="0" data-f="tendered" value="${t.tendered ?? ''}"></div>` : ''}
                  ${['card', 'online', 'bank_transfer', 'digital'].includes(t.method) ? `<div class="field f-6"><label>Slip / txn ref</label><input type="text" maxlength="120" data-f="reference" value="${esc(t.reference)}"></div>` : ''}
                  ${t.method === 'room_charge' ? `<div class="field f-6"><label>Villa / guest</label><select data-f="booking_id">${(inHouse || []).map(g => `<option value="${g.booking_id}" ${g.booking_id == t.booking_id ? 'selected' : ''} ${g.blocked ? 'disabled' : ''}>${esc(g.villas)} · ${esc(g.guest)}</option>`).join('')}</select></div>` : ''}
                </div>`;
            row.querySelector('[data-del]').onclick = () => { tenders.splice(idx, 1); renderTenders(); };
            row.querySelectorAll('[data-f]').forEach(inp => {
                inp.addEventListener('input', () => { t[inp.dataset.f] = inp.type === 'number' ? (inp.value === '' ? null : Number(inp.value)) : inp.value; refreshPay(); });
                inp.addEventListener('focus', () => focusInput = inp);
            });
            box.appendChild(row);
        });
        const last = box.querySelector('[data-f=tendered]') || box.querySelector('[data-f=amount]');
        if (last) { focusInput = last; }
        refreshPay();
    }
    function refreshPay() {
        const entered = tenders.reduce((s, t) => s + Number(t.amount || 0), 0);
        const change = tenders.filter(t => t.method === 'cash' && t.tendered).reduce((s, t) => s + Math.max(0, t.tendered - t.amount), 0);
        $('#pay-due').textContent = money(order.balance);
        $('#pay-entered').textContent = money(entered);
        $('#pay-change').textContent = money(change);
        $('#pay-ok').disabled = entered <= 0 || entered - order.balance > 0.009;
    }
    document.querySelectorAll('[data-tender]').forEach(b => b.onclick = () => addTender(b.dataset.tender));
    document.querySelectorAll('#keypad [data-key]').forEach(b => b.onclick = () => {
        if (!focusInput) return;
        const k = b.dataset.key;
        focusInput.value = k === '⌫' ? focusInput.value.slice(0, -1) : (focusInput.value === '0' ? '' : focusInput.value) + k;
        focusInput.dispatchEvent(new Event('input'));
    });
    document.querySelectorAll('[data-quick]').forEach(b => b.onclick = () => {
        const cash = tenders.find(t => t.method === 'cash');
        if (!cash) return;
        cash.tendered = b.dataset.quick === 'exact' ? cash.amount : Number(b.dataset.quick);
        renderTenders();
    });
    $('#pay-ok').onclick = async () => {
        try {
            $('#pay-ok').disabled = true;
            const r = await api(`/orders/${order.id}/pay`, 'POST', { tenders: tenders.filter(t => t.amount > 0) });
            VV.closeModal($('#m-pay'));
            setOrder(r);
            if (['paid', 'charged_to_room'].includes(r.status)) {
                VV.toast('Payment complete' + (r.change ? ' — change ' + money(r.change) : '') + '.', 'success', 8000);
                window.open(r.receipt_url, '_blank');
            } else {
                VV.toast('Partial payment recorded. Balance ' + money(r.balance), 'info');
            }
        } catch (e) { VV.toast(e.message, 'error'); $('#pay-ok').disabled = false; }
    };

    // ------------------------------------------------------------------ boot
    loadTables();
    loadMenu();
    if (root.dataset.order) loadOrder(root.dataset.order);
    // Deep links from the POS dashboard quick actions: ?start=takeaway | room | menu
    const start = new URLSearchParams(location.search).get('start');
    if (!root.dataset.order && start === 'takeaway') $('#btn-takeaway').click();
    if (!root.dataset.order && start === 'room') $('#btn-room').click();
    if (!root.dataset.order && start === 'menu') VV.toast('Choose a table, Takeaway or Room service to start the order.', 'info', 5000);
    setInterval(() => { if (!$('#tables-view').hidden) loadTables(); }, 20000);
})();
