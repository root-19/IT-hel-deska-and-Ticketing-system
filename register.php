<?php
require_once __DIR__ . '/includes/functions.php';
redirect_if_logged_in();

// Only students self-register. Computer Lab, Faculty & Staff, IT Technician,
// and Admin accounts are created by the IT Admin (users.php).
$errors = [];
$old = [
    'first_name' => '',
    'last_name'  => '',
    'id_number'  => '',
    'email'      => '',
    'department' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $default) {
        $old[$key] = trim($_POST[$key] ?? $default);
    }
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please try again.';
    } else {
        if (mb_strlen($old['first_name']) < 2 || mb_strlen($old['first_name']) > 60) {
            $errors[] = 'First name must be 2–60 characters.';
        }
        if (mb_strlen($old['last_name']) < 2 || mb_strlen($old['last_name']) > 60) {
            $errors[] = 'Last name must be 2–60 characters.';
        }
        if (!preg_match('/^[A-Za-z0-9-]{4,30}$/', $old['id_number'])) {
            $errors[] = 'ID number must be 4–30 characters (letters, numbers, and dashes only).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($old['department']) > 100) {
            $errors[] = 'Course must be 100 characters or less.';
        }
        if (strlen($password) < MIN_PASSWORD_LENGTH || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters with uppercase, lowercase, and a number.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($_POST['terms'])) {
            $errors[] = 'You must agree to the terms and IT usage policy.';
        }
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare('SELECT email, id_number FROM users WHERE email = ? OR id_number = ?');
            $stmt->execute([$old['email'], $old['id_number']]);
            foreach ($stmt->fetchAll() as $row) {
                if (strcasecmp($row['email'], $old['email']) === 0) {
                    $errors[] = 'This email is already registered.';
                }
                if (strcasecmp($row['id_number'], $old['id_number']) === 0) {
                    $errors[] = 'This ID number is already registered.';
                }
            }

            if (!$errors) {
                $insert = db()->prepare(
                    "INSERT INTO users (id_number, first_name, last_name, email, role, department, password_hash)
                     VALUES (?, ?, ?, ?, 'student', ?, ?)"
                );
                $insert->execute([
                    $old['id_number'],
                    $old['first_name'],
                    $old['last_name'],
                    strtolower($old['email']),
                    $old['department'] !== '' ? $old['department'] : null,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);

                flash('success', 'Account created successfully! You can now log in.');
                redirect('login.php');
            }
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Unable to connect to the database. Please make sure MySQL is running and helpdesk_db is imported.';
        }
    }
}

$pageTitle = 'Register';
$asideTitle = 'Get IT support<br><span class="text-brand-gradient">the easy way.</span>';
$asideText = 'Create your account with your ICCT ID number and start submitting help desk tickets.';
include __DIR__ . '/includes/head.php';

$inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
?>
<body class="bg-slate-50 font-sans text-slate-700 antialiased">
<div class="flex min-h-screen">
    <?php include __DIR__ . '/includes/auth_aside.php'; ?>

    <main class="flex w-full items-center justify-center px-4 py-12 sm:px-8 lg:w-1/2">
        <div class="w-full max-w-xl">
            <!-- Mobile logo -->
            <a href="index.php" class="bg-brand-gradient mb-8 flex items-center gap-3 rounded-2xl p-4 lg:hidden">
                <?= logo('h-10 w-10') ?>
                <span class="text-lg font-bold text-white">ICCT IT Help Desk</span>
            </a>

            <h2 class="text-3xl font-bold text-slate-900">Create a student account</h2>
            <p class="mt-2 text-slate-500">Register to report student portal problems, login or account issues, and request technical assistance.</p>
            <p class="mt-3 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800">
                Computer Lab and Faculty &amp; Staff accounts are created by the IT Admin. Contact the IT Department if you need one.
            </p>

            <?php if ($errors): ?>
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="mt-8 space-y-5" data-validate novalidate>
                <?= csrf_field() ?>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="mb-1.5 block text-sm font-medium text-slate-700">First Name</label>
                        <input id="first_name" name="first_name" type="text" value="<?= e($old['first_name']) ?>" required minlength="2" maxlength="60" autocomplete="given-name" placeholder="Juan" class="<?= $inputClass ?>">
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="last_name" class="mb-1.5 block text-sm font-medium text-slate-700">Last Name</label>
                        <input id="last_name" name="last_name" type="text" value="<?= e($old['last_name']) ?>" required minlength="2" maxlength="60" autocomplete="family-name" placeholder="Dela Cruz" class="<?= $inputClass ?>">
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="id_number" class="mb-1.5 block text-sm font-medium text-slate-700">ICCT ID Number</label>
                        <input id="id_number" name="id_number" type="text" value="<?= e($old['id_number']) ?>" required pattern="[A-Za-z0-9\-]{4,30}" maxlength="30" placeholder="e.g. 2024-00123" class="<?= $inputClass ?>">
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email Address</label>
                        <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" required maxlength="120" autocomplete="email" placeholder="you@icct.edu.ph" class="<?= $inputClass ?>">
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                </div>

                <div>
                    <label for="department" class="mb-1.5 block text-sm font-medium text-slate-700">Course <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="department" name="department" type="text" value="<?= e($old['department']) ?>" maxlength="100" placeholder="e.g. BS Information Technology" class="<?= $inputClass ?>">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required minlength="<?= MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" placeholder="Min. <?= MIN_PASSWORD_LENGTH ?> characters" data-strength="password-strength" class="<?= $inputClass ?> pr-12">
                            <button type="button" data-toggle-password="password" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-brand-600" aria-label="Show password">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="password_confirm" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm Password</label>
                        <div class="relative">
                            <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password" placeholder="Re-enter password" data-match="password" class="<?= $inputClass ?> pr-12">
                            <button type="button" data-toggle-password="password_confirm" class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-brand-600" aria-label="Show password">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                    </div>
                </div>

                <!-- Password strength meter -->
                <div id="password-strength" class="-mt-1">
                    <div class="flex gap-1.5">
                        <span class="h-1.5 flex-1 rounded-full bg-slate-200"></span>
                        <span class="h-1.5 flex-1 rounded-full bg-slate-200"></span>
                        <span class="h-1.5 flex-1 rounded-full bg-slate-200"></span>
                        <span class="h-1.5 flex-1 rounded-full bg-slate-200"></span>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500" data-strength-label>Use 8+ characters with uppercase, lowercase, and a number.</p>
                </div>

                <div>
                    <label class="flex items-start gap-3 text-sm text-slate-600">
                        <input type="checkbox" name="terms" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span>I agree to the ICCT IT usage policy and confirm that the information above is accurate.</span>
                    </label>
                    <p class="field-error mt-1 hidden text-xs text-red-600"></p>
                </div>

                <button type="submit" class="bg-brand-gradient flex w-full items-center justify-center gap-2 rounded-xl py-3.5 font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95 hover:shadow-xl disabled:opacity-70">
                    <span>Create Account</span>
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-slate-500">
                Already have an account?
                <a href="login.php" class="font-semibold text-brand-600 hover:text-brand-800">Log in</a>
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
