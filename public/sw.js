/* Tarombo Batak service worker: installable app shell + news and chat push. */
const CACHE = 'tarombo-offline-v2';
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
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

// Pages are always fetched live (Inertia data must stay fresh); the cached
// page is only shown when the network is unreachable.
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL)),
    );
});

// A chat push for the conversation the user is looking at only updates
// silently; every other chat or news push alerts like a phone message.
function isViewing(clients, url) {
    const target = new URL(url, self.location.origin);

    return clients.some((client) => {
        const current = new URL(client.url);

        return (
            client.focused &&
            client.visibilityState === 'visible' &&
            current.pathname === target.pathname
        );
    });
}

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    const url = data.url || '/berita-marga';
    const isChat =
        typeof data.tag === 'string' && /^(chat|group)-/.test(data.tag);

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                const silent = isChat && isViewing(clients, url);

                return self.registration.showNotification(
                    data.title || 'Tarombo Batak',
                    {
                        body:
                            data.body ||
                            (isChat
                                ? 'Pesan baru.'
                                : 'Ada berita baru di Tarombo.'),
                        icon: data.icon || '/icons/icon-192.png',
                        badge: data.badge || '/icons/badge-72.png',
                        tag: data.tag,
                        renotify: Boolean(data.tag) && !silent,
                        silent,
                        vibrate:
                            isChat && !silent ? [200, 100, 200] : undefined,
                        data: { url },
                    },
                );
            }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(
        event.notification.data?.url || '/',
        self.location.origin,
    ).href;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                const existing = clients.find((client) =>
                    client.url.startsWith(self.location.origin),
                );

                if (existing) {
                    return existing
                        .navigate(target)
                        .then((client) => (client || existing).focus());
                }

                return self.clients.openWindow(target);
            }),
    );
});
