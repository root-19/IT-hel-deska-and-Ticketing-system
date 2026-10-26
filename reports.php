<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/charts.php';
$user = require_role('admin');

// ---------- Date range ----------
$presets = [
    '7d'         => ['Last 7 days', date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    'month'      => ['This month', date('Y-m-01'), date('Y-m-d')],
    'last_month' => ['Last month', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'year'       => ['This year', date('Y-01-01'), date('Y-m-d')],
];
$preset = $_GET['preset'] ?? (isset($_GET['from']) ? '' : 'month');
$isDate = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value);

if (isset($presets[$preset])) {
    [, $from, $to] = $presets[$preset];
} else {
    $preset = '';
    $from = $isDate($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
    $to = $isDate($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
}
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];
$rangeLabel = date('M j, Y', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));

// ---------- CSV export ----------
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = db()->prepare(
        'SELECT t.*, c.name AS category_name, c.audience,
                r.first_name AS requester_first_name, r.last_name AS requester_last_name, r.role AS requester_role,
                a.first_name AS assignee_first_name, a.last_name AS assignee_last_name
         FROM tickets t
         JOIN categories c ON c.id = t.category_id
         JOIN users r ON r.id = t.requester_id
         LEFT JOIN users a ON a.id = t.assigned_to
         WHERE t.created_at BETWEEN ? AND ?
         ORDER BY t.created_at'
    );
    $stmt->execute($range);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="it-helpdesk-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly
    fputcsv($out, ['Ticket No.', 'Submitted', 'Support Area', 'Category', 'Subject', 'Requester', 'Account Type', 'Location',
        'Priority', 'Status', 'Assigned To', 'Resolved', 'Resolution Hours', 'Rating']);

    // Prefix values that spreadsheets would treat as formulas
    $safe = fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    foreach ($stmt as $row) {
        fputcsv($out, array_map($safe, [
            ticket_no((int) $row['id']),
            $row['created_at'],
            ROLES[$row['audience']]['portal'],
            $row['category_name'],
            $row['subject'],
            full_name($row, 'requester_'),
            role_label($row['requester_role']),
            $row['location'],
            TICKET_PRIORITIES[$row['priority']][0],
            TICKET_STATUSES[$row['status']][0],
            $row['assigned_to'] ? full_name($row, 'assignee_') : '',
            $row['resolved_at'],
            $row['resolved_at'] ? round((strtotime($row['resolved_at']) - strtotime($row['created_at'])) / 3600, 1) : '',
            $row['rating'],
        ]));
    }
    exit;
}

// ---------- Report data ----------
$summaryStmt = db()->prepare(
    "SELECT COUNT(*) AS submitted,
            SUM(status IN ('resolved','closed')) AS completed,
            SUM(status IN ('open','assigned','in_progress','on_hold')) AS unresolved,
            SUM(assigned_to IS NULL AND status IN ('open','assigned','in_progress','on_hold')) AS unassigned,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) / 60 END) AS avg_hours,
            AVG(rating) AS avg_rating,
            COUNT(rating) AS ratings
     FROM tickets WHERE created_at BETWEEN ? AND ?"
);
$summaryStmt->execute($range);
$summary = $summaryStmt->fetch();
$submitted = (int) $summary['submitted'];
$resolutionRate = $submitted ? round((int) $summary['completed'] / $submitted * 100) : 0;

$grouped = function (string $sql) use ($range): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($range);
    return $stmt->fetchAll();
};

$byArea = array_fill_keys(array_map(fn ($r) => ROLES[$r]['portal'], ['student', 'lab', 'faculty']), 0);
foreach ($grouped('SELECT c.audience, COUNT(*) n FROM tickets t JOIN categories c ON c.id = t.category_id WHERE t.created_at BETWEEN ? AND ? GROUP BY c.audience') as $row) {
    $byArea[ROLES[$row['audience']]['portal']] = (int) $row['n'];
}

$byPriority = [];
$priorityCounts = array_column($grouped('SELECT priority, COUNT(*) n FROM tickets WHERE created_at BETWEEN ? AND ? GROUP BY priority'), 'n', 'priority');
foreach (TICKET_PRIORITIES as $key => [$label]) {
    $byPriority[$label] = (int) ($priorityCounts[$key] ?? 0);
}

