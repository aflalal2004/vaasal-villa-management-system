<?php
// Reads the document outline (bookmarks generated from <h1>/<h2>) of a Chrome PDF and writes the page number
// of every heading, in document order, to pages-<doc>.json so the table of contents can show real page numbers.
// Usage: php pdf_pages.php <pdf> <doc>
ini_set('memory_limit', '1024M');
[$_, $file, $doc] = $argv;
$build = 'C:/xampp/htdocs/vaasal_villa_hospitality_management_system28/docs/qa-training/_build';
$pdf = file_get_contents($file);

preg_match_all('/(?<![0-9])(\d+) 0 obj\b/', $pdf, $m, PREG_OFFSET_CAPTURE);
$obj = [];
foreach ($m[1] as $i => $hit) {
    $start = $m[0][$i][1] + strlen($m[0][$i][0]);
    $end = strpos($pdf, 'endobj', $start);
    $body = substr($pdf, $start, min($end - $start, 4000));
    if (($s = strpos($body, 'stream')) !== false) $body = substr($body, 0, $s);
    $obj[(int) $hit[0]] = $body;
}
$ref = fn (string $body, string $key) => preg_match('/\/'.$key.'\s+(\d+) 0 R/', $body, $r) ? (int) $r[1] : null;

$catalog = null;
foreach ($obj as $id => $b) if (preg_match('/\/Type\s*\/Catalog/', $b)) { $catalog = $b; break; }
if (! $catalog) exit("No catalog\n");

// Physical page order
$pageNo = []; $n = 0;
$walk = function (int $id) use (&$walk, &$obj, &$pageNo, &$n) {
    $b = $obj[$id] ?? '';
    if (preg_match('/\/Type\s*\/Pages\b/', $b) && preg_match('/\/Kids\s*\[([^\]]*)\]/', $b, $k)) {
        preg_match_all('/(\d+) 0 R/', $k[1], $kids);
        foreach ($kids[1] as $kid) $walk((int) $kid);
    } else {
        $pageNo[$id] = ++$n;
    }
};
$walk($ref($catalog, 'Pages'));

// Outline, depth-first
$out = [];
$visit = function (?int $id) use (&$visit, &$obj, &$out, &$pageNo, $ref) {
    while ($id) {
        $b = $obj[$id] ?? '';
        $out[] = preg_match('/\/(?:Dest|D)\s*\[\s*(\d+) 0 R/', $b, $d) ? ($pageNo[(int) $d[1]] ?? null) : null;
        if ($first = $ref($b, 'First')) $visit($first);
        $id = $ref($b, 'Next');
    }
};
$root = $ref($catalog, 'Outlines');
if (! $root) exit("No outline in $file\n");
$visit($ref($obj[$root], 'First'));

file_put_contents("$build/pages-$doc.json", json_encode($out));
$heads = json_decode(file_get_contents("$build/heads-$doc.json"), true);
echo "$doc: $n page(s), ".count($out).' outline entr(ies), '.count($heads)." heading(s)".(count($out) === count($heads) ? ' — OK' : ' — MISMATCH')."\n";
