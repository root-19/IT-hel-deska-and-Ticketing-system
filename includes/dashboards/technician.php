<?php
// Dashboard for IT Technicians: their assigned work queue
$stmt = db()->prepare(
    "SELECT
        SUM(status = 'assigned')                                   AS new_assigned,
        SUM(status = 'in_progress')                                AS in_progress,
        SUM(status = 'on_hold')                                    AS on_hold,
        SUM(status IN ('resolved','closed') AND resolved_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS resolved_month,
        AVG(rating)                                                AS avg_rating
     FROM tickets WHERE assigned_to = ?"
);
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$stmt = db()->prepare(TICKET_LIST_SQL . " WHERE t.assigned_to = ? AND t.status IN ('assigned','in_progress','on_hold') ORDER BY " . PRIORITY_ORDER_SQL);
$stmt->execute([$user['id']]);
$queue = $stmt->fetchAll();

$stmt = db()->prepare(TICKET_LIST_SQL . " WHERE t.assigned_to = ? AND t.status IN ('resolved','closed') ORDER BY t.resolved_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recentlyResolved = $stmt->fetchAll();
?>
<section class="bg-brand-gradient relative overflow-hidden rounded-3xl p-6 text-white shadow-xl shadow-brand-200 sm:p-8">
    <div class="grid-pattern absolute inset-0"></div>
    <div class="relative">
        <p class="text-sm font-semibold uppercase tracking-wider text-sky-200">IT Technician Workspace</p>
        <h2 class="mt-1 text-2xl font-bold sm:text-3xl">Good day, <?= e($user['first_name']) ?>!</h2>
        <p class="mt-2 text-blue-100">
            <?= count($queue) ? 'You have ' . count($queue) . ' active ticket' . (count($queue) === 1 ? '' : 's') . ' in your queue. Urgent ones are listed first.' : 'Your queue is clear. New assignments will show up here.' ?>
        </p>
    </div>
</section>

<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
    <?= stat_tile('New Assignments', (int) $stats['new_assigned'], 'Not started yet', 'text-indigo-700') ?>
    <?= stat_tile('In Progress', (int) $stats['in_progress'], 'Currently working on', 'text-amber-600') ?>
    <?= stat_tile('On Hold', (int) $stats['on_hold'], 'Waiting on parts or info') ?>
    <?= stat_tile('Resolved This Month', (int) $stats['resolved_month'], date('F Y'), 'text-emerald-600') ?>
    <?= stat_tile('Average Rating', $stats['avg_rating'] !== null ? number_format((float) $stats['avg_rating'], 1) . ' / 5' : '—', 'From requester feedback') ?>
</div>

<section class="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center justify-between px-5 py-4">
        <h3 class="font-semibold text-slate-900">My Queue</h3>
        <a href="tickets.php" class="text-sm font-medium text-brand-600 hover:text-brand-800">All my tickets &rarr;</a>
    </div>
    <?php $ticketRows = $queue; $emptyMessage = 'No active tickets assigned to you.'; include __DIR__ . '/../ticket_table.php'; ?>
</section>

<section class="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="px-5 py-4"><h3 class="font-semibold text-slate-900">Recently Resolved</h3></div>
    <?php $ticketRows = $recentlyResolved; $emptyMessage = "Tickets you resolve will appear here."; include __DIR__ . '/../ticket_table.php'; ?>
</section>
