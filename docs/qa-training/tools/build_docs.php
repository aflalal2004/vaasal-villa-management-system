<?php
// Builds printable HTML for the PDFs from *.src.html sources.
//   [[FIG:name|caption]] or [[FIG:name|caption|maxSlices]]  → numbered figure (JPEG slices of screenshots/name.png)
//   [[TOC]]                                                  → table of contents of <h1>/<h2> (page numbers from pages-<doc>.json)
//   [[INCLUDE:file]]                                         → raw HTML fragment from the scratchpad
// Usage: php build_docs.php <doc> [<doc> ...]   (doc = manual | qa | video)
$S = __DIR__;
$root = 'C:/xampp/htdocs/vaasal_villa_hospitality_management_system28/docs/qa-training';
$build = "$root/_build";
$images = [];
foreach (json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents("$build/images.json")), true) as $im) $images[$im['name']] = $im;

$figLabel = ['manual' => 'படம்', 'qa' => 'Figure', 'video' => 'படம்'];
$contLabel = ['manual' => 'தொடர்ச்சி', 'qa' => 'continued', 'video' => 'தொடர்ச்சி'];
$missing = [];

foreach (array_slice($argv, 1) as $doc) {
    $html = file_get_contents("$S/$doc.src.html");
    $html = preg_replace_callback('/\[\[INCLUDE:([^\]]+)\]\]/', fn ($m) => file_get_contents("$S/".trim($m[1])), $html);

    // Figures
    $n = 0;
    $html = preg_replace_callback('/\[\[FIG:([^|\]]+)\|([^|\]]*)(?:\|(\d+))?\]\]/u', function ($m) use (&$n, $images, $doc, $figLabel, $contLabel, &$missing) {
        $name = trim($m[1]); $cap = trim($m[2]); $max = isset($m[3]) ? (int) $m[3] : 99;
        if (! isset($images[$name])) { $missing[] = $name; return '<div class="callout warn"><b>Missing screenshot:</b> '.htmlspecialchars($name).'</div>'; }
        $n++;
        $im = $images[$name];
        $mobile = $im['w'] < 800;
        $slices = array_slice($im['slices'], 0, $max);
        $more = count($im['slices']) - count($slices);
        $out = '';
        foreach ($slices as $i => $s) {
            $cls = 'shot'.($mobile ? ' mobile' : '').($i > 0 ? ' cont' : '');
            $out .= "<figure class=\"$cls\"".($i === 0 ? " id=\"fig-$name\"" : '').'>';
            $out .= "<img src=\"img/{$s['file']}\" alt=\"".htmlspecialchars($cap).'">';
            $label = $figLabel[$doc].' '.$n.($doc !== 'qa' ? " · Figure $n" : '').($i > 0 ? ' ('.$contLabel[$doc].' '.($i + 1).'/'.count($slices).')' : (count($slices) > 1 ? ' (1/'.count($slices).')' : ''));
            $tail = ($i === count($slices) - 1)
                ? '<span class="file">screenshots/'.$name.'.png · '.$im['w'].'×'.$im['h'].' px'.($more > 0 ? ($doc === 'qa' ? " · $more more part(s) in the full-size file" : " · மீதி $more பகுதி முழு அளவுப் படத்தில்") : '').'</span>'
                : '';
            $out .= "<figcaption><b>$label —</b> ".htmlspecialchars($cap)." $tail</figcaption></figure>";
        }
        return $out;
    }, $html);

    // Cross-references [[REF:name]] → figure number
    preg_match_all('/<figure class="shot[^"]*" id="fig-([^"]+)">.*?<b>\S+ (\d+)/su', $html, $fm, PREG_SET_ORDER);
    $figNo = [];
    foreach ($fm as $x) $figNo[$x[1]] = $x[2];
    $html = preg_replace_callback('/\[\[REF:([^\]]+)\]\]/', fn ($m) => '<a href="#fig-'.$m[1].'">'.$figLabel[$doc].' '.($figNo[$m[1]] ?? '?').'</a>', $html);

    // Role matrix from the direct-URL test results
    if (str_contains($html, '[[ROLEMATRIX]]')) {
        $rm = json_decode(file_get_contents("$root/data/role_matrix.json"), true);
        $cols = array_values(array_diff(array_keys($rm[0]), ['ROLE', 'USERNAME', 'HOME']));
        $t = '<table class="grid matrix small"><tr><th>Role</th>';
        foreach ($cols as $c) $t .= '<th>'.htmlspecialchars(ucwords(strtolower($c))).'</th>';
        $t .= '</tr>';
        foreach ($rm as $r) {
            $t .= '<tr><td>'.htmlspecialchars($r['ROLE']).'</td>';
            foreach ($cols as $c) $t .= str_starts_with($r[$c], 'Yes') ? '<td class="y">✓</td>' : '<td class="n">—</td>';
            $t .= '</tr>';
        }
        $html = str_replace('[[ROLEMATRIX]]', $t.'</table>', $html);
    }

    // Appendix tables from the generated data files
    $csv = function (string $path) {
        $f = fopen($path, 'r'); if (fread($f, 3) !== "\xEF\xBB\xBF") rewind($f);
        $h = fgetcsv($f); $rows = [];
        while (($r = fgetcsv($f)) !== false) if (count($r) === count($h)) $rows[] = array_combine($h, $r);
        return $rows;
    };
    $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $badge = fn ($s) => '<span class="badge '.(['PASS' => 'b-pass', 'FAIL' => 'b-fail', 'BLOCKED' => 'b-blocked'][$s] ?? 'b-info').'">'.$e($s).'</span>';
    if (str_contains($html, '[[CASES]]')) {
        $t = '<table class="grid small"><tr><th style="width:23mm">ID</th><th style="width:24mm">Module</th><th>Test case / expected</th><th>Actual result</th><th style="width:15mm">Status</th></tr>';
        foreach ($csv("$root/TEST_CASES.csv") as $r) {
            if (str_starts_with($r['Source'], 'Browser')) continue;
            $t .= '<tr><td class="mono" style="font-size:7pt">'.$e($r['ID']).'</td><td>'.$e($r['Module']).'</td><td>'.$e($r['Test case']).'<div class="muted">Expected: '.$e($r['Expected']).'</div></td><td>'.$e(mb_strimwidth($r['Actual'], 0, 260, '…')).'</td><td>'.$badge($r['Status']).'</td></tr>';
        }
        $html = str_replace('[[CASES]]', $t.'</table>', $html);
    }
    if (str_contains($html, '[[SHOTS]]')) {
        $t = '<table class="grid small"><tr><th style="width:44mm">File</th><th style="width:52mm">URL</th><th>What it shows</th><th style="width:15mm">Result</th></tr>';
        foreach ($csv("$root/data/screenshot_log.csv") as $r) {
            if (! str_ends_with($r['file'], '.png')) continue;
            $t .= '<tr><td class="mono" style="font-size:7pt">'.$e($r['file']).'</td><td class="mono" style="font-size:6.8pt;word-break:break-all">'.$e($r['url']).'</td><td>'.$e($r['note']).($r['first_pass_error'] ? '<div class="muted">Re-run; first pass: '.$e($r['first_pass_error']).'</div>' : '').'</td><td>'.$badge($r['status'] === 'captured' ? 'PASS' : 'FAIL').'</td></tr>';
        }
        $html = str_replace('[[SHOTS]]', $t.'</table>', $html);
    }

    // Heading ids + TOC
    $heads = [];
    $html = preg_replace_callback('/<(h[12])([^>]*)>(.*?)<\/\1>/su', function ($m) use (&$heads) {
        $attrs = $m[2];
        if (! preg_match('/id="([^"]+)"/', $attrs, $idm)) { $id = 'h'.(count($heads) + 1); $attrs .= " id=\"$id\""; } else { $id = $idm[1]; }
        $text = preg_replace('/<span class="(num|sub)">.*?<\/span>/s', '', $m[3]);
        $heads[] = ['level' => $m[1], 'id' => $id, 'text' => trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'num' => preg_match('/<span class="num">(.*?)<\/span>/s', $m[3], $nm) ? strip_tags($nm[1]) : ''];
        return "<{$m[1]}$attrs>{$m[3]}</{$m[1]}>";
    }, $html);
    $pages = is_file("$build/pages-$doc.json") ? json_decode(file_get_contents("$build/pages-$doc.json"), true) : [];
    $toc = '<ol class="toc">';
    foreach ($heads as $i => $h) {
        $pg = $pages[$i] ?? '';
        $toc .= '<li class="toc-'.$h['level'].'"><a href="#'.$h['id'].'"><span class="toc-num">'.htmlspecialchars($h['num']).'</span><span class="toc-text">'.htmlspecialchars($h['text']).'</span><span class="toc-dots"></span><span class="toc-pg">'.$pg.'</span></a></li>';
    }
    $toc .= '</ol>';
    $html = str_replace('[[TOC]]', $toc, $html);

    file_put_contents("$build/$doc.html", $html);
    file_put_contents("$build/heads-$doc.json", json_encode($heads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "$doc: $n figure(s), ".count($heads)." heading(s)".($pages ? ', page numbers applied' : '')."\n";
}
if ($missing) echo 'MISSING: '.implode(', ', array_unique($missing))."\n";
