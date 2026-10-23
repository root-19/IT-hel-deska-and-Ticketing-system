<?php
// Logged-in layout: sidebar + top bar. Set $pageTitle and $activeNav before including,
// and include app_bottom.php at the end of the page.
$user = current_user();
$flash = get_flash();

$navIcons = [
    'dashboard' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    'create'    => 'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z',
    'tickets'   => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
    'unassigned'=> 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    'users'     => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    'reports'   => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    'profile'   => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
];

$nav = match ($user['role']) {
    'admin' => [
        'dashboard'  => ['dashboard.php', 'Dashboard'],
        'tickets'    => ['tickets.php', 'All Tickets'],
        'unassigned' => ['tickets.php?view=unassigned', 'Unassigned'],
        'users'      => ['users.php', 'Accounts'],
        'reports'    => ['reports.php', 'Reports'],
        'profile'    => ['profile.php', 'My Profile'],
    ],
    'technician' => [
        'dashboard' => ['dashboard.php', 'Dashboard'],
        'tickets'   => ['tickets.php', 'My Assigned Tickets'],
        'profile'   => ['profile.php', 'My Profile'],
    ],
    default => [
        'dashboard' => ['dashboard.php', 'Dashboard'],
        'create'    => ['ticket_create.php', 'Submit a Ticket'],
        'tickets'   => ['tickets.php', 'My Tickets'],
        'profile'   => ['profile.php', 'My Profile'],
    ],
};

// Badge counts in the sidebar
$navBadges = [];
if ($user['role'] === 'admin') {
    $navBadges['unassigned'] = (int) db()->query("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status IN ('open','assigned','in_progress','on_hold')")->fetchColumn();
} elseif ($user['role'] === 'technician') {
    $stmt = db()->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status IN ('assigned','in_progress','on_hold')");
    $stmt->execute([$user['id']]);
    $navBadges['tickets'] = (int) $stmt->fetchColumn();
}

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();
$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$user['id']]);
$unreadCount = (int) $stmt->fetchColumn();

include __DIR__ . '/head.php';
?>
<body class="min-h-screen bg-slate-100 font-sans text-slate-700 antialiased">

<!-- Sidebar -->
<aside id="sidebar" class="bg-brand-gradient fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col transition-transform duration-200 lg:translate-x-0">
    <a href="dashboard.php" class="flex items-center gap-3 px-6 py-5">
        <?= logo('h-10 w-10') ?>
        <div class="leading-tight">
            <span class="block text-xs font-semibold uppercase tracking-wider text-sky-200">ICCT Colleges</span>
            <span class="block font-bold text-white">IT Help Desk</span>
        </div>
    </a>

    <div class="mx-4 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-sky-200">Portal</p>
        <p class="text-sm font-semibold text-white"><?= e(ROLES[$user['role']]['portal']) ?></p>
    </div>

    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto px-3">
        <?php foreach ($nav as $key => [$href, $label]):
            $active = ($activeNav ?? '') === $key; ?>
            <a href="<?= $href ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition <?= $active ? 'bg-white text-brand-800 shadow-lg' : 'text-blue-100 hover:bg-white/10 hover:text-white' ?>">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $navIcons[$key] ?>"/></svg>
                <span class="flex-1"><?= e($label) ?></span>
                <?php if (!empty($navBadges[$key])): ?>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?= $active ? 'bg-brand-100 text-brand-700' : 'bg-white/20 text-white' ?>"><?= $navBadges[$key] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="border-t border-white/10 p-4">
        <form method="POST" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-blue-100 transition hover:bg-white/10 hover:text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Log out
            </button>
        </form>
    </div>
</aside>
<div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>

<div class="lg:pl-64">
    <!-- Top bar -->
    <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">
        <button id="sidebar-toggle" type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open menu">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-slate-900"><?= e($pageTitle ?? 'Dashboard') ?></h1>

        <!-- Notifications -->
        <div class="relative" data-dropdown>
            <button type="button" data-dropdown-toggle class="relative rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-brand-700" aria-label="Notifications" aria-expanded="false">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <?php if ($unreadCount): ?>
                    <span class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"><?= min($unreadCount, 99) ?></span>
                <?php endif; ?>
            </button>
            <div data-dropdown-menu class="absolute right-0 mt-2 hidden w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">Notifications</p>
                    <?php if ($unreadCount): ?>
                        <form method="POST" action="notifications.php">
                            <?= csrf_field() ?>
                            <button name="action" value="read_all" class="text-xs font-medium text-brand-600 hover:text-brand-800">Mark all as read</button>
                        </form>
                    <?php endif; ?>
                </div>
                <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                    <?php foreach ($notifications as $note): ?>
                        <li>
                            <a href="notifications.php?open=<?= (int) $note['id'] ?>" class="flex gap-3 px-4 py-3 text-sm transition hover:bg-slate-50 <?= $note['is_read'] ? 'text-slate-500' : 'bg-brand-50/50 text-slate-800' ?>">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $note['is_read'] ? 'bg-transparent' : 'bg-brand-600' ?>"></span>
                                <span class="flex-1">
                                    <?= e($note['message']) ?>
                                    <span class="mt-0.5 block text-xs text-slate-400"><?= time_ago($note['created_at']) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$notifications): ?>
                        <li class="px-4 py-10 text-center text-sm text-slate-400">You're all caught up.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- User menu -->
        <div class="relative" data-dropdown>
            <button type="button" data-dropdown-toggle class="flex items-center gap-3 rounded-full py-1 pl-1 pr-2 transition hover:bg-slate-100" aria-expanded="false">
                <span class="bg-brand-gradient flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold text-white"><?= e(initials(full_name($user))) ?></span>
                <span class="hidden text-left sm:block">
                    <span class="block text-sm font-semibold leading-tight text-slate-900"><?= e(full_name($user)) ?></span>
                    <span class="block text-xs text-slate-500"><?= e(role_label($user['role'])) ?></span>
                </span>
            </button>
            <div data-dropdown-menu class="absolute right-0 mt-2 hidden w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white py-2 shadow-xl">
                <a href="profile.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">My Profile</a>
                <a href="index.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Home Page</a>
                <form method="POST" action="logout.php">
                    <?= csrf_field() ?>
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">Log out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <?php if ($flash): ?>
            <div class="mb-6 rounded-xl border px-4 py-3 text-sm shadow-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' ?>" role="alert">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>
