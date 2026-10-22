<?php
// Virtual IT Office: live map of the IT team at work and where the viewer's ticket is.
// Drawn and animated by assets/js/office.js from api/office.php.
$officeStages = ['Submitted', 'Front Desk', 'Technician', 'Done'];
?>
<section id="virtual-office" class="mt-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
            <h3 class="font-semibold text-slate-900">Virtual IT Office</h3>
            <p class="text-sm text-slate-500">See the IT team at work and follow your ticket live.</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
            <span data-office-live class="office-live-dot h-2 w-2 rounded-full bg-slate-400"></span>
            <span data-office-updated>Connecting…</span>
        </span>
    </div>

    <!-- Ticket tracker -->
    <div class="px-5 pt-5">
        <div data-office-tabs class="flex flex-wrap gap-2"></div>

        <div class="relative mx-4 mb-2 mt-20 sm:mx-10">
            <div class="h-1.5 rounded-full bg-slate-200"></div>
            <div data-office-progress class="office-progress absolute left-0 top-0 h-1.5 rounded-full bg-brand-500" style="width:0"></div>
            <div data-office-guide class="office-guide absolute -top-20 h-20 w-16 -translate-x-1/2" style="left:0" aria-hidden="true"></div>
            <ol class="absolute inset-x-0 -top-1.5 flex justify-between">
                <?php foreach ($officeStages as $i => $stage): ?>
                    <li class="relative flex flex-col items-center" data-office-stage="<?= $i ?>">
                        <span class="office-stop h-4 w-4 rounded-full border-2 border-slate-300 bg-white"></span>
                        <span class="absolute top-6 whitespace-nowrap text-xs font-medium text-slate-400"><?= $stage ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <p data-office-message aria-live="polite" class="mt-10 rounded-2xl bg-brand-50 px-4 py-3 text-sm text-brand-900">
            Loading the office…
        </p>
    </div>

    <!-- Office map: scrolls sideways on small screens -->
    <div class="p-5">
        <div class="overflow-x-auto rounded-2xl ring-1 ring-slate-200">
            <div data-office-map class="office-map relative min-w-[720px]" role="img"
                 aria-label="Map of the IT office showing the IT team and visitors as robots"></div>
        </div>
        <div data-office-legend class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500"></div>
    </div>
</section>
<script src="assets/js/office.js" defer></script>
