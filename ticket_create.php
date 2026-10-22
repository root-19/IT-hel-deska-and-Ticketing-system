<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_role('student', 'lab', 'faculty');

$categories = categories_for($user['role']);
$categoryIds = array_map('intval', array_column($categories, 'id'));

$errors = [];
$old = [
    'category_id' => in_array((int) ($_GET['category'] ?? 0), $categoryIds, true) ? (int) $_GET['category'] : 0,
    'subject'     => '',
    'description' => '',
    'location'    => $user['role'] === 'lab' ? (string) $user['department'] : '',
    'priority'    => 'medium',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['category_id'] = (int) ($_POST['category_id'] ?? 0);
    foreach (['subject', 'description', 'location', 'priority'] as $key) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please try again.';
    }
    if (!in_array($old['category_id'], $categoryIds, true)) {
        $errors[] = 'Please choose a category.';
    }
    if (mb_strlen($old['subject']) < 5 || mb_strlen($old['subject']) > 150) {
        $errors[] = 'Subject must be 5–150 characters.';
    }
    if (mb_strlen($old['description']) < 10 || mb_strlen($old['description']) > 5000) {
        $errors[] = 'Please describe the problem in 10–5000 characters.';
    }
    if (mb_strlen($old['location']) > 120) {
        $errors[] = 'Location must be 120 characters or less.';
    }
    if ($user['role'] === 'lab' && $old['location'] === '') {
        $errors[] = 'Please enter the laboratory room and PC number.';
    }
    if (!array_key_exists($old['priority'], TICKET_PRIORITIES)) {
        $errors[] = 'Please choose a priority.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO tickets (requester_id, category_id, subject, description, location, priority) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $user['id'],
            $old['category_id'],
            $old['subject'],
            $old['description'],
            $old['location'] !== '' ? $old['location'] : null,
            $old['priority'],
        ]);
        $ticketId = (int) $pdo->lastInsertId();
        log_activity($ticketId, $user['id'], 'created', 'Submitted the ticket.');
        notify(admin_ids(), $ticketId, 'New ticket ' . ticket_no($ticketId) . ' from ' . full_name($user) . ': ' . $old['subject']);
        $pdo->commit();

        flash('success', 'Ticket ' . ticket_no($ticketId) . ' submitted! The IT team will assign a technician soon.');
        redirect('ticket.php?id=' . $ticketId);
    }
}

$pageTitle = 'Submit a Ticket';
$activeNav = 'create';
include __DIR__ . '/includes/app_top.php';

$inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
?>
<div class="mx-auto max-w-3xl">
    <p class="text-sm text-slate-500"><?= e(ROLES[$user['role']]['portal']) ?></p>
    <h2 class="text-2xl font-bold text-slate-900">What's the problem?</h2>
    <p class="mt-1 text-slate-500">Give us enough detail so the technician can fix it on the first visit.</p>

    <?php if ($errors): ?>
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <ul class="list-inside list-disc space-y-1">
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="ticket_create.php" class="mt-6 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8" data-validate novalidate>
        <?= csrf_field() ?>

        <fieldset>
            <legend class="mb-3 text-sm font-medium text-slate-700">Category</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach ($categories as $category): ?>
                    <label class="relative flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-brand-300 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-200">
                        <input type="radio" name="category_id" value="<?= (int) $category['id'] ?>" class="peer sr-only" required <?= $old['category_id'] === (int) $category['id'] ? 'checked' : '' ?>>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 peer-checked:bg-brand-600 peer-checked:text-white">
                            <?= category_icon($category['slug'], 'h-5 w-5') ?>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900"><?= e($category['name']) ?></span>
                            <span class="block text-xs text-slate-500"><?= e($category['description']) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="field-error mt-1 hidden text-xs text-red-600"></p>
        </fieldset>

        <div>
            <label for="subject" class="mb-1.5 block text-sm font-medium text-slate-700">Subject</label>
            <input id="subject" name="subject" type="text" value="<?= e($old['subject']) ?>" required minlength="5" maxlength="150"
                   placeholder="<?= e(match ($user['role']) { 'lab' => 'e.g. PC-12 won\'t turn on', 'faculty' => 'e.g. Can\'t share screen in online class', default => 'e.g. Can\'t see my grades on the portal' }) ?>"
                   class="<?= $inputClass ?>">
            <p class="field-error mt-1 hidden text-xs text-red-600"></p>
        </div>

        <div>
            <label for="description" class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
            <textarea id="description" name="description" rows="6" required minlength="10" maxlength="5000"
                      placeholder="What happened? When did it start? Any error message? What have you tried?"
                      class="<?= $inputClass ?>"><?= e($old['description']) ?></textarea>
            <p class="field-error mt-1 hidden text-xs text-red-600"></p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="location" class="mb-1.5 block text-sm font-medium text-slate-700"><?= e(location_label($user['role'])) ?></label>
                <input id="location" name="location" type="text" value="<?= e($old['location']) ?>" maxlength="120" <?= $user['role'] === 'lab' ? 'required' : '' ?>
                       placeholder="<?= $user['role'] === 'lab' ? 'e.g. Lab 1, PC-12' : 'e.g. Room 304, Main Building' ?>" class="<?= $inputClass ?>">
                <p class="field-error mt-1 hidden text-xs text-red-600"></p>
            </div>
            <div>
                <label for="priority" class="mb-1.5 block text-sm font-medium text-slate-700">Priority</label>
                <select id="priority" name="priority" class="<?= $inputClass ?>">
                    <option value="low" <?= $old['priority'] === 'low' ? 'selected' : '' ?>>Low – minor, can wait</option>
                    <option value="medium" <?= $old['priority'] === 'medium' ? 'selected' : '' ?>>Medium – affects my work</option>
                    <option value="high" <?= $old['priority'] === 'high' ? 'selected' : '' ?>>High – can't work or class affected</option>
                    <option value="urgent" <?= $old['priority'] === 'urgent' ? 'selected' : '' ?>>Urgent – many people affected</option>
                </select>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
            <a href="dashboard.php" class="rounded-xl border border-slate-300 px-6 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="bg-brand-gradient flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95">
                <span>Submit Ticket</span>
            </button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/includes/app_bottom.php'; ?>
