# Follow-up: restaurant cash control + Example B (T6). The cashier's only open shift was yesterday's In-villa dining
# shift, and the Shifts page offers "Open a shift" only when no shift is open — so it is closed through Day-end first.
. "$PSScriptRoot\cdp.ps1"
$out = 'C:\xampp\htdocs\vaasal_villa_hospitality_management_system28\docs\qa-training\screenshots'
Start-Cdp $out
Viewport 1440 900
$state = @{}

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

# ---------------------------------------------------------------- Day-end closing of the old shift
Step '81_day_end_count' {
    LoginAs 'cashier'; Nav '/pos/shifts'
    ClickNav "(() => { const a = document.querySelector('a[href*=`"day-end`"][href*=`"shift=`"]'); if (!a) throw new Error('No Day-end link'); a.click(); })()"
    Js "(() => { const f = document.getElementById('dayend'); const c = document.getElementById('counted'); c.value = f.dataset.expected; c.dispatchEvent(new Event('input', { bubbles: true })); f.querySelector('[name=confirm]').checked = true; const n = f.querySelector('[name=notes]'); if (n) n.value = 'Closed next morning before opening the restaurant shift.'; return true })()" | Out-Null
    Start-Sleep -Milliseconds 600
    Shot '81_day_end_count' $true 'Day-end closing: counted cash equals expected, confirmation ticked'
    Submit '#dayend'
    Shot '82_shift_closed' $true 'Shift closed and locked; waiting for manager review'
}
Step '32_open_shift' {
    Nav '/pos/shifts'
    Js "(() => { const f = document.querySelector('form[action*=`"/shifts/open`"]'); if (!f) throw new Error('No open-shift form'); const s = f.querySelector('[name=outlet_id]'); s.value = [...s.options].find(o => o.textContent.includes('Vaasal Kitchen')).value; f.querySelector('[name=opening_float]').value = '10000'; return true })()" | Out-Null
    Shot '32_open_shift' $true 'Open a shift at Vaasal Kitchen with a LKR 10,000 float'
    Submit "form[action*='/shifts/open']"
    Shot '33_shift_open' $true 'Shift open at Vaasal Kitchen'
}
# Setup: the demo data still has two earlier checks on T6; the cashier settles them by card so T6 is free.
Step 'setup_free_t6' {
    Nav '/pos?outlet=1' 1200; Helper
    $state.t6Cleared = Js "(async () => { const ids = []; for (;;) { const tt = await __vv.call('GET', '/pos/api/tables?outlet=1'); const x = tt.tables.find(y => y.name === 'T6'); if (!x.order) break; const o = await __vv.call('GET', '/pos/api/orders/' + x.order.id); if (o.balance > 0) await __vv.call('POST', '/pos/api/orders/' + o.id + '/pay', { tenders: [{ method: 'card', amount: o.balance, reference: 'EARLIER-GUESTS' }] }); ids.push(o.order_no + ' LKR ' + o.balance); if (ids.length > 5) break; } return ids.join(', ') })()"
    Write-Host "T6 cleared: $($state.t6Cleared)"
}

# ---------------------------------------------------------------- Example B — T6, 4 guests
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
    Shot '31_kitchen_kot_t6' $false 'Kitchen and bar tickets for table T6'
    foreach ($i in 1..3) { Js "(() => { const t = [...document.querySelectorAll('article.kot')].filter(a => a.querySelector('.kot-head').textContent.includes('T6')); t.forEach(a => { const b = a.querySelector('[data-kot]'); if (b) b.click(); }); return t.length })()" | Out-Null; Start-Sleep -Milliseconds 2200 }
}
Step '34_pos_bill' {
    LoginAs 'cashier'; Nav "/pos/order/$($state.t6)" 1500
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

# ---------------------------------------------------------------- Restaurant back office (after the sale)
Step 'pos_backoffice' {
    LoginAs 'posmanager'
    foreach ($p in @(@('38_pos_dashboard', '/pos/dashboard'), @('39_table_reservations', '/pos/reservations'), @('40_cashier_shifts', '/pos/shifts'), @('41_day_end', '/pos/day-end'),
                     @('42_pos_sales', '/pos/sales'), @('43_food_menu', '/pos/menu'), @('44_orders_history', '/pos/orders'), @('45_pos_inventory', '/pos/inventory'), @('46_pos_outlets', '/pos/outlets'))) {
        Step $p[0] { Nav $p[1] 1200; Shot $p[0] }
    }
}

# ---------------------------------------------------------------- Session ended while working in the POS
Step '75_session_ended' {
    LoginAs 'cashier'; Nav '/pos?outlet=1' 1500
    Send 'Network.clearBrowserCookies' @{} | Out-Null
    Js "(() => { const base = document.querySelector('meta[name=login-url]').content.replace(/\/login$/, ''); VV.api(base + '/pos/api/tables?outlet=1').catch(() => {}); return true })()" | Out-Null
    Start-Sleep -Milliseconds 900
    Shot '75_session_ended' $false 'Session ended: clear message, then back to sign-in'
    WaitFor "location.pathname.endsWith('/login')" 10000 | Out-Null
    Start-Sleep -Milliseconds 600
    Shot '76_session_return' $true 'Sign-in page after the session ended (returns to the POS after sign-in)'
    $state.returnUrl = Js 'location.href'
}

Stop-Cdp
$log = Join-Path $out '..\data\screenshot_log.csv'
$prev = @(Import-Csv $log | Where-Object { $names = $script:Log.file; $names -notcontains $_.file })
@($prev) + @($script:Log) | Sort-Object file | Export-Csv -NoTypeInformation -Encoding UTF8 $log
$old = @(); if (Test-Path (Join-Path $out '..\data\browser_console.csv')) { $old = @(Import-Csv (Join-Path $out '..\data\browser_console.csv')) }
@($old) + @($script:Console) | Export-Csv -NoTypeInformation -Encoding UTF8 (Join-Path $out '..\data\browser_console.csv')
$state | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $out '..\data\story_state_pos.json')
$script:Log | Format-Table -AutoSize | Out-String -Width 250
"Console errors: " + $script:Console.Count
$script:Console | Format-Table -AutoSize | Out-String -Width 250
