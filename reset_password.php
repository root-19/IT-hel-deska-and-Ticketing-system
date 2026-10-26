<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
redirect_if_logged_in();

$pending = $_SESSION['reset_pending'] ?? null;
if (!$pending) {
    redirect('forgot_password.php');
}

function restart_reset(string $message): never
{
    unset($_SESSION['reset_pending']);
    flash('error', $message);
    redirect('login.php');
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$pending['user_id']]);
$user = $stmt->fetch();
if (!$user || $user['status'] !== 'active') {
    restart_reset('Your account is inactive. Please contact the IT Department.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'reset';
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($action === 'resend') {
        $wait = $pending['sent_at'] + OTP_RESEND_COOLDOWN - time();
        if ($wait > 0) {
            $errors[] = "Please wait {$wait} second(s) before requesting a new code.";
        } elseif (send_password_reset_otp($user)) {
            flash('success', 'A new code has been sent to your email.');
            redirect('reset_password.php');
        } else {
            $errors[] = "We couldn't send a new code. Please try again later or contact the IT Department.";
        }
    } elseif ($pending['expires'] < time()) {
        $errors[] = 'This code has expired. Please request a new one.';
    } elseif (strlen($code) !== OTP_LENGTH) {
        $errors[] = 'Please enter the ' . OTP_LENGTH . '-digit code from your email.';
    } elseif (!password_verify($code, $pending['hash'])) {
        $_SESSION['reset_pending']['attempts']++;
        if ($_SESSION['reset_pending']['attempts'] >= OTP_MAX_ATTEMPTS) {
            restart_reset('Too many incorrect codes. Please request a new password reset.');
        }
        $left = OTP_MAX_ATTEMPTS - $_SESSION['reset_pending']['attempts'];
        $errors[] = "Incorrect code. You have {$left} attempt(s) left.";
    } elseif (!valid_password($new)) {
        $errors[] = 'New password must be at least 8 characters with uppercase, lowercase, and a number.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        unset($_SESSION['reset_pending'], $_SESSION['login_attempts'], $_SESSION['login_locked_until']);
        flash('success', 'Your password was changed. You can now log in with your new password.');
        redirect('login.php');
    }
}

$resendIn = max(0, $_SESSION['reset_pending']['sent_at'] + OTP_RESEND_COOLDOWN - time());
$flash = get_flash();
$pageTitle = 'Reset password';
$asideTitle = 'Set a new<br><span class="text-brand-gradient">password</span>';
$asideText = 'Enter the code from your email and choose a new password for your account.';
$inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
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

            <h2 class="text-3xl font-bold text-slate-900">Set a new password</h2>
            <p class="mt-2 text-slate-500">
                Enter the <?= OTP_LENGTH ?>-digit code we sent to
                <span class="font-medium text-slate-700"><?= e(mask_email($user['email'])) ?></span>.
                It expires in <?= (int) ceil(OTP_TTL / 60) ?> minutes.
            </p>

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

            <form method="POST" action="reset_password.php" class="mt-8 space-y-5" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset">

                <div>
                    <label for="code" class="mb-1.5 block text-sm font-medium text-slate-700">Reset code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
                           minlength="<?= OTP_LENGTH ?>" maxlength="<?= OTP_LENGTH ?>" placeholder="<?= str_repeat('•', OTP_LENGTH) ?>"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-center text-2xl font-semibold tracking-[0.5em] text-slate-900 placeholder-slate-300 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="new_password" class="mb-1.5 block text-sm font-medium text-slate-700">New password</label>
                    <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password"
                           placeholder="Min. 8 characters" class="<?= $inputClass ?>">
                    <p class="mt-1 text-xs text-slate-400">At least 8 characters with uppercase, lowercase, and a number.</p>
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="confirm_password" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password"
                           placeholder="Type it again" class="<?= $inputClass ?>">
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>

                <button type="submit" class="bg-brand-gradient flex w-full items-center justify-center gap-2 rounded-xl py-3.5 font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95 hover:shadow-xl disabled:opacity-70">
                    <span>Change password</span>
                </button>
            </form>

            <form method="POST" action="reset_password.php" class="mt-6 text-center text-sm text-slate-500">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend">
                Didn't get the code? Check your spam folder, or
                <button type="submit" id="resend-btn" data-wait="<?= $resendIn ?>" class="font-semibold text-brand-600 hover:text-brand-800 disabled:cursor-not-allowed disabled:text-slate-400">
                    resend code
                </button>
            </form>

            <p class="mt-8 text-center text-sm">
                <a href="login.php" class="text-slate-400 hover:text-brand-600">&larr; Back to log in</a>
            </p>
        </div>
    </main>
</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
    // Keep the code field digits-only and count down the resend cooldown
    document.getElementById('code').addEventListener('input', (event) => {
        event.target.value = event.target.value.replace(/\D/g, '');
    });

    const resendBtn = document.getElementById('resend-btn');
    let wait = Number(resendBtn.dataset.wait);
    const tick = () => {
        resendBtn.disabled = wait > 0;
        resendBtn.textContent = wait > 0 ? `resend code (${wait}s)` : 'resend code';
        if (wait-- > 0) setTimeout(tick, 1000);
    };
    tick();
</script>
</body>
</html>
