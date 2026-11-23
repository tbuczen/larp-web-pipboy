// Motion detector (proof of concept). Sharing is opt-in and runs only while
// this screen is open: the phone's position is sent every few seconds, and
// the radar shows other sharing PipBoys as anonymous blips (offsets only).
(() => {
    const root = document.querySelector('[data-motion]');
    if (!root) {
        return;
    }
    const toggle = root.querySelector('[data-motion-toggle]');
    const state = root.querySelector('[data-motion-state]');
    const badge = root.querySelector('[data-motion-badge]');
    const status = root.querySelector('[data-motion-status]');
    const layer = root.querySelector('[data-motion-blips]');
    const rangeLabel = root.querySelector('[data-motion-range-label]');
    const SEND_EVERY = 5000;
    const HEARTBEAT = 20000;
    const POLL_EVERY = 3000;
    const t = window.pipT ?? ((text) => text);
    const say = (text) => { status.textContent = text; };

    let range = 100;
    let watchId = null;
    let pollTimer = null;
    let lastSent = 0;
    let lastFix = null;

    const post = (url, body, keepalive = false) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': root.dataset.token },
        body: body ? JSON.stringify(body) : null,
        credentials: 'same-origin',
        keepalive,
    });

    const send = async (force = false) => {
        if (!lastFix || (!force && Date.now() - lastSent < SEND_EVERY)) {
            return;
        }
        lastSent = Date.now();
        const { latitude, longitude, accuracy } = lastFix.coords;
        try {
            const response = await post(root.dataset.positionUrl, { latitude, longitude, accuracy });
            if (!response.ok) {
                say(response.status === 403 ? t('SESSION EXPIRED - RELOAD THE PAGE') : t('UPLINK ERROR'));
            }
        } catch {
            say(t('NO SIGNAL - RETRYING'));
        }
    };

    const draw = (blips) => {
        layer.replaceChildren(...blips.map(({ x, y }) => {
            const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            dot.setAttribute('class', 'blip');
            dot.setAttribute('cx', String((x / range) * 100));
            dot.setAttribute('cy', String((-y / range) * 100));
            dot.setAttribute('r', '4');
            return dot;
        }));
    };

    const poll = async () => {
        if (document.hidden || watchId === null) {
            return;
        }
        try {
            const response = await fetch(`${root.dataset.blipsUrl}?range=${range}`, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            draw(data.blips);
            if (!data.sharing) {
                say(t('ACQUIRING YOUR POSITION…'));
            } else {
                const n = data.blips.length;
                say(n === 0 ? t('NO MOTION WITHIN {range} M', { range }) : (window.pipPlural ?? ((count) => `${count}`))(n, { range }));
            }
        } catch {
            say(t('NO SIGNAL - RETRYING'));
        }
    };

    const setSharing = (on) => {
        toggle.setAttribute('aria-pressed', String(on));
        state.textContent = t(on ? 'ON' : 'OFF');
        badge.hidden = !on;
        root.classList.toggle('is-sharing', on);
        try { sessionStorage.setItem('pipboy-motion-share', on ? '1' : '0'); } catch { /* private mode */ }
    };

    const start = () => {
        if (!('geolocation' in navigator)) {
            say(t('NO GPS ON THIS DEVICE'));
            return;
        }
        setSharing(true);
        say(t('ACQUIRING YOUR POSITION…'));
        watchId = navigator.geolocation.watchPosition((position) => {
            lastFix = position;
            send(lastSent === 0);
        }, (error) => {
            say(error.code === 1 ? t('LOCATION PERMISSION DENIED') : t('NO GPS SIGNAL'));
            if (error.code === 1) { stop(); }
        }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 });
        // Keeps the shared position fresh while standing still.
        pollTimer = window.setInterval(() => {
            poll();
            if (Date.now() - lastSent > HEARTBEAT) { send(true); }
        }, POLL_EVERY);
    };

    const stop = (leaving = false) => {
        if (watchId !== null) { navigator.geolocation.clearWatch(watchId); }
        window.clearInterval(pollTimer);
        watchId = null;
        lastFix = null;
        lastSent = 0;
        draw([]);
        post(root.dataset.stopUrl, null, leaving).catch(() => { /* goes stale on the server anyway */ });
        if (!leaving) {
            setSharing(false);
            say(t('Detector idle. Turn sharing on to scan.'));
        }
    };

    toggle.addEventListener('click', () => (watchId === null ? start() : stop()));
    root.querySelectorAll('[data-motion-range]').forEach((button) => button.addEventListener('click', () => {
        range = Number(button.dataset.motionRange);
        rangeLabel.textContent = `${range} m`;
        root.querySelectorAll('[data-motion-range]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        poll();
    }));
    // Leaving the screen stops sharing; the session remembers the choice for coming back.
    window.addEventListener('pagehide', () => { if (watchId !== null) { stop(true); } });

    let resume = false;
    try { resume = sessionStorage.getItem('pipboy-motion-share') === '1'; } catch { /* private mode */ }
    if (resume || root.dataset.sharing === '1') {
        start();
    } else {
        setSharing(false);
    }
})();
