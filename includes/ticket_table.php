<?php
// Ticket list table. Set $ticketRows (rows from a tickets query joined with category,
// requester_*, and assignee_* columns) and optionally $emptyMessage before including.
$viewerRole = current_user()['role'];
$showRequester = in_array($viewerRole, ['admin', 'technician'], true);
$showAssignee = $viewerRole !== 'technician';
?>
<?php if (!$ticketRows): ?>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        </span>
        <p class="mt-4 font-medium text-slate-900">No tickets here</p>
        <p class="mt-1 text-sm text-slate-500"><?= e($emptyMessage ?? 'Nothing to show yet.') ?></p>
    </div>
<?php else: ?>
    <!-- Phones: one card per ticket, so status and priority are visible without scrolling sideways -->
    <ul class="divide-y divide-slate-100 md:hidden">
        <?php foreach ($ticketRows as $row): ?>
            <li>
                <a href="ticket.php?id=<?= (int) $row['id'] ?>" class="block px-4 py-4 transition hover:bg-brand-50/40">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-slate-900"><?= e($row['subject']) ?></span>
                            <span class="block text-xs text-slate-400"><?= ticket_no((int) $row['id']) ?> · <?= e($row['category_name']) ?></span>
                        </span>
                        <span class="shrink-0 text-xs text-slate-400"><?= time_ago($row['updated_at']) ?></span>
                    </span>
                    <span class="mt-2 flex flex-wrap items-center gap-2">
                        <?= status_badge($row['status']) ?>
                        <?= priority_badge($row['priority']) ?>
                    </span>
                    <?php if ($showRequester || $showAssignee): ?>
                        <span class="mt-2 block text-xs text-slate-500">
                            <?php if ($showRequester): ?>By <?= e(full_name($row, 'requester_')) ?> (<?= e(role_label($row['requester_role'])) ?>)<?php endif; ?>
                            <?php if ($showRequester && $showAssignee): ?> · <?php endif; ?>
                            <?php if ($showAssignee): ?><?= $row['assigned_to'] ? 'Assigned to ' . e(full_name($row, 'assignee_')) : '<span class="italic text-slate-400">Unassigned</span>' ?><?php endif; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-semibold">Ticket</th>
                    <th class="px-5 py-3 font-semibold">Category</th>
                    <?php if ($showRequester): ?><th class="px-5 py-3 font-semibold">Requester</th><?php endif; ?>
                    <?php if ($showAssignee): ?><th class="px-5 py-3 font-semibold">Assigned To</th><?php endif; ?>
                    <th class="px-5 py-3 font-semibold">Priority</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold">Updated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($ticketRows as $row): ?>
                    <tr class="cursor-pointer transition hover:bg-brand-50/40" data-href="ticket.php?id=<?= (int) $row['id'] ?>">
                        <td class="max-w-xs px-5 py-4">
                            <a href="ticket.php?id=<?= (int) $row['id'] ?>" class="block font-semibold text-slate-900 hover:text-brand-700">
                                <span class="block truncate"><?= e($row['subject']) ?></span>
                            </a>
                            <span class="text-xs text-slate-400"><?= ticket_no((int) $row['id']) ?></span>
                        </td>
                        <td class="px-5 py-4 text-slate-600"><?= e($row['category_name']) ?></td>
                        <?php if ($showRequester): ?>
                            <td class="px-5 py-4">
                                <span class="block text-slate-800"><?= e(full_name($row, 'requester_')) ?></span>
                                <span class="text-xs text-slate-400"><?= e(role_label($row['requester_role'])) ?></span>
                            </td>
                        <?php endif; ?>
                        <?php if ($showAssignee): ?>
                            <td class="px-5 py-4 <?= $row['assigned_to'] ? 'text-slate-700' : 'italic text-slate-400' ?>">
                                <?= $row['assigned_to'] ? e(full_name($row, 'assignee_')) : 'Unassigned' ?>
                            </td>
                        <?php endif; ?>
                        <td class="px-5 py-4"><?= priority_badge($row['priority']) ?></td>
                        <td class="px-5 py-4"><?= status_badge($row['status']) ?></td>
                        <td class="whitespace-nowrap px-5 py-4 text-slate-500"><?= time_ago($row['updated_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
