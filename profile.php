<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($action === 'details') {
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        if (mb_strlen($phone) > 30 || mb_strlen($department) > 100) {
            $errors[] = 'Phone must be 30 characters or less, and department 100 or less.';
        } else {
            db()->prepare('UPDATE users SET phone = ?, department = ? WHERE id = ?')
                ->execute([$phone !== '' ? $phone : null, $department !== '' ? $department : null, $user['id']]);
            flash('success', 'Profile updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        if (!password_verify($current, (string) $stmt->fetchColumn())) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors[] = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters with uppercase, lowercase, and a number.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed successfully.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'My Profile';
$activeNav = 'profile';
include __DIR__ . '/includes/app_top.php';

$inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
?>
<div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-3">
    <section class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
        <span class="bg-brand-gradient mx-auto flex h-20 w-20 items-center justify-center rounded-full text-2xl font-bold text-white"><?= e(initials(full_name($user))) ?></span>
        <h2 class="mt-4 text-lg font-bold text-slate-900"><?= e(full_name($user)) ?></h2>
        <p class="text-sm text-slate-500"><?= e(role_label($user['role'])) ?> · <?= e(ROLES[$user['role']]['portal']) ?></p>
        <dl class="mt-6 space-y-3 border-t border-slate-100 pt-6 text-left text-sm">
            <div><dt class="text-slate-400">ID Number</dt><dd class="font-medium text-slate-900"><?= e($user['id_number']) ?></dd></div>
            <div><dt class="text-slate-400">Email</dt><dd class="break-all font-medium text-slate-900"><?= e($user['email']) ?></dd></div>
            <div><dt class="text-slate-400">Member since</dt><dd class="font-medium text-slate-900"><?= date('F j, Y', strtotime($user['created_at'])) ?></dd></div>
        </dl>
        <p class="mt-6 text-xs text-slate-400">To change your name, email, or ID number, contact the IT Admin.</p>
    </section>

    <div class="space-y-6 lg:col-span-2">
        <?php if ($errors): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="details">
            <h3 class="font-semibold text-slate-900">Contact details</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="department" class="mb-1.5 block text-sm font-medium text-slate-700"><?= $user['role'] === 'student' ? 'Course' : 'Department / Office' ?></label>
                    <input id="department" name="department" value="<?= e($user['department']) ?>" maxlength="100" class="<?= $inputClass ?>">
                </div>
                <div>
                    <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-700">Phone / Local</label>
                    <input id="phone" name="phone" value="<?= e($user['phone']) ?>" maxlength="30" class="<?= $inputClass ?>" placeholder="So IT can reach you">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="bg-brand-gradient rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:opacity-95">Save</button>
            </div>
        </form>

        <form method="POST" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <h3 class="font-semibold text-slate-900">Change password</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-700">Current password</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="<?= $inputClass ?>">
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="new_password" class="mb-1.5 block text-sm font-medium text-slate-700">New password</label>
                    <input id="new_password" name="new_password" type="password" required minlength="<?= MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" class="<?= $inputClass ?>">
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="confirm_password" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm new password</label>
                        <input id="confirm_password" name="confirm_password" type="password" required data-match="new_password" autocomplete="new-password" class="<?= $inputClass ?>">
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                </div>
                <p class="text-xs text-slate-400">At least 8 characters, with uppercase, lowercase, and a number.</p>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="bg-brand-gradient flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:opacity-95"><span>Change Password</span></button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/app_bottom.php'; ?>
