<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
$user = require_login();

$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = $ticketId ? find_ticket($ticketId) : null;
if (!$ticket || !can_view_ticket($user, $ticket)) {
    flash('error', 'Ticket not found, or you do not have access to it.');
    redirect('tickets.php');
}

$isAdmin = $user['role'] === 'admin';
$isAssignedTech = $user['role'] === 'technician' && (int) $ticket['assigned_to'] === (int) $user['id'];
$isRequester = (int) $ticket['requester_id'] === (int) $user['id'];
$isStaff = $isAdmin || $isAssignedTech;
$isClosed = $ticket['status'] === 'closed';
$statusLabel = fn (string $status) => TICKET_STATUSES[$status][0] ?? $status;

// Statuses each role may move a ticket to
$staffStatuses = $isAdmin ? ['open', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed'] : ['in_progress', 'on_hold', 'resolved'];

$technicians = $isAdmin
    ? db()->query("SELECT u.id, u.first_name, u.last_name, u.department,
                          (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.status IN ('assigned','in_progress','on_hold')) AS active
                   FROM users u WHERE u.role = 'technician' AND u.status = 'active' ORDER BY u.first_name")->fetchAll()
    : [];

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ticketNo = ticket_no($ticketId);
    $pdo = db();
    $error = null;
    $success = null;

    if (!verify_csrf()) {
        $error = 'Your session has expired. Please try again.';
    } elseif ($action === 'comment') {
        $body = trim($_POST['body'] ?? '');
        $internal = $isStaff && !empty($_POST['internal']);
        if ($isClosed) {
            $error = 'This ticket is closed.';
        } elseif (mb_strlen($body) < 2 || mb_strlen($body) > 3000) {
            $error = 'Comments must be 2–3000 characters.';
        } else {
            $pdo->beginTransaction();
            log_activity($ticketId, $user['id'], $internal ? 'internal_note' : 'comment', $body);
            $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?')->execute([$ticketId]);
            if (!$internal) {
                notify([$ticket['requester_id'], $ticket['assigned_to']], $ticketId, full_name($user) . " commented on $ticketNo");
            }
            $pdo->commit();
            $success = $internal ? 'Internal note added.' : 'Comment posted.';
        }
    } elseif ($action === 'assign' && $isAdmin) {
        $techId = (int) ($_POST['technician_id'] ?? 0);
        $tech = null;
        foreach ($technicians as $candidate) {
            if ((int) $candidate['id'] === $techId) {
                $tech = $candidate;
            }
        }
        if (!$tech) {
            $error = 'Please choose an active IT technician.';
        } elseif ($isClosed) {
            $error = 'Reopen the ticket before reassigning it.';
        } elseif ($techId === (int) $ticket['assigned_to']) {
            $error = full_name($tech) . ' is already assigned to this ticket.';
        } else {
            $newStatus = $ticket['status'] === 'open' ? 'assigned' : $ticket['status'];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE tickets SET assigned_to = ?, assigned_at = NOW(), status = ? WHERE id = ?')
                ->execute([$techId, $newStatus, $ticketId]);
            log_activity($ticketId, $user['id'], 'assignment', ($ticket['assigned_to'] ? 'Reassigned' : 'Assigned') . ' to ' . full_name($tech) . '.');
            notify([$techId], $ticketId, "You were assigned to $ticketNo: {$ticket['subject']}");
            notify([$ticket['requester_id']], $ticketId, full_name($tech) . " is now handling your ticket $ticketNo");
            if ($ticket['assigned_to']) {
                notify([$ticket['assigned_to']], $ticketId, "$ticketNo was reassigned to " . full_name($tech));
            }
            $pdo->commit();
            $success = 'Ticket assigned to ' . full_name($tech) . '.';
        }
    } elseif ($action === 'status' && $isStaff) {
        $newStatus = $_POST['status'] ?? '';
        $note = trim($_POST['note'] ?? '');
        if (!in_array($newStatus, $staffStatuses, true) || $newStatus === $ticket['status']) {
            $error = 'Please choose a different status.';
        } elseif ($newStatus === 'resolved' && $note === '') {
            $error = 'Please add a short note explaining how the issue was resolved.';
        } elseif ($isClosed && !$isAdmin) {
            $error = 'Only the IT Admin can reopen a closed ticket.';
        } elseif ($newStatus === 'assigned' && !$ticket['assigned_to']) {
            $error = 'Assign a technician first.';
        } elseif (mb_strlen($note) > 3000) {
            $error = 'The note must be 3000 characters or less.';
        } else {
            $resolvedAt = match (true) {
                $newStatus === 'resolved'                       => 'NOW()',
                in_array($newStatus, UNRESOLVED_STATUSES, true) => 'NULL',
                default                                         => 'resolved_at', // closing keeps the resolve time
            };
            $closedAt = $newStatus === 'closed' ? 'NOW()' : 'NULL';

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tickets SET status = ?, resolved_at = $resolvedAt, closed_at = $closedAt WHERE id = ?")
                ->execute([$newStatus, $ticketId]);
            $message = 'Changed status from ' . $statusLabel($ticket['status']) . ' to ' . $statusLabel($newStatus) . '.';
            log_activity($ticketId, $user['id'], 'status', $note !== '' ? "$message\n\n$note" : $message);
            $notice = match ($newStatus) {
                'resolved' => "Your ticket $ticketNo was resolved. Please confirm the fix.",
                'closed'   => "Your ticket $ticketNo was closed.",
                default    => "Your ticket $ticketNo is now " . $statusLabel($newStatus) . '.',
            };
            notify([$ticket['requester_id']], $ticketId, $notice);
            if ($isAdmin) {
                notify([$ticket['assigned_to']], $ticketId, "Admin set $ticketNo to " . $statusLabel($newStatus));
            }
            $pdo->commit();
            $success = 'Status updated to ' . $statusLabel($newStatus) . '.';
            if ($newStatus === 'resolved') {
                $success .= send_ticket_resolved_email($ticket, $note, $user)
                    ? ' The requester was notified by email.'
                    : " The requester couldn't be emailed, but they'll see it in their notifications.";
            }
        }
    } elseif ($action === 'priority' && $isStaff) {
        $priority = $_POST['priority'] ?? '';
        if (!array_key_exists($priority, TICKET_PRIORITIES) || $priority === $ticket['priority'] || $isClosed) {
            $error = 'Please choose a different priority.';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE tickets SET priority = ? WHERE id = ?')->execute([$priority, $ticketId]);
            log_activity($ticketId, $user['id'], 'priority', 'Changed priority from ' . TICKET_PRIORITIES[$ticket['priority']][0] . ' to ' . TICKET_PRIORITIES[$priority][0] . '.');
            $pdo->commit();
            $success = 'Priority updated.';
        }
    } elseif ($action === 'confirm' && $isRequester && $ticket['status'] === 'resolved') {
        $rating = (int) ($_POST['rating'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $error = 'Please rate the support from 1 to 5 stars.';
        } elseif (mb_strlen($feedback) > 500) {
            $error = 'Feedback must be 500 characters or less.';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tickets SET status = 'closed', closed_at = NOW(), rating = ?, feedback = ? WHERE id = ?")
                ->execute([$rating, $feedback !== '' ? $feedback : null, $ticketId]);
            log_activity($ticketId, $user['id'], 'feedback', "Confirmed the fix and rated the support $rating/5." . ($feedback !== '' ? "\n\n$feedback" : ''));
            notify(array_merge([$ticket['assigned_to']], admin_ids()), $ticketId, full_name($user) . " confirmed $ticketNo is fixed ($rating/5)");
            $pdo->commit();
            $success = 'Thanks for your feedback! This ticket is now closed.';
        }
    } elseif ($action === 'reopen' && $isRequester && $ticket['status'] === 'resolved') {
        $reason = trim($_POST['reason'] ?? '');
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 3000) {
            $error = "Please tell us what's still wrong (at least 5 characters).";
        } else {
            $newStatus = $ticket['assigned_to'] ? 'in_progress' : 'open';
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE tickets SET status = ?, resolved_at = NULL WHERE id = ?')->execute([$newStatus, $ticketId]);
            log_activity($ticketId, $user['id'], 'status', "Reopened the ticket.\n\n$reason");
            notify(array_merge([$ticket['assigned_to']], admin_ids()), $ticketId, full_name($user) . " reopened $ticketNo");
            $pdo->commit();
            $success = 'Ticket reopened. The IT team has been notified.';
        }
    } else {
        $error = "That action isn't available for this ticket.";
    }

    flash($error ? 'error' : 'success', $error ?? $success);
    redirect('ticket.php?id=' . $ticketId);
}

// ---------- Timeline ----------
$stmt = db()->prepare(
    'SELECT a.*, u.first_name, u.last_name, u.role
     FROM ticket_activity a JOIN users u ON u.id = a.user_id
     WHERE a.ticket_id = ?' . ($isStaff ? '' : " AND a.type <> 'internal_note'") . '
     ORDER BY a.created_at, a.id'
);
$stmt->execute([$ticketId]);
$activity = $stmt->fetchAll();

$pageTitle = ticket_no($ticketId);
$activeNav = 'tickets';
include __DIR__ . '/includes/app_top.php';

$inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
$buttonClass = 'bg-brand-gradient w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:opacity-95';
?>
<a href="tickets.php" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-700">&larr; Back to tickets</a>

<div class="mt-4 grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <!-- Ticket -->
        <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-brand-700"><?= ticket_no($ticketId) ?></span>
                <?= status_badge($ticket['status']) ?>
                <?= priority_badge($ticket['priority']) ?>
            </div>
            <h2 class="mt-3 text-xl font-bold text-slate-900 sm:text-2xl"><?= e($ticket['subject']) ?></h2>
            <p class="mt-1 text-sm text-slate-500">
                <?= e($ticket['category_name']) ?> · <?= e(ROLES[$ticket['audience']]['portal']) ?> · submitted <?= time_ago($ticket['created_at']) ?>
            </p>
            <div class="mt-5 whitespace-pre-line break-words rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700"><?= e($ticket['description']) ?></div>
        </article>

        <!-- Timeline -->
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-semibold text-slate-900">Activity</h3>
            <ol class="mt-5 space-y-5">
                <?php foreach ($activity as $item):
                    $isNote = $item['type'] === 'internal_note';
                    $isMessage = in_array($item['type'], ['comment', 'internal_note', 'feedback'], true);
                    $typeLabel = match ($item['type']) {
                        'created'       => 'created the ticket',
                        'comment'       => 'commented',
                        'internal_note' => 'added an internal note',
                        'status'        => 'updated the status',
                        'assignment'    => 'updated the assignment',
                        'priority'      => 'changed the priority',
                        'feedback'      => 'left feedback',
                        default         => $item['type'],
                    }; ?>
                    <li class="flex gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold <?= in_array($item['role'], ['admin', 'technician'], true) ? 'bg-brand-gradient text-white' : 'bg-slate-200 text-slate-700' ?>">
                            <?= e(initials(full_name($item))) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <span class="font-semibold text-slate-900"><?= e(full_name($item)) ?></span>
                                <span class="text-xs text-slate-400">(<?= e(role_label($item['role'])) ?>)</span>
                                <span class="text-slate-500"><?= $typeLabel ?></span>
                                <span class="text-xs text-slate-400">· <?= time_ago($item['created_at']) ?></span>
                            </p>
                            <?php if ($item['body'] !== null && $item['body'] !== ''): ?>
                                <div class="mt-1.5 whitespace-pre-line break-words text-sm leading-relaxed <?= $isMessage ? 'rounded-xl px-4 py-3 ' . ($isNote ? 'bg-amber-50 text-amber-900 ring-1 ring-amber-200' : 'bg-slate-50 text-slate-700') : 'text-slate-600' ?>">
                                    <?php if ($isNote): ?><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-amber-700">Internal · hidden from requester</span><?php endif; ?><?= e($item['body']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if (!$isClosed): ?>
                <form method="POST" data-loading class="mt-6 border-t border-slate-100 pt-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="comment">
                    <label for="body" class="mb-1.5 block text-sm font-medium text-slate-700">Add a comment</label>
                    <textarea id="body" name="body" rows="3" required maxlength="3000" class="<?= $inputClass ?>"
                              placeholder="<?= $isStaff ? 'Update the requester or leave an internal note…' : 'Add more details or ask for an update…' ?>"></textarea>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <?php if ($isStaff): ?>
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="internal" value="1" class="h-4 w-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                                Internal note (only IT staff can see it)
                            </label>
                        <?php else: ?><span></span><?php endif; ?>
                        <button class="bg-brand-gradient rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:opacity-95">Post</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="mt-6 border-t border-slate-100 pt-6 text-center text-sm text-slate-400">This ticket is closed. Submit a new ticket if you need more help.</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-semibold text-slate-900">Details</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Requester</dt><dd class="text-right font-medium text-slate-900"><?= e(full_name($ticket, 'requester_')) ?><span class="block text-xs font-normal text-slate-400"><?= e(role_label($ticket['requester_role'])) ?><?= $ticket['requester_department'] ? ' · ' . e($ticket['requester_department']) : '' ?></span></dd></div>
                <?php if ($isStaff): ?>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Email</dt><dd class="break-all text-right text-slate-700"><?= e($ticket['requester_email']) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Category</dt><dd class="text-right text-slate-700"><?= e($ticket['category_name']) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Location</dt><dd class="text-right text-slate-700"><?= e($ticket['location'] ?: '—') ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Assigned to</dt><dd class="text-right <?= $ticket['assigned_to'] ? 'font-medium text-slate-900' : 'italic text-slate-400' ?>"><?= $ticket['assigned_to'] ? e(full_name($ticket, 'assignee_')) : 'Not yet assigned' ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Submitted</dt><dd class="text-right text-slate-700"><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></dd></div>
                <?php if ($ticket['resolved_at']): ?>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Resolved</dt><dd class="text-right text-slate-700"><?= date('M j, Y g:i A', strtotime($ticket['resolved_at'])) ?></dd></div>
                <?php endif; ?>
                <?php if ($ticket['rating']): ?>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Rating</dt><dd class="text-right text-amber-500" aria-label="<?= (int) $ticket['rating'] ?> out of 5"><?= str_repeat('★', (int) $ticket['rating']) ?><span class="text-slate-300"><?= str_repeat('★', 5 - (int) $ticket['rating']) ?></span></dd></div>
                <?php endif; ?>
            </dl>
        </section>

        <?php if ($isAdmin && !$isClosed): ?>
            <form method="POST" data-loading class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign">
                <h3 class="font-semibold text-slate-900"><?= $ticket['assigned_to'] ? 'Reassign' : 'Assign' ?> IT Personnel</h3>
                <select name="technician_id" required class="<?= $inputClass ?> mt-3">
                    <option value="">Choose a technician…</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?= (int) $tech['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $tech['id'] ? 'selected' : '' ?>>
                            <?= e(full_name($tech)) ?> — <?= (int) $tech['active'] ?> active<?= $tech['department'] ? ' · ' . e($tech['department']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$technicians): ?>
                    <p class="mt-2 text-xs text-red-600">No technician accounts yet. <a href="users.php?action=create&role=technician" class="font-medium underline">Create one</a>.</p>
                <?php endif; ?>
                <button class="<?= $buttonClass ?> mt-3">Assign</button>
            </form>
        <?php endif; ?>

        <?php if ($isStaff && ($isAdmin || !$isClosed)): ?>
            <form method="POST" data-loading class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <h3 class="font-semibold text-slate-900"><?= $isClosed ? 'Reopen Ticket' : 'Update Status' ?></h3>
                <select name="status" required class="<?= $inputClass ?> mt-3">
                    <?php foreach ($staffStatuses as $status): ?>
                        <option value="<?= $status ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= $statusLabel($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="note" rows="3" maxlength="3000" class="<?= $inputClass ?> mt-3" placeholder="Note for the requester (required when resolving)"></textarea>
                <button class="<?= $buttonClass ?> mt-3">Update Status</button>
            </form>

            <?php if (!$isClosed): ?>
                <form method="POST" data-loading class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="priority">
                    <h3 class="font-semibold text-slate-900">Priority</h3>
                    <div class="mt-3 flex gap-2">
                        <select name="priority" class="<?= $inputClass ?>">
                            <?php foreach (TICKET_PRIORITIES as $key => [$label]): ?>
                                <option value="<?= $key ?>" <?= $ticket['priority'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="shrink-0 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-700">Save</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($isRequester && $ticket['status'] === 'resolved'): ?>
            <form method="POST" data-loading class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm">
                <h3 class="font-semibold text-emerald-800">Is the problem fixed?</h3>
                <p class="mt-1 text-sm text-emerald-700">Confirm and rate the support you received.</p>
                <div class="star-rating mt-4 flex flex-row-reverse justify-end gap-1" role="radiogroup" aria-label="Rating">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <input type="radio" id="star<?= $star ?>" name="rating" value="<?= $star ?>" class="peer sr-only" required>
                        <label for="star<?= $star ?>" class="cursor-pointer text-3xl text-slate-300 transition" title="<?= $star ?> star<?= $star > 1 ? 's' : '' ?>">★</label>
                    <?php endfor; ?>
                </div>
                <textarea name="feedback" rows="2" maxlength="500" class="<?= $inputClass ?> mt-3" placeholder="Feedback (optional)"></textarea>
                <button class="mt-3 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-emerald-700">Confirm &amp; Close Ticket</button>
            </form>

            <form method="POST" data-loading class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reopen">
                <h3 class="font-semibold text-slate-900">Still not working?</h3>
                <textarea name="reason" rows="2" required maxlength="3000" class="<?= $inputClass ?> mt-3" placeholder="Tell us what's still wrong"></textarea>
                <button class="mt-3 w-full rounded-xl border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">Reopen Ticket</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/app_bottom.php'; ?>
