<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/knowledge.php';
$user = require_role('admin', 'technician');

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

$editing = null;
if ($action === 'edit') {
    $stmt = db()->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'Article not found.');
        redirect('knowledge.php');
    }
}

$old = ['title' => '', 'keywords' => '', 'content' => '', 'content_tl' => '', 'is_active' => 1];
if ($editing) {
    $old = array_intersect_key($editing, $old);
}

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if (!verify_csrf()) {
        flash('error', 'Your session has expired. Please try again.');
        redirect('knowledge.php');
    }

    if (in_array($postAction, ['toggle', 'delete'], true)) {
        $targetId = (int) ($_POST['id'] ?? 0);
        if ($postAction === 'toggle') {
            db()->prepare('UPDATE kb_articles SET is_active = 1 - is_active WHERE id = ?')->execute([$targetId]);
            flash('success', 'Article updated.');
        } else {
            db()->prepare('DELETE FROM kb_articles WHERE id = ?')->execute([$targetId]);
            flash('success', 'Article deleted.');
        }
        redirect('knowledge.php');
    }

    if (in_array($postAction, ['create', 'update'], true)) {
        foreach (['title', 'keywords', 'content', 'content_tl'] as $key) {
            $old[$key] = trim($_POST[$key] ?? '');
        }
        $old['is_active'] = empty($_POST['is_active']) ? 0 : 1;

        if (mb_strlen($old['title']) < 5 || mb_strlen($old['title']) > 150) {
            $errors[] = 'Title must be 5–150 characters.';
        }
        if (mb_strlen($old['keywords']) > 500) {
            $errors[] = 'Keywords must be 500 characters or less.';
        }
        if (mb_strlen($old['content']) < 20 || mb_strlen($old['content']) > 5000) {
            $errors[] = 'The English answer must be 20–5000 characters.';
        }
        if (mb_strlen($old['content_tl']) > 5000) {
            $errors[] = 'The Tagalog answer must be 5000 characters or less.';
        }

        if (!$errors) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM kb_articles WHERE title = ? AND id <> ?');
            $stmt->execute([$old['title'], $editing['id'] ?? 0]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'Another article already has that title.';
            }
        }

        if (!$errors) {
            $values = [$old['title'], $old['keywords'], $old['content'], $old['content_tl'] !== '' ? $old['content_tl'] : null, $old['is_active']];
            if ($postAction === 'create') {
                db()->prepare('INSERT INTO kb_articles (title, keywords, content, content_tl, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([...$values, $user['id']]);
                flash('success', 'Article added. The AI assistant can use it right away.');
            } else {
                db()->prepare('UPDATE kb_articles SET title = ?, keywords = ?, content = ?, content_tl = ?, is_active = ? WHERE id = ?')
                    ->execute([...$values, $editing['id']]);
                flash('success', 'Article updated.');
            }
            redirect('knowledge.php');
        }
        $action = $postAction === 'create' ? 'create' : 'edit';
    }
}

// ---------- List ----------
// With a search, list articles the way the AI ranks them, so staff can test what it will find
$search = trim($_GET['q'] ?? '');
$articles = [];
$tableMissing = false;
if ($action === 'list') {
    try {
        $articles = $search !== ''
            ? kb_search($search, 50, false)
            : db()->query('SELECT * FROM kb_articles ORDER BY is_active DESC, title')->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $tableMissing = true;
    }
}

$pageTitle = match ($action) { 'create' => 'Add Article', 'edit' => 'Edit Article', default => 'Knowledge Base' };
$activeNav = 'knowledge';
include __DIR__ . '/includes/app_top.php';

$inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100';
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="mx-auto max-w-3xl">
        <a href="knowledge.php" class="text-sm text-slate-500 hover:text-brand-700">&larr; Back to knowledge base</a>
        <h2 class="mt-2 text-2xl font-bold text-slate-900"><?= $action === 'create' ? 'Add an article' : 'Edit article' ?></h2>
        <p class="mt-1 text-slate-500">The AI assistant searches these articles and answers from them in English or Tagalog.</p>

        <?php if ($errors): ?>
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <ul class="list-inside list-disc space-y-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="knowledge.php?action=<?= $action ?><?= $editing ? '&id=' . (int) $editing['id'] : '' ?>" class="mt-6 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">

            <div>
                <label for="title" class="mb-1.5 block text-sm font-medium text-slate-700">Problem / Title</label>
                <input id="title" name="title" value="<?= e($old['title']) ?>" required maxlength="150" class="<?= $inputClass ?>" placeholder="e.g. Printer not printing or paper jam">
            </div>

            <div>
                <label for="keywords" class="mb-1.5 block text-sm font-medium text-slate-700">Keywords</label>
                <textarea id="keywords" name="keywords" rows="2" maxlength="500" class="<?= $inputClass ?>"
                          placeholder="printer, paper jam, toner, hindi nagpi print, naipit papel"><?= e($old['keywords']) ?></textarea>
                <p class="mt-1 text-xs text-slate-400">Comma-separated words people might type, in English <strong>and</strong> Tagalog. Keywords count most when matching a question.</p>
            </div>

            <div>
                <label for="content" class="mb-1.5 block text-sm font-medium text-slate-700">Answer (English)</label>
                <textarea id="content" name="content" rows="9" required maxlength="5000" class="<?= $inputClass ?>"
                          placeholder="Steps to fix the problem, one per line, and when to submit a ticket."><?= e($old['content']) ?></textarea>
            </div>

            <div>
                <label for="content_tl" class="mb-1.5 block text-sm font-medium text-slate-700">Answer (Tagalog) <span class="font-normal text-slate-400">(optional)</span></label>
                <textarea id="content_tl" name="content_tl" rows="9" maxlength="5000" class="<?= $inputClass ?>"
                          placeholder="Parehong sagot sa Tagalog o Taglish."><?= e($old['content_tl']) ?></textarea>
                <p class="mt-1 text-xs text-slate-400">The AI can translate on its own. This version is shown as-is when the AI is offline and the question is in Tagalog.</p>
            </div>

            <label class="flex items-center gap-3 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" <?= $old['is_active'] ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Active – the AI assistant may use this article
            </label>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                <a href="knowledge.php" class="rounded-xl border border-slate-300 px-6 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button class="bg-brand-gradient rounded-xl px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95"><?= $action === 'create' ? 'Add Article' : 'Save Changes' ?></button>
            </div>
        </form>
    </div>

<?php else: ?>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">Articles the AI assistant searches before it answers. Search below to test what it finds for a question.</p>
        <a href="knowledge.php?action=create" class="bg-brand-gradient inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 transition hover:opacity-95">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Article
        </a>
    </div>

    <form method="GET" class="mt-4 flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Type a question, e.g. walang internet sa lab 3" class="<?= $inputClass ?>">
        <button class="rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
        <?php if ($search !== ''): ?><a href="knowledge.php" class="flex items-center rounded-xl px-3 text-sm text-slate-500 hover:bg-slate-100">Clear</a><?php endif; ?>
    </form>

    <?php if ($tableMissing): ?>
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
            The knowledge base table doesn't exist yet. Import <code>database/migrations/004_knowledge_base.sql</code> in phpMyAdmin.
        </div>
    <?php endif; ?>

    <section class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Article</th>
                        <?php if ($search !== ''): ?><th class="px-5 py-3 text-right font-semibold">Match</th><?php endif; ?>
                        <th class="px-5 py-3 font-semibold">Languages</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($articles as $row): ?>
                        <tr class="<?= $row['is_active'] ? '' : 'bg-slate-50 text-slate-400' ?>">
                            <td class="px-5 py-3">
                                <span class="block font-medium <?= $row['is_active'] ? 'text-slate-900' : '' ?>"><?= e($row['title']) ?></span>
                                <span class="line-clamp-1 text-xs text-slate-400"><?= e($row['keywords']) ?></span>
                            </td>
                            <?php if ($search !== ''): ?><td class="px-5 py-3 text-right tabular-nums"><?= (int) $row['score'] ?></td><?php endif; ?>
                            <td class="whitespace-nowrap px-5 py-3"><?= $row['content_tl'] ? 'English, Tagalog' : 'English' ?></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $row['is_active'] ? 'text-emerald-700' : 'text-slate-500' ?>">
                                    <span class="h-2 w-2 rounded-full <?= $row['is_active'] ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span><?= $row['is_active'] ? 'Active' : 'Hidden' ?>
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1">
                                    <a href="knowledge.php?action=edit&id=<?= (int) $row['id'] ?>" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-50">Edit</a>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100"><?= $row['is_active'] ? 'Hide' : 'Show' ?></button>
                                    </form>
                                    <form method="POST" data-confirm="Delete &quot;<?= e($row['title']) ?>&quot;? This can't be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$articles && !$tableMissing): ?>
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400"><?= $search !== '' ? 'No article matches that question. The AI will answer from general IT knowledge.' : 'No articles yet.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_bottom.php'; ?>
