# Minimal Chrome DevTools Protocol driver for full-page screenshots (Windows PowerShell 5.1).
# Dot-source this file, then call Start-Cdp, Nav, Js, WaitFor, Shot, LoginAs, Stop-Cdp.
$ErrorActionPreference = 'Stop'
$script:cdpId = 0
$script:Base = 'http://localhost/vaasal_villa_hospitality_management_system28/public'
$script:Log = @()

function Start-Cdp([string]$OutDir, [int]$Port = 9333) {
    $script:OutDir = $OutDir
    New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
    $script:Profile = Join-Path $env:TEMP ('vvqa-' + [guid]::NewGuid())
    $chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
    $script:Proc = Start-Process $chrome -PassThru -ArgumentList @('--headless=new', "--remote-debugging-port=$Port", "--user-data-dir=$($script:Profile)", '--allow-file-access-from-files',
        '--window-size=1440,900', '--hide-scrollbars', '--force-device-scale-factor=1', '--lang=en-GB', '--no-first-run', '--disable-extensions', 'about:blank')
    for ($i = 0; $i -lt 30; $i++) { try { $tabs = Invoke-RestMethod "http://127.0.0.1:$Port/json/list"; if ($tabs) { break } } catch { Start-Sleep -Milliseconds 500 } }
    $page = $tabs | Where-Object { $_.type -eq 'page' } | Select-Object -First 1
    $script:Ws = New-Object System.Net.WebSockets.ClientWebSocket
    $script:Ws.ConnectAsync([Uri]$page.webSocketDebuggerUrl, [Threading.CancellationToken]::None).Wait()
    Send 'Page.enable' @{} | Out-Null
    Send 'Runtime.enable' @{} | Out-Null
    Send 'Log.enable' @{} | Out-Null
    $script:Console = @()
}

function Stop-Cdp {
    try { $script:Ws.Dispose() } catch {}
    try { Stop-Process -Id $script:Proc.Id -Force } catch {}
    Get-Process chrome -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like "*$($script:Profile)*" } | Stop-Process -Force -ErrorAction SilentlyContinue
}

# Returns the raw JSON text of the response with our id (events are skipped).
function Send([string]$method, $params) {
    $script:cdpId++
    $myId = $script:cdpId
    $json = @{ id = $myId; method = $method; params = $params } | ConvertTo-Json -Depth 30 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)
    $script:Ws.SendAsync([ArraySegment[byte]]$bytes, [System.Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).Wait()
    while ($true) {
        $ms = New-Object System.IO.MemoryStream
        $buf = New-Object byte[] 262144
        do {
            $r = $script:Ws.ReceiveAsync([ArraySegment[byte]]$buf, [Threading.CancellationToken]::None).Result
            $ms.Write($buf, 0, $r.Count)
        } while (-not $r.EndOfMessage)
        $text = [Text.Encoding]::UTF8.GetString($ms.ToArray())
        # Record browser console errors and failed resource loads for the QA report.
        if ($text.Contains('"method":"Runtime.exceptionThrown"') -or ($text.Contains('"method":"Log.entryAdded"') -and $text.Contains('"level":"error"'))) {
            $script:Console += [pscustomobject]@{ at = $script:LastUrl; message = ($text -replace '.*"text":"([^"]*)".*', '$1').Substring(0, [Math]::Min(300, ($text -replace '.*"text":"([^"]*)".*', '$1').Length)); url = ($text -replace '.*"url":"([^"]*)".*', '$1') }
        }
        if ($text.StartsWith('{"id":' + $myId + ',')) {
            if ($text -like '*"error":{*' -and $text -notlike '*"result":*') { throw "CDP $method failed: $text" }
            return $text
        }
    }
}

function Js([string]$expr) {
    $raw = Send 'Runtime.evaluate' @{ expression = $expr; awaitPromise = $true; returnByValue = $true; timeout = 30000 }
    $o = $raw | ConvertFrom-Json
    if ($o.result.exceptionDetails) { throw ('JS error: ' + ($o.result.exceptionDetails.exception.description, $o.result.exceptionDetails.text -ne $null)[0]) }
    return $o.result.result.value
}

function WaitFor([string]$cond, [int]$ms = 10000) {
    $t = [Diagnostics.Stopwatch]::StartNew()
    while ($t.ElapsedMilliseconds -lt $ms) {
        try { if (Js "!!($cond)") { return $true } } catch {}
        Start-Sleep -Milliseconds 250
    }
    throw "Timeout waiting for: $cond"
}

function Viewport([int]$w = 1440, [int]$h = 900, [bool]$mobile = $false) {
    Send 'Emulation.setDeviceMetricsOverride' @{ width = $w; height = $h; deviceScaleFactor = 1; mobile = $mobile } | Out-Null
    $script:VpW = $w; $script:VpH = $h; $script:VpMobile = $mobile
}

function Nav([string]$path, [int]$settle = 900) {
    $url = if ($path.StartsWith('http')) { $path } else { $script:Base + $path }
    $script:LastUrl = $path
    Send 'Page.navigate' @{ url = $url } | Out-Null
    Start-Sleep -Milliseconds 300
    WaitFor "document.readyState === 'complete'" 20000 | Out-Null
    Js 'document.fonts ? document.fonts.ready.then(() => true) : true' | Out-Null
    Start-Sleep -Milliseconds $settle
}

