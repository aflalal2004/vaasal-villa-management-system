<?php
// Builds, from video_data.php: VAASAL_VILLA_TRAINING.srt, video_frames/slides.txt (FFmpeg concat list),
// frames.json (for make_frames.ps1) and the storyboard / narration HTML fragments used by video.src.html.
$S = __DIR__;
$root = 'C:/xampp/htdocs/vaasal_villa_hospitality_management_system28/docs/qa-training';
$scenes = require "$S/video_data.php";
$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$srtTime = fn (float $t) => sprintf('%02d:%02d:%02d,%03d', intdiv((int) $t, 3600), intdiv((int) $t % 3600, 60), (int) $t % 60, (int) round(($t - floor($t)) * 1000));
$clock = fn (float $t) => sprintf('%d:%02d', intdiv((int) round($t), 60), (int) round($t) % 60);
// Wrap a subtitle into at most two balanced lines at a space.
$wrap = function (string $s): string {
    if (mb_strlen($s) <= 46) return $s;
    $mid = intdiv(mb_strlen($s), 2); $best = null;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $i => $ch) if ($ch === ' ' && ($best === null || abs($i - $mid) < abs($best - $mid))) $best = $i;
    return $best === null ? $s : mb_substr($s, 0, $best)."\n".mb_substr($s, $best + 1);
};

$t = 2.0;                 // 2 s title card before the first subtitle
$gap = 0.4;               // pause between subtitles
$srt = []; $n = 0; $frames = []; $board = ''; $script = '';
foreach ($scenes as &$sc) {
    $sc['start'] = $t;
    $rows = '';
    foreach ($sc['lines'] as [$shot, $text, $action]) {
        // Spoken Tamil ≈ 13 code points per second (vowel signs are separate code points) + breathing room.
        $dur = round(min(10.0, max(3.5, mb_strlen($text) / 13 + 0.9)), 1);
        $srt[] = (++$n)."\n".$srtTime($t).' --> '.$srtTime($t + $dur)."\n".$wrap($text)."\n";
        $last = end($frames);
        if ($last && $last['shot'] === $shot) { $frames[key($frames)]['dur'] += $dur + $gap; }
        else { $frames[] = ['shot' => $shot, 'dur' => $dur + $gap, 'scene' => $sc['id']]; }
        $rows .= '<tr><td class="num">'.$clock($t).'–'.$clock($t + $dur).'</td><td><img class="thumb" src="thumb/'.$e($shot).'.jpg" alt=""><div class="mono" style="font-size:6.5pt">'.$e($shot).'.png</div></td><td class="small">'.$e($action).'</td><td>'.$e($text).'</td></tr>';
        $t += $dur + $gap;
    }
    $t += 0.8;            // scene transition
    $frames[array_key_last($frames)]['dur'] += 0.8;
    $sc['end'] = $t;
    $board .= '<div class="scene avoid"><div class="scene-h"><span class="sn">'.$sc['id'].'</span><b>'.$e($sc['title']).'</b> <span class="en-t">'.$e($sc['en']).'</span><span class="st">'.$clock($sc['start']).' – '.$clock($sc['end']).'</span></div>'
        .'<table class="grid small" style="margin-top:1.5mm"><tr><th style="width:17mm">நேரம்</th><th style="width:44mm">திரை</th><th style="width:40mm">செயல் / Action</th><th>விவரிப்பு (Narration)</th></tr>'.$rows.'</table></div>';
    $script .= '<div class="avoid"><div class="h3">காட்சி '.$sc['id'].' — '.$e($sc['title']).' <span class="en-t">('.$clock($sc['start']).')</span></div><p>'
        .implode(' ', array_map(fn ($l) => $e($l[1]), $sc['lines'])).'</p></div>';
}
unset($sc);
$total = $t + 3.0;         // end card
$frames[array_key_last($frames)]['dur'] += 3.0;
$frames[0]['dur'] += 2.0;  // title card time on the first frame

file_put_contents("$root/VAASAL_VILLA_TRAINING.srt", "\xEF\xBB\xBF".implode("\n", $srt));

@mkdir("$root/video_frames", 0777, true);
$list = '';
foreach ($frames as $i => &$f) {
    $f['file'] = sprintf('%02d_%s.png', $i + 1, $f['shot']);
    $list .= "file '{$f['file']}'\nduration ".number_format($f['dur'], 1, '.', '')."\n";
}
unset($f);
$list .= "file '".end($frames)['file']."'\n";   // concat demuxer needs the last file repeated
file_put_contents("$root/video_frames/slides.txt", $list);
file_put_contents("$S/frames.json", json_encode($frames, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$summary = '<table class="grid"><tr><th>#</th><th>காட்சி</th><th>Scene</th><th class="num">தொடக்கம்</th><th class="num">நீளம்</th></tr>';
foreach ($scenes as $sc) $summary .= '<tr><td>'.$sc['id'].'</td><td>'.$e($sc['title']).'</td><td class="en-t">'.$e($sc['en']).'</td><td class="num">'.$clock($sc['start']).'</td><td class="num">'.$clock($sc['end'] - $sc['start']).'</td></tr>';
$summary .= '<tr class="total"><td></td><td>மொத்தம்</td><td class="en-t">Total incl. title and end cards</td><td></td><td class="num">'.$clock($total).'</td></tr></table>';

file_put_contents("$S/video_storyboard.html", $board);
file_put_contents("$S/video_script.html", $script);
file_put_contents("$S/video_summary.html", $summary);
file_put_contents("$S/video_stats.json", json_encode(['subtitles' => $n, 'frames' => count($frames), 'seconds' => round($total, 1), 'duration' => $clock($total)]));
echo "Subtitles: $n · frames: ".count($frames).' · duration '.$clock($total)."\n";
