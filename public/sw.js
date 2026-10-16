/* Tarombo Batak service worker: installable app shell + news and chat push. */
const CACHE = 'tarombo-offline-v3';
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
                        // Android and desktop show these as buttons; iPhone
                        // ignores them and simply opens the chat on tap.
                        actions: Array.isArray(data.actions)
                            ? data.actions
                            : [],
                        data: { url, readUrl: data.readUrl },
                    },
                );
            }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const { url, readUrl } = event.notification.data || {};

    // "Tandai dibaca" marks the chat read without opening Tarombo. The link
    // is signed for this reader, so no session or CSRF token is needed.
    if (event.action === 'read') {
        if (readUrl) {
            event.waitUntil(
                fetch(readUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                }).catch(() => undefined),
            );
        }

        return;
    }

    const targetUrl = new URL(url || '/', self.location.origin);

    // "Balas" opens the chat with the message box focused.
    if (event.action === 'reply') {
        targetUrl.searchParams.set('reply', '1');
    }

    const target = targetUrl.href;

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
