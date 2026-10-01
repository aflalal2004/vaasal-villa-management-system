# Vaasal Villa — end-to-end workflow screenshots on the local XAMPP system (real UI, real database).
# Required files 01–13 use the names from the brief; supporting screenshots are numbered 14 onward.
. "$PSScriptRoot\cdp.ps1"
$out = 'C:\xampp\htdocs\vaasal_villa_hospitality_management_system28\docs\qa-training\screenshots'
Get-ChildItem $out -Filter '*.png' -ErrorAction SilentlyContinue | Remove-Item
Start-Cdp $out
Viewport 1440 900

$today = (Get-Date).ToString('yyyy-MM-dd')
$depart = '2026-10-03'
$oliver = 21; $g1 = 1
$state = @{}

# Page helper for POS JSON calls (session + CSRF of the signed-in user).
$helper = @'
window.__vv = {
  base: document.querySelector('meta[name=login-url]').content.replace(/\/login$/, ''),
  tok: () => document.querySelector('meta[name=csrf-token]').content,
  call: async (m, p, b) => {
    const r = await fetch(__vv.base + p, { method: m, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': __vv.tok() }, body: b ? JSON.stringify(b) : undefined });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(r.status + ' ' + (d.message || p));
    return d;
  },
  add: (oid, itemId, qty) => __vv.call('POST', '/pos/api/orders/' + oid + '/items', { menu_item_id: itemId, qty: qty, modifiers: [] })
};
true
'@
function Helper { Js $helper | Out-Null }
function Click([string]$css) { $s = ConvertTo-Json $css; Js "(() => { const e = document.querySelector($s); if (!e) throw new Error('Missing ' + $s); e.click(); return true })()" | Out-Null }

# ================================================================ 1. Sign-in and admin dashboard
Step '14_login_page' { Nav '/login'; Shot '14_login_page' $true 'Sign-in page with the local-only demo role cards (no password shown)' }
Step '01_admin_dashboard' { LoginAs 'admin'; Nav '/admin' 1500; Shot '01_admin_dashboard' $true 'Administrator dashboard' }

# ================================================================ 2. Oliver Jensen (G1) checkout — blocked by an open room-service check
Step 'setup_open_check' {
    LoginAs 'cashier'; Nav '/pos?outlet=3'; Helper
    $state.oliverOrder = Js "(async () => { const o = await __vv.call('POST', '/pos/api/orders', { outlet_id: 3, type: 'room_service', booking_id: $oliver }); await __vv.add(o.id, 13, 1); await __vv.add(o.id, 19, 1); await __vv.call('POST', '/pos/api/orders/' + o.id + '/fire'); return o.id })()"
}
Step '15_front_desk' { LoginAs 'reception'; Nav '/admin/front-desk' 1200; Shot '15_front_desk' $true 'Front desk: arrivals, in-house guests, departures' }
Step '16_checkout_blocked' { Nav "/admin/front-desk/$oliver/check-out"; Shot '16_checkout_blocked' $true 'Open restaurant check blocks checkout; Complete checkout disabled' }
Step '17_settlement_requested' { Submit "form[action*='restaurant-settlement']"; Shot '17_settlement_requested' $true 'Restaurant settlement requested from the front desk' }
Step '18_cashier_notification' { LoginAs 'cashier'; Nav '/admin/notifications'; Shot '18_cashier_notification' $true 'Cashier notification: settle the open check' }
Step '19_pos_charge_to_villa' {
    Nav "/pos/order/$($state.oliverOrder)" 1500
    WaitFor "document.querySelector('#t-actions') && !document.querySelector('#t-actions').hidden" | Out-Null
    Click '[data-act=pay]'; Start-Sleep -Milliseconds 700
    Click '[data-tender=room_charge]'; Start-Sleep -Milliseconds 1200
    Shot '19_pos_charge_to_villa' $false 'Payment dialog: Charge to villa G1 (Oliver Jensen)'
    Click '#pay-ok'; Start-Sleep -Milliseconds 2200
}
Step '10_checkout' {
    LoginAs 'reception'; Nav "/admin/front-desk/$oliver/check-out"
    Js "document.querySelectorAll('select[name^=payments]').forEach(s => s.value = 'card'); true" | Out-Null
    Shot '10_checkout' $true 'Checkout: room and restaurant on one bill, balance settled by card, Complete checkout enabled'
}
Step '20_checkout_complete' { Submit '#checkout-form'; Shot '20_checkout_complete' $true 'Checked out: villa G1 becomes Dirty and a departure clean is created' }
Step '21_final_invoice' {
    $href = Js "(() => { const a = [...document.querySelectorAll('a[href*=`"/invoices/`"]')].pop(); return a ? a.getAttribute('href') : '' })()"
    if ($href) { Nav $href; Shot '21_final_invoice' $true 'Final invoice' } else { throw 'Invoice link not found' }
}

# ================================================================ 3. Example E — housekeeping cleans G1
Step '22_my_cleaning_tasks' { LoginAs 'housekeeping'; Nav '/admin/housekeeping/my-tasks'; Shot '22_my_cleaning_tasks' $true 'Room attendant: G1 is ready for cleaning' }
Step 'hk_accept_start' {
    ClickNav "(() => { const c = [...document.querySelectorAll('.hk-card')].find(x => x.querySelector('.hk-code') && x.querySelector('.hk-code').textContent.trim() === 'G1' && x.querySelector('form[action*=`"/accept`"]')); if (!c) throw new Error('No G1 task to accept'); c.querySelector('form').requestSubmit(); })()"
    $state.task = Js "(() => { const a = [...document.querySelectorAll('a.hk-code')].filter(x => x.textContent.trim() === 'G1').map(x => x.getAttribute('href')); return a.length ? a[a.length - 1] : '' })()"
    if (-not $state.task) { throw 'G1 task link not found after accept' }
    Nav $state.task
    Submit "form[action*='/start']"
}
Step '23_cleaning_checklist' {
    Nav $state.task
    Js "(async () => { const b = [...document.querySelectorAll('[data-toggle-url]')]; for (const c of b.slice(0, 6)) { c.click(); await new Promise(r => setTimeout(r, 400)); } return b.length })()" | Out-Null
    Start-Sleep -Milliseconds 800; Nav $state.task
    Shot '23_cleaning_checklist' $true 'Cleaning in progress: checklist partly ticked'
    Js "(async () => { const b = [...document.querySelectorAll('[data-toggle-url]')].filter(c => !c.checked); for (const c of b) { c.click(); await new Promise(r => setTimeout(r, 350)); } return b.length })()" | Out-Null
    Start-Sleep -Milliseconds 800; Nav $state.task
    Submit "form[action*='/submit']"
}
Step '11_housekeeping' { LoginAs 'hksupervisor'; Nav '/admin/housekeeping'; Shot '11_housekeeping' $true 'Housekeeping board: G1 waiting for inspection' }
Step '24_inspection' { Nav $state.task; Shot '24_inspection' $true 'Supervisor inspection: Approve or Reject' }
Step '25_villa_ready' { Submit "form[action*='/approve']"; Nav '/admin/housekeeping'; Shot '25_villa_ready' $true 'G1 approved: Ready for the next guest' }

# ================================================================ 4. Example A — walk-in Nuwan Perera, G1
Step '02_walkin_booking' {
    LoginAs 'manager'; Nav "/admin/bookings/create?source=walk_in&arrival=$today&departure=$depart" 1500
    WaitFor "document.querySelector('[name=`"villas[$g1][selected]`"]')" 15000 | Out-Null
    Js @"
(() => {
  const set = (n, v) => { const e = document.querySelector('[name="' + n + '"]'); if (!e) throw new Error('Missing ' + n); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); };
  set('guest[first_name]', 'Nuwan'); set('guest[last_name]', 'Perera'); set('guest[country]', 'Sri Lanka');
  set('guest[email]', 'nuwan.perera.walkin@example.com'); set('guest[phone]', '077 123 4567'); set('arrival_time', '14:30');
  const cb = document.querySelector('[name="villas[$g1][selected]"]'); cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true }));
  set('villas[$g1][adults]', '2'); set('villas[$g1][children]', '0');
  document.querySelectorAll('details').forEach(d => d.open = true);
  set('rate_override', '30500'); set('override_reason', 'Walk-in rate agreed by manager');
  set('deposit_amount', '20000'); set('deposit_method', 'card');
  return true;
})()
"@ | Out-Null
    Start-Sleep -Milliseconds 1800
    Shot '02_walkin_booking' $true "Walk-in: Nuwan Perera, G1, $today to $depart, 2 adults, LKR 30,500/night, deposit LKR 20,000"
}
Step '03_room_selection' {
    Js "document.getElementById('villa-table').scrollIntoView({ block: 'start' }); window.scrollBy(0, -90); true" | Out-Null; Start-Sleep -Milliseconds 500
    Shot '03_room_selection' $false 'Villas free for every night of the stay; G1 ticked'
}
Step '04_checkin' {
    ClickNav "document.getElementById('submit-btn').click()" 1200
    $state.nuwan = Js "(() => { const m = location.pathname.match(/front-desk\/(\d+)\/check-in/); return m ? m[1] : '' })()"
    if (-not $state.nuwan) { throw ('Did not reach the check-in page: ' + (Js 'location.pathname') + ' ' + (Js "(document.querySelector('.alert, .flash, .errors, .invalid-feedback') || {}).textContent || ''")) }
    Js "(() => { const chip = document.querySelector('.form-grid .chip'); if (chip) chip.click(); const i = document.querySelector('[name=id_number]'); if (i) i.value = 'N7842211'; const n = document.querySelector('[name=nationality]'); if (n) n.value = 'Sri Lankan'; return true })()" | Out-Null
    $state.card = Js "(document.querySelector('[name^=card_uids]') || {}).value || ''"
    Shot '04_checkin' $true 'Check-in: ID document and key card for villa G1'
}
Step '26_checked_in' { Submit "form[action*='/check-in']"; Shot '26_checked_in' $true 'Checked in: villa G1 occupied, key card active' }

# ================================================================ 5. Example C — room service for Nuwan (G1), charged to the villa
Step '27_room_service_select' {
    LoginAs 'cashier'; Nav '/pos?outlet=3' 1500
    Click '#btn-room'
    WaitFor "!document.getElementById('m-room').hidden && document.querySelectorAll('#room-list button').length" | Out-Null
    Start-Sleep -Milliseconds 400
    Shot '27_room_service_select' $false 'Room service: choose the in-house villa and guest'
}
Step '08_room_service' {
    Js "(() => { const b = [...document.querySelectorAll('#room-list button')].find(x => x.textContent.includes('G1') && x.textContent.includes('Nuwan')); if (!b) throw new Error('G1 / Nuwan not in the in-house list'); b.click(); return true })()" | Out-Null
    WaitFor "new URLSearchParams(location.search).get('order')" | Out-Null
    $state.rs = Js "new URLSearchParams(location.search).get('order')"
    Helper
    Js "(async () => { await __vv.add($($state.rs), 12, 1); await __vv.add($($state.rs), 6, 1); return true })()" | Out-Null
    Nav "/pos/order/$($state.rs)" 1500
    Shot '28_room_service_check' $false 'Room-service check for villa G1: Seafood linguine + Crab soup (menu subtotal LKR 8,500)'
    Click '[data-act=fire]'; Start-Sleep -Milliseconds 1500
}
Step '07_kitchen_kot' { LoginAs 'kitchen'; Nav '/pos/kds?station=all' 2500; Shot '07_kitchen_kot' $false 'Kitchen display: new KOT for villa G1' }
Step 'kitchen_progress_g1' {
    foreach ($i in 1..3) { Js "(() => { const t = [...document.querySelectorAll('article.kot')].filter(a => a.querySelector('.kot-head').textContent.includes('G1')); t.forEach(a => { const b = a.querySelector('[data-kot]'); if (b) b.click(); }); return t.length })()" | Out-Null; Start-Sleep -Milliseconds 2200 }
}
Step '08_room_service_pay' {
    LoginAs 'cashier'; Nav "/pos/order/$($state.rs)" 1500
    Click '[data-act=pay]'; Start-Sleep -Milliseconds 700
    Click '[data-tender=room_charge]'; Start-Sleep -Milliseconds 1200
    Shot '08_room_service' $false 'Room service charged to villa G1 (Nuwan Perera)'
    Click '#pay-ok'; Start-Sleep -Milliseconds 2200
}
Step '29_laundry_charge' {
    LoginAs 'reception'; Nav "/admin/bookings/$($state.nuwan)" 1200
    Click "[data-modal-open='#charge-modal']"; Start-Sleep -Milliseconds 600
    Js "(() => { const s = document.getElementById('charge_item_id'); s.value = [...s.options].find(o => o.dataset.name && o.dataset.name.toLowerCase().includes('pressing')).value; s.dispatchEvent(new Event('change', { bubbles: true })); document.getElementById('charge_qty').value = '5'; return true })()" | Out-Null
    Start-Sleep -Milliseconds 500
    Shot '29_laundry_charge' $false 'Post a laundry charge: pressing 5 x LKR 400 = LKR 2,000'
    ClickNav "(() => { const f = document.querySelector('#charge-modal form'); f.requestSubmit(); })()"
}
Step '09_guest_folio' { Nav "/admin/bookings/$($state.nuwan)" 1200; Shot '09_guest_folio' $true 'Guest folio: deposit, room-service charge and laundry' }

# ================================================================ 6. Example B — dine-in table T6, 4 guests
# Setup: the demo data still has two earlier checks on T6; the cashier settles them by card so T6 is free.
Step 'setup_free_t6' {
    LoginAs 'cashier'; Nav '/pos?outlet=1' 1200; Helper
    $state.t6Cleared = Js "(async () => { const t = await __vv.call('GET', '/pos/api/tables?outlet=1'); const tb = t.tables.find(x => x.name === 'T6'); const ids = []; for (;;) { const tt = await __vv.call('GET', '/pos/api/tables?outlet=1'); const x = tt.tables.find(y => y.name === 'T6'); if (!x.order) break; const o = await __vv.call('GET', '/pos/api/orders/' + x.order.id); if (o.balance > 0) await __vv.call('POST', '/pos/api/orders/' + o.id + '/pay', { tenders: [{ method: 'card', amount: o.balance, reference: 'EARLIER-GUESTS' }] }); ids.push(o.order_no); if (ids.length > 5) break; } return ids.join(', ') })()"
}
Step '30_pos_floor_plan' { LoginAs 'waiter'; Nav '/pos?outlet=1' 2000; Shot '30_pos_floor_plan' $false 'Waiter floor plan: free, occupied and reserved tables' }
Step '05_pos_table' {
    Helper
    $state.t6 = Js "(async () => { const t = await __vv.call('GET', '/pos/api/tables?outlet=1'); const tb = t.tables.find(x => x.name === 'T6'); if (!tb) throw new Error('No T6'); if (tb.order) throw new Error('T6 is busy'); const o = await __vv.call('POST', '/pos/api/orders', { outlet_id: 1, type: 'dine_in', pos_table_id: tb.id, covers: 4 }); for (const [id, q] of [[10, 1], [12, 1], [6, 1], [5, 1], [14, 1], [19, 4]]) await __vv.add(o.id, id, q); return o.id })()"
    Nav "/pos/order/$($state.t6)" 1500
    Shot '05_pos_table' $false 'Table T6, 4 guests: order before sending to the kitchen'
    Click '[data-act=fire]'; Start-Sleep -Milliseconds 1500
}
Step '31_kitchen_kot_t6' {
    LoginAs 'kitchen'; Nav '/pos/kds?station=all' 2500
    Shot '31_kitchen_kot_t6' $false 'Kitchen display with the T6 tickets'
    foreach ($i in 1..3) { Js "(() => { const t = [...document.querySelectorAll('article.kot')].filter(a => a.querySelector('.kot-head').textContent.includes('T6')); t.forEach(a => { const b = a.querySelector('[data-kot]'); if (b) b.click(); }); return t.length })()" | Out-Null; Start-Sleep -Milliseconds 2200 }
}
Step '32_open_shift' {
    LoginAs 'cashier'; Nav '/pos/shifts'
    Js "(() => { const f = document.querySelector('form[action*=`"/shifts/open`"]'); if (!f) throw new Error('No open-shift form'); f.querySelector('[name=outlet_id]').value = '1'; f.querySelector('[name=opening_float]').value = '10000'; return true })()" | Out-Null
    Shot '32_open_shift' $true 'Cashier opens a shift at Vaasal Kitchen with a LKR 10,000 float'
    Submit "form[action*='/shifts/open']"
    Shot '33_shift_open' $true 'Shift open'
}
Step '34_pos_bill' {
    Nav "/pos/order/$($state.t6)" 1500
    Helper; Js "(async () => { await __vv.call('POST', '/pos/api/orders/$($state.t6)/bill'); return true })()" | Out-Null
    Nav "/pos/order/$($state.t6)/bill"; Shot '34_pos_bill' $false 'Guest bill (80 mm)'
}
Step '06_pos_payment' {
    Nav "/pos/order/$($state.t6)" 1500
    Click '[data-act=pay]'; Start-Sleep -Milliseconds 700
    Click '[data-tender=cash]'; Start-Sleep -Milliseconds 700
    Js "(() => { const i = document.querySelector('#tenders [data-f=tendered]'); i.value = '30000'; i.dispatchEvent(new Event('input', { bubbles: true })); return true })()" | Out-Null; Start-Sleep -Milliseconds 700
    Shot '06_pos_payment' $false 'Cash payment: LKR 30,000 tendered, change calculated'
    Click '#pay-ok'; Start-Sleep -Milliseconds 2500
}
Step '35_pos_receipt' { Nav "/pos/order/$($state.t6)/receipt"; Shot '35_pos_receipt' $false 'Receipt' }
Step '36_table_released' { Nav '/pos?outlet=1' 2000; Shot '36_table_released' $false 'After payment T6 is free again' }
Step '37_cashier_today' { Nav '/pos/today'; Shot '37_cashier_today' }

