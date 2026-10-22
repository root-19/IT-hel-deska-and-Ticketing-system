// Virtual IT Office - an office map where the IT team and visitors walk around as robots,
// plus a tracker showing where the viewer's ticket is. Data comes from api/office.php.
(() => {
    const root = document.getElementById('virtual-office');
    if (!root) return;

    const REFRESH_MS = 10000;
    const STAGE_COUNT = 4;
    const WALK_SPEED = 9;     // map units (% of width) per second
    const MAX_VISITORS = 8;   // lobby robots drawn besides "You"
    const STILL = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
    const LOBBY = [['student', 'students'], ['lab', 'labs'], ['faculty', 'faculty & staff']];

    // ---------- Map layout ----------
    // Coordinates are % of the map's width (x) and height (y). Everything is anchored at its bottom centre.
    const FURNITURE = [
        ['counter', 12, 46, 14],
        ['plant', 3.5, 24, 3.2], ['plant', 3.5, 97, 3.2],
        ['coffee', 31, 86, 3.4], ['cooler', 40, 86, 2.6], ['plant', 49, 85, 2.8],
        ['couch', 66, 97, 14],
        ['sofa', 87, 34, 15], ['plant', 97, 24, 3.2], ['plant', 77, 97, 3.2],
    ];
    const INBOX = [16, 37.5];
    const ADMIN_HOME = [11, 41];
    const ADMIN_EXIT = [[21.5, 41], [21.5, 52], [21.5, 84], [27, 84]]; // around the counter, through the door
    const BREAK_SPOTS = [[31, 92], [40, 92], [45, 76], [55, 76], [70, 74]];
    const COUCH_SPOTS = [[61, 92], [66, 92], [71, 92]];
    const SOFA_SPOTS = [[83, 30], [87, 30], [91, 30]];

    const $ = (name) => root.querySelector(`[data-office-${name}]`);
    const esc = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const rand = (min, max) => min + Math.random() * (max - min);
    const pick = (list) => list[Math.floor(Math.random() * list.length)];
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const jitter = ([x, y], amount = 1.5) => [x + rand(-amount, amount), y + rand(-amount / 2, amount / 2)];

    let data = null;
    let selectedId = null;
    let timer = null;
    let desks = new Map(); // technician id -> {x, y}

    // ---------- Artwork ----------
    // Robot avatar. state: working | available | away | offline | guide
    const bot = (color, state) => `
        <svg viewBox="0 0 64 72" class="bot bot-${state}" aria-hidden="true">
            <g class="bot-legs" fill="#475569">
                <rect class="leg-l" x="24" y="55" width="6" height="14" rx="3"/>
                <rect class="leg-r" x="34" y="55" width="6" height="14" rx="3"/>
            </g>
            <g class="bot-body">
                <line x1="32" y1="6" x2="32" y2="13" stroke="#94a3b8" stroke-width="2"/>
                <circle class="bot-antenna" cx="32" cy="5" r="3" fill="${color}"/>
                <rect class="arm-l" x="13" y="40" width="6" height="13" rx="3" fill="${color}"/>
                <rect class="arm-r" x="45" y="40" width="6" height="13" rx="3" fill="${color}"/>
                <rect x="20" y="38" width="24" height="19" rx="5" fill="${color}" opacity=".85"/>
                <rect x="16" y="13" width="32" height="24" rx="8" fill="${color}"/>
                <rect x="21" y="18" width="22" height="13" rx="5" fill="#0f172a"/>
                <g class="bot-eyes" fill="#7dd3fc">
                    <circle cx="28" cy="24.5" r="2.4"/><circle cx="36" cy="24.5" r="2.4"/>
                </g>
            </g>
        </svg>`;

    const ART = {
        counter: `<svg viewBox="0 0 120 40"><rect x="0" y="4" width="120" height="8" rx="3" fill="#c4b5fd"/><rect x="4" y="12" width="112" height="28" rx="3" fill="#ede9fe" stroke="#c4b5fd"/><rect x="14" y="20" width="92" height="3" rx="1.5" fill="#ddd6fe"/><text x="60" y="34" text-anchor="middle" font-size="7" font-weight="700" fill="#7c3aed" letter-spacing="1">IT HELP DESK</text></svg>`,
        desk: `<svg viewBox="0 0 80 44"><rect x="0" y="8" width="80" height="8" rx="3" fill="#cbd5e1"/><rect x="4" y="16" width="72" height="28" rx="3" fill="#e2e8f0" stroke="#cbd5e1"/><rect class="desk-screen" x="52" y="-6" width="22" height="14" rx="2" fill="#334155"/><rect x="61" y="7" width="4" height="2" fill="#64748b"/></svg>`,
        plant: `<svg viewBox="0 0 30 40"><path d="M15 22C6 20 3 10 6 4c5 4 9 10 9 18zm0 0c9-2 12-12 9-18-5 4-9 10-9 18z" fill="#22c55e"/><path d="M15 24c-6-4-12-2-13 2 5 1 9 0 13-2zm0 0c6-4 12-2 13 2-5 1-9 0-13-2z" fill="#16a34a"/><path d="M7 26h16l-3 14H10z" fill="#b45309"/></svg>`,
        coffee: `<svg viewBox="0 0 30 44"><rect x="2" y="0" width="26" height="44" rx="3" fill="#475569"/><rect x="6" y="5" width="18" height="8" rx="2" fill="#7dd3fc"/><rect x="8" y="22" width="14" height="14" rx="1" fill="#1e293b"/><rect x="11" y="28" width="8" height="8" rx="1" fill="#f8fafc"/></svg>`,
        cooler: `<svg viewBox="0 0 24 50"><rect x="5" y="0" width="14" height="16" rx="5" fill="#7dd3fc" opacity=".85"/><rect x="2" y="16" width="20" height="34" rx="3" fill="#e2e8f0" stroke="#cbd5e1"/><rect x="9" y="24" width="6" height="3" rx="1" fill="#0ea5e9"/></svg>`,
        couch: `<svg viewBox="0 0 100 40"><rect x="6" y="0" width="88" height="22" rx="8" fill="#fb923c"/><rect x="0" y="14" width="100" height="22" rx="8" fill="#fdba74"/><rect x="4" y="36" width="6" height="4" fill="#9a3412"/><rect x="90" y="36" width="6" height="4" fill="#9a3412"/></svg>`,
        sofa: `<svg viewBox="0 0 100 40"><rect x="6" y="0" width="88" height="22" rx="8" fill="#2dd4bf"/><rect x="0" y="14" width="100" height="22" rx="8" fill="#5eead4"/><rect x="4" y="36" width="6" height="4" fill="#115e59"/><rect x="90" y="36" width="6" height="4" fill="#115e59"/></svg>`,
    };

    // Places an item on the map; deeper (higher y) items draw in front
    const item = (x, y, width, html, extra = '') =>
        `<div class="office-item ${extra}" style="left:${x}%;top:${y}%;width:${width}%;z-index:${Math.round(y * 10)}">${html}</div>`;

    const ticketChip = (ticket) =>
        `<span class="office-chip">🎫 Your ticket ${esc(ticket.no)}</span>`;

    const drawMap = () => {
        $('map').innerHTML = `
            <div class="office-room" style="left:0;top:0;width:24%;height:100%;background:#f5f3ff"><span>Front Desk</span></div>
            <div class="office-room office-tiles" style="left:24%;top:0;width:50%;height:70%"><span>Repair Bay</span></div>
            <div class="office-room" style="left:24%;top:70%;width:50%;height:30%;background:#fff7ed"><span>Break Area</span></div>
            <div class="office-room" style="left:74%;top:0;width:26%;height:100%;background:#ecfeff"><span>Lobby</span></div>
            <div class="office-wall" style="left:24%;top:0;height:76%"></div>
            <div class="office-wall" style="left:24%;top:92%;height:8%"></div>
            <div class="office-wall" style="left:74%;top:0;height:76%"></div>
            <div class="office-wall" style="left:74%;top:92%;height:8%"></div>
            ${FURNITURE.map(([type, x, y, w]) => item(x, y, w, ART[type])).join('')}
            <div data-office-desks></div>
            <div data-office-actors></div>`;
    };

    // ---------- Robots that walk around ----------
    const actors = new Map();

    const setPos = (actor, [x, y], seconds = 0) => {
        actor.x = x;
        actor.y = y;
        actor.el.style.transitionDuration = `${seconds}s`;
        actor.el.style.left = `${x}%`;
        actor.el.style.top = `${y}%`;
        actor.el.style.zIndex = Math.round(y * 10) + 1;
    };

    const distance = (actor, [x, y]) => Math.hypot(x - actor.x, (y - actor.y) * 9 / 16);

    // Walks through each point in turn; stops early if the robot was given new orders
    const walk = async (actor, points, token) => {
        for (const point of points) {
            if (actor.token !== token) return false;
            actor.el.classList.add('is-walking');
            const seconds = distance(actor, point) / WALK_SPEED;
            setPos(actor, point, seconds);
            await sleep(seconds * 1000);
        }
        if (actor.token === token) actor.el.classList.remove('is-walking');
        return actor.token === token;
    };

    const deskOf = (actor) => desks.get(actor.id);
    const homeOf = (actor) => {
        if (actor.kind === 'admin') return ADMIN_HOME;
        const desk = deskOf(actor);
        return desk ? [desk.x - 1.5, desk.y - 4] : [49, 60];
    };
    // Path from a technician's chair out from behind the desk into the aisle
    const deskExit = (actor) => {
        const desk = deskOf(actor);
        return desk ? [[desk.x + 7.5, desk.y - 4], [desk.x + 7.5, desk.y + 4]] : [];
    };
    const inFrontOf = (desk) => [desk.x + rand(-2, 2), desk.y + 4];

    // Decides the robot's next trip: {path, dwell, returns, stays}
    const nextTrip = (actor) => {
        const roll = Math.random();
        if (actor.kind === 'visitor') {
            const spot = roll < 0.25 ? jitter(pick(SOFA_SPOTS), 0.6) : [rand(78, 96), rand(44, 95)];
            return { path: [spot], dwell: rand(1500, 5000) };
        }
        if (actor.kind === 'admin') {
            if (actor.state !== 'available' || roll < 0.45) return null;
            const deskList = [...desks.values()];
            if (roll < 0.8 && deskList.length) {
                return { path: [...ADMIN_EXIT, inFrontOf(pick(deskList))], dwell: 2500, returns: true, delivering: true };
            }
            return { path: [ADMIN_EXIT[0], ADMIN_EXIT[1], [rand(5, 19), rand(58, 92)]], dwell: rand(2000, 4000), returns: true };
        }
        // Technician
        if (actor.state === 'working') return null;
        if (actor.state === 'away') {
            return actor.onCouch ? null : { path: [...deskExit(actor), jitter(COUCH_SPOTS[actor.id % COUCH_SPOTS.length], 0.8)], stays: true, couch: true };
        }
        if (roll < 0.4) return null;
        const others = [...desks.entries()].filter(([id]) => id !== actor.id).map(([, desk]) => desk);
        const target = roll < 0.65 && others.length ? inFrontOf(pick(others)) : jitter(pick(BREAK_SPOTS));
        return { path: [...deskExit(actor), target], dwell: rand(2500, 6000), returns: true };
    };

    const run = async (actor) => {
        const token = actor.token;
        // Away from home with new orders: head back first
        if (actor.wayHome && !(actor.state === 'away' && actor.onCouch)) {
            const ok = await walk(actor, actor.wayHome, token);
            if (ok) {
                actor.wayHome = null;
                actor.onCouch = false;
                paint(actor);
            }
            return;
        }
        const trip = nextTrip(actor);
        if (!trip) return;

        if (actor.kind !== 'visitor') {
            actor.wayHome = [...trip.path.slice(0, -1).reverse(), homeOf(actor)];
        }
        actor.delivering = !!trip.delivering;
        paint(actor);
        if (!(await walk(actor, trip.path, token))) return;
        if (trip.couch) {
            actor.onCouch = true;
            paint(actor);
        }
        if (trip.stays || !trip.returns) {
            if (trip.dwell) await sleep(trip.dwell);
            return;
        }
        await sleep(trip.dwell);
        actor.delivering = false;
        paint(actor);
        if (await walk(actor, actor.wayHome, token)) {
            actor.wayHome = null;
            paint(actor);
        }
    };

    // Speech bubble, name tag, and look of a robot for its current state
    const paint = (actor) => {
        const atHome = !actor.wayHome;
        const settled = atHome || actor.onCouch;
        const ticket = currentTicket();
        let bubble = '';
        if (actor.kind === 'technician' && ticket?.stage === 2 && ticket.tech_id === actor.id) {
            bubble = ticketChip(ticket);
        } else if (actor.delivering) {
            bubble = '<span class="office-bubble">🎫</span>';
        } else if (actor.state === 'away' && settled) {
            bubble = '<span class="office-bubble office-zzz">z z</span>';
        } else if (actor.state === 'working' && atHome) {
            bubble = '<span class="office-bubble"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></span>';
        }
        const look =actor.state === 'working' && atHome ? 'working' : actor.state === 'away' && settled ? 'away' : 'available';
        actor.el.querySelector('.office-actor-bubble').innerHTML = bubble;
        actor.el.querySelector('.office-actor-bot').innerHTML = bot(actor.color, look);
    };

    const spawn = (key, props) => {
        const el = document.createElement('div');
        el.className = 'office-actor';
        el.innerHTML = `<div class="office-actor-bubble"></div>${props.tag ? `<div class="office-actor-tag ${props.tagClass || ''}">${esc(props.tag)}</div>` : ''}<div class="office-actor-bot"></div>`;
        $('actors').appendChild(el);
        const actor = { key, el, token: 0, busy: false, nextAt: Date.now() + rand(500, 3000), wayHome: null, onCouch: false, ...props };
        actors.set(key, actor);
        setPos(actor, props.start || homeOf(actor));
        return actor;
    };

    // Brings the robots in line with the latest data
    const syncActors = () => {
        const wanted = new Set();

        data.staff.filter((p) => p.presence !== 'offline').forEach((person) => {
            const key = `staff-${person.id}`;
            wanted.add(key);
            let actor = actors.get(key);
            if (!actor) {
                actor = spawn(key, { kind: person.role, id: person.id, color: ROLE_COLORS[person.role], tag: person.first, state: person.presence });
            } else if (actor.state !== person.presence) {
                actor.state = person.presence;
                actor.token++;             // cancel whatever it was doing
                actor.nextAt = 0;
                actor.el.classList.remove('is-walking');
                actor.delivering = false;
            }
            if (!actor.busy && !actor.wayHome) setPos(actor, homeOf(actor)); // desks may have moved
            paint(actor);
        });

        // Visitors in the lobby: "You" plus a sample of everyone else online
        const me = data.me.role;
        wanted.add('me');
        if (!actors.get('me')) {
            spawn('me', { kind: 'visitor', color: ROLE_COLORS[me], tag: 'You', tagClass: 'is-me', state: 'available', start: [rand(80, 94), rand(50, 90)] });
        }
        const others = LOBBY.flatMap(([role]) => Array(Math.max(0, data.lobby[role] - (role === me ? 1 : 0))).fill(role)).slice(0, MAX_VISITORS);
        others.forEach((role, i) => {
            const key = `visitor-${i}-${role}`;
            wanted.add(key);
            if (!actors.get(key)) {
                spawn(key, { kind: 'visitor', color: ROLE_COLORS[role], state: 'available', start: [rand(78, 96), rand(44, 95)] });
            }
        });
        actors.forEach((actor) => actor.kind === 'visitor' && paint(actor));

        actors.forEach((actor, key) => {
            if (!wanted.has(key)) {
                actor.token++;
                actor.el.classList.add('is-leaving');
                setTimeout(() => actor.el.remove(), 400);
                actors.delete(key);
            }
        });
    };

    // Moves every idle robot whose rest time is up
    const tick = () => {
        if (STILL || document.hidden) return;
        const now = Date.now();
        actors.forEach((actor) => {
            if (actor.busy || now < actor.nextAt) return;
            actor.busy = true;
            run(actor).finally(() => {
                actor.busy = false;
                actor.nextAt = Date.now() + rand(1500, 5000);
            });
        });
    };

    // ---------- Desks, legend, tracker ----------
    const currentTicket = () => data?.tickets.find((t) => t.id === selectedId) || null;

    const layoutDesks = (techs) => {
        const cols = Math.min(4, Math.max(1, techs.length));
        const rows = Math.ceil(techs.length / cols);
        desks = new Map(techs.map((tech, i) => {
            const col = i % cols;
            const row = Math.floor(i / cols);
            const x = cols === 1 ? 49 : 32 + col * (34 / (cols - 1));
            const y = rows <= 1 ? 42 : 26 + row * (36 / (rows - 1));
            return [tech.id, { x, y }];
        }));
    };

    const drawDesks = () => {
        const ticket = currentTicket();
        const techs = data.staff.filter((p) => p.role === 'technician');
        const admins = data.staff.filter((p) => p.role === 'admin');
        layoutDesks(techs);

        const deskHtml = techs.map((tech) => {
            const { x, y } = desks.get(tech.id);
            const presence = PRESENCE[tech.presence];
            const plate = `<span class="office-plate"><span class="h-1.5 w-1.5 rounded-full ${presence.dot}"></span>${esc(tech.name)}</span>`;
            return item(x, y, 9, ART.desk, tech.presence === 'working' ? 'is-busy' : '')
                + `<div class="office-label" style="left:${x}%;top:${y + 1}%">${plate}</div>`;
        }).join('');

        const adminOnline = admins.some((a) => a.presence !== 'offline');
        const inbox = `
            <div class="office-label" style="left:${INBOX[0]}%;top:${INBOX[1] - 7}%;z-index:900">
                ${ticket?.stage === 1 ? ticketChip(ticket) : ''}
                <span class="office-inbox" title="Tickets waiting for a technician">📥 ${data.waiting}</span>
            </div>
            ${adminOnline ? '' : `<div class="office-label" style="left:12%;top:49%"><span class="office-plate">IT Admin offline</span></div>`}`;

        $('desks').innerHTML = deskHtml + inbox
            + (techs.length ? '' : '<div class="office-label" style="left:49%;top:40%"><span class="office-plate">No technicians yet</span></div>');
    };

    const drawLegend = () => {
        const online = LOBBY.map(([role, label]) => `${data.lobby[role]} ${label}`).join(' · ');
        $('legend').innerHTML = Object.values(PRESENCE).slice(0, 3)
            .map((p) => `<span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full ${p.dot}"></span>${p.label}</span>`)
            .join('') + `<span>Online in the lobby: ${online}</span><span>Inbox: ${data.waiting} waiting</span>`;
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
        const guide = $('guide');
        const moved = guide.style.left !== `${pct}%`;
        $('progress').style.width = `${ticket ? pct : 0}%`;
        guide.style.left = `${pct}%`;
        guide.innerHTML = bot(ROLE_COLORS.technician, ticket ? 'guide' : 'away');
        if (moved && !STILL) {
            guide.classList.add('is-walking');
            setTimeout(() => guide.classList.remove('is-walking'), 800);
        }
        root.querySelectorAll('[data-office-stage]').forEach((li) => {
            li.querySelector('.office-stop').classList.toggle('is-reached', !!ticket && Number(li.dataset.officeStage) <= stage);
        });

        $('message').innerHTML = ticket
            ? `<a href="ticket.php?id=${ticket.id}" class="font-semibold hover:underline">${esc(ticket.no)}</a>
               <span class="text-brand-700">(${esc(ticket.status)}, updated ${esc(ticket.updated)})</span><br>${esc(ticket.message)}`
            : 'You have no tickets yet. <a href="ticket_create.php" class="font-semibold hover:underline">Submit a ticket</a> and watch it move through the office here.';
    };

    const render = () => {
        renderTabs();
        renderTrack(currentTicket());
        drawDesks();
        syncActors();
        drawLegend();
    };

    // ---------- Live updates ----------
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

    drawMap();
    refresh();
    setInterval(tick, 500);
})();
