<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Home';
include __DIR__ . '/includes/head.php';
?>
<body class="bg-slate-50 font-sans text-slate-700 antialiased">

<!-- ===================== NAVBAR ===================== -->
<header id="navbar" class="fixed inset-x-0 top-0 z-50 transition-all duration-300">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="index.php" class="flex items-center gap-3">
            <?= logo('h-11 w-11') ?>
            <div class="leading-tight">
                <span class="block text-sm font-semibold uppercase tracking-wider text-sky-200">ICCT Colleges</span>
                <span class="block text-lg font-bold text-white">IT Help Desk</span>
            </div>
        </a>

        <ul class="hidden items-center gap-8 text-sm font-medium text-blue-100 md:flex">
            <li><a href="#features" class="transition hover:text-white">Features</a></li>
            <li><a href="#how-it-works" class="transition hover:text-white">How It Works</a></li>
            <li><a href="#services" class="transition hover:text-white">Services</a></li>
            <li><a href="#faq" class="transition hover:text-white">FAQ</a></li>
        </ul>

        <div class="hidden items-center gap-3 md:flex">
            <?php if (is_logged_in()): ?>
                <a href="dashboard.php" class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-brand-800 shadow-lg shadow-black/10 transition hover:bg-sky-50">Go to Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="rounded-full px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">Log in</a>
                <a href="register.php" class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-brand-800 shadow-lg shadow-black/10 transition hover:bg-sky-50">Register</a>
            <?php endif; ?>
        </div>

        <button id="menu-btn" type="button" class="rounded-lg p-2 text-white hover:bg-white/10 md:hidden" aria-label="Open menu" aria-expanded="false">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </nav>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden border-t border-white/10 bg-brand-950/95 px-4 pb-6 pt-2 backdrop-blur md:hidden">
        <a href="#features" class="block rounded-lg px-3 py-2 text-blue-100 hover:bg-white/10">Features</a>
        <a href="#how-it-works" class="block rounded-lg px-3 py-2 text-blue-100 hover:bg-white/10">How It Works</a>
        <a href="#services" class="block rounded-lg px-3 py-2 text-blue-100 hover:bg-white/10">Services</a>
        <a href="#faq" class="block rounded-lg px-3 py-2 text-blue-100 hover:bg-white/10">FAQ</a>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <?php if (is_logged_in()): ?>
                <a href="dashboard.php" class="col-span-2 rounded-full bg-white py-2.5 text-center text-sm font-semibold text-brand-800">Go to Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="rounded-full border border-white/30 py-2.5 text-center text-sm font-semibold text-white">Log in</a>
                <a href="register.php" class="rounded-full bg-white py-2.5 text-center text-sm font-semibold text-brand-800">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ===================== HERO ===================== -->
<section class="bg-brand-gradient relative overflow-hidden pb-24 pt-36 lg:pb-32 lg:pt-44">
    <div class="grid-pattern absolute inset-0"></div>
    <div class="blob -left-24 top-20 h-72 w-72 bg-sky-400"></div>
    <div class="blob -right-20 bottom-0 h-96 w-96 bg-indigo-500"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-medium text-sky-100 backdrop-blur">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
                IT Support is online
            </span>
            <h1 class="mt-6 text-4xl font-extrabold leading-tight text-white sm:text-5xl lg:text-6xl">
                Tech problems?<br>
                <span class="text-brand-gradient">We've got you covered.</span>
            </h1>
            <p class="mt-6 max-w-xl text-lg text-blue-100">
                The ICCT IT Help Desk lets students, faculty, and staff report technical issues,
                track requests in real time, and get them resolved faster — all in one place.
            </p>
            <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                <a href="<?= is_logged_in() ? 'dashboard.php' : 'register.php' ?>" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-8 py-3.5 font-semibold text-brand-800 shadow-xl shadow-black/20 transition hover:-translate-y-0.5 hover:bg-sky-50">
                    Submit a Ticket
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                <a href="<?= is_logged_in() ? 'dashboard.php' : 'login.php' ?>" class="inline-flex items-center justify-center rounded-full border border-white/30 px-8 py-3.5 font-semibold text-white transition hover:bg-white/10">
                    Track My Ticket
                </a>
            </div>
            <dl class="mt-12 grid max-w-md grid-cols-3 gap-6 border-t border-white/15 pt-8">
                <div><dt class="text-xs uppercase tracking-wide text-sky-200">Availability</dt><dd class="mt-1 text-2xl font-bold text-white">24/7</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-sky-200">Avg. response</dt><dd class="mt-1 text-2xl font-bold text-white">&lt; 1 hr</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-sky-200">Campuses</dt><dd class="mt-1 text-2xl font-bold text-white">All</dd></div>
            </dl>
        </div>

        <!-- Hero mock ticket card -->
        <div class="relative mx-auto w-full max-w-md lg:max-w-none">
            <div class="rounded-3xl border border-white/20 bg-white/10 p-6 shadow-2xl backdrop-blur-xl">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-white">Recent Tickets</p>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs text-sky-100">Live</span>
                </div>
                <ul class="mt-5 space-y-3">
                    <li class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">Wi-Fi not connecting in Room 304</p>
                            <p class="text-xs text-slate-500">#TKT-1042 · Network</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">In Progress</span>
                    </li>
                    <li class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">Reset student portal password</p>
                            <p class="text-xs text-slate-500">#TKT-1041 · Account</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">Resolved</span>
                    </li>
                    <li class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">Printer jam at the Library</p>
                            <p class="text-xs text-slate-500">#TKT-1040 · Hardware</p>
                        </div>
                        <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-medium text-brand-700">Open</span>
                    </li>
                </ul>
            </div>
            <div class="absolute -bottom-6 -left-6 hidden rounded-2xl bg-white p-4 shadow-xl sm:block">
                <p class="text-xs text-slate-500">Tickets resolved this week</p>
                <p class="text-2xl font-bold text-brand-700">128</p>
            </div>
        </div>
    </div>
