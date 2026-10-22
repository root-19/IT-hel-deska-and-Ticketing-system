<?php
require_once __DIR__ . '/includes/functions.php';
redirect_if_logged_in();

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 300;

$errors = [];
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($lockedUntil > time()) {
        $minutes = ceil(($lockedUntil - time()) / 60);
        $errors[] = "Too many failed attempts. Please try again in {$minutes} minute(s).";
    } elseif ($login === '' || $password === '') {
        $errors[] = 'Please enter your email/ID number and password.';
    } else {
        try {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? OR id_number = ? LIMIT 1');
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $errors[] = 'Your account is inactive. Please contact the IT Department.';
                } else {
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                    }
                    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

                    session_regenerate_id(true);
                    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                    $_SESSION['user_id'] = (int) $user['id'];

                    flash('success', 'Welcome back, ' . $user['first_name'] . '!');
                    redirect('dashboard.php');
                }
            } else {
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                    $_SESSION['login_locked_until'] = time() + LOCKOUT_SECONDS;
                    $_SESSION['login_attempts'] = 0;
                }
                $errors[] = 'Invalid email/ID number or password.';
            }
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Unable to connect to the database. Please make sure MySQL is running and helpdesk_db is imported.';
        }
    }
}

$flash = get_flash();
$pageTitle = 'Log in';
$asideTitle = 'Welcome back to<br><span class="text-brand-gradient">IT Help Desk</span>';
$asideText = 'Log in to submit new requests and follow up on your existing tickets.';
include __DIR__ . '/includes/head.php';
?>
<body class="bg-slate-50 font-sans text-slate-700 antialiased">
<div class="flex min-h-screen">
    <?php include __DIR__ . '/includes/auth_aside.php'; ?>

    <main class="flex w-full items-center justify-center px-4 py-12 sm:px-8 lg:w-1/2">
        <div class="w-full max-w-md">
            <!-- Mobile logo -->
            <a href="index.php" class="bg-brand-gradient mb-8 flex items-center gap-3 rounded-2xl p-4 lg:hidden">
                <?= logo('h-10 w-10') ?>
                <span class="text-lg font-bold text-white">ICCT IT Help Desk</span>
            </a>

            <h2 class="text-3xl font-bold text-slate-900">Log in</h2>
            <p class="mt-2 text-slate-500">Enter your credentials to access your account.</p>

            <?php if ($flash): ?>
                <div class="mt-6 rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' ?>" role="alert">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="mt-8 space-y-5" data-validate novalidate>
                <?= csrf_field() ?>

                <div>
                    <label for="login" class="mb-1.5 block text-sm font-medium text-slate-700">Email or ID Number</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <input id="login" name="login" type="text" value="<?= e($login) ?>" required autocomplete="username" autofocus
                               placeholder="you@icct.edu.ph or ID number"
                               class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                    </div>
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password"
                               class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-12 text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        <button type="button" data-toggle-password="password" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-brand-600" aria-label="Show password">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Forgot password? Contact the IT office.</span>
                </div>

                <button type="submit" class="bg-brand-gradient flex w-full items-center justify-center gap-2 rounded-xl py-3.5 font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95 hover:shadow-xl disabled:opacity-70">
                    <span>Log in</span>
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-slate-500">
                Don't have an account?
                <a href="register.php" class="font-semibold text-brand-600 hover:text-brand-800">Register here</a>
            </p>
            <p class="mt-3 text-center text-sm">
                <a href="index.php" class="text-slate-400 hover:text-brand-600">&larr; Back to home</a>
            </p>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/chatbot.php'; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
