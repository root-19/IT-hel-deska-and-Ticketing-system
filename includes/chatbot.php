<?php
// Floating AI assistant: black sparkle button that opens a chat panel. Include before </body>.
$aiSparkle = '<path d="M12 3c.6 5.4 3.6 8.4 9 9-5.4.6-8.4 3.6-9 9-.6-5.4-3.6-8.4-9-9 5.4-.6 8.4-3.6 9-9z"/>'
    . '<path opacity=".65" d="M19 2c.25 1.9 1.1 2.75 3 3-1.9.25-2.75 1.1-3 3-.25-1.9-1.1-2.75-3-3 1.9-.25 2.75-1.1 3-3z"/>';
$chatSuggestions = ["Wi-Fi won't connect", 'I forgot my password', 'Projector has no display', 'How do I submit a ticket?'];
?>
<div id="chatbot" class="fixed bottom-4 right-4 z-[60] flex flex-col items-end gap-4 sm:bottom-6 sm:right-6">

    <!-- Chat panel -->
    <section id="chat-panel" role="dialog" aria-label="ICCT AI Assistant" aria-hidden="true"
             class="chat-panel flex h-[min(600px,calc(100dvh-7rem))] w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-brand-950/30 sm:w-96">

        <header class="bg-brand-gradient relative flex items-center gap-3 px-4 py-4">
            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-950 text-white ring-2 ring-white/30">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= $aiSparkle ?></svg>
                <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-brand-700"></span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold leading-tight text-white">ICCT AI Assistant</p>
                <p class="text-xs text-sky-100">Online · usually replies instantly</p>
            </div>
            <button id="chat-reset" type="button" class="rounded-lg p-2 text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Start a new chat" title="New chat">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
            <button id="chat-close" type="button" class="rounded-lg p-2 text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Close chat">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </header>

        <div id="chat-messages" class="flex-1 space-y-4 overflow-y-auto bg-slate-50 px-4 py-5" aria-live="polite"></div>

        <div id="chat-suggestions" class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50 px-4 pb-3 pt-1">
            <?php foreach ($chatSuggestions as $suggestion): ?>
                <button type="button" class="rounded-full border border-brand-200 bg-white px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:border-brand-400 hover:bg-brand-50"><?= e($suggestion) ?></button>
            <?php endforeach; ?>
        </div>

        <form id="chat-form" class="flex items-end gap-2 border-t border-slate-200 bg-white p-3">
            <label for="chat-input" class="sr-only">Type your message</label>
            <textarea id="chat-input" rows="1" maxlength="1000" placeholder="Describe your IT problem…"
                      class="max-h-32 min-h-[44px] flex-1 resize-none rounded-2xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100"></textarea>
            <button id="chat-send" type="submit" class="bg-brand-gradient flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white shadow-lg shadow-brand-200 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Send message">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
        </form>
        <p class="bg-white px-4 pb-3 text-center text-[11px] text-slate-400">AI can make mistakes. For urgent issues, submit a ticket.</p>
    </section>

    <!-- Floating AI button -->
    <div class="flex items-center gap-3">
        <span id="chat-hint" class="hidden rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-lg ring-1 ring-slate-200 sm:block">Need help? <span class="text-brand-600">Ask AI</span></span>
        <button id="chat-toggle" type="button" aria-controls="chat-panel" aria-expanded="false" aria-label="Open AI assistant"
                class="ai-fab relative flex h-14 w-14 items-center justify-center rounded-full bg-neutral-950 text-white shadow-xl shadow-black/30 ring-1 ring-white/10 transition hover:scale-105 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">
            <svg class="chat-icon-open h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= $aiSparkle ?></svg>
            <svg class="chat-icon-close hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>

<template id="chat-bot-avatar">
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-950 text-white">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= $aiSparkle ?></svg>
    </span>
</template>

<script src="assets/js/chatbot.js"></script>
