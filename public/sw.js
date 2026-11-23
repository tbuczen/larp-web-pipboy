// PipBoy service worker. Makes the app installable and usable on weak signal:
// - static files (CSS, JS, fonts, icons, vendored libraries) are cached by
//   their versioned URL and served from the phone;
// - the LARP map image and the map screen are kept after the first visit, so
//   MAP > Local map with GPS works without signal;
// - every other page always comes from the network (personal data is not
//   stored), with a "No signal" screen when offline.
// Signing out or opening the sign-in page clears the stored map data.
const VERSION = 'v2';
const STATIC_CACHE = `pipboy-static-${VERSION}`;
const MAP_CACHE = 'pipboy-map';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/fonts/vt323-regular.woff2', '/icon-192.png'];

const isStatic = (url) => /^\/(css|js|fonts|vendor|bundles|images)\//.test(url.pathname)
    || /^\/(icon-[\w-]+\.png|apple-touch-icon\.png|favicon\.(svg|ico)|manifest\.webmanifest)$/.test(url.pathname);
const isMapImage = (url) => /^\/larp-map\/\d+\/image$/.test(url.pathname);
const isMapScreen = (url) => /^\/pipboy\/\d+\/map(\/local)?$/.test(url.pathname);
const endsSession = (url) => url.pathname === '/logout' || url.pathname === '/login';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((key) => key.startsWith('pipboy-static-') && key !== STATIC_CACHE).map((key) => caches.delete(key))))
        .then(() => self.clients.claim()));
});

// Stores a response and drops older versions of the same file (other ?v= values).
const store = async (cacheName, request, response) => {
    const cache = await caches.open(cacheName);
    const path = new URL(request.url).pathname;
    for (const old of await cache.keys()) {
        if (new URL(old.url).pathname === path && old.url !== request.url) {
            await cache.delete(old);
        }
    }
    await cache.put(request, response);
};

const cacheFirst = async (cacheName, request) => {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }
    const response = await fetch(request);
    // Only real files: never a redirect to the sign-in page or an error.
    if (response.ok && !response.redirected && response.type === 'basic') {
        await store(cacheName, request, response.clone());
    }
    return response;
};

const offlinePage = async () => (await caches.match(OFFLINE_URL)) ?? new Response('No signal', { status: 503 });

const mapScreen = async (request) => {
    try {
        const response = await fetch(request);
        if (response.ok && !response.redirected) {
            await store(MAP_CACHE, request, response.clone());
        }
        return response;
    } catch {
        return (await caches.match(request, { cacheName: MAP_CACHE })) ?? offlinePage();
    }
};

const page = async (request) => {
    try {
        return await fetch(request);
    } catch {
        return offlinePage();
    }
};

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/admin')) {
        return;
    }

    if (request.mode === 'navigate') {
        if (endsSession(url)) {
            event.waitUntil(caches.delete(MAP_CACHE));
            return;
        }
        event.respondWith(isMapScreen(url) ? mapScreen(request) : page(request));
        return;
    }

    if (isMapImage(url)) {
        event.respondWith(cacheFirst(MAP_CACHE, request));
    } else if (isStatic(url)) {
        event.respondWith(cacheFirst(STATIC_CACHE, request));
    }
});
