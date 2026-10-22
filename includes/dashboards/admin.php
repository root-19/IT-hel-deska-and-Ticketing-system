<?php
// IT Admin dashboard: every ticket, assignment backlog, workload, and trends
$stats = db()->query(
    "SELECT
        COUNT(*)                                              AS total,
        SUM(assigned_to IS NULL AND status IN ('open','assigned','in_progress','on_hold')) AS unassigned,
        SUM(status IN ('open','assigned','in_progress','on_hold')) AS unresolved,
        SUM(status IN ('resolved','closed'))                  AS completed,
        SUM(priority = 'urgent' AND status IN ('open','assigned','in_progress','on_hold')) AS urgent_open,
        AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) / 60 END) AS avg_hours
     FROM tickets"
)->fetch();

$byArea = array_fill_keys(['Student Support', 'Computer Laboratory Support', 'Faculty & Staff Support'], 0);
foreach (db()->query('SELECT c.audience, COUNT(*) n FROM tickets t JOIN categories c ON c.id = t.category_id GROUP BY c.audience') as $row) {
    $byArea[ROLES[$row['audience']]['portal']] = (int) $row['n'];
}

$byStatus = [];
$statusCounts = db()->query('SELECT status, COUNT(*) FROM tickets GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (TICKET_STATUSES as $key => [$label]) {
    $byStatus[$label] = (int) ($statusCounts[$key] ?? 0);
}

$trend = daily_ticket_counts(date('Y-m-d', strtotime('-29 days')), date('Y-m-d'));

$unassigned = db()->query(
    TICKET_LIST_SQL . " WHERE t.assigned_to IS NULL AND t.status IN ('open','assigned','in_progress','on_hold') ORDER BY " . PRIORITY_ORDER_SQL . ' LIMIT 8'
)->fetchAll();

$workload = db()->query(
    "SELECT u.id, u.first_name, u.last_name, u.department,
            SUM(t.status IN ('assigned','in_progress','on_hold')) AS active,
            SUM(t.status IN ('resolved','closed'))                AS resolved,
            AVG(t.rating)                                         AS avg_rating
     FROM users u
     LEFT JOIN tickets t ON t.assigned_to = u.id
     WHERE u.role = 'technician' AND u.status = 'active'
     GROUP BY u.id
     ORDER BY active DESC, u.first_name"
)->fetchAll();
?>
<section class="bg-brand-gradient relative overflow-hidden rounded-3xl p-6 text-white shadow-xl shadow-brand-200 sm:p-8">
    <div class="grid-pattern absolute inset-0"></div>
    <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-sky-200">IT Admin Dashboard</p>
            <h2 class="mt-1 text-2xl font-bold sm:text-3xl">Welcome, <?= e($user['first_name']) ?>!</h2>
            <p class="mt-2 text-blue-100">
                <?= (int) $stats['unassigned'] ? (int) $stats['unassigned'] . ' ticket(s) need an IT technician assigned.' : 'Every open ticket has a technician assigned.' ?>
                <?= (int) $stats['urgent_open'] ? ' ' . (int) $stats['urgent_open'] . ' urgent ticket(s) still open.' : '' ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="tickets.php?view=unassigned" class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-brand-800 shadow-lg transition hover:bg-sky-50">Assign Tickets</a>
            <a href="users.php?action=create" class="rounded-full border border-white/40 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">Create Account</a>
            <a href="reports.php" class="rounded-full border border-white/40 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">Reports</a>
        </div>
    </div>
</section>

<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
    <?= stat_tile('Total Tickets', (int) $stats['total'], 'All time') ?>
    <?= stat_tile('Unassigned', (int) $stats['unassigned'], 'Need a technician', (int) $stats['unassigned'] ? 'text-red-600' : 'text-slate-900') ?>
    <?= stat_tile('Unresolved', (int) $stats['unresolved'], 'Open, assigned, in progress, on hold', 'text-amber-600') ?>
    <?= stat_tile('Completed', (int) $stats['completed'], 'Resolved or closed', 'text-emerald-600') ?>
    <?= stat_tile('Avg. Resolution Time', format_hours($stats['avg_hours'] !== null ? (float) $stats['avg_hours'] : null), 'From submission to resolved') ?>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-3">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
        <h3 class="font-semibold text-slate-900">New tickets per day</h3>
        <p class="text-xs text-slate-400">Last 30 days</p>
        <div class="mt-6"><?= column_chart($trend) ?></div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="font-semibold text-slate-900">Tickets by support area</h3>
        <p class="text-xs text-slate-400">All time</p>
        <div class="mt-6"><?= bar_chart($byArea, 'No tickets submitted yet.') ?></div>
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 xl:col-span-2">
        <div class="flex items-center justify-between px-5 py-4">
            <h3 class="font-semibold text-slate-900">Unassigned Tickets</h3>
            <a href="tickets.php?view=unassigned" class="text-sm font-medium text-brand-600 hover:text-brand-800">View all &rarr;</a>
        </div>
        <?php $ticketRows = $unassigned; $emptyMessage = 'Every open ticket has been assigned.'; include __DIR__ . '/../ticket_table.php'; ?>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="font-semibold text-slate-900">Tickets by status</h3>
        <p class="text-xs text-slate-400">All time</p>
        <div class="mt-6"><?= bar_chart($byStatus, 'No tickets submitted yet.') ?></div>
    </section>
</div>

<section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center justify-between px-5 py-4">
        <h3 class="font-semibold text-slate-900">IT Personnel Workload</h3>
        <a href="users.php?role=technician" class="text-sm font-medium text-brand-600 hover:text-brand-800">Manage technicians &rarr;</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-semibold">Technician</th>
                    <th class="px-5 py-3 text-right font-semibold">Active</th>
                    <th class="px-5 py-3 text-right font-semibold">Resolved</th>
                    <th class="px-5 py-3 text-right font-semibold">Avg. Rating</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($workload as $tech): ?>
                    <tr>
                        <td class="px-5 py-3">
                            <span class="block font-medium text-slate-900"><?= e(full_name($tech)) ?></span>
                            <span class="text-xs text-slate-400"><?= e($tech['department'] ?: 'IT Technician') ?></span>
                        </td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= (int) $tech['active'] ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= (int) $tech['resolved'] ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?= $tech['avg_rating'] !== null ? number_format((float) $tech['avg_rating'], 1) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workload): ?>
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">No technician accounts yet. <a href="users.php?action=create&role=technician" class="font-medium text-brand-600">Create one</a>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