$byCategory = $grouped(
    "SELECT c.name, c.audience, COUNT(*) AS total,
            SUM(t.status IN ('resolved','closed')) AS completed,
            SUM(t.status IN ('open','assigned','in_progress','on_hold')) AS unresolved,
            AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) / 60 END) AS avg_hours
     FROM tickets t JOIN categories c ON c.id = t.category_id
     WHERE t.created_at BETWEEN ? AND ?
     GROUP BY c.id ORDER BY total DESC, c.name"
);

$byTechnician = $grouped(
    "SELECT u.first_name, u.last_name, u.department, COUNT(t.id) AS assigned,
            SUM(t.status IN ('resolved','closed')) AS completed,
            SUM(t.status IN ('assigned','in_progress','on_hold')) AS active,
            AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) / 60 END) AS avg_hours,
            AVG(t.rating) AS avg_rating
     FROM users u
     LEFT JOIN tickets t ON t.assigned_to = u.id AND t.created_at BETWEEN ? AND ?
     WHERE u.role = 'technician'
     GROUP BY u.id ORDER BY completed DESC, assigned DESC, u.first_name"
);

$topLocations = $grouped(
    "SELECT t.location, COUNT(*) AS total, SUM(t.status IN ('open','assigned','in_progress','on_hold')) AS unresolved
     FROM tickets t WHERE t.created_at BETWEEN ? AND ? AND t.location IS NOT NULL AND t.location <> ''
     GROUP BY t.location ORDER BY total DESC LIMIT 10"
);

$days = (strtotime($to) - strtotime($from)) / 86400 + 1;
if ($days <= 62) {
    $trend = daily_ticket_counts($from, $to);
} else {
    $monthly = [];
    foreach ($grouped("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) n FROM tickets WHERE created_at BETWEEN ? AND ? GROUP BY m ORDER BY m") as $row) {
        $monthly[date('F Y', strtotime($row['m'] . '-01'))] = (int) $row['n'];
    }
}

$exportUrl = 'reports.php?' . http_build_query(['from' => $from, 'to' => $to, 'export' => 'csv']);

$pageTitle = 'Reports';
$activeNav = 'reports';
include __DIR__ . '/includes/app_top.php';
?>
<div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-900">IT Problems Report</h2>
        <p class="text-slate-500">Tickets submitted <?= e($rangeLabel) ?></p>
    </div>
    <div class="no-print flex flex-wrap items-end gap-3">
        <nav class="flex max-w-full gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
            <?php foreach ($presets as $key => [$label]): ?>
                <a href="reports.php?preset=<?= $key ?>" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium <?= $preset === $key ? 'bg-brand-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="GET" class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:items-end">
            <label class="min-w-0 text-xs text-slate-500">From<input type="date" name="from" value="<?= e($from) ?>" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="min-w-0 text-xs text-slate-500">To<input type="date" name="to" value="<?= e($to) ?>" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
            <button class="col-span-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Apply</button>
        </form>
        <a href="<?= e($exportUrl) ?>" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export CSV</a>
        <button type="button" onclick="window.print()" class="bg-brand-gradient rounded-xl px-4 py-2 text-sm font-semibold text-white shadow">Print</button>
    </div>
</div>

<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
    <?= stat_tile('Tickets Submitted', $submitted) ?>
    <?= stat_tile('Completed', (int) $summary['completed'], $resolutionRate . '% resolution rate', 'text-emerald-600') ?>
    <?= stat_tile('Unresolved', (int) $summary['unresolved'], 'Still open', 'text-amber-600') ?>
    <?= stat_tile('Unassigned', (int) $summary['unassigned'], 'No technician yet', (int) $summary['unassigned'] ? 'text-red-600' : 'text-slate-900') ?>
    <?= stat_tile('Avg. Resolution Time', format_hours($summary['avg_hours'] !== null ? (float) $summary['avg_hours'] : null)) ?>
    <?= stat_tile('Satisfaction', $summary['avg_rating'] !== null ? number_format((float) $summary['avg_rating'], 1) . ' / 5' : '—', (int) $summary['ratings'] . ' rating(s)') ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
        <h3 class="font-semibold text-slate-900"><?= isset($trend) ? 'New tickets per day' : 'New tickets per month' ?></h3>
        <p class="text-xs text-slate-400"><?= e($rangeLabel) ?></p>
        <div class="mt-6"><?= isset($trend) ? column_chart($trend) : bar_chart($monthly, 'No tickets in this period.') ?></div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="font-semibold text-slate-900">By support area</h3>
        <div class="mt-6"><?= bar_chart($byArea, 'No tickets in this period.') ?></div>
        <h3 class="mt-8 font-semibold text-slate-900">By priority</h3>
        <div class="mt-4"><?= bar_chart($byPriority, 'No tickets in this period.') ?></div>
    </section>
