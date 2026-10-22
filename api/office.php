<?php
// Virtual IT Office feed: GET -> {me, staff, guests, lobby, max, waiting, tickets}
// Polled by assets/js/office.js. Shows IT staff presence and where the viewer's own tickets are.
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const ONLINE_SECONDS = 120; // seen this recently = at their desk
const AWAY_SECONDS = 900;   // seen this recently = away from desk
// These roles always show as online in the office, whether or not they have the site open
const ALWAYS_ONLINE_ROLES = ['admin', 'technician', 'faculty', 'lab'];
const MAX_PER_ROLE = 7; // robots drawn per role; the office only has room for so many

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$user = current_user();
if (!$user) {
    respond(401, ['error' => 'Please log in again.']);
}
session_write_close();

function ago(?int $seconds): string
{
    return match (true) {
        $seconds === null => 'never',
        $seconds < 60     => 'just now',
        $seconds < 3600   => floor($seconds / 60) . 'm ago',
        $seconds < 86400  => floor($seconds / 3600) . 'h ago',
        default           => floor($seconds / 86400) . 'd ago',
    };
}

// Keeps at most MAX_PER_ROLE people per role, oldest accounts first, so desks stay put as
// accounts are added. Ids in $keep (e.g. the viewer's technician) always make the cut.
function cap_per_role(array $people, array $keep = []): array
{
    $byRole = [];
    foreach ($people as $person) {
        $byRole[$person['role']][] = $person;
    }
    $kept = [];
    foreach ($byRole as $group) {
        $rank = fn ($p) => [!in_array($p['id'], $keep, true), $p['id']];
        usort($group, fn ($a, $b) => $rank($a) <=> $rank($b));
        $group = array_slice($group, 0, MAX_PER_ROLE);
        usort($group, fn ($a, $b) => $a['id'] <=> $b['id']);
        array_push($kept, ...$group);
    }
    return $kept;
}

// ---------- The viewer's tickets ----------
$stmt = db()->prepare(
    "SELECT t.id, t.subject, t.status, t.assigned_to,
            a.first_name, a.last_name,
            TIMESTAMPDIFF(SECOND, t.updated_at, NOW()) AS updated,
            (SELECT COUNT(*) FROM tickets q WHERE q.status = 'open' AND q.assigned_to IS NULL AND q.id <= t.id) AS line
     FROM tickets t
     LEFT JOIN users a ON a.id = t.assigned_to
     WHERE t.requester_id = ?
     ORDER BY t.status = 'closed', t.updated_at DESC
     LIMIT 4"
);
$stmt->execute([$user['id']]);
$ticketRows = $stmt->fetchAll();

// Show only active tickets, or the latest closed one when nothing is active
$active = array_filter($ticketRows, fn ($r) => $r['status'] !== 'closed');
$ticketRows = $active ?: array_slice($ticketRows, 0, 1);
$myTechIds = array_values(array_filter(array_map(fn ($r) => (int) $r['assigned_to'], $ticketRows)));

// ---------- IT staff at their desks ----------
$rows = db()->query(
    "SELECT u.id, u.first_name, u.last_name, u.role,
            TIMESTAMPDIFF(SECOND, u.last_seen_at, NOW()) AS idle,
            (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.status = 'in_progress') AS working,
            (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.status IN ('assigned','in_progress','on_hold')) AS active
     FROM users u
     WHERE u.role IN ('admin','technician') AND u.status = 'active'
     ORDER BY FIELD(u.role, 'admin', 'technician'), u.id"
)->fetchAll();

$staff = [];
$presenceOf = [];
foreach ($rows as $row) {
    $idle = $row['idle'] === null ? null : (int) $row['idle'];
    $alwaysOnline = in_array($row['role'], ALWAYS_ONLINE_ROLES, true);
    $presence = match (true) {
        !$alwaysOnline && ($idle === null || $idle > AWAY_SECONDS) => 'offline',
        !$alwaysOnline && $idle > ONLINE_SECONDS                   => 'away',
        $row['role'] === 'technician' && $row['working'] > 0 => 'working',
        default                                => 'available',
    };
    $presenceOf[(int) $row['id']] = $presence;
    $staff[] = [
        'id'       => (int) $row['id'],
        'name'     => full_name($row),
        'first'    => $row['first_name'],
        'role'     => $row['role'],
        'presence' => $presence,
        'active'   => (int) $row['active'],
        'seen'     => ago($idle),
    ];
}
$staff = cap_per_role($staff, $myTechIds);

