// PipBoy local map: the organizers' uploaded image placed by its three aligned
// corners, with no street tiles underneath. The player's GPS position is read
// on this device only and never sent anywhere.
(() => {
    const element = document.getElementById('pip-map');
    if (!element || !window.L || !L.imageOverlay.rotated) {
        return;
    }
    const status = document.querySelector('[data-map-status]');
    const locateButton = document.querySelector('[data-map-locate]');
    const say = (text) => { if (status) { status.textContent = text; } };

    const raw = JSON.parse(element.dataset.corners);
    const topLeft = L.latLng(raw.topLeft);
    const topRight = L.latLng(raw.topRight);
    const bottomLeft = L.latLng(raw.bottomLeft);
    const bottomRight = L.latLng(topRight.lat + bottomLeft.lat - topLeft.lat, topRight.lng + bottomLeft.lng - topLeft.lng);
    const bounds = L.latLngBounds([topLeft, topRight, bottomLeft, bottomRight]);

    const map = L.map(element, { zoomControl: false, attributionControl: false, zoomSnap: 0.25, maxBoundsViscosity: 0.9 });
    map.fitBounds(bounds);
    map.setMinZoom(map.getZoom() - 1);
    map.setMaxZoom(Math.max(map.getZoom() + 4, 18));
    map.setMaxBounds(bounds.pad(0.3));

    const overlay = L.imageOverlay.rotated(element.dataset.imageUrl, topLeft, topRight, bottomLeft, { interactive: false }).addTo(map);
    overlay.on('error', () => say('MAP DATA CORRUPTED - RELOAD'));

    document.querySelector('[data-map-zoom-in]')?.addEventListener('click', () => map.zoomIn());
    document.querySelector('[data-map-zoom-out]')?.addEventListener('click', () => map.zoomOut());
    document.querySelector('[data-map-reset]')?.addEventListener('click', () => { following = false; map.flyToBounds(bounds, { duration: 0.4 }); });

    // Position on the map in image units (0..1 across and down), to tell when the player is off the map.
    const insideMap = (latLng) => {
        const ax = topRight.lng - topLeft.lng; const ay = topRight.lat - topLeft.lat;
        const bx = bottomLeft.lng - topLeft.lng; const by = bottomLeft.lat - topLeft.lat;
        const px = latLng.lng - topLeft.lng; const py = latLng.lat - topLeft.lat;
        const det = ax * by - ay * bx;
        const across = (px * by - py * bx) / det;
        const down = (ax * py - ay * px) / det;
        return across >= 0 && across <= 1 && down >= 0 && down <= 1;
    };

    const arrow = L.divIcon({
        className: 'pip-map-player',
        html: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 20 21 12 16 4 21Z"/></svg>',
        iconSize: [30, 30],
    });
    let watchId = null;
    let marker = null;
    let accuracy = null;
    let following = false;
    let last = null;
    map.on('dragstart', () => { following = false; });

    const show = (position) => {
        const { latitude, longitude, accuracy: metres, heading, speed } = position.coords;
        last = L.latLng(latitude, longitude);
        if (!marker) {
            accuracy = L.circle(last, { radius: metres, className: 'pip-map-accuracy', interactive: false }).addTo(map);
            marker = L.marker(last, { icon: arrow, interactive: false, keyboard: false }).addTo(map);
        }
        marker.setLatLng(last);
        accuracy.setLatLng(last).setRadius(metres);
        // Heading is only known while moving; standing still shows a plain marker.
        const moving = Number.isFinite(heading) && (speed ?? 0) > 0.5;
        const svg = marker.getElement()?.querySelector('svg');
        marker.getElement()?.classList.toggle('is-still', !moving);
        if (svg && moving) { svg.style.transform = `rotate(${heading}deg)`; }

        if (!insideMap(last)) {
            say(`OUTSIDE MAPPED AREA ±${Math.round(metres)} m`);
        } else {
            say(`POSITION LOCKED ±${Math.round(metres)} m`);
            if (following) { map.panTo(last, { animate: true }); }
        }
    };

    const stop = () => {
        if (watchId !== null) { navigator.geolocation.clearWatch(watchId); }
        watchId = null;
        following = false;
        marker?.remove(); accuracy?.remove();
        marker = accuracy = last = null;
        locateButton?.setAttribute('aria-pressed', 'false');
        say('GPS OFF');
    };

    const fail = (error) => {
        say(error.code === 1 ? 'LOCATION PERMISSION DENIED' : 'NO GPS SIGNAL');
        if (error.code === 1) { stop(); say('LOCATION PERMISSION DENIED'); }
    };

    // Tap once to start and follow, again to re-centre, and once more while centred to turn GPS off.
    locateButton?.addEventListener('click', () => {
        if (!('geolocation' in navigator)) {
            say('NO GPS ON THIS DEVICE');
            return;
        }
        if (watchId === null) {
            following = true;
            locateButton.setAttribute('aria-pressed', 'true');
            say('ACQUIRING SIGNAL…');
            watchId = navigator.geolocation.watchPosition(show, fail, { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 });
        } else if (!following && last) {
            following = true;
            map.panTo(last);
        } else {
            stop();
        }
    });
})();
