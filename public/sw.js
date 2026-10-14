/* Tarombo Batak service worker: installable app shell + news push. */
const CACHE = 'tarombo-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Pages are always fetched live (Inertia data must stay fresh); the cached
// page is only shown when the network is unreachable.
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Tarombo Batak', {
            body: data.body || 'Ada berita baru di Tarombo.',
            icon: data.icon || '/icons/icon-192.png',
            badge: data.badge || '/icons/badge-72.png',
            tag: data.tag,
            renotify: Boolean(data.tag),
            data: { url: data.url || '/berita-marga' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((client) => client.url.startsWith(self.location.origin));

            if (existing) {
                return existing.navigate(target).then((client) => (client || existing).focus());
            }

            return self.clients.openWindow(target);
        }),
    );
});
