<?php
// Virtual IT Office: live map of the IT team at work and where the viewer's tickets are.
// Drawn and animated by assets/js/office.js from api/office.php.
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

    <!-- Office map: scrolls sideways on small screens -->
    <div class="p-5">
        <div class="overflow-x-auto rounded-2xl ring-1 ring-slate-200">
            <div data-office-map class="office-map relative min-w-[720px]" role="img"
                 aria-label="Map of the IT office showing the IT team and visitors as robots"></div>
        </div>
        <div data-office-legend class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500"></div>
    </div>
</section>
<script src="<?= asset('assets/js/office.js') ?>" defer></script>
