<?php
/* OWNER DASHBOARD: small drawing helpers. Charts are plain inline SVG made on the server: no chart library needed. */

function ow_money($n): string { return '₱' . number_format((float) $n, 2); }
function ow_money0($n): string { return '₱' . number_format((float) $n, 0); }
function ow_dt(?string $dt): string { return $dt ? date('M j, Y · g:i A', strtotime($dt)) : '—'; }
function ow_ico(string $d, int $s = 18): string
{
    return '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** Rounds the top of a chart axis up to a tidy number. */
function ow_nice_max(float $max): float
{
    if ($max <= 0) { return 4; }
    $pow = pow(10, floor(log10($max)));
    foreach ([1, 2, 2.5, 5, 10] as $m) { if ($m * $pow >= $max) { return $m * $pow; } }
    return 10 * $pow;
}

/** Bar chart. $labels and $values have the same length. $money = true prints peso amounts. */
function ow_bar_chart(array $labels, array $values, string $what, bool $money = false, string $color = 'var(--brand)'): string
{
    $n = count($values);
    if ($n === 0 || array_sum($values) <= 0) { return ''; }
    $W = 640; $H = 230; $L = $money ? 58 : 38; $R = 8; $T = 10; $B = 30;
    $m0 = (float) max($values);
    $max = (!$money && $m0 <= 40) ? max(4, ceil($m0 / 4) * 4) : ow_nice_max($m0);   // whole-number ticks for small counts
    $pw = $W - $L - $R; $ph = $H - $T - $B; $slot = $pw / $n; $bw = max(2, min(34, $slot * 0.68));
    $svg = '<svg class="ow-chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($what) . '" preserveAspectRatio="xMidYMid meet">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $T + $ph - ($ph * $i / 4);
        $v = $max * $i / 4;
        $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $y . '" y2="' . $y . '" class="ow-grid"/>';
        $svg .= '<text x="' . ($L - 6) . '" y="' . ($y + 4) . '" text-anchor="end" class="ow-axis">' . ($money ? ow_money0($v) : (floor($v) == $v ? number_format($v) : number_format($v, 1))) . '</text>';
    }
    $every = max(1, (int) ceil($n / 9));
    foreach ($values as $i => $v) {
        $h = $max > 0 ? $ph * ($v / $max) : 0;
        $x = $L + $slot * $i + ($slot - $bw) / 2;
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($T + $ph - $h, 1) . '" width="' . round($bw, 1) . '" height="' . round($h, 1) . '" rx="2" style="fill:' . $color . '"><title>' . e($labels[$i]) . ': ' . ($money ? ow_money($v) : $v) . '</title></rect>';
        if ($i % $every === 0) {
            $svg .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($H - 10) . '" text-anchor="middle" class="ow-axis">' . e($labels[$i]) . '</text>';
        }
    }
    return $svg . '</svg>';
}

/** Donut chart of status counts. Gives '' when there is nothing to show. */
function ow_donut(array $parts, array $colors): string
{
    $total = array_sum($parts);
    if ($total <= 0) { return ''; }
    $svg = '<svg class="ow-donut" viewBox="0 0 42 42" role="img" aria-label="Status distribution"><circle cx="21" cy="21" r="15.9155" fill="none" stroke="var(--line-cool)" stroke-width="6"/>';
    $offset = 25;   // start at 12 o'clock
    foreach ($parts as $k => $v) {
        if ($v <= 0) { continue; }
        $pct = $v / $total * 100;
        $svg .= '<circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="6" style="stroke:' . $colors[$k] . '" stroke-dasharray="' . round($pct, 3) . ' ' . round(100 - $pct, 3) . '" stroke-dashoffset="' . round($offset, 3) . '"><title>' . e(ucfirst($k)) . ': ' . $v . '</title></circle>';
        $offset -= $pct;
    }
    return $svg . '<text x="21" y="22.5" text-anchor="middle" class="ow-donut-num">' . $total . '</text><text x="21" y="27.5" text-anchor="middle" class="ow-donut-sub">requests</text></svg>';
}

/** Pager links that keep the current filters. */
function ow_pager(int $total, int $page, int $per): string
{
    $pages = (int) max(1, ceil($total / $per));
    if ($pages <= 1) { return ''; }
    $keep = $_GET; unset($keep['page'], $keep['id'], $keep['modal']);
    $link = fn(int $p, string $text, bool $on = false, bool $off = false) => $off
        ? '<span class="st-chip is-off">' . $text . '</span>'
        : '<a class="st-chip' . ($on ? ' is-on' : '') . '" href="?' . e(http_build_query($keep + ['page' => $p])) . '"' . ($on ? ' aria-current="page"' : '') . '>' . $text . '</a>';
    return '<nav class="st-filters ow-pager" aria-label="Pages">' . $link($page - 1, '‹ Prev', false, $page <= 1) . '<span class="st-foot">Page ' . $page . ' of ' . $pages . '</span>' . $link($page + 1, 'Next ›', false, $page >= $pages) . '</nav>';
}

function ow_empty(string $title, string $text): string
{
    return '<div class="st-empty"><span class="st-empty-icon">' . ow_ico('<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>', 28) . '</span><h2>' . e($title) . '</h2><p>' . e($text) . '</p></div>';
}
