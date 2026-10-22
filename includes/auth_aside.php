<?php
// Left-side gradient panel shared by login.php and register.php.
// Set $asideTitle and $asideText before including.
?>
<aside class="bg-brand-gradient relative hidden overflow-hidden lg:flex lg:w-1/2 lg:flex-col lg:justify-between lg:p-12 xl:p-16">
    <div class="grid-pattern absolute inset-0"></div>
    <div class="blob -left-20 top-1/3 h-72 w-72 bg-sky-400"></div>
    <div class="blob -right-16 bottom-10 h-80 w-80 bg-indigo-500"></div>

    <a href="index.php" class="relative flex items-center gap-3">
        <?= logo('h-12 w-12') ?>
        <div class="leading-tight">
            <span class="block text-sm font-semibold uppercase tracking-wider text-sky-200">ICCT Colleges</span>
            <span class="block text-xl font-bold text-white">IT Help Desk</span>
        </div>
    </a>

    <div class="relative">
        <h1 class="text-4xl font-extrabold leading-tight text-white xl:text-5xl"><?= $asideTitle ?></h1>
        <p class="mt-5 max-w-md text-lg text-blue-100"><?= e($asideText) ?></p>

        <ul class="mt-10 space-y-4 text-blue-50">
            <?php foreach (['Submit IT requests anytime, anywhere', 'Track your ticket status in real time', 'Get help from ICCT IT technicians'] as $point): ?>
                <li class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/15">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <?= e($point) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <p class="relative text-sm text-blue-200">&copy; <?= date('Y') ?> ICCT Colleges · IT Help Desk &amp; Ticketing System</p>
</aside>