</div>

<section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="px-5 py-4"><h3 class="font-semibold text-slate-900">Problems by category</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-semibold">Category</th>
                    <th class="px-5 py-3 font-semibold">Support Area</th>
                    <th class="px-5 py-3 text-right font-semibold">Tickets</th>
                    <th class="px-5 py-3 text-right font-semibold">Completed</th>
                    <th class="px-5 py-3 text-right font-semibold">Unresolved</th>
                    <th class="px-5 py-3 text-right font-semibold">Avg. Resolution</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($byCategory as $row): ?>
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-900"><?= e($row['name']) ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= e(ROLES[$row['audience']]['portal']) ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= (int) $row['total'] ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= (int) $row['completed'] ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= (int) $row['unresolved'] ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= format_hours($row['avg_hours'] !== null ? (float) $row['avg_hours'] : null) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$byCategory): ?>
                    <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No tickets in this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 xl:col-span-2">
        <div class="px-5 py-4"><h3 class="font-semibold text-slate-900">IT personnel performance</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Technician</th>
                        <th class="px-5 py-3 text-right font-semibold">Assigned</th>
                        <th class="px-5 py-3 text-right font-semibold">Completed</th>
                        <th class="px-5 py-3 text-right font-semibold">Active</th>
                        <th class="px-5 py-3 text-right font-semibold">Avg. Resolution</th>
                        <th class="px-5 py-3 text-right font-semibold">Avg. Rating</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($byTechnician as $row): ?>
                        <tr>
                            <td class="px-5 py-3"><span class="block font-medium text-slate-900"><?= e(full_name($row)) ?></span><span class="text-xs text-slate-400"><?= e($row['department'] ?: 'IT Technician') ?></span></td>
                            <td class="px-5 py-3 text-right tabular-nums"><?= (int) $row['assigned'] ?></td>
                            <td class="px-5 py-3 text-right tabular-nums"><?= (int) $row['completed'] ?></td>
                            <td class="px-5 py-3 text-right tabular-nums"><?= (int) $row['active'] ?></td>
                            <td class="px-5 py-3 text-right tabular-nums"><?= format_hours($row['avg_hours'] !== null ? (float) $row['avg_hours'] : null) ?></td>
                            <td class="px-5 py-3 text-right tabular-nums"><?= $row['avg_rating'] !== null ? number_format((float) $row['avg_rating'], 1) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byTechnician): ?>
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No technician accounts yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="font-semibold text-slate-900">Locations with the most problems</h3>
        <p class="text-xs text-slate-400">Labs and rooms reported in tickets</p>
        <ol class="mt-4 divide-y divide-slate-100 text-sm">
            <?php foreach ($topLocations as $i => $row): ?>
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span class="min-w-0 truncate"><span class="mr-2 text-slate-400"><?= $i + 1 ?>.</span><?= e($row['location']) ?></span>
                    <span class="shrink-0 tabular-nums"><span class="font-semibold text-slate-900"><?= (int) $row['total'] ?></span><span class="text-slate-400"> · <?= (int) $row['unresolved'] ?> open</span></span>
                </li>
            <?php endforeach; ?>
            <?php if (!$topLocations): ?>
                <li class="py-8 text-center text-slate-400">No locations reported in this period.</li>
            <?php endif; ?>
        </ol>
    </section>
</div>

<p class="mt-6 hidden text-xs text-slate-400 print:block">Generated <?= date('M j, Y g:i A') ?> by <?= e(full_name($user)) ?> · <?= APP_NAME ?></p>
<?php include __DIR__ . '/includes/app_bottom.php'; ?>
