<?php
// Opens a notification (marks it read, then goes to its ticket) or marks all as read
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf() && ($_POST['action'] ?? '') === 'read_all') {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
    // Return to the page the user was on (same site only)
    $from = parse_url($_SERVER['HTTP_REFERER'] ?? '');
    $page = basename($from['path'] ?? '') . (isset($from['query']) ? '?' . $from['query'] : '');
    redirect(preg_match('/^[\w-]+\.php(\?[\w=&%+.-]*)?$/', $page) ? $page : 'dashboard.php');
}

$stmt = db()->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ?');
$stmt->execute([(int) ($_GET['open'] ?? 0), $user['id']]);
$notification = $stmt->fetch();

if ($notification) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([$notification['id']]);
    if ($notification['ticket_id']) {
        redirect('ticket.php?id=' . (int) $notification['ticket_id']);
    }
}

redirect('dashboard.php');
