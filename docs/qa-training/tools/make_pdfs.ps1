# Two-pass PDF build: HTML → PDF → page numbers from the PDF outline → TOC with page numbers → final PDF.
param([string[]]$Docs = @('manual', 'qa', 'video'))
. "$PSScriptRoot\cdp.ps1"
$root = 'C:\xampp\htdocs\vaasal_villa_hospitality_management_system28\docs\qa-training'
$build = Join-Path $root '_build'
$php = 'C:\xampp\php\php.exe'
$names = @{ manual = 'VAASAL_VILLA_TAMIL_USER_MANUAL.pdf'; qa = 'VAASAL_VILLA_QA_TEST_REPORT.pdf'; video = 'VAASAL_VILLA_TRAINING_VIDEO_SCRIPT.pdf' }
# Footer texts live in a UTF-8 JSON file (Windows PowerShell reads BOM-less .ps1 files as ANSI, which garbles Tamil).
$footers = Get-Content -Raw -Encoding UTF8 "$PSScriptRoot\footers.json" | ConvertFrom-Json

Start-Cdp $build 9334
try {
    foreach ($d in $Docs) {
        Remove-Item (Join-Path $build "pages-$d.json") -ErrorAction SilentlyContinue
        foreach ($pass in 1, 2) {
            & $php "$PSScriptRoot\build_docs.php" $d
            $html = 'file:///' + ((Join-Path $build "$d.html") -replace '\\', '/')
            Send 'Page.navigate' @{ url = $html } | Out-Null
            Start-Sleep -Milliseconds 800
            WaitFor "document.readyState === 'complete'" 60000 | Out-Null
            Js "document.fonts.ready.then(() => Promise.all([...document.images].map(i => i.complete ? 1 : new Promise(r => { i.onload = i.onerror = r; })))).then(() => true)" | Out-Null
            Start-Sleep -Milliseconds 800
            $pdf = Join-Path $root $names[$d]
            PrintPdf $pdf ([System.Net.WebUtility]::HtmlEncode($footers.$d))
            & $php "$PSScriptRoot\pdf_pages.php" $pdf $d
        }
        $size = [Math]::Round((Get-Item (Join-Path $root $names[$d])).Length / 1MB, 1)
        Write-Host "$($names[$d]): $size MB"
    }
} finally { Stop-Cdp }
