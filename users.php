<?php
require_once __DIR__ . '/includes/functions.php';
$admin = require_role('admin');

const DEPARTMENT_LABELS = [
    'student'    => ['Course', 'e.g. BS Information Technology'],
    'lab'        => ['Laboratory / Room', 'e.g. Computer Laboratory 1, Room 201'],
    'faculty'    => ['Department / Office', 'e.g. College of Computer Studies'],
    'technician' => ['IT Team / Specialization', 'e.g. Hardware & Laboratory'],
    'admin'      => ['Office', 'e.g. IT Department'],
];

function temp_password(): string
{
    // Readable temporary password that satisfies the password rules
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < 8; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'Icct' . $password . random_int(10, 99);
}

function valid_password(string $password): bool
{
    return strlen($password) >= 8 && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/\d/', $password);
}

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

$editing = null;
if ($action === 'edit') {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'Account not found.');
        redirect('users.php');
    }
}

$old = [
    'role'       => array_key_exists($_GET['role'] ?? '', ROLES) ? $_GET['role'] : 'lab',
    'first_name' => '',
    'last_name'  => '',
    'id_number'  => '',
    'email'      => '',
    'department' => '',
    'phone'      => '',
    'status'     => 'active',
];
if ($editing) {
    $old = array_intersect_key($editing, $old);
}

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if (!verify_csrf()) {
        flash('error', 'Your session has expired. Please try again.');
        redirect('users.php');
    }

    if (in_array($postAction, ['toggle_status', 'reset_password'], true)) {
        $targetId = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if (!$target) {
            flash('error', 'Account not found.');
        } elseif ($postAction === 'toggle_status') {
            if ((int) $target['id'] === (int) $admin['id']) {
                flash('error', "You can't deactivate your own account.");
            } else {
                $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
                db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $targetId]);
                flash('success', full_name($target) . ($newStatus === 'active' ? ' can log in again.' : ' was deactivated and can no longer log in.'));
            }
        } else {
            $password = temp_password();
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);
            flash('success', 'Password reset for ' . full_name($target) . ". Temporary password: $password — share it privately and ask them to change it from My Profile.");
        }
        $return = (string) ($_POST['return'] ?? '');
        redirect(preg_match('/^users\.php(\?[\w=&%+.-]*)?$/', $return) ? $return : 'users.php');
    }

    if (in_array($postAction, ['create', 'update'], true)) {
        foreach ($old as $key => $value) {
            $old[$key] = trim($_POST[$key] ?? '');
        }
        $password = $_POST['password'] ?? '';
        $isSelf = $editing && (int) $editing['id'] === (int) $admin['id'];

        if (!array_key_exists($old['role'], ROLES)) {
            $errors[] = 'Please choose an account type.';
        }
        if (mb_strlen($old['first_name']) < 2 || mb_strlen($old['first_name']) > 60 || mb_strlen($old['last_name']) < 1 || mb_strlen($old['last_name']) > 60) {
            $errors[] = 'Please enter a first name (2–60 characters) and last name.';
        }
        if (!preg_match('/^[A-Za-z0-9-]{4,30}$/', $old['id_number'])) {
            $errors[] = 'ID number must be 4–30 characters (letters, numbers, and dashes only).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($old['department']) > 100 || mb_strlen($old['phone']) > 30) {
            $errors[] = 'Department must be 100 characters or less, and phone 30 or less.';
        }
        if (!in_array($old['status'], ['active', 'inactive'], true)) {
            $old['status'] = 'active';
        }
        if ($isSelf && ($old['role'] !== 'admin' || $old['status'] !== 'active')) {
            $errors[] = "You can't remove your own admin access or deactivate yourself.";
        }
        if ($postAction === 'create' && $password === '') {
            $password = temp_password();
            $generated = true;
        }
        if ($password !== '' && !valid_password($password)) {
            $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and a number.';
        }

        if (!$errors) {
            $stmt = db()->prepare('SELECT email, id_number FROM users WHERE (email = ? OR id_number = ?) AND id <> ?');
            $stmt->execute([$old['email'], $old['id_number'], $editing['id'] ?? 0]);
            foreach ($stmt->fetchAll() as $row) {
                if (strcasecmp($row['email'], $old['email']) === 0) {
                    $errors[] = 'That email is already used by another account.';
                }
                if (strcasecmp($row['id_number'], $old['id_number']) === 0) {
                    $errors[] = 'That ID number is already used by another account.';
                }
            }
        }

        if (!$errors) {
            $values = [
                $old['id_number'], $old['first_name'], $old['last_name'], strtolower($old['email']), $old['role'],
                $old['department'] !== '' ? $old['department'] : null, $old['phone'] !== '' ? $old['phone'] : null, $old['status'],
            ];

            if ($postAction === 'create') {
                db()->prepare(
                    'INSERT INTO users (id_number, first_name, last_name, email, role, department, phone, status, password_hash, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([...$values, password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
                $message = role_label($old['role']) . ' account created for ' . $old['first_name'] . ' ' . $old['last_name'] . '.';
                if (!empty($generated)) {
                    $message .= " Temporary password: $password — share it privately.";
                }
            } else {
                $sql = 'UPDATE users SET id_number = ?, first_name = ?, last_name = ?, email = ?, role = ?, department = ?, phone = ?, status = ?';
                if ($password !== '') {
                    $sql .= ', password_hash = ?';
                    $values[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $values[] = $editing['id'];
                db()->prepare($sql . ' WHERE id = ?')->execute($values);
                $message = 'Account updated.';
            }

            flash('success', $message);
            redirect('users.php?role=' . $old['role']);
        }
        $action = $postAction === 'create' ? 'create' : 'edit';
    }
}

// ---------- List ----------
$roleFilter = array_key_exists($_GET['role'] ?? '', ROLES) ? $_GET['role'] : '';
$search = trim($_GET['q'] ?? '');
$roleCounts = db()->query('SELECT role, COUNT(*) FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);

$users = [];
if ($action === 'list') {
    $where = [];
    $params = [];
    if ($roleFilter) {
        $where[] = 'u.role = ?';
        $params[] = $roleFilter;
    }
    if ($search !== '') {
        $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.id_number LIKE ? OR u.department LIKE ?)";
        array_push($params, ...array_fill(0, 4, "%$search%"));
    }
    $stmt = db()->prepare(
        "SELECT u.*,
                (SELECT COUNT(*) FROM tickets t WHERE t.requester_id = u.id) AS submitted,
                (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.status IN ('assigned','in_progress','on_hold')) AS active_assigned
         FROM users u" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
         ORDER BY FIELD(u.role, 'admin', 'technician', 'faculty', 'lab', 'student'), u.first_name
         LIMIT 200"
    );
    $stmt->execute($params);
    $users = $stmt->fetchAll();
}

$pageTitle = match ($action) { 'create' => 'Create Account', 'edit' => 'Edit Account', default => 'Accounts' };
$activeNav = 'users';
include __DIR__ . '/includes/app_top.php';

$inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="mx-auto max-w-3xl">
        <a href="users.php" class="text-sm text-slate-500 hover:text-brand-700">&larr; Back to accounts</a>
        <h2 class="mt-2 text-2xl font-bold text-slate-900"><?= $action === 'create' ? 'Create an account' : 'Edit ' . e(full_name($editing)) ?></h2>
        <p class="mt-1 text-slate-500">Computer Lab, Faculty &amp; Staff, IT Technician, and Admin accounts can only be created here. Students can also register themselves.</p>

        <?php if ($errors): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <ul class="list-inside list-disc space-y-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="users.php?action=<?= $action ?><?= $editing ? '&id=' . (int) $editing['id'] : '' ?>" class="mt-6 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">

            <fieldset>
                <legend class="mb-3 text-sm font-medium text-slate-700">Account type</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <?php foreach (ROLES as $key => $role): ?>
                        <label class="flex cursor-pointer flex-col rounded-xl border border-slate-200 p-4 transition hover:border-brand-300 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-200">
                            <input type="radio" name="role" value="<?= $key ?>" class="sr-only" data-role-input <?= $old['role'] === $key ? 'checked' : '' ?>>
                            <span class="text-sm font-semibold text-slate-900"><?= e($role['label']) ?></span>
                            <span class="text-xs text-slate-500"><?= e($role['portal']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="mb-1.5 block text-sm font-medium text-slate-700">First Name</label>
                    <input id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required maxlength="60" class="<?= $inputClass ?>" placeholder="e.g. Maria">
                </div>
                <div>
                    <label for="last_name" class="mb-1.5 block text-sm font-medium text-slate-700">Last Name</label>
                    <input id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required maxlength="60" class="<?= $inputClass ?>" placeholder="e.g. Cruz">
                </div>
            </div>
            <p class="-mt-3 text-xs text-slate-400">For Computer Lab accounts, use the lab's name, e.g. "Computer" + "Laboratory 1".</p>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="id_number" class="mb-1.5 block text-sm font-medium text-slate-700">ID Number</label>
                    <input id="id_number" name="id_number" value="<?= e($old['id_number']) ?>" required maxlength="30" class="<?= $inputClass ?>" placeholder="e.g. FAC-0002 or LAB-0002">
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" required maxlength="120" class="<?= $inputClass ?>" placeholder="name@icct.edu.ph">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="department" class="mb-1.5 block text-sm font-medium text-slate-700" data-department-label><?= e(DEPARTMENT_LABELS[$old['role']][0] ?? 'Department') ?></label>
                    <input id="department" name="department" value="<?= e($old['department']) ?>" maxlength="100" class="<?= $inputClass ?>" placeholder="<?= e(DEPARTMENT_LABELS[$old['role']][1] ?? '') ?>">
                </div>
                <div>
                    <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-700">Phone / Local <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="phone" name="phone" value="<?= e($old['phone']) ?>" maxlength="30" class="<?= $inputClass ?>" placeholder="e.g. 0917 123 4567 or local 204">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700"><?= $action === 'create' ? 'Password' : 'New Password' ?> <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="password" name="password" type="text" autocomplete="new-password" class="<?= $inputClass ?>"
                           placeholder="<?= $action === 'create' ? 'Leave blank to auto-generate' : 'Leave blank to keep current' ?>">
                </div>
                <div>
                    <label for="status" class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="<?= $inputClass ?>">
                        <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active – can log in</option>
                        <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactive – blocked from logging in</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                <a href="users.php" class="rounded-xl border border-slate-300 px-6 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button class="bg-brand-gradient rounded-xl px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95"><?= $action === 'create' ? 'Create Account' : 'Save Changes' ?></button>
            </div>
        </form>
    </div>

    <script>
        // Relabel the department field for the chosen account type
        (() => {
            const labels = <?= json_encode(DEPARTMENT_LABELS) ?>;
            const label = document.querySelector('[data-department-label]');
            const input = document.getElementById('department');
            document.querySelectorAll('[data-role-input]').forEach((radio) => radio.addEventListener('change', () => {
                [label.textContent, input.placeholder] = labels[radio.value];
            }));
        })();
    </script>

<?php else: ?>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <nav class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
            <a href="users.php" class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium <?= $roleFilter === '' ? 'bg-brand-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>">All <span class="opacity-70"><?= array_sum($roleCounts) ?></span></a>
            <?php foreach (ROLES as $key => $role): ?>
                <a href="users.php?role=<?= $key ?>" class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium <?= $roleFilter === $key ? 'bg-brand-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' ?>">
                    <?= e($role['label']) ?> <span class="opacity-70"><?= (int) ($roleCounts[$key] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <a href="users.php?action=create<?= $roleFilter && $roleFilter !== 'student' ? '&role=' . $roleFilter : '' ?>" class="bg-brand-gradient inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Create Account
        </a>
    </div>

    <form method="GET" class="mt-4 flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <?php if ($roleFilter): ?><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, ID number, or department" class="<?= $inputClass ?>">
        <button class="rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
    </form>

    <section class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Name</th>
                        <th class="px-5 py-3 font-semibold">Account Type</th>
                        <th class="px-5 py-3 font-semibold">Department</th>
                        <th class="px-5 py-3 text-right font-semibold">Tickets</th>
                        <th class="px-5 py-3 font-semibold">Last Login</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $row):
                        $returnUrl = 'users.php?' . http_build_query(array_filter(['role' => $roleFilter, 'q' => $search])); ?>
                        <tr class="<?= $row['status'] === 'inactive' ? 'bg-slate-50 text-slate-400' : '' ?>">
                            <td class="px-5 py-3">
                                <span class="block font-medium <?= $row['status'] === 'inactive' ? '' : 'text-slate-900' ?>"><?= e(full_name($row)) ?></span>
                                <span class="text-xs text-slate-400"><?= e($row['id_number']) ?> · <?= e($row['email']) ?></span>
                            </td>
                            <td class="px-5 py-3"><span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700"><?= e(role_label($row['role'])) ?></span></td>
                            <td class="px-5 py-3"><?= e($row['department'] ?: '—') ?></td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                <?= $row['role'] === 'technician' ? (int) $row['active_assigned'] . ' active' : (in_array($row['role'], ['student', 'lab', 'faculty'], true) ? (int) $row['submitted'] . ' submitted' : '—') ?>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3"><?= $row['last_login_at'] ? time_ago($row['last_login_at']) : 'Never' ?></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $row['status'] === 'active' ? 'text-emerald-700' : 'text-slate-500' ?>">
                                    <span class="h-2 w-2 rounded-full <?= $row['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span><?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1">
                                    <a href="users.php?action=edit&id=<?= (int) $row['id'] ?>" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-50">Edit</a>
                                    <form method="POST" data-confirm="Reset the password for <?= e(full_name($row)) ?>? A new temporary password will be shown once.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                                        <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100">Reset Password</button>
                                    </form>
                                    <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                                        <form method="POST" data-confirm="<?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?> <?= e(full_name($row)) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium <?= $row['status'] === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50' ?>"><?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No accounts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_bottom.php'; ?>
