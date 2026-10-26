<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

const PER_PAGE = 15;

$isAdmin = $user['role'] === 'admin';
$views = ['all' => 'All', 'unresolved' => 'Unresolved', 'completed' => 'Completed'];
if ($isAdmin) {
    $views = ['all' => 'All', 'unassigned' => 'Unassigned', 'unresolved' => 'Unresolved', 'completed' => 'Completed'];
}

$filters = [
    'view'     => array_key_exists($_GET['view'] ?? '', $views) ? $_GET['view'] : 'all',
    'q'        => trim($_GET['q'] ?? ''),
    'status'   => array_key_exists($_GET['status'] ?? '', TICKET_STATUSES) ? $_GET['status'] : '',
    'priority' => array_key_exists($_GET['priority'] ?? '', TICKET_PRIORITIES) ? $_GET['priority'] : '',
    'area'     => $isAdmin && in_array($_GET['area'] ?? '', ['student', 'lab', 'faculty'], true) ? $_GET['area'] : '',
    'category' => (int) ($_GET['category'] ?? 0),
];
$page = max(1, (int) ($_GET['page'] ?? 1));

// Each role only sees its own slice of tickets
$where = [];
$params = [];
if ($user['role'] === 'technician') {
    $where[] = 't.assigned_to = ?';
    $params[] = $user['id'];
} elseif (is_requester($user)) {
    $where[] = 't.requester_id = ?';
    $params[] = $user['id'];
}

$in = fn (array $values) => "'" . implode("','", $values) . "'";
match ($filters['view']) {
    'unassigned' => $where[] = 't.assigned_to IS NULL AND t.status IN (' . $in(UNRESOLVED_STATUSES) . ')',
    'unresolved' => $where[] = 't.status IN (' . $in(UNRESOLVED_STATUSES) . ')',
    'completed'  => $where[] = 't.status IN (' . $in(COMPLETED_STATUSES) . ')',
    default      => null,
};

if ($filters['q'] !== '') {
    $ticketId = preg_match('/^(?:TKT-)?0*(\d+)$/i', $filters['q'], $m) ? (int) $m[1] : 0;
    $where[] = "(t.subject LIKE ? OR t.description LIKE ? OR t.id = ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $ticketId, $like);
}
foreach (['status' => 't.status', 'priority' => 't.priority', 'area' => 'c.audience'] as $key => $column) {
    if ($filters[$key] !== '') {
        $where[] = "$column = ?";
        $params[] = $filters[$key];
    }
}
if ($filters['category']) {
    $where[] = 't.category_id = ?';
    $params[] = $filters['category'];
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare('SELECT COUNT(*) FROM tickets t JOIN categories c ON c.id = t.category_id JOIN users r ON r.id = t.requester_id' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = min($page, $pages);

$order = $filters['view'] === 'completed' ? 't.updated_at DESC' : (in_array($filters['view'], ['unassigned', 'unresolved'], true) ? PRIORITY_ORDER_SQL : 't.updated_at DESC');
$stmt = db()->prepare(TICKET_LIST_SQL . $whereSql . " ORDER BY $order LIMIT " . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$stmt->execute($params);
$ticketRows = $stmt->fetchAll();

$categoryOptions = is_requester($user)
    ? categories_for($user['role'])
    : db()->query('SELECT id, name, audience FROM categories ORDER BY audience, sort_order')->fetchAll();

$query = fn (array $changes) => 'tickets.php?' . http_build_query(array_filter(array_merge($filters, ['page' => $page], $changes)));

$pageTitle = match ($user['role']) {
    'admin'      => $filters['view'] === 'unassigned' ? 'Unassigned Tickets' : 'All Tickets',
    'technician' => 'My Assigned Tickets',
    default      => 'My Tickets',
};
$activeNav = $isAdmin && $filters['view'] === 'unassigned' ? 'unassigned' : 'tickets';
include __DIR__ . '/includes/app_top.php';

$selectClass = 'w-full min-w-0 sm:w-auto rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
?>
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <nav class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
        <?php foreach ($views as $key => $label): ?>
            <a href="<?= e($query(['view' => $key, 'page' => null])) ?>"
               class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition <?= $filters['view'] === $key ? 'bg-brand-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (is_requester($user)): ?>
        <a href="ticket_create.php" class="bg-brand-gradient inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Submit a Ticket
        </a>
    <?php endif; ?>
</div>

<form method="GET" action="tickets.php" class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:flex sm:flex-wrap">
    <input type="hidden" name="view" value="<?= e($filters['view']) ?>">
    <div class="relative col-span-2 sm:min-w-[200px] sm:flex-1">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search subject, ticket no., or name"
               class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
    </div>
    <select name="status" class="<?= $selectClass ?>" aria-label="Status">
        <option value="">Any status</option>
        <?php foreach (TICKET_STATUSES as $key => [$label]): ?>
            <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <select name="priority" class="<?= $selectClass ?>" aria-label="Priority">
        <option value="">Any priority</option>
        <?php foreach (TICKET_PRIORITIES as $key => [$label]): ?>
            <option value="<?= $key ?>" <?= $filters['priority'] === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($isAdmin): ?>
        <select name="area" class="<?= $selectClass ?>" aria-label="Support area">
            <option value="">All support areas</option>
            <?php foreach (['student', 'lab', 'faculty'] as $area): ?>
                <option value="<?= $area ?>" <?= $filters['area'] === $area ? 'selected' : '' ?>><?= e(ROLES[$area]['portal']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select name="category" class="<?= $selectClass ?>" aria-label="Category">
        <option value="">All categories</option>
        <?php foreach ($categoryOptions as $category): ?>
            <option value="<?= (int) $category['id'] ?>" <?= $filters['category'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="col-span-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Filter</button>
    <?php if ($filters['q'] !== '' || $filters['status'] || $filters['priority'] || $filters['area'] || $filters['category']): ?>
        <a href="tickets.php?view=<?= e($filters['view']) ?>" class="col-span-2 self-center text-center text-sm text-slate-500 hover:text-brand-700">Clear</a>
    <?php endif; ?>
</form>

<section class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <?php $emptyMessage = is_requester($user) ? 'Tickets you submit will appear here.' : 'No tickets match these filters.'; include __DIR__ . '/includes/ticket_table.php'; ?>

    <?php if ($total > 0): ?>
        <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-500 sm:flex-row">
            <p>Showing <?= ($page - 1) * PER_PAGE + 1 ?>–<?= min($page * PER_PAGE, $total) ?> of <?= $total ?> tickets</p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= e($query(['page' => $page - 1])) ?>" class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">&larr; Previous</a>
                <?php endif; ?>
                <span class="px-2 py-1.5">Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a href="<?= e($query(['page' => $page + 1])) ?>" class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/app_bottom.php'; ?>
