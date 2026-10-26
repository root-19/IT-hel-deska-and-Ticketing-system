<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
redirect_if_logged_in();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $wait = ($_SESSION['reset_pending']['sent_at'] ?? 0) + OTP_RESEND_COOLDOWN - time();

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ($wait > 0) {
        $errors[] = "Please wait {$wait} second(s) before requesting another code.";
    } else {
        try {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $errors[] = "We couldn't find an account with that email. Check the spelling, or register if you're a student.";
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'Your account is inactive. Please contact the IT Department.';
            } elseif (send_password_reset_otp($user)) {
                redirect('reset_password.php');
            } else {
                unset($_SESSION['reset_pending']);
                $errors[] = "We couldn't send the reset code by email. Please try again later or contact the IT Department.";
            }
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Unable to connect to the database. Please try again later.';
        }
    }
}

$pageTitle = 'Forgot password';
$asideTitle = 'Forgot your<br><span class="text-brand-gradient">password?</span>';
$asideText = 'Enter the email on your account and we\'ll send you a code to set a new password.';
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

            <h2 class="text-3xl font-bold text-slate-900">Forgot password</h2>
            <p class="mt-2 text-slate-500">Enter your account's email. We'll send a <?= OTP_LENGTH ?>-digit code so you can set a new password.</p>

            <?php if ($errors): ?>
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php" class="mt-8 space-y-5" data-validate novalidate>
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="email" autofocus
                               placeholder="you@icct.edu.ph"
                               class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                    </div>
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>

                <button type="submit" class="bg-brand-gradient flex w-full items-center justify-center gap-2 rounded-xl py-3.5 font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95 hover:shadow-xl disabled:opacity-70">
                    <span>Send reset code</span>
                </button>
            </form>

            <p class="mt-8 text-center text-sm">
                <a href="login.php" class="text-slate-400 hover:text-brand-600">&larr; Back to log in</a>
            </p>
        </div>
    </main>
</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