// ---------- Requesters in the lobby ----------
// Real counts for the legend
$alwaysOnlineIn = implode(',', array_fill(0, count(ALWAYS_ONLINE_ROLES), '?'));
$stmt = db()->prepare(
    "SELECT role, COUNT(*) FROM users
     WHERE role IN ('student','lab','faculty') AND status = 'active'
       AND (role IN ($alwaysOnlineIn) OR last_seen_at >= NOW() - INTERVAL " . ONLINE_SECONDS . ' SECOND)
     GROUP BY role'
);
$stmt->execute(ALWAYS_ONLINE_ROLES);
$lobby = array_merge(['student' => 0, 'lab' => 0, 'faculty' => 0], array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR)));

// Faculty and lab accounts get named robots (the viewer is flagged so they show as "You").
// Students stay anonymous: the map only draws as many as the student count.
$stmt = db()->prepare(
    "SELECT id, first_name, last_name, role FROM users
     WHERE role IN ('faculty','lab') AND status = 'active' ORDER BY id"
);
$stmt->execute();
$guests = cap_per_role(array_map(fn ($row) => [
    'id'   => (int) $row['id'],
    'role' => $row['role'],
    'name' => $row['role'] === 'lab' ? full_name($row) : $row['first_name'],
    'me'   => (int) $row['id'] === (int) $user['id'],
], $stmt->fetchAll()), [(int) $user['id']]);

$waiting = (int) db()->query("SELECT COUNT(*) FROM tickets WHERE status = 'open' AND assigned_to IS NULL")->fetchColumn();

// ---------- Where the viewer's tickets are ----------
$tickets = [];
foreach ($ticketRows as $row) {
    $tech = $row['assigned_to'] ? full_name($row) : 'The IT team';
    $techHere = $row['assigned_to'] && in_array($presenceOf[(int) $row['assigned_to']] ?? '', ['working', 'available'], true);
    $line = (int) $row['line'];

    [$stage, $message] = match ($row['status']) {
        'open' => $row['assigned_to']
            ? [2, "$tech has your ticket and will start on it soon."]
            : [1, $line > 1
                ? "Your ticket is at the Front Desk. You're #$line in line, and an IT Admin will assign a technician soon."
                : "Your ticket is next at the Front Desk. An IT Admin will assign a technician soon."],
        'assigned'    => [2, "$tech picked up your ticket and will start on it soon."],
        'in_progress' => [2, $techHere ? "$tech is working on your ticket right now." : "$tech is handling your ticket and will continue when back at their desk."],
        'on_hold'     => [2, "Your ticket is on hold. Open it to check if $tech needs anything from you."],
        'resolved'    => [3, 'Fixed! Please open the ticket to confirm the fix and rate the support.'],
        default       => [3, 'All done. This ticket is closed. Thanks for your feedback!'],
    };

    $tickets[] = [
        'id'       => (int) $row['id'],
        'no'       => ticket_no((int) $row['id']),
        'subject'  => $row['subject'],
        'status'   => TICKET_STATUSES[$row['status']][0] ?? $row['status'],
        'stage'    => $stage,
        'tech_id'  => $row['assigned_to'] ? (int) $row['assigned_to'] : null,
        'message'  => $message,
        'updated'  => ago((int) $row['updated']),
    ];
}

respond(200, [
    'me'      => ['role' => $user['role']],
    'staff'   => $staff,
    'guests'  => $guests,
    'lobby'   => $lobby,
    'max'     => MAX_PER_ROLE,
    'waiting' => $waiting,
    'tickets' => $tickets,
]);
