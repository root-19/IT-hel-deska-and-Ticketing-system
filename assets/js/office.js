// Virtual IT Office - draws the IT team as robots at their desks and tracks the viewer's ticket.
// Data comes from api/office.php and refreshes every few seconds.
(() => {
    const root = document.getElementById('virtual-office');
    if (!root) return;

    const REFRESH_MS = 10000;
    const STAGE_COUNT = 4;
    const ROLE_COLORS = {
        admin: '#7c3aed',
        technician: '#1d53f2',
        student: '#0ea5e9',
        lab: '#10b981',
        faculty: '#f59e0b',
    };
    const PRESENCE = {
        working:   { label: 'Working',   dot: 'bg-amber-400' },
        available: { label: 'Available', dot: 'bg-emerald-500' },
        away:      { label: 'Away',      dot: 'bg-slate-300' },
        offline:   { label: 'Offline',   dot: 'bg-slate-300' },
    };
    const LOBBY = [['student', 'Students'], ['lab', 'Computer Labs'], ['faculty', 'Faculty & Staff']];

    const $ = (name) => root.querySelector(`[data-office-${name}]`);
    const esc = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    let data = null;
    let selectedId = null;
    let timer = null;

    // Robot avatar. state: working | available | away | offline | guide
    const bot = (color, state, withDesk = true) => `
        <svg viewBox="0 0 64 64" class="bot bot-${state}" aria-hidden="true">
            <g class="bot-body">
                <line x1="32" y1="6" x2="32" y2="13" stroke="#94a3b8" stroke-width="2"/>
                <circle class="bot-antenna" cx="32" cy="5" r="3" fill="${color}"/>
                <rect x="16" y="13" width="32" height="24" rx="8" fill="${color}"/>
                <rect x="21" y="18" width="22" height="13" rx="5" fill="#0f172a"/>
                <g class="bot-eyes" fill="#7dd3fc">
                    <circle cx="28" cy="24.5" r="2.4"/><circle cx="36" cy="24.5" r="2.4"/>
                </g>
                <rect x="22" y="38" width="20" height="${withDesk ? 12 : 16}" rx="4" fill="${color}" opacity=".85"/>
            </g>
            ${withDesk ? `
                <rect class="bot-screen" x="45" y="40" width="14" height="10" rx="1.5" fill="#334155"/>
                <rect x="4" y="50" width="56" height="5" rx="2" fill="#cbd5e1"/>` : ''}
            ${state === 'away' ? '<text class="bot-zzz" x="48" y="14" font-size="10" font-weight="700" fill="#94a3b8">z</text>' : ''}
        </svg>`;

    const myTicketChip = (ticket) => `
        <span class="office-chip mt-2 inline-flex max-w-full items-center gap-1.5 rounded-lg bg-brand-600 px-2 py-1 text-[11px] font-semibold text-white shadow">
            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            <span class="truncate">Your ticket ${esc(ticket.no)}</span>
        </span>`;

    const desk = (person, ticket) => {
        const presence = PRESENCE[person.presence];
        const isMine = ticket && ticket.stage === 2 && ticket.tech_id === person.id;
        const detail = person.presence === 'offline' || person.presence === 'away'
            ? `Seen ${esc(person.seen)}`
            : person.role === 'admin' ? 'Assigning tickets' : `${person.active} active ticket${person.active === 1 ? '' : 's'}`;
        return `
            <div class="office-desk ${isMine ? 'is-mine' : ''}">
                <div class="mx-auto h-20 w-20">${bot(ROLE_COLORS[person.role], person.presence)}</div>
                <p class="mt-1 truncate text-sm font-semibold text-slate-800" title="${esc(person.name)}">${esc(person.name)}</p>
                <p class="flex items-center justify-center gap-1.5 text-xs text-slate-500">
                    <span class="h-1.5 w-1.5 rounded-full ${presence.dot}"></span>${presence.label}
                </p>
                <p class="truncate text-[11px] text-slate-400">${detail}</p>
                ${isMine ? myTicketChip(ticket) : ''}
            </div>`;
    };

    const renderTabs = () => {
        $('tabs').innerHTML = data.tickets.map((t) => `
            <button type="button" data-ticket="${t.id}"
                    class="max-w-xs truncate rounded-full px-3 py-1.5 text-xs font-medium transition ${t.id === selectedId ? 'bg-brand-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}">
                ${esc(t.no)} · ${esc(t.subject)}
            </button>`).join('');
    };

    const renderTrack = (ticket) => {
        const stage = ticket ? ticket.stage : 0;
        const pct = (stage / (STAGE_COUNT - 1)) * 100;
        $('progress').style.width = `${ticket ? pct : 0}%`;
        const guide = $('guide');
        guide.style.left = `${pct}%`;
        guide.innerHTML = bot(ROLE_COLORS.technician, ticket ? 'guide' : 'away', false);
        root.querySelectorAll('[data-office-stage]').forEach((li) => {
            const reached = ticket && Number(li.dataset.officeStage) <= stage;
            li.querySelector('.office-stop').classList.toggle('is-reached', !!reached);
        });

        const message = $('message');
        if (ticket) {
            message.innerHTML = `<a href="ticket.php?id=${ticket.id}" class="font-semibold hover:underline">${esc(ticket.no)}</a>
                <span class="text-brand-700">(${esc(ticket.status)}, updated ${esc(ticket.updated)})</span><br>${esc(ticket.message)}`;
        } else {
            message.innerHTML = 'You have no tickets yet. <a href="ticket_create.php" class="font-semibold hover:underline">Submit a ticket</a> and watch it move through the office here.';
        }
    };

    const render = () => {
        const ticket = data.tickets.find((t) => t.id === selectedId) || null;
        renderTabs();
        renderTrack(ticket);

        const admins = data.staff.filter((p) => p.role === 'admin');
        const techs = data.staff.filter((p) => p.role === 'technician');
        $('admins').innerHTML = admins.map((p) => desk(p, ticket)).join('') || '<p class="text-sm text-slate-400">No admins yet.</p>';
        $('techs').innerHTML = techs.map((p) => desk(p, ticket)).join('') || '<p class="text-sm text-slate-400">No technicians yet.</p>';

        $('inbox').innerHTML = `
            <div class="rounded-xl border border-dashed border-slate-300 bg-white/70 p-3 text-center">
                <p class="text-xs font-medium text-slate-500">Inbox</p>
                <p class="text-lg font-bold tabular-nums text-slate-800">${data.waiting}</p>
                <p class="text-[11px] text-slate-400">waiting for a technician</p>
                ${ticket && ticket.stage === 1 ? myTicketChip(ticket) : ''}
            </div>`;

        $('lobby').innerHTML = LOBBY.map(([role, label]) => `
            <div class="flex flex-col items-center gap-1 rounded-xl bg-white/70 p-2 text-center lg:flex-row lg:gap-3 lg:text-left">
                <div class="h-12 w-12 shrink-0">${bot(ROLE_COLORS[role], data.lobby[role] > 0 ? 'available' : 'offline', false)}</div>
                <div class="min-w-0">
                    <p class="text-lg font-bold leading-none tabular-nums text-slate-800">${data.lobby[role]}</p>
                    <p class="truncate text-[11px] text-slate-500">${label}</p>
                </div>
            </div>`).join('');
    };

    const setLive = (ok) => {
        $('live').classList.toggle('bg-emerald-500', ok);
        $('live').classList.toggle('bg-slate-400', !ok);
        $('updated').textContent = ok
            ? `Live · ${new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' })}`
            : 'Reconnecting…';
    };

    const refresh = async () => {
        clearTimeout(timer);
        try {
            const res = await fetch('api/office.php', { headers: { Accept: 'application/json' } });
            if (res.status === 401) {
                window.location.href = 'login.php';
                return;
            }
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            data = await res.json();
            if (!data.tickets.some((t) => t.id === selectedId)) {
                selectedId = data.tickets[0]?.id ?? null;
            }
            render();
            setLive(true);
        } catch (err) {
            setLive(false);
        }
        if (!document.hidden) timer = setTimeout(refresh, REFRESH_MS);
    };

    $('tabs').addEventListener('click', (event) => {
        const button = event.target.closest('[data-ticket]');
        if (!button || !data) return;
        selectedId = Number(button.dataset.ticket);
        render();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(timer);
        else refresh();
    });

    refresh();
})();
