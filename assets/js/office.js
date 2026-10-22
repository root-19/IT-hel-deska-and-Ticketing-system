// Virtual IT Office - an office map where the IT team and visitors walk around as robots,
// with the viewer's tickets shown where they are. Data comes from api/office.php.
(() => {
    const root = document.getElementById('virtual-office');
    if (!root) return;

    const REFRESH_MS = 10000;
    const WALK_SPEED = 9;     // map units (% of width) per second
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
    const INBOX = [12, 48];
    // Admins stand behind the counter, three to a row, extra rows further back
    const adminSpot = (slot) => [7 + (slot % 3) * 5, 41 - Math.floor(slot / 3) * 12];
    // Around the counter, then (when going to the Repair Bay) down and through the door
    const adminExit = (actor) => [[21.5, homeOf(actor)[1]], [21.5, 52], [21.5, 84], [27, 84]];
    const BREAK_SPOTS = [[31, 92], [40, 92], [45, 76], [55, 76], [70, 74]];
    // Seats: robots walk to `front`, then sit at (x, y), drawn just in front of the sofa (z)
    const seatRow = (name, xs, y, frontY, z) => xs.map((x, i) => ({ key: `${name}-${i}`, x, y, front: [x, frontY], z }));
    const COUCH_SEATS = seatRow('couch', [61.5, 66, 70.5], 94, 99, 975); // break area couch
    const SOFA_SEATS = seatRow('sofa', [83.5, 90.5], 31, 38, 345);       // lobby sofa, roomy enough for name tags

    const $ = (name) => root.querySelector(`[data-office-${name}]`);
    const esc = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const rand = (min, max) => min + Math.random() * (max - min);
    const pick = (list) => list[Math.floor(Math.random() * list.length)];
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const jitter = ([x, y], amount = 1.5) => [x + rand(-amount, amount), y + rand(-amount / 2, amount / 2)];

    let data = null;
    let timer = null;
    let desks = new Map(); // technician id -> {x, y}

    // ---------- Artwork ----------
    // Robot avatar. state: working | available | away | offline
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

    // ---------- Sitting on sofas ----------
    // A seat is reserved as soon as a robot heads for it, so two robots never pick the same one
    const takenSeats = new Set();
    const freeSeat = (seats) => {
        const free = seats.filter((seat) => !takenSeats.has(seat.key));
        if (!free.length) return null;
        const seat = pick(free);
        takenSeats.add(seat.key);
        return seat;
    };

    const sit = async (actor, seat) => {
        actor.seat = seat;
        actor.el.classList.add('is-sitting');
        setPos(actor, [seat.x, seat.y], 0.3);
        actor.el.style.zIndex = seat.z;
        await sleep(300);
    };

    const stand = async (actor) => {
        const seat = actor.seat;
        actor.seat = null;
        takenSeats.delete(seat.key);
        actor.el.classList.remove('is-sitting');
        setPos(actor, seat.front, 0.3);
        await sleep(300);
    };

    const deskOf = (actor) => desks.get(actor.id);
    const homeOf = (actor) => {
        if (actor.kind === 'admin') return adminSpot(actor.slot);
        const desk = deskOf(actor);
        return desk ? [desk.x - 1, desk.y - 4] : [49, 60];
    };
    // Path from a technician's chair, through the gap beside the desk, into the aisle
    const deskExit = (actor) => {
        const desk = deskOf(actor);
        return desk ? [[desk.x + 5.6, desk.y - 4], [desk.x + 5.6, desk.y + 8]] : [];
    };
    const inFrontOf = (desk) => [desk.x + rand(-2, 2), desk.y + 8]; // below the name plate

    // Decides the robot's next trip: {path, dwell, returns, stays, seat}
    const nextTrip = (actor) => {
        const roll = Math.random();
        if (actor.kind === 'visitor') {
            const seat = roll < 0.3 ? freeSeat(SOFA_SEATS) : null;
            return seat
                ? { path: [seat.front], seat, dwell: rand(4000, 9000) }
                : { path: [[rand(78, 96), rand(44, 95)]], dwell: rand(1500, 5000) };
        }
        if (actor.kind === 'admin') {
            if (actor.state !== 'available' || roll < 0.45) return null;
            const deskList = [...desks.values()];
            const exit = adminExit(actor);
            if (roll < 0.8 && deskList.length) {
                return { path: [...exit, inFrontOf(pick(deskList))], dwell: 2500, returns: true, delivering: true };
            }
            return { path: [exit[0], exit[1], [rand(5, 19), rand(58, 92)]], dwell: rand(2000, 4000), returns: true };
        }
        // Technician
        if (actor.state === 'working') return null;
        if (actor.state === 'away') {
            if (actor.onCouch) return null;
            const seat = freeSeat(COUCH_SEATS);
            return { path: [...deskExit(actor), seat ? seat.front : jitter(pick(BREAK_SPOTS))], seat, stays: true, couch: true };
        }
        if (roll < 0.4) return null;
        const breakSeat = roll < 0.55 ? freeSeat(COUCH_SEATS) : null;
        if (breakSeat) {
            return { path: [...deskExit(actor), breakSeat.front], seat: breakSeat, dwell: rand(4000, 8000), returns: true };
        }
        const others = [...desks.entries()].filter(([id]) => id !== actor.id).map(([, desk]) => desk);
        const target = roll < 0.65 && others.length ? inFrontOf(pick(others)) : jitter(pick(BREAK_SPOTS));
        return { path: [...deskExit(actor), target], dwell: rand(2500, 6000), returns: true };
    };

    const run = async (actor) => {
        const token = actor.token;
        const resting = actor.state === 'away' && actor.onCouch;
        if (actor.seat && !resting) await stand(actor);
        // Away from home with new orders: head back first
        if (actor.wayHome && !resting) {
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
        actor.seatClaim = trip.seat || null;
        const arrived = await walk(actor, trip.path, token);
        actor.seatClaim = null;
        if (!arrived) {
            if (trip.seat) takenSeats.delete(trip.seat.key); // never sat down
            return;
        }
        if (trip.seat) await sit(actor, trip.seat);
        if (trip.couch) {
            actor.onCouch = true;
            paint(actor);
        }
        if (trip.stays || !trip.returns) {
            if (trip.dwell) await sleep(trip.dwell);
            return; // a seated robot stands up at the start of its next run
        }
        await sleep(trip.dwell);
        if (actor.token !== token) return;
        if (actor.seat) await stand(actor);
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
        const ticket = actor.kind === 'technician' ? myTickets().find((t) => t.stage === 2 && t.tech_id === actor.id) : null;
        let bubble = '';
        if (ticket) {
            bubble = ticketChip(ticket);
        } else if (actor.delivering) {
            bubble = '<span class="office-bubble">🎫</span>';
        } else if (actor.state === 'away' && settled) {
            bubble = '<span class="office-bubble office-zzz">z z</span>';
        } else if (actor.state === 'working' && atHome) {
            bubble = '<span class="office-bubble"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></span>';
        }
        const look = actor.state === 'working' && atHome ? 'working' : actor.state === 'away' && settled ? 'away' : 'available';
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

        const adminSlots = new Map(data.staff.filter((p) => p.role === 'admin').map((p, i) => [p.id, i]));
        data.staff.filter((p) => p.presence !== 'offline').forEach((person) => {
            const key = `staff-${person.id}`;
            wanted.add(key);
            let actor = actors.get(key);
            const slot = adminSlots.get(person.id);
            if (!actor) {
                actor = spawn(key, { kind: person.role, id: person.id, slot, color: ROLE_COLORS[person.role], tag: person.first, state: person.presence });
            } else if (actor.state !== person.presence) {
                actor.state = person.presence;
                actor.token++;             // cancel whatever it was doing
                actor.nextAt = 0;
                actor.el.classList.remove('is-walking');
                actor.delivering = false;
            }
            actor.slot = slot;
            if (!actor.busy && !actor.wayHome) setPos(actor, homeOf(actor)); // desks may have moved
            paint(actor);
        });

        // Visitors in the lobby, up to data.max per role: named faculty and lab robots,
        // anonymous students, and the viewer as "You"
        const visit = (key, role, tag) => {
            wanted.add(key);
            if (!actors.get(key)) {
                spawn(key, {
                    kind: 'visitor', color: ROLE_COLORS[role], state: 'available', start: [rand(78, 96), rand(44, 95)],
                    tag: key === 'me' ? 'You' : tag, tagClass: key === 'me' ? 'is-me' : '',
                });
            }
        };
        data.guests.forEach((guest) => visit(guest.me ? 'me' : `guest-${guest.id}`, guest.role, guest.name));
        const meIsStudent = data.me.role === 'student';
        if (meIsStudent) visit('me', 'student');
        const students = Math.min(data.lobby.student, data.max) - (meIsStudent ? 1 : 0);
        for (let i = 0; i < students; i++) visit(`student-${i}`, 'student');
        actors.forEach((actor) => actor.kind === 'visitor' && paint(actor));

        actors.forEach((actor, key) => {
            if (!wanted.has(key)) {
                actor.token++;
                [actor.seat, actor.seatClaim].forEach((seat) => seat && takenSeats.delete(seat.key));
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

    // ---------- Desks and legend ----------
    const myTickets = () => data?.tickets ?? [];

    const layoutDesks = (techs) => {
        const cols = Math.min(4, Math.max(1, techs.length));
        const rows = Math.ceil(techs.length / cols);
        desks = new Map(techs.map((tech, i) => {
            const col = i % cols;
            const row = Math.floor(i / cols);
            const x = cols === 1 ? 49 : 32 + col * (34 / (cols - 1));
            const y = rows <= 1 ? 42 : 28 + row * (34 / (rows - 1));
            return [tech.id, { x, y }];
        }));
    };

    const drawDesks = () => {
        const techs = data.staff.filter((p) => p.role === 'technician');
        const admins = data.staff.filter((p) => p.role === 'admin');
        layoutDesks(techs);

        const deskHtml = techs.map((tech) => {
            const { x, y } = desks.get(tech.id);
            const presence = PRESENCE[tech.presence];
            const plate = `<span class="office-plate"><span class="h-1.5 w-1.5 rounded-full ${presence.dot}"></span>${esc(tech.name)}</span>`;
            // The plate sits just in front of its desk, so robots walking past draw over it
            return item(x, y, 8, ART.desk, tech.presence === 'working' ? 'is-busy' : '')
                + `<div class="office-label" style="left:${x}%;top:${y + 1}%;z-index:${Math.round(y * 10) + 2}">${plate}</div>`;
        }).join('');

        const adminOnline = admins.some((a) => a.presence !== 'offline');
        const inbox = `
            <div class="office-label" style="left:${INBOX[0]}%;top:${INBOX[1]}%;z-index:900">
                ${myTickets().filter((t) => t.stage === 1).map(ticketChip).join('')}
                <span class="office-inbox" title="Tickets waiting for a technician">📥 ${data.waiting}</span>
            </div>
            ${adminOnline ? '' : `<div class="office-label" style="left:12%;top:56%"><span class="office-plate">IT Admin offline</span></div>`}`;

        $('desks').innerHTML = deskHtml + inbox
            + (techs.length ? '' : '<div class="office-label" style="left:49%;top:40%"><span class="office-plate">No technicians yet</span></div>');
    };

    const drawLegend = () => {
        const online = LOBBY.map(([role, label]) => `${data.lobby[role]} ${label}`).join(' · ');
        $('legend').innerHTML = Object.values(PRESENCE).slice(0, 3)
            .map((p) => `<span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full ${p.dot}"></span>${p.label}</span>`)
            .join('') + `<span>Online in the lobby: ${online}</span><span>Inbox: ${data.waiting} waiting</span>`;
    };

    const render = () => {
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
            render();
            setLive(true);
        } catch (err) {
            setLive(false);
        }
        if (!document.hidden) timer = setTimeout(refresh, REFRESH_MS);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(timer);
        else refresh();
    });

    drawMap();
    refresh();
    setInterval(tick, 500);
})();