# ================================================================ 7. Restaurant back office
Step 'pos_backoffice' {
    LoginAs 'posmanager'
    foreach ($p in @(@('38_pos_dashboard', '/pos/dashboard'), @('39_table_reservations', '/pos/reservations'), @('40_cashier_shifts', '/pos/shifts'), @('41_day_end', '/pos/day-end'),
                     @('42_pos_sales', '/pos/sales'), @('43_food_menu', '/pos/menu'), @('44_orders_history', '/pos/orders'), @('45_pos_inventory', '/pos/inventory'), @('46_pos_outlets', '/pos/outlets'))) {
        Step $p[0] { Nav $p[1] 1200; Shot $p[0] }
    }
}

# ================================================================ 8. Key cards (simulation) and night audit
Step '12_key_cards' { LoginAs 'admin'; Nav '/admin/key-cards'; Shot '12_key_cards' $true 'Key cards (LOCK_DRIVER=simulator)' }
Step '47_rfid_simulator' {
    Nav '/admin/access/simulator'
    $uid = ConvertTo-Json ([string]$state.card)
    ClickNav "(() => { const f = document.getElementById('sim-form'); f.uid.value = $uid; f.villa_id.value = '$g1'; f.requestSubmit(); })()"
    Shot '47_rfid_simulator' $true 'SIMULATION: guest card presented at the villa G1 door'
}
Step '48_access_logs' { Nav '/admin/key-cards/access-logs'; Shot '48_access_logs' }
Step '49_night_audit_confirm' {
    LoginAs 'manager'; Nav '/admin/front-desk'
    Click 'form[action*=night-audit] button'; Start-Sleep -Milliseconds 800
    Shot '49_night_audit_confirm' $false 'Run night audit now? confirmation'
}
Step '13_night_audit' { ClickNav "document.getElementById('vv-confirm-ok').click()"; Shot '13_night_audit' $true 'Night audit result' }
Step '50_folio_after_audit' { Nav "/admin/bookings/$($state.nuwan)"; Shot '50_folio_after_audit' $true 'Folio after night audit: the room night is posted' }

