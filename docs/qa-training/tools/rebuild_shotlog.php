<?php
// Rebuilds docs/qa-training/data/screenshot_log.csv from both walkthrough runs (story2 = main pass, story3 = POS/cash re-run).
$S = __DIR__;
$root = 'C:/xampp/htdocs/vaasal_villa_hospitality_management_system28/docs/qa-training';

// Captions from the scripts: Shot 'name' <full> 'note'
$notes = [];
foreach (['story2.ps1', 'story3.ps1', 'story4.ps1'] as $f) {
    preg_match_all("/Shot '([^']+)'(?:\s+\\\$(?:true|false))?(?:\s+(['\"])(.*?)\\2)?/", file_get_contents("$S/$f"), $m, PREG_SET_ORDER);
    foreach ($m as $x) if (! empty($x[3])) $notes[$x[1]] = str_replace("''", "'", $x[3]);
}
// URLs of the previous CSV (main pass) where known
$urls = [];
$f = fopen("$root/data/screenshot_log.csv", 'r'); fread($f, 3) === "\xEF\xBB\xBF" || rewind($f); fgetcsv($f);
while ($r = fgetcsv($f)) if (($r[2] ?? '') === 'captured') $urls[$r[0]] = $r[1];
fclose($f);
// Pages captured only in the re-run
$urls = array_merge($urls, [
    '81_day_end_count.png' => '/pos/day-end?shift=36', '82_shift_closed.png' => '/pos/shifts', '32_open_shift.png' => '/pos/shifts', '33_shift_open.png' => '/pos/shifts',
    '30_pos_floor_plan.png' => '/pos?outlet=1', '05_pos_table.png' => '/pos?outlet=1&order=107', '31_kitchen_kot_t6.png' => '/pos/kds?station=all',
    '34_pos_bill.png' => '/pos/order/107/bill', '06_pos_payment.png' => '/pos?outlet=1&order=107', '35_pos_receipt.png' => '/pos/order/107/receipt',
    '36_table_released.png' => '/pos?outlet=1', '37_cashier_today.png' => '/pos/today', '75_session_ended.png' => '/pos?outlet=1', '76_session_return.png' => '/login?return=/pos?outlet=1',
]);

$final = []; $first = [];
foreach (['story2_run.log' => 'main', 'story3_run.log' => 'rerun'] as $log => $pass) {
    $lines = preg_split('/\R/', preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents("$S/$log")));
    foreach ($lines as $line) {
        if (preg_match('/^captured (\S+)/', $line, $m)) {
            $final[$m[1]] = ['status' => 'captured', 'pass' => $pass];
        } elseif (preg_match('/^FAILED (\S+) : (.*)$/', $line, $m)) {
            $first[$m[1]] = trim($m[2]);
            if (! isset($final[$m[1]]) || $final[$m[1]]['status'] !== 'captured') $final[$m[1]] = ['status' => 'FAILED', 'pass' => $pass];
        }
    }
}
// Website shots re-captured by story4 once the weather cache held a successful forecast
foreach (['78_website_home', '80_website_mobile'] as $w) {
    $final[$w] = ['status' => 'captured', 'pass' => 'rerun'];
    $first[$w] = 'First capture had no weather widget (a cached Open-Meteo timeout); re-captured';
}
// setup step of the re-run succeeded (T6 cleared) — it has no screenshot of its own
if (str_contains(file_get_contents("$S/story3_run.log"), 'T6 cleared:')) $final['setup_free_t6'] = ['status' => 'done', 'pass' => 'rerun'];
ksort($final);

$f = fopen("$root/data/screenshot_log.csv", 'w');
fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, ['file', 'url', 'status', 'note', 'pass', 'first_pass_error']);
$c = [];
foreach ($final as $name => $r) {
    $file = str_starts_with($name, 'setup') || str_starts_with($name, 'hk_') || str_starts_with($name, 'kitchen_') || str_starts_with($name, 'pos_') || str_starts_with($name, 'hotel_') ? $name : "$name.png";
    fputcsv($f, [$file, $urls["$name.png"] ?? '', $r['status'], $notes[$name] ?? '', $r['pass'], $first[$name] ?? '']);
    $c[$r['status']] = ($c[$r['status']] ?? 0) + 1;
}
fclose($f);
echo json_encode($c), ' — PNG files on disk: ', count(glob("$root/screenshots/*.png")), "\n";