# Full-page capture: the viewport is grown to the page height so sticky sidebars and 100vh layouts render fully.
function Shot([string]$name, [bool]$full = $true, [string]$note = '') {
    $w = $script:VpW; $h = $script:VpH; $m = $script:VpMobile
    if ($full) {
        Js 'window.scrollTo(0,0); true' | Out-Null
        $ph = [int](Js 'Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)')
        if ($ph -gt $h) {
            Send 'Emulation.setDeviceMetricsOverride' @{ width = $w; height = [Math]::Min($ph, 7000); deviceScaleFactor = 1; mobile = $m } | Out-Null
            Start-Sleep -Milliseconds 500
            $ph2 = [int](Js 'Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)')
            if ($ph2 -ne $ph) { Send 'Emulation.setDeviceMetricsOverride' @{ width = $w; height = [Math]::Min($ph2, 7000); deviceScaleFactor = 1; mobile = $m } | Out-Null; Start-Sleep -Milliseconds 400 }
        }
    }
    $raw = Send 'Page.captureScreenshot' @{ format = 'png'; captureBeyondViewport = $false }
    $start = $raw.IndexOf('"data":"') + 8
    $end = $raw.IndexOf('"', $start)
    [IO.File]::WriteAllBytes((Join-Path $script:OutDir "$name.png"), [Convert]::FromBase64String($raw.Substring($start, $end - $start)))
    if ($full) { Viewport $w $h $m }
    $url = Js 'location.href'
    $script:Log += [pscustomobject]@{ file = "$name.png"; url = ($url -replace [regex]::Escape($script:Base), ''); status = 'captured'; note = $note }
    Write-Host "captured $name"
}

function Fail([string]$name, [string]$err) {
    $script:Log += [pscustomobject]@{ file = "$name.png"; url = ''; status = 'FAILED'; note = $err }
    Write-Host "FAILED $name : $err"
}

function Logout {
    try { Js "(async () => { const t = document.querySelector('meta[name=csrf-token]'); if (!t) return false; await fetch('$($script:Base)/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': t.content } }); return true; })()" | Out-Null } catch {}
}

# Signs in through the local-only demo role cards (the script never types a password).
# Cookies are cleared first so every role starts from a fresh browser session.
function LoginAs([string]$username) {
    Send 'Network.clearBrowserCookies' @{} | Out-Null
    Nav '/login' 300
    WaitFor "document.querySelector('.demo-role[data-username=$username]')" 8000 | Out-Null
    ClickNav "document.querySelector('.demo-role[data-username=$username]').click(); document.getElementById('login-form').requestSubmit()"
    if (Js "location.pathname.endsWith('/login')") { throw "Sign-in as $username did not leave the login page" }
}

# Runs a click/submit expression and waits for the NEXT document to finish loading.
function ClickNav([string]$expr, [int]$settle = 900) {
    Js "window.__oldDoc = 1; $expr; true" | Out-Null
    WaitFor "!window.__oldDoc && document.readyState === 'complete'" 20000 | Out-Null
    $script:LastUrl = Js 'location.pathname'
    Start-Sleep -Milliseconds $settle
}

# Submits the first form matching a CSS selector (confirmation dialogs pre-accepted).
function Submit([string]$selector) {
    $sel = ConvertTo-Json $selector
    ClickNav "(() => { const f = document.querySelector($sel); if (!f) throw new Error('No form ' + $sel); f.dataset.confirmed = '1'; f.requestSubmit ? f.requestSubmit() : f.submit(); })()"
}

# JSON call from the page context with the session + CSRF token (used only to set up data the UI then shows).
function Api([string]$method, [string]$path, [string]$bodyJs = 'null') {
    return Js "(async () => { const r = await fetch('$($script:Base)$path', { method: '$method', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: $bodyJs === null ? undefined : JSON.stringify($bodyJs) }); const d = await r.json().catch(() => ({})); d._status = r.status; return d; })()"
}

# Prints the current document to PDF through a stream (large files), with a page-number footer.
function PrintPdf([string]$pdf, [string]$footerLeft) {
    $footer = "<div style=`"font-family:'Nirmala UI',sans-serif;font-size:7.5px;color:#8a7458;width:100%;padding:0 16mm;display:flex;justify-content:space-between`"><span>$footerLeft</span><span><span class=`"pageNumber`"></span> / <span class=`"totalPages`"></span></span></div>"
    $raw = Send 'Page.printToPDF' @{ printBackground = $true; preferCSSPageSize = $true; displayHeaderFooter = $true; headerTemplate = '<div></div>'; footerTemplate = $footer; transferMode = 'ReturnAsStream'; generateDocumentOutline = $true; generateTaggedPDF = $true }
    $handle = ($raw | ConvertFrom-Json).result.stream
    $fs = [IO.File]::Create($pdf)
    try {
        do {
            $r = Send 'IO.read' @{ handle = $handle; size = 8388608 }
            $s = $r.IndexOf('"data":"') + 8; $e = $r.IndexOf('"', $s)
            $bytes = [Convert]::FromBase64String($r.Substring($s, $e - $s).Replace('\/', '/'))
            $fs.Write($bytes, 0, $bytes.Length)
        } while (-not $r.Contains('"eof":true'))
    } finally { $fs.Close() }
    Send 'IO.close' @{ handle = $handle } | Out-Null
}

function Step([string]$name, [scriptblock]$block) {
    try { & $block } catch { Fail $name $_.Exception.Message }
}
