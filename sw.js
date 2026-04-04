// RELAY STATION: BACKGROUND SERVICE WORKER
// Memenuhi syarat PWA browser & Mode Bunker (Caching)

const CACHE_NAME = 'relay-bunker-v3.0.4';
const STATIC_ASSETS = [
    'https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css',
    'https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js',
    'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap'
];

self.addEventListener('install', (event) => {
    console.log('[ RELAY ] Service Worker: DOCKING SUCCESSFUL.');
    // Simpan kerangka UI Terminal ke dalam memori bunker saat instalasi
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('[ RELAY ] Service Worker: SHIELDS ONLINE.');
    // Hapus cache lama jika ada versi Relay baru
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Strategi: "Cache First" untuk kerangka Terminal UI
    if (STATIC_ASSETS.some(url => event.request.url.includes(url))) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                return cachedResponse || fetch(event.request);
            })
        );
    } 
    // Strategi: "Network First" untuk PHP dan Data Sinyal
    else {
        event.respondWith(
            fetch(event.request).catch(() => {
                // Jika internet mati (offline)
                return new Response(
                    '<body style="background:#030303; color:#00ff41; font-family:monospace; padding:20px;">' +
                    '<h3>> SIGNAL LOST</h3><p>Koneksi terputus. Menunggu satelit lewat...</p></body>',
                    { headers: { 'Content-Type': 'text/html' } }
                );
            })
        );
    }
});