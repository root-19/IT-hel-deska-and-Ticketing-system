<?php
// Shared helpers: session, CSRF, escaping, flash messages, auth guards, ticket helpers

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/db.php';

define('APP_NAME', 'ICCT IT Help Desk');

// Account types. "requester" roles submit tickets; the others work on them.
const ROLES = [
    'student'    => ['label' => 'Student',         'portal' => 'Student Support',             'requester' => true],
    'lab'        => ['label' => 'Computer Lab',    'portal' => 'Computer Laboratory Support', 'requester' => true],
    'faculty'    => ['label' => 'Faculty & Staff', 'portal' => 'Faculty & Staff Support',     'requester' => true],
    'technician' => ['label' => 'IT Technician',   'portal' => 'IT Technician Workspace',     'requester' => false],
    'admin'      => ['label' => 'IT Admin',        'portal' => 'IT Admin Dashboard',          'requester' => false],
];

const TICKET_STATUSES = [
    'open'        => ['Open',        'bg-sky-100 text-sky-700'],
    'assigned'    => ['Assigned',    'bg-indigo-100 text-indigo-700'],
    'in_progress' => ['In Progress', 'bg-amber-100 text-amber-700'],
    'on_hold'     => ['On Hold',     'bg-slate-200 text-slate-700'],
    'resolved'    => ['Resolved',    'bg-emerald-100 text-emerald-700'],
    'closed'      => ['Closed',      'bg-slate-100 text-slate-500'],
];

const UNRESOLVED_STATUSES = ['open', 'assigned', 'in_progress', 'on_hold'];
const COMPLETED_STATUSES = ['resolved', 'closed'];

const TICKET_PRIORITIES = [
    'low'    => ['Low',    'bg-slate-100 text-slate-600'],
    'medium' => ['Medium', 'bg-sky-100 text-sky-700'],
    'high'   => ['High',   'bg-orange-100 text-orange-700'],
    'urgent' => ['Urgent', 'bg-red-100 text-red-700'],
];

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals(csrf_token(), $token);
}

// ---------- Flash messages ----------
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ---------- Auth ----------

// Loads the logged-in user from the database once per request, so role or
// status changes made by an admin take effect immediately.
function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare(
                'SELECT id, id_number, first_name, last_name, email, role, department, phone, status, last_login_at, created_at
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();
            if ($row && $row['status'] === 'active') {
                $user = $row;
            } else {
                unset($_SESSION['user_id']);
            }
        }
    }

    return $user;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && current_user() !== null;
}

function require_login(): array
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
    return current_user();
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        flash('error', "You don't have access to that page.");
        redirect('dashboard.php');
    }
    return $user;
}

function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        redirect('dashboard.php');
    }
}

function is_requester(array $user): bool
{
    return ROLES[$user['role']]['requester'] ?? false;
}

function role_label(string $role): string
{
    return ROLES[$role]['label'] ?? ucfirst($role);
}

function full_name(array $row, string $prefix = ''): string
{
    return trim(($row[$prefix . 'first_name'] ?? '') . ' ' . ($row[$prefix . 'last_name'] ?? ''));
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr(end($parts) ?: '', 0, 1));
}

// Renders the ICCT logo, or a fallback icon if assets/img/logo.png is missing
function logo(string $class = 'h-10 w-10'): string
{
    if (file_exists(__DIR__ . '/../assets/img/logo.png')) {
        return '<img src="assets/img/logo.png" alt="ICCT Colleges logo" class="' . $class . ' object-contain">';
    }

    return '<span class="' . $class . ' inline-flex items-center justify-center rounded-full bg-white text-brand-700 shadow">'
        . '<svg xmlns="http://www.w3.org/2000/svg" class="h-3/5 w-3/5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>'
        . '</svg></span>';
}