# ================================================================ 9. Hotel management pages
Step 'hotel_pages' {
    foreach ($p in @(@('51_bookings', '/admin/bookings'), @('52_villa_calendar', '/admin/calendar'), @('53_villas', '/admin/villas'), @('54_guests', '/admin/guests'),
                     @('55_website_requests', '/admin/enquiries'), @('56_tour_operators', '/admin/operators'), @('57_maintenance', '/admin/maintenance'),
                     @('58_invoices', '/admin/invoices'), @('59_payments', '/admin/payments'), @('60_reports', '/admin/reports'), @('61_report_occupancy', '/admin/reports/occupancy'))) {
        Step $p[0] { Nav $p[1] 1200; Shot $p[0] }
    }
    LoginAs 'admin'
    foreach ($p in @(@('62_users', '/admin/users'), @('63_roles', '/admin/roles'), @('64_settings', '/admin/settings'), @('65_social_media', '/admin/website/social'),
                     @('66_website_content', '/admin/website'), @('67_audit_log', '/admin/audit'), @('68_staff_attendance', '/admin/staff/attendance'), @('69_staff_roster', '/admin/staff/roster'))) {
        Step $p[0] { Nav $p[1] 1200; Shot $p[0] }
    }
}

# ================================================================ 10. Responsive, errors, session end, website
Step '70_mobile_my_tasks' { LoginAs 'housekeeping'; Viewport 390 844 $true; Nav '/admin/housekeeping/my-tasks'; Shot '70_mobile_my_tasks'; Viewport 1440 900 }
Step '71_mobile_pos_dashboard' { LoginAs 'posmanager'; Viewport 390 844 $true; Nav '/pos/dashboard'; Shot '71_mobile_pos_dashboard'; Viewport 1440 900 }
Step '72_tablet_frontdesk' { LoginAs 'reception'; Viewport 768 1024 $true; Nav '/admin/front-desk'; Shot '72_tablet_frontdesk'; Viewport 1440 900 }
Step '73_forbidden_403' { Nav '/pos'; Shot '73_forbidden_403' $true 'Reception opening the POS: 403 Forbidden' }
Step '74_not_found_404' { Nav '/admin/bookings/99999999'; Shot '74_not_found_404' }
Step '75_session_ended' {
    LoginAs 'cashier'; Nav '/pos?outlet=1' 1500
    Send 'Network.clearBrowserCookies' @{} | Out-Null
    Js "VV.api('/pos/api/tables?outlet=1').catch(() => {}); true" | Out-Null
    Start-Sleep -Milliseconds 700
    Shot '75_session_ended' $false 'Session ended: clear message, then back to sign-in'
    WaitFor "location.pathname.endsWith('/login')" 8000 | Out-Null
    Start-Sleep -Milliseconds 500
    Shot '76_session_return' $true 'Sign-in page after the session ended (returns to the POS after sign-in)'
}
Step '77_invalid_login' {
    Send 'Network.clearBrowserCookies' @{} | Out-Null
    Nav '/login'
    ClickNav "(() => { document.getElementById('login').value = 'cashier'; document.getElementById('password').value = 'not-the-password'; document.getElementById('login-form').requestSubmit(); })()"
    Shot '77_invalid_login' $true 'Wrong password: generic error message'
}
Step '78_website_home' { Nav '/' 2000; Shot '78_website_home' }
Step '79_website_booking' { Nav '/book?arrival=2026-10-20&departure=2026-10-23&adults=2' 1800; Shot '79_website_booking' }
Step '80_website_mobile' { Viewport 390 844 $true; Nav '/' 2000; Shot '80_website_mobile'; Viewport 1440 900 }

Stop-Cdp
$script:Log | Export-Csv -NoTypeInformation -Encoding UTF8 (Join-Path $out '..\data\screenshot_log.csv')
$script:Console | Export-Csv -NoTypeInformation -Encoding UTF8 (Join-Path $out '..\data\browser_console.csv')
$state | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $out '..\data\story_state.json')
$script:Log | Format-Table -AutoSize | Out-String -Width 250
"Console errors: " + $script:Console.Count
$script:Console | Format-Table -AutoSize | Out-String -Width 250
