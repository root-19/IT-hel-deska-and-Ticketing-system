// ICCT IT Help Desk - floating AI assistant
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('chatbot');
    if (!root) return;

    const panel = document.getElementById('chat-panel');
    const toggle = document.getElementById('chat-toggle');
    const hint = document.getElementById('chat-hint');
    const list = document.getElementById('chat-messages');
    const suggestions = document.getElementById('chat-suggestions');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const sendBtn = document.getElementById('chat-send');
    const avatarTemplate = document.getElementById('chat-bot-avatar');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const STORAGE_KEY = 'icct-chat-history';
    const MAX_HISTORY = 12;
    const WELCOME = "Hi! I'm the ICCT AI Assistant. Tell me what's wrong, whether it's Wi-Fi, your account, a printer, or software, and I'll help you troubleshoot or file a ticket.";

    let history = loadHistory();
    let busy = false;

    // ---------- History (kept for this browser tab only) ----------
    function loadHistory() {
        try {
            const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY));
            return Array.isArray(saved) ? saved : [];
        } catch {
            return [];
        }
    }

    function saveHistory() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-MAX_HISTORY)));
        } catch {
            // Storage unavailable (private mode) - chat still works for this page view
        }
    }

    // ---------- Rendering ----------
    function scrollToBottom() {
        list.scrollTop = list.scrollHeight;
    }

    function addBubble(role, text, { error = false } = {}) {
        const row = document.createElement('div');
        const bubble = document.createElement('div');
        bubble.textContent = text;
        bubble.className = 'max-w-[80%] whitespace-pre-line break-words rounded-2xl px-4 py-2.5 text-sm leading-relaxed';

        if (role === 'user') {
            row.className = 'chat-msg flex justify-end';
            bubble.classList.add('bg-brand-gradient', 'rounded-br-md', 'text-white', 'shadow');
        } else {
            row.className = 'chat-msg flex items-end gap-2';
            row.appendChild(avatarTemplate.content.cloneNode(true));
            bubble.classList.add('rounded-bl-md', 'shadow-sm', 'ring-1');
            bubble.classList.add(...(error
                ? ['bg-red-50', 'text-red-700', 'ring-red-200']
                : ['bg-white', 'text-slate-700', 'ring-slate-200']));
        }

        row.appendChild(bubble);
        list.appendChild(row);
        scrollToBottom();
    }

    function showTyping() {
        const row = document.createElement('div');
        row.className = 'chat-msg flex items-end gap-2';
        row.setAttribute('aria-label', 'Assistant is typing');
        row.appendChild(avatarTemplate.content.cloneNode(true));
        const dots = document.createElement('div');
        dots.className = 'flex gap-1 rounded-2xl rounded-bl-md bg-white px-4 py-3.5 shadow-sm ring-1 ring-slate-200';
        dots.innerHTML = '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>';
        row.appendChild(dots);
        list.appendChild(row);
        scrollToBottom();
        return row;
    }

    function renderAll() {
        list.innerHTML = '';
        addBubble('assistant', WELCOME);
        history.forEach((message) => addBubble(message.role, message.content));
        suggestions.classList.toggle('hidden', history.length > 0);
    }

    // ---------- Open / close ----------
    function isOpen() {
        return panel.classList.contains('is-open');
    }

    function setOpen(open) {
        panel.classList.toggle('is-open', open);
        panel.setAttribute('aria-hidden', String(!open));
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close AI assistant' : 'Open AI assistant');
        toggle.querySelector('.chat-icon-open').classList.toggle('hidden', open);
        toggle.querySelector('.chat-icon-close').classList.toggle('hidden', !open);
        hint?.classList.add('sm:hidden');

        if (open) {
            scrollToBottom();
            setTimeout(() => input.focus(), 200);
        }
    }

    toggle.addEventListener('click', () => setOpen(!isOpen()));
    document.getElementById('chat-close').addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            setOpen(false);
            toggle.focus();
        }
    });

    document.getElementById('chat-reset').addEventListener('click', () => {
        if (busy) return;
        history = [];
        saveHistory();
        renderAll();
        input.focus();
    });

    // ---------- Sending ----------
    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 128) + 'px';
    }

    function setBusy(state) {
        busy = state;
        sendBtn.disabled = state;
        input.readOnly = state;
    }

    async function send(text) {
        text = text.trim();
        if (!text || busy) return;

        setBusy(true);
        history.push({ role: 'user', content: text });
        addBubble('user', text);
        input.value = '';
        autoResize();
        suggestions.classList.add('hidden');
        const typing = showTyping();

        try {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ messages: history.slice(-MAX_HISTORY) }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.reply) {
                throw new Error(data.error || 'Something went wrong. Please try again.');
            }

            history.push({ role: 'assistant', content: data.reply });
            typing.remove();
            addBubble('assistant', data.reply);
        } catch (error) {
            // Drop the unanswered message so the next request keeps a valid user/assistant order
            history.pop();
            typing.remove();
            addBubble('assistant', error.message || 'Unable to reach the assistant. Check your connection.', { error: true });
        } finally {
            setBusy(false);
            saveHistory();
            input.focus();
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        send(input.value);
    });

    input.addEventListener('input', autoResize);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            send(input.value);
        }
    });

    suggestions.querySelectorAll('button').forEach((button) =>
        button.addEventListener('click', () => send(button.textContent))
    );

    renderAll();
});
