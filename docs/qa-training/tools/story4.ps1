# Re-capture of the public website after the weather cache was refreshed (the first capture hit a cached Open-Meteo timeout).
. "$PSScriptRoot\cdp.ps1"
$out = 'C:\xampp\htdocs\vaasal_villa_hospitality_management_system28\docs\qa-training\screenshots'
Start-Cdp $out
Viewport 1440 900
function NavSlow([string]$path) {
    Send 'Page.navigate' @{ url = $script:Base + $path } | Out-Null
    Start-Sleep -Milliseconds 500
    WaitFor "document.readyState !== 'loading'" 30000 | Out-Null
    try { WaitFor "document.readyState === 'complete'" 45000 | Out-Null } catch { Write-Host "note: page still loading external resources" }
    Start-Sleep -Milliseconds 2500
}
Step '78_website_home' { NavSlow '/'; WaitFor "document.querySelector('.wx-ic')" 25000 | Out-Null; Shot '78_website_home' $true 'Public website home page with the live Jaffna weather (SVG icons)' }
Step '80_website_mobile' { Viewport 390 844 $true; NavSlow '/'; Shot '80_website_mobile' $true 'Website on a phone'; Viewport 1440 900 }
Stop-Cdp
$script:Log | Format-Table -AutoSize | Out-String -Width 200
"Console errors: " + $script:Console.Count
$script:Console | Format-Table -AutoSize | Out-String -Width 200
