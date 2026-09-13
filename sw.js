// RELAY STATION: BACKGROUND SERVICE WORKER
// Memenuhi syarat PWA browser & Mode Bunker (Caching)

// Bumped for v8.1.0. The version string is what forces an upgrade: a client
// holding the old cache keeps serving the old CSS/JS until this changes.
const CACHE_NAME = 'relay-bunker-v8.1.0';

// Served from this origin. These were previously pulled from a CDN, which
// meant the stylesheet could not be loaded offline and a CDN outage would
// fail cache.addAll() below - which rejects the whole install, taking Bunker
// Mode down with it.
const STATIC_ASSETS = [
    'assets/terminal.css',
    'assets/terminal.js'
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
    const req = event.request;

    // Hanya tangani GET. Metode lain (POST form, mis. CSRF-protected actions)
    // tidak boleh dilayani dari cache dan tidak boleh di-fallback.
    if (req.method !== 'GET') {
        return;
    }

    // Strategi: "Cache First" untuk kerangka Terminal UI
    const is_static_asset = STATIC_ASSETS.some(path => req.url.includes(path));

    if (is_static_asset) {
        event.respondWith(
            caches.match(req).then((cachedResponse) => {
                return cachedResponse || fetch(req);
            })
        );
        return;
    }

    // Strategi: "Network First" untuk PHP dan Data Sinyal
    event.respondWith(
        fetch(req).catch(() => {
            // Fallback layar "SIGNAL LOST" HANYA untuk navigasi dokumen.
            // Sebelumnya fallback ini dipasang untuk semua request, sehingga
            // gambar/CSS/JS yang gagal dikembalikan sebagai HTML - browser
            // menerima halaman error di tempat aset yang diharapkan.
            if (req.mode === 'navigate') {
                return new Response(
                    '<body style="background:#030303; color:#00ff41; font-family:monospace; padding:20px;">' +
                    '<h3>> SIGNAL LOST</h3><p>Koneksi terputus. Menunggu satelit lewat...</p></body>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            }
            // Selain navigasi, biarkan error apa adanya.
            return Response.error();
        })
    );
});
