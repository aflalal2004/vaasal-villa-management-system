<?php
// Merges the automated QA harness results and the live HTTP checks into docs/qa-training/TEST_CASES.csv (+ .xlsx).
$root = 'C:/xampp/htdocs/vaasal_villa_hospitality_management_system28';
$out = "$root/docs/qa-training";

function readCsv(string $path): array
{
    $f = fopen($path, 'r');
    if (fread($f, 3) !== "\xEF\xBB\xBF") rewind($f); // skip a UTF-8 BOM so the quoted header parses
    $h = fgetcsv($f);
    $rows = [];
    while (($r = fgetcsv($f)) !== false) {
        if (count($r) === count($h)) $rows[] = array_combine($h, $r);
    }
    return $rows;
}

copy("$root/storage/app/qa/test_cases.csv", "$out/data/automated_test_cases.csv");
$auto = readCsv("$out/data/automated_test_cases.csv");
$live = readCsv("$out/data/live_checks.csv");

$rows = [];
foreach ($auto as $r) $rows[] = ['ID' => $r['ID'], 'Source' => 'Automated (tests/QA/QaAuditTest.php)', 'Module' => $r['Module'], 'Test case' => $r['Test case'], 'Expected' => $r['Expected'], 'Actual' => $r['Actual'], 'Status' => $r['Status']];
foreach ($live as $r) $rows[] = ['ID' => $r['ID'], 'Source' => 'Live HTTP check (XAMPP)', 'Module' => $r['Module'], 'Test case' => $r['Test case'], 'Expected' => $r['Expected'], 'Actual' => $r['Actual'], 'Status' => $r['Status']];

// Screenshot walkthrough (real browser against the live database). Keep the final result per screenshot;
// first-pass failures that a corrected re-run captured are kept as a note, not as a failed case.
$shotLog = "$out/data/screenshot_log.csv";
if (is_file($shotLog)) {
    foreach (readCsv($shotLog) as $r) {
        $name = $r['file'];
        if (! preg_match('/^\d\d_.*\.png$/', $name)) continue;
        $err = $r['first_pass_error'] ?? '';
        $rows[] = ['ID' => 'UI-'.substr($name, 0, 2), 'Source' => 'Browser walkthrough (headless Chrome)', 'Module' => 'UI walkthrough', 'Test case' => $name.($r['note'] ? ' — '.$r['note'] : ''),
            'Expected' => 'Page renders; workflow step succeeds', 'Actual' => $r['status'] === 'captured' ? 'Captured '.$r['url'].($err ? ' (captured on re-run; first pass: '.$err.')' : '') : $err,
            'Status' => $r['status'] === 'captured' ? 'PASS' : 'FAIL'];
    }
}

$cols = ['ID', 'Source', 'Module', 'Test case', 'Expected', 'Actual', 'Status'];
$f = fopen("$out/TEST_CASES.csv", 'w');
fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, $cols);
foreach ($rows as $r) fputcsv($f, array_map(fn ($c) => $r[$c], $cols));
fclose($f);

// Minimal XLSX (inline strings, bold frozen header, autofilter, coloured status cells).
if (class_exists('ZipArchive')) {
    $esc = fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $colL = fn ($i) => chr(65 + $i);
    $styleFor = fn ($status) => ['PASS' => 2, 'FAIL' => 3, 'BLOCKED' => 4][$status] ?? 0;
    $xmlRows = '';
    $all = array_merge([array_combine($cols, $cols)], $rows);
    foreach ($all as $n => $r) {
        $rn = $n + 1;
        $xmlRows .= "<row r=\"$rn\">";
        foreach ($cols as $ci => $c) {
            $s = $n === 0 ? 1 : ($c === 'Status' ? $styleFor($r[$c]) : 5);
            $xmlRows .= '<c r="'.$colL($ci).$rn.'" t="inlineStr" s="'.$s.'"><is><t xml:space="preserve">'.$esc($r[$c]).'</t></is></c>';
        }
        $xmlRows .= '</row>';
    }
    $last = count($all);
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        .'<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="22" customWidth="1"/><col min="4" max="4" width="60" customWidth="1"/><col min="5" max="5" width="50" customWidth="1"/><col min="6" max="6" width="70" customWidth="1"/><col min="7" max="7" width="11" customWidth="1"/></cols>'
        ."<sheetData>$xmlRows</sheetData><autoFilter ref=\"A1:G$last\"/></worksheet>";
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        .'<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FF0E5A4E"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD7F2E3"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFFBD9D6"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF1CC"/></patternFill></fill></fills>'
        .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
        .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/>'
        .'<xf numFmtId="0" fontId="0" fillId="3" borderId="0" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="4" borderId="0" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="5" borderId="0" applyFill="1"/>'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>';
    $z = new ZipArchive();
    @unlink("$out/TEST_CASES.xlsx");
    $z->open("$out/TEST_CASES.xlsx", ZipArchive::CREATE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Test cases" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'Test cases\'!$A$1:$G$'.$last.'</definedName></definedNames></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/styles.xml', $styles);
    $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $z->close();
}

$count = [];
foreach ($rows as $r) { $count[$r['Source']][$r['Status']] = ($count[$r['Source']][$r['Status']] ?? 0) + 1; }
$modules = [];
foreach ($auto as $r) { $modules[$r['Module']][$r['Status']] = ($modules[$r['Module']][$r['Status']] ?? 0) + 1; }
file_put_contents("$out/data/test_summary.json", json_encode(['total' => count($rows), 'by_source' => $count, 'automated_by_module' => $modules], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['total' => count($rows), 'by_source' => $count, 'xlsx' => class_exists('ZipArchive')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
