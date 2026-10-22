<?php
// Dashboard for Student, Computer Lab, and Faculty & Staff accounts
$intro = match ($user['role']) {
    'lab'     => 'Report broken computers, internet or printer problems, and request software installation for your laboratory.',
    'faculty' => 'Get technical support for online classes and school systems, and request email, accounts, or equipment.',
    default   => 'Report student portal problems and login or account issues, or request technical assistance.',
};

$stmt = db()->prepare('SELECT status, COUNT(*) FROM tickets WHERE requester_id = ? GROUP BY status');
$stmt->execute([$user['id']]);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$countOf = fn (array $statuses) => array_sum(array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses));

$stmt = db()->prepare(TICKET_LIST_SQL . ' WHERE t.requester_id = ? ORDER BY t.updated_at DESC LIMIT 6');
$stmt->execute([$user['id']]);
$ticketRows = $stmt->fetchAll();

$stmt = db()->prepare(TICKET_LIST_SQL . " WHERE t.requester_id = ? AND t.status = 'resolved' ORDER BY t.resolved_at DESC");
$stmt->execute([$user['id']]);
$awaitingConfirmation = $stmt->fetchAll();
?>
<section class="bg-brand-gradient relative overflow-hidden rounded-3xl p-6 text-white shadow-xl shadow-brand-200 sm:p-8">
    <div class="grid-pattern absolute inset-0"></div>
    <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-wider text-sky-200"><?= e(ROLES[$user['role']]['portal']) ?></p>
            <h2 class="mt-1 text-2xl font-bold sm:text-3xl">Hello, <?= e($user['first_name']) ?>!</h2>
            <p class="mt-2 text-blue-100"><?= e($intro) ?></p>
        </div>
        <a href="ticket_create.php" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-white px-6 py-3 font-semibold text-brand-800 shadow-lg transition hover:bg-sky-50">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Submit a Ticket
        </a>
    </div>
</section>

<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= stat_tile('Open', $countOf(['open', 'assigned']), 'Waiting for a technician', 'text-sky-700') ?>
    <?= stat_tile('In Progress', $countOf(['in_progress', 'on_hold']), 'Being worked on', 'text-amber-600') ?>
    <?= stat_tile('Resolved', $countOf(COMPLETED_STATUSES), 'Fixed or closed', 'text-emerald-600') ?>
    <?= stat_tile('Total Tickets', array_sum($counts), 'All time') ?>
</div>

<section class="mt-8">
    <h3 class="text-lg font-semibold text-slate-900">What do you need help with?</h3>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach (categories_for($user['role']) as $category): ?>
            <a href="ticket_create.php?category=<?= (int) $category['id'] ?>" class="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-brand-300">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">
                    <?= category_icon($category['slug']) ?>
                </span>
                <p class="mt-4 font-semibold text-slate-900"><?= e($category['name']) ?></p>
                <p class="mt-1 text-sm text-slate-500"><?= e($category['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($awaitingConfirmation): ?>
    <section class="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
        <h3 class="font-semibold text-emerald-800">Please confirm these fixes</h3>
        <p class="mt-1 text-sm text-emerald-700">IT marked these tickets as resolved. Open each one to confirm and rate the support, or reopen it if the problem is still there.</p>
        <ul class="mt-4 space-y-2">
            <?php foreach ($awaitingConfirmation as $row): ?>
                <li>
                    <a href="ticket.php?id=<?= (int) $row['id'] ?>" class="flex items-center justify-between gap-4 rounded-xl bg-white px-4 py-3 text-sm shadow-sm transition hover:shadow-md">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-slate-900"><?= e($row['subject']) ?></span>
                            <span class="text-xs text-slate-400"><?= ticket_no((int) $row['id']) ?> · resolved <?= time_ago($row['resolved_at']) ?></span>
                        </span>
                        <span class="shrink-0 font-semibold text-emerald-700">Review &rarr;</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center justify-between px-5 py-4">
        <h3 class="font-semibold text-slate-900">Recent Tickets</h3>
        <a href="tickets.php" class="text-sm font-medium text-brand-600 hover:text-brand-800">View all &rarr;</a>
    </div>
    <?php $emptyMessage = "You haven't submitted any tickets yet."; include __DIR__ . '/../ticket_table.php'; ?>
</section>
