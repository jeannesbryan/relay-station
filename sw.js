// RELAY STATION: BACKGROUND SERVICE WORKER
// Memenuhi syarat PWA browser.

self.addEventListener('install', (event) => {
    console.log('[ RELAY ] Service Worker: DOCKING SUCCESSFUL.');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('[ RELAY ] Service Worker: SHIELDS ONLINE.');
    return self.clients.claim();
});

// Pass-through (Biarkan jaringan berjalan normal tanpa caching offline)
self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});