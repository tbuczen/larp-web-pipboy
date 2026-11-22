// Organizer map calibration: the uploaded image is laid over OpenStreetMap and
// placed by dragging handles. The three corners (top-left, top-right,
// bottom-left) are written into the alignment form as latitude/longitude.
(() => {
    const element = document.getElementById('map-calibrate');
    if (!element || !window.L || !L.imageOverlay.rotated) {
        return;
    }
    const form = document.querySelector('[data-map-align-form]');
    const width = Number(element.dataset.imageWidth);
    const height = Number(element.dataset.imageHeight);
    const saved = JSON.parse(element.dataset.corners || 'null');

    const map = L.map(element, { zoomSnap: 0.25 });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Geometry in projected pixels at a fixed zoom, so right angles stay right on screen.
    const ZOOM = 20;
    const project = (latLng) => map.project(latLng, ZOOM);
    const unproject = (point) => map.unproject(point, ZOOM);
    const fourth = (c) => unproject(project(c.topRight).add(project(c.bottomLeft)).subtract(project(c.topLeft)));
    const centre = (c) => unproject(project(c.topRight).add(project(c.bottomLeft)).divideBy(2));

    // Places the image in the middle of the current view at 60% of its size, unrotated.
    const cornersInView = () => {
        const size = map.getSize();
        const scale = Math.min(size.x / width, size.y / height) * 0.6;
        const origin = size.divideBy(2).subtract(L.point(width * scale, height * scale).divideBy(2));
        const at = (x, y) => map.containerPointToLatLng(origin.add(L.point(x * scale, y * scale)));
        return { topLeft: at(0, 0), topRight: at(width, 0), bottomLeft: at(0, height) };
    };

    let corners;
    if (saved) {
        corners = { topLeft: L.latLng(saved.topLeft), topRight: L.latLng(saved.topRight), bottomLeft: L.latLng(saved.bottomLeft) };
        map.fitBounds(L.latLngBounds([corners.topLeft, corners.topRight, corners.bottomLeft, fourth(corners)]), { padding: [40, 40] });
    } else {
        map.setView([50, 15], 4);
        corners = cornersInView();
    }

    const overlay = L.imageOverlay.rotated(element.dataset.imageUrl, corners.topLeft, corners.topRight, corners.bottomLeft, { opacity: 0.6, interactive: false }).addTo(map);
    const outline = L.polygon([], { color: '#1cfa80', weight: 2, fill: false, dashArray: '6 6', interactive: false }).addTo(map);
    const handle = (label, title) => L.marker([0, 0], {
        draggable: true,
        autoPan: true,
        title,
        icon: L.divIcon({ className: 'map-handle', html: label, iconSize: [36, 36] }),
    }).addTo(map);
    const handles = {
        topLeft: handle('TL', 'Top-left corner'),
        topRight: handle('TR', 'Top-right corner'),
        bottomLeft: handle('BL', 'Bottom-left corner'),
    };
    const mover = handle('✛', 'Move the whole map');

    const render = (except) => {
        overlay.reposition(corners.topLeft, corners.topRight, corners.bottomLeft);
        outline.setLatLngs([corners.topLeft, corners.topRight, fourth(corners), corners.bottomLeft]);
        Object.entries(handles).forEach(([key, marker]) => { if (marker !== except) { marker.setLatLng(corners[key]); } });
        if (mover !== except) { mover.setLatLng(centre(corners)); }
        form?.querySelectorAll('[data-corner]').forEach((input) => {
            input.value = corners[input.dataset.corner][input.dataset.axis].toFixed(7);
        });
    };

    // Corner handles keep the image a rectangle with its own proportions:
    // the dragged corner rotates and scales the map around the opposite edge.
    const rectangleFrom = (key, point) => {
        const topLeft = project(corners.topLeft);
        if (key === 'bottomLeft') {
            const down = project(point).subtract(topLeft);
            const right = L.point(down.y, -down.x).multiplyBy(width / height);
            return { topLeft: corners.topLeft, topRight: unproject(topLeft.add(right)), bottomLeft: point };
        }
        const start = key === 'topLeft' ? project(point) : topLeft;
        const right = (key === 'topLeft' ? project(corners.topRight) : project(point)).subtract(start);
        const down = L.point(-right.y, right.x).multiplyBy(height / width);
        return { topLeft: unproject(start), topRight: unproject(start.add(right)), bottomLeft: unproject(start.add(down)) };
    };
    Object.entries(handles).forEach(([key, marker]) => marker.on('drag', () => {
        corners = rectangleFrom(key, marker.getLatLng());
        render(marker);
    }));

    let moveStart = null;
    mover.on('dragstart', () => { moveStart = { at: project(mover.getLatLng()), corners: { ...corners } }; });
    mover.on('drag', () => {
        const delta = project(mover.getLatLng()).subtract(moveStart.at);
        corners = Object.fromEntries(Object.entries(moveStart.corners).map(([key, latLng]) => [key, unproject(project(latLng).add(delta))]));
        render(mover);
    });

    document.querySelector('[data-map-opacity]')?.addEventListener('input', (event) => overlay.setOpacity(Number(event.target.value)));
    document.querySelector('[data-map-fit]')?.addEventListener('click', () => { corners = cornersInView(); render(); });
    document.querySelector('[data-map-locate]')?.addEventListener('click', () => map.locate({ setView: true, maxZoom: 17 }));
    render();
})();
