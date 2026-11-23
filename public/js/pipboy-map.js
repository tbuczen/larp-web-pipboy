// PipBoy local map: the organizers' uploaded image placed by its three aligned
// corners, with no street tiles underneath. The player's GPS position is read
// on this device only and never sent anywhere.
(() => {
    const element = document.getElementById('pip-map');
    if (!element || !window.L || !L.imageOverlay.rotated) {
        return;
    }
    // On a screen rotated 180° (data-rotate on <html>), Leaflet's pointer maths
    // would pan and zoom the wrong way: mirror its coordinates back.
    const rotated = () => document.documentElement.dataset.rotate === '180';
    const toContainerPoint = L.Map.prototype.mouseEventToContainerPoint;
    L.Map.prototype.mouseEventToContainerPoint = function (event) {
        const point = toContainerPoint.call(this, event);
        const size = this.getSize();
        return rotated() ? L.point(size.x - point.x, size.y - point.y) : point;
    };
    const onDown = L.Draggable.prototype._onDown;
    L.Draggable.prototype._onDown = function (event) {
        onDown.call(this, event);
        if (rotated() && this._parentScale) {
            this._parentScale = { ...this._parentScale, x: -this._parentScale.x, y: -this._parentScale.y };
        }
    };

    const status = document.querySelector('[data-map-status]');
    const locateButton = document.querySelector('[data-map-locate]');
    const t = window.pipT ?? ((text) => text);
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
    overlay.on('error', () => say(t('MAP DATA CORRUPTED - RELOAD')));

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
            say(t('OUTSIDE MAPPED AREA ±{m} m', { m: Math.round(metres) }));
        } else {
            say(t('POSITION LOCKED ±{m} m', { m: Math.round(metres) }));
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
        say(t('GPS OFF'));
    };

    const fail = (error) => {
        say(t(error.code === 1 ? 'LOCATION PERMISSION DENIED' : 'NO GPS SIGNAL'));
        if (error.code === 1) { stop(); say(t('LOCATION PERMISSION DENIED')); }
    };

    // Tap once to start and follow, again to re-centre, and once more while centred to turn GPS off.
    locateButton?.addEventListener('click', () => {
        if (!('geolocation' in navigator)) {
            say(t('NO GPS ON THIS DEVICE'));
            return;
        }
        if (watchId === null) {
            following = true;
            locateButton.setAttribute('aria-pressed', 'true');
            say(t('ACQUIRING SIGNAL…'));
            watchId = navigator.geolocation.watchPosition(show, fail, { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 });
        } else if (!following && last) {
            following = true;
            map.panTo(last);
        } else {
            stop();
        }
    });
})();