// ---------- Tickets ----------
function ticket_no(int $id): string
{
    return 'TKT-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function status_badge(string $status): string
{
    [$label, $class] = TICKET_STATUSES[$status] ?? [ucfirst($status), 'bg-slate-100 text-slate-600'];
    return '<span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ' . $class . '">' . $label . '</span>';
}

function priority_badge(string $priority): string
{
    [$label, $class] = TICKET_PRIORITIES[$priority] ?? [ucfirst($priority), 'bg-slate-100 text-slate-600'];
    return '<span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ' . $class . '">' . $label . '</span>';
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $seconds = time() - strtotime($datetime);
    return match (true) {
        $seconds < 60     => 'just now',
        $seconds < 3600   => floor($seconds / 60) . 'm ago',
        $seconds < 86400  => floor($seconds / 3600) . 'h ago',
        $seconds < 604800 => floor($seconds / 86400) . 'd ago',
        default           => date('M j, Y', strtotime($datetime)),
    };
}

// Human-readable duration from a number of hours, e.g. "3.5 hrs" or "2.1 days"
function format_hours(?float $hours): string
{
    if ($hours === null) {
        return '—';
    }
    return $hours < 24 ? round($hours, 1) . ' hrs' : round($hours / 24, 1) . ' days';
}

// Base query for ticket lists (used with includes/ticket_table.php)
const TICKET_LIST_SQL = '
    SELECT t.id, t.subject, t.priority, t.status, t.assigned_to, t.requester_id,
           t.created_at, t.updated_at, t.resolved_at,
           c.name AS category_name, c.audience,
           r.first_name AS requester_first_name, r.last_name AS requester_last_name, r.role AS requester_role,
           a.first_name AS assignee_first_name, a.last_name AS assignee_last_name
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    JOIN users r ON r.id = t.requester_id
    LEFT JOIN users a ON a.id = t.assigned_to';

// SQL fragment that sorts the most urgent, oldest tickets first
const PRIORITY_ORDER_SQL = "FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.created_at";

function stat_tile(string $label, string|int $value, string $hint = '', string $valueClass = 'text-slate-900'): string
{
    return '<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">'
        . '<p class="text-sm text-slate-500">' . e($label) . '</p>'
        . '<p class="mt-2 text-3xl font-bold tabular-nums ' . $valueClass . '">' . e((string) $value) . '</p>'
        . ($hint !== '' ? '<p class="mt-1 text-xs text-slate-400">' . e($hint) . '</p>' : '')
        . '</div>';
}

// Ticket with requester, category, and assignee details, or null if missing
function find_ticket(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT t.*, c.name AS category_name, c.audience,
                r.first_name AS requester_first_name, r.last_name AS requester_last_name,
                r.email AS requester_email, r.role AS requester_role, r.department AS requester_department,
                a.first_name AS assignee_first_name, a.last_name AS assignee_last_name
         FROM tickets t
         JOIN categories c ON c.id = t.category_id
         JOIN users r ON r.id = t.requester_id
         LEFT JOIN users a ON a.id = t.assigned_to
         WHERE t.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function can_view_ticket(array $user, array $ticket): bool
{
    return match ($user['role']) {
        'admin'      => true,
        'technician' => (int) $ticket['assigned_to'] === (int) $user['id'],
        default      => (int) $ticket['requester_id'] === (int) $user['id'],
    };
}

function log_activity(int $ticketId, int $userId, string $type, ?string $body = null): void
{
    db()->prepare('INSERT INTO ticket_activity (ticket_id, user_id, type, body) VALUES (?, ?, ?, ?)')
        ->execute([$ticketId, $userId, $type, $body]);
}

// Sends an in-app notification to each user, skipping whoever triggered it
function notify(array $userIds, ?int $ticketId, string $message): void
{
    $me = (int) ($_SESSION['user_id'] ?? 0);
    $stmt = db()->prepare('INSERT INTO notifications (user_id, ticket_id, message) VALUES (?, ?, ?)');
    foreach (array_unique(array_filter(array_map('intval', $userIds))) as $userId) {
        if ($userId !== $me) {
            $stmt->execute([$userId, $ticketId, mb_substr($message, 0, 255)]);
        }
    }
}

function admin_ids(): array
{
    return db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
}

// ---------- Categories ----------
const CATEGORY_ICONS = [
    'student_portal'    => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    'student_account'   => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
    'student_assist'    => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z',
    'lab_computer'      => 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z',
    'lab_internet'      => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
    'lab_printer'       => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
    'lab_software'      => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
    'faculty_online'    => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
    'faculty_systems'   => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'faculty_account'   => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    'faculty_equipment' => 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z',
];

function category_icon(string $slug, string $class = 'h-6 w-6'): string
{
    $path = CATEGORY_ICONS[$slug] ?? 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
    return '<svg class="' . $class . '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $path . '"/></svg>';
}

function categories_for(string $audience): array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE audience = ? AND is_active = 1 ORDER BY sort_order, name');
    $stmt->execute([$audience]);
    return $stmt->fetchAll();
}

// Label for the "location" field on the ticket form, per account type
function location_label(string $role): string
{
    return match ($role) {
        'lab'     => 'Laboratory room & PC number',
        'faculty' => 'Room / Office',
        default   => 'Location (optional)',
    };
}