</section>

<!-- ===================== FEATURES ===================== -->
<section id="features" class="py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">Features</p>
            <h2 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">Everything you need for fast IT support</h2>
            <p class="mt-4 text-slate-500">A simple, organized way to request help and stay updated until your issue is fixed.</p>
        </div>

        <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <?php
            $features = [
                ['Easy Ticket Submission', 'Report an issue in seconds with a guided form — attach details so technicians can act quickly.', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['Real-Time Tracking', 'See every status change — Open, In Progress, Resolved — the moment it happens.', 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['Priority & Assignment', 'Tickets are prioritized and routed to the right technician automatically.', 'M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12'],
                ['Secure Accounts', 'Your ICCT ID and data are protected with encrypted passwords and secure sessions.', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
            ];
            foreach ($features as [$title, $desc, $icon]): ?>
                <div class="reveal group rounded-3xl border border-slate-200 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-100">
                    <span class="bg-brand-gradient flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-lg shadow-brand-200">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                    </span>
                    <h3 class="mt-6 text-lg font-semibold text-slate-900"><?= e($title) ?></h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500"><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== HOW IT WORKS ===================== -->
<section id="how-it-works" class="bg-white py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">How It Works</p>
            <h2 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">From problem to solution in 4 steps</h2>
        </div>

        <ol class="relative mt-16 grid gap-10 md:grid-cols-4">
            <div class="absolute left-0 right-0 top-8 hidden h-0.5 bg-gradient-to-r from-brand-100 via-brand-400 to-sky-300 md:block"></div>
            <?php
            $steps = [
                ['Create an account', 'Register using your ICCT ID number and email.'],
                ['Submit a ticket', 'Describe the issue, choose a category, and set its urgency.'],
                ['Get assigned', 'An IT technician picks up your ticket and keeps you updated.'],
                ['Issue resolved', 'Confirm the fix and rate the support you received.'],
            ];
            foreach ($steps as $i => [$title, $desc]): ?>
                <li class="reveal relative text-center" style="transition-delay: <?= $i * 100 ?>ms">
                    <span class="bg-brand-gradient relative mx-auto flex h-16 w-16 items-center justify-center rounded-full text-xl font-bold text-white ring-8 ring-white shadow-lg"><?= $i + 1 ?></span>
                    <h3 class="mt-6 font-semibold text-slate-900"><?= e($title) ?></h3>
                    <p class="mt-2 text-sm text-slate-500"><?= e($desc) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- ===================== SERVICES ===================== -->
<section id="services" class="py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">Support Areas</p>
            <h2 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">A help desk for every part of the school</h2>
            <p class="mt-4 text-slate-500">Each group has its own portal and dashboard, so requests reach the right IT personnel faster.</p>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <?php
            $areas = [
                ['Student Support', 'Students · register online', 'student_portal', [
                    'Report student portal problems',
                    'Report login or account issues',
                    'Request technical assistance',
                ]],
                ['Computer Laboratory Support', 'Lab accounts · created by IT Admin', 'lab_computer', [
                    'Report broken or defective computers',
                    'Report internet or printer problems',
                    'Request software installation',
                ]],
                ['Faculty & Staff Support', 'Faculty & staff · created by IT Admin', 'faculty_online', [
                    'Technical support for teachers',
                    'Online class and school system problems',
                    'Email, account, and equipment requests',
                ]],
                ['IT Admin Dashboard', 'IT Department', 'faculty_systems', [
                    'View all reported issues',
                    'Assign IT personnel to tickets',
                    'Track unresolved and completed tickets',
                    'Generate reports on school IT problems',
                ]],
            ];
            foreach ($areas as $i => [$title, $who, $icon, $points]): ?>
                <div class="reveal flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-100" style="transition-delay: <?= $i * 80 ?>ms">
                    <span class="bg-brand-gradient flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg shadow-brand-200"><?= category_icon($icon) ?></span>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900"><?= e($title) ?></h3>
                    <p class="text-xs font-medium text-brand-600"><?= e($who) ?></p>
                    <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                        <?php foreach ($points as $point): ?>
                            <li class="flex gap-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <?= e($point) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== FAQ ===================== -->
<section id="faq" class="bg-white py-24">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="reveal text-center">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">FAQ</p>
            <h2 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">Frequently asked questions</h2>
        </div>

        <div class="mt-12 space-y-4">
            <?php
            $faqs = [
                ['Who can use the IT Help Desk?', 'Students can register here using their ICCT ID number. Computer Laboratory and Faculty & Staff accounts are created by the IT Admin. Contact the IT Department to get one.'],
                ['How long before my ticket is answered?', 'Most tickets receive a first response within an hour during office hours. Urgent issues are prioritized.'],
                ['Can I track the status of my request?', 'Yes. After logging in, your dashboard shows every ticket you submitted and its current status.'],
                ['I forgot my password. What should I do?', 'Visit the IT office or contact the help desk so a technician can verify your identity and reset your account.'],
            ];
            foreach ($faqs as [$q, $a]): ?>
                <details class="reveal group rounded-2xl border border-slate-200 bg-slate-50 p-6 open:bg-white open:shadow-lg">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-slate-900">
                        <?= e($q) ?>
                        <svg class="h-5 w-5 shrink-0 text-brand-600 transition group-open:rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </summary>
                    <p class="mt-4 text-sm leading-relaxed text-slate-500"><?= e($a) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== CTA ===================== -->
<section class="px-4 py-24 sm:px-6 lg:px-8">
    <div class="bg-brand-gradient reveal relative mx-auto max-w-5xl overflow-hidden rounded-[2rem] px-8 py-16 text-center shadow-2xl shadow-brand-300 sm:px-16">
        <div class="grid-pattern absolute inset-0"></div>
        <div class="relative">
            <h2 class="text-3xl font-bold text-white sm:text-4xl">Need IT help right now?</h2>
            <p class="mx-auto mt-4 max-w-xl text-blue-100">Create your free account and submit your first ticket in less than a minute.</p>
            <div class="mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                <a href="register.php" class="rounded-full bg-white px-8 py-3.5 font-semibold text-brand-800 shadow-lg transition hover:bg-sky-50">Create an Account</a>
                <a href="login.php" class="rounded-full border border-white/40 px-8 py-3.5 font-semibold text-white transition hover:bg-white/10">I already have an account</a>
            </div>
        </div>
    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="bg-brand-950 text-blue-200">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-3 lg:px-8">
        <div>
            <div class="flex items-center gap-3">
                <?= logo('h-10 w-10') ?>
                <span class="text-lg font-bold text-white">ICCT IT Help Desk</span>
            </div>
            <p class="mt-4 text-sm text-blue-300">Fast, reliable IT support for the ICCT Colleges community.</p>
        </div>
        <div>
            <h4 class="font-semibold text-white">Quick Links</h4>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="#features" class="hover:text-white">Features</a></li>
                <li><a href="#how-it-works" class="hover:text-white">How It Works</a></li>
                <li><a href="login.php" class="hover:text-white">Log in</a></li>
                <li><a href="register.php" class="hover:text-white">Register</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold text-white">Contact IT Support</h4>
            <ul class="mt-4 space-y-2 text-sm">
                <li>IT Department, ICCT Colleges</li>
                <li>Mon – Sat, 8:00 AM – 5:00 PM</li>
                <li>helpdesk@icct.edu.ph</li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10 py-6 text-center text-xs text-blue-300">
        &copy; <?= date('Y') ?> ICCT Colleges · IT Help Desk &amp; Ticketing System
    </div>
</footer>

<?php include __DIR__ . '/includes/chatbot.php'; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
