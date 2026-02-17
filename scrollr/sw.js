// SCROLLR - Service Worker
const CACHE = 'scrollr-v1';
const ASSETS = [
    '/scrollr/',
    '/scrollr/assets/css/app.css',
    '/scrollr/assets/js/app.js',
];

self.addEventListener('install', e => {
    e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', e => {
    const url = e.request.url;

    // API – zawsze sieć
    if (url.includes('/api/')) {
        e.respondWith(fetch(e.request).catch(() =>
            new Response(JSON.stringify({ error: 'Brak połączenia' }),
                { headers: { 'Content-Type': 'application/json' } })
        ));
        return;
    }

    // Inne – najpierw cache
    e.respondWith(
        caches.match(e.request).then(cached => cached || fetch(e.request))
    );
});
