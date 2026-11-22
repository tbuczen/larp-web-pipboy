// PipBoy player interface enhancements. Every page works without this file;
// it adds a live clock, the screen-effects toggle, a once-per-session boot
// sequence, swipe/arrow-key tab switching, centring of the active sub-tab,
// service worker registration and the "Install PipBoy" prompt.
(() => {
    const root = document.documentElement;
    const store = {
        get(storage, key) { try { return window[storage].getItem(key); } catch { return null; } },
        set(storage, key, value) { try { window[storage].setItem(key, value); } catch { /* private mode */ } },
    };
    const effectsOn = () => root.dataset.fx !== 'off' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Screen effects toggle (the stored choice is applied in <head> before paint).
    const syncFxButtons = () => document.querySelectorAll('[data-fx-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(root.dataset.fx !== 'off'));
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-fx-toggle]')) {
            return;
        }
        const off = root.dataset.fx !== 'off';
        root.dataset.fx = off ? 'off' : 'on';
        store.set('localStorage', 'pipboy-fx', root.dataset.fx);
        syncFxButtons();
    });

    const startClock = () => {
        const clocks = document.querySelectorAll('[data-clock]');
        if (clocks.length === 0) {
            return;
        }
        const tick = () => {
            const now = new Date();
            const text = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
            clocks.forEach((clock) => { clock.textContent = text; clock.dateTime = now.toISOString(); });
        };
        tick();
        window.setInterval(tick, 15000);
    };

    // A short power-on log the first time the PipBoy opens in a browser session.
    const boot = () => {
        if (!document.body.classList.contains('is-pipboy') || store.get('sessionStorage', 'pipboy-booted') || !effectsOn()) {
            return;
        }
        store.set('sessionStorage', 'pipboy-booted', '1');
        const lines = ['PIPBOY PERSONAL TERMINAL', 'MEMORY CHECK ......... OK', 'DISPLAY ............... OK', 'LOADING PROFILE', '> READY'];
        const overlay = document.createElement('div');
        overlay.className = 'pip-boot';
        overlay.setAttribute('aria-hidden', 'true');
        document.body.append(overlay);
        lines.forEach((line, index) => window.setTimeout(() => {
            const row = document.createElement('p');
            row.textContent = line;
            overlay.append(row);
        }, 120 + index * 170));
        const close = () => {
            overlay.classList.add('is-closing');
            window.setTimeout(() => overlay.remove(), 320);
        };
        overlay.addEventListener('click', close);
        window.setTimeout(close, 1350);
    };

    // Swipe left/right on phones and arrow keys elsewhere switch main tabs.
    const tabNavigation = () => {
        const nav = document.querySelector('.pip-nav');
        if (!nav) {
            return;
        }
        const go = (direction) => {
            const href = nav.dataset[direction === 'next' ? 'nextTab' : 'prevTab'];
            if (href && href !== window.location.pathname) {
                window.location.assign(href);
            }
        };
        document.addEventListener('keydown', (event) => {
            if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.target.closest('input, textarea, select, audio, [contenteditable], .leaflet-container')) {
                return;
            }
            if (event.key === 'ArrowRight') { go('next'); }
            if (event.key === 'ArrowLeft') { go('prev'); }
        });
        let start = null;
        const screen = document.getElementById('screen');
        screen?.addEventListener('touchstart', (event) => {
            start = event.target.closest('audio, input, textarea, .pip-subtabs, .leaflet-container') ? null : { x: event.touches[0].clientX, y: event.touches[0].clientY };
        }, { passive: true });
        screen?.addEventListener('touchend', (event) => {
            if (!start) {
                return;
            }
            const dx = event.changedTouches[0].clientX - start.x;
            const dy = event.changedTouches[0].clientY - start.y;
            start = null;
            if (Math.abs(dx) > 70 && Math.abs(dx) > 2 * Math.abs(dy)) {
                go(dx < 0 ? 'next' : 'prev');
            }
        }, { passive: true });
        nav.querySelector('.pip-subtab.is-active')?.scrollIntoView({ inline: 'center', block: 'nearest' });
    };

    // Forms marked data-confirm ask before submitting (e.g. closing a chat).
    document.addEventListener('submit', (event) => {
        const message = event.target.closest('form[data-confirm]')?.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Installable app: the service worker enables installation and offline use.
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).catch(() => { /* app still works online */ });
        });
    }

    // "Install PipBoy": Android/desktop browsers offer a prompt; iOS needs Share › Add to Home Screen.
    let installPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        document.querySelectorAll('[data-install]').forEach((box) => { box.hidden = false; box.querySelector('[data-install-button]').hidden = false; });
    });
    window.addEventListener('appinstalled', () => {
        document.querySelectorAll('[data-install]').forEach((box) => { box.hidden = true; });
    });
    const installHelp = () => {
        const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        document.querySelectorAll('[data-install]').forEach((box) => {
            if (standalone) {
                box.hidden = true;
                return;
            }
            if (ios) {
                box.hidden = false;
                box.querySelector('[data-install-ios]').hidden = false;
            }
            box.querySelector('[data-install-button]')?.addEventListener('click', async () => {
                if (!installPrompt) {
                    return;
                }
                installPrompt.prompt();
                await installPrompt.userChoice;
                installPrompt = null;
                box.hidden = true;
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        installHelp();
        syncFxButtons();
        startClock();
        boot();
        tabNavigation();
    });
})();
