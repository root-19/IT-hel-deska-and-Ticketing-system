<?php
// Lightweight single-series CSS charts (one brand hue, direct value labels, hover tooltips)

// Horizontal bars: ['Label' => count, ...]
function bar_chart(array $items, string $emptyText = 'No data yet.'): string
{
    if (!$items || max($items) === 0) {
        return '<p class="py-8 text-center text-sm text-slate-400">' . e($emptyText) . '</p>';
    }

    $max = max($items);
    $total = array_sum($items);
    $html = '<ul class="space-y-3">';
    foreach ($items as $label => $value) {
        $width = $value > 0 ? max(2, round($value / $max * 100, 1)) : 0;
        $share = $total ? round($value / $total * 100) : 0;
        $html .= '<li class="group" title="' . e($label) . ': ' . $value . ' (' . $share . '%)">'
            . '<div class="mb-1 flex items-baseline justify-between gap-3 text-sm">'
            . '<span class="truncate text-slate-600">' . e($label) . '</span>'
            . '<span class="shrink-0 font-semibold tabular-nums text-slate-900">' . $value . ' <span class="font-normal text-slate-400">· ' . $share . '%</span></span>'
            . '</div>'
            . '<div class="h-2.5 rounded-full bg-slate-100">'
            . '<div class="h-2.5 rounded-full bg-brand-600 transition-opacity group-hover:opacity-80" style="width:' . $width . '%"></div>'
            . '</div></li>';
    }
    return $html . '</ul>';
}

// Vertical columns over time: ['2026-10-01' => count, ...] (keys are Y-m-d dates)
function column_chart(array $series): string
{
    $max = max(1, ...array_values($series));
    $count = count($series);
    $labelEvery = max(1, (int) ceil($count / 7));

    $html = '<div class="relative">'
        . '<div class="flex h-44 items-end gap-[2px] border-b border-slate-200">';
    $i = 0;
    foreach ($series as $date => $value) {
        $height = $value > 0 ? max(3, round($value / $max * 100, 1)) : 0;
        $label = date('M j', strtotime($date));
        $html .= '<div class="group relative flex h-full flex-1 items-end justify-center" tabindex="0" aria-label="' . $label . ': ' . $value . ' tickets">'
            . '<div class="w-full max-w-[18px] rounded-t bg-brand-600 transition-opacity group-hover:opacity-80" style="height:' . $height . '%"></div>'
            . '<div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus:block">'
            . $label . ' · <span class="font-semibold">' . $value . '</span> ticket' . ($value === 1 ? '' : 's') . '</div>'
            . '</div>';
        $i++;
    }
    $html .= '</div><div class="mt-2 flex gap-[2px] text-[11px] text-slate-400">';
    $i = 0;
    foreach (array_keys($series) as $date) {
        $show = $i % $labelEvery === 0;
        $html .= '<span class="flex-1 text-center">' . ($show ? date('M j', strtotime($date)) : '') . '</span>';
        $i++;
    }
    $html .= '</div><p class="mt-1 text-right text-[11px] text-slate-400">Peak: ' . $max . ' per day</p></div>';

    return $html;
}

// Zero-filled daily counts of tickets created between two dates (inclusive)
function daily_ticket_counts(string $from, string $to): array
{
    $stmt = db()->prepare('SELECT DATE(created_at) d, COUNT(*) c FROM tickets WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY d');
    $stmt->execute([$from, $to]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $series = [];
    for ($day = strtotime($from); $day <= strtotime($to); $day = strtotime('+1 day', $day)) {
        $key = date('Y-m-d', $day);
        $series[$key] = (int) ($counts[$key] ?? 0);
    }
    return $series;
}
