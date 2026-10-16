/*
 * Service worker of the Antico Burger admin PWA.
 *
 * - Pages of the admin are always loaded from the network (they contain live,
 *   authenticated data); when offline the static offline page is shown instead.
 * - Versioned static assets (Filament CSS/JS/fonts, icons, logos) are cache-first.
 * - API calls, Livewire requests and every non-GET request are never cached.
 *
 * Bump CACHE_VERSION when the offline page or the precached files change.
 * Icons live in /pwa/ and not /icons/: Apache reserves /icons/ with a default alias.
 */
const CACHE_VERSION = 'v2';
const CACHE_NAME = `antico-burger-admin-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/pwa/icon-192.png',
    '/images/logo-anticoburger-dark.png',
    '/images/logo-anticoburger-white.png',
];
const STATIC_PATH_PREFIXES = ['/css/', '/js/', '/fonts/', '/pwa/', '/images/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('antico-burger-admin-') && key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/livewire')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );

        return;
    }

    if (STATIC_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) => cache.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(request).then((response) => {
                    if (response.ok) {
                        cache.put(request, response.clone());
                    }

                    return response;
                });
            })),
        );
    }
});
