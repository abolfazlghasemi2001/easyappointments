/*
 * Conservative PWA worker: cache only same-origin static assets. Never cache HTML, booking API responses,
 * customer data, authentication pages or form submissions.
 */
const CACHE_NAME = 'easyappointments-static-v1';
const STATIC_ASSETS = [
    './assets/img/pwa-icon.svg',
    './assets/img/barber-mark.svg',
    './assets/img/barber-hero.jpg',
];
const STATIC_EXTENSIONS = /\.(?:css|js|mjs|svg|png|jpe?g|webp|gif|ico|woff2?)$/i;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('easyappointments-static-') && key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin || !url.pathname.includes('/assets/') || !STATIC_EXTENSIONS.test(url.pathname)) return;

    event.respondWith(
        caches.match(request).then((cached) => {
            const network = fetch(request).then((response) => {
                if (response && response.status === 200 && response.type === 'basic') {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }
                return response;
            });
            return cached || network;
        })
    );
});
