/*
 * Service worker of the Antico Burger admin PWA.
 *
 * - Pages of the admin are always loaded from the network (they contain live,
 *   authenticated data); when offline the static offline page is shown instead.
 * - Versioned static assets (Filament CSS/JS/fonts, icons, logos) are cache-first.
 * - API calls, Livewire requests and every non-GET request are never cached.
 * - Web Push: shows the notifications sent by the server (VAPID) and opens the
 *   related admin page when the notification is tapped.
 *
 * Bump CACHE_VERSION when the offline page or the precached files change.
 * Icons live in /pwa/ and not /icons/: Apache reserves /icons/ with a default alias.
 */
const CACHE_VERSION = 'v3';
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

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Antico Burger', {
            body: payload.body || '',
            icon: '/pwa/icon-192.png',
            tag: payload.tag,
            renotify: Boolean(payload.tag),
            lang: 'it',
            timestamp: Date.now(),
            data: { url: payload.url || '/admin' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || '/admin', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const existing = windows.find((client) => client.url.startsWith(self.location.origin));

            if (existing) {
                return existing.focus().then((client) => client.navigate(target));
            }

            return self.clients.openWindow(target);
        }),
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
