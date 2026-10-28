<?php
// Streams a ticket image to anyone allowed to see that ticket
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$stmt = db()->prepare('SELECT * FROM ticket_attachments WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$attachment = $stmt->fetch();

$ticket = $attachment ? find_ticket((int) $attachment['ticket_id']) : null;
$path = $attachment ? ATTACHMENT_DIR . '/' . basename($attachment['stored_name']) : '';

if (!$ticket || !can_view_ticket($user, $ticket) || !is_file($path)) {
    http_response_code(404);
    exit('Attachment not found, or you do not have access to it.');
}

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($path));
header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($attachment['original_name']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($path);
