import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import pushSubscriptions from '@/routes/push-subscriptions';

export type PushStatus =
    | 'loading'
    | 'unsupported'
    | 'needs-install'
    | 'denied'
    | 'subscribed'
    | 'idle';

function isIos(): boolean {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

export function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

async function send(
    method: 'POST' | 'DELETE',
    url: string,
    body: unknown,
): Promise<void> {
    const xsrfToken = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrfToken
                ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) }
                : {}),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Push subscription request failed: ${response.status}`);
    }
}

function sameKey(
    current: ArrayBuffer | null,
    expected: Uint8Array<ArrayBuffer>,
): boolean {
    if (!current) {
        return true;
    }

    const bytes = new Uint8Array(current);

    return (
        bytes.length === expected.length &&
        bytes.every((byte, index) => byte === expected[index])
    );
}

function subscriptionPayload(subscription: PushSubscription) {
    const json = subscription.toJSON();

    return {
        endpoint: json.endpoint,
        keys: json.keys,
        content_encoding: (
            PushManager as unknown as {
                supportedContentEncodings?: string[];
            }
        ).supportedContentEncodings?.includes('aes128gcm')
            ? 'aes128gcm'
            : 'aesgcm',
    };
}

/**
 * Subscribe this browser to Tarombo news and chat notifications through the
 * service worker registered in app.tsx.
 */
export function usePushNotifications() {
    const { vapidPublicKey, auth, pushLinked } = usePage().props;
    const userId = auth.user?.id;
    const [status, setStatus] = useState<PushStatus>('loading');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let cancelled = false;

        const detect = async (): Promise<PushStatus> => {
            const supported =
                'serviceWorker' in navigator &&
                'PushManager' in window &&
                'Notification' in window;

            if (!supported || !vapidPublicKey) {
                // iPhone only exposes Web Push to an installed home-screen app.
                return isIos() && !isStandalone()
                    ? 'needs-install'
                    : 'unsupported';
            }

            if (Notification.permission === 'denied') {
                return 'denied';
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription =
                await registration.pushManager.getSubscription();

            return subscription ? 'subscribed' : 'idle';
        };

        detect()
            .then((next) => !cancelled && setStatus(next))
            .catch(() => !cancelled && setStatus('unsupported'));

        return () => {
            cancelled = true;
        };
    }, [vapidPublicKey]);

    /** Resolves true once this device is subscribed and saved. */
    const subscribe = useCallback(async (): Promise<boolean> => {
        if (!vapidPublicKey) {
            return false;
        }

        setBusy(true);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setStatus(permission === 'denied' ? 'denied' : 'idle');

                return false;
            }

            const registration = await navigator.serviceWorker.ready;
            const options = {
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            };
            let subscription = await registration.pushManager.getSubscription();

            // A subscription made with an earlier VAPID key can never receive
            // pushes signed with the current one, so it is replaced.
            if (
                subscription &&
                !sameKey(
                    subscription.options.applicationServerKey,
                    options.applicationServerKey,
                )
            ) {
                await subscription.unsubscribe();
                subscription = null;
            }

            try {
                subscription ??=
                    await registration.pushManager.subscribe(options);
            } catch {
                // A subscription left from an earlier key or a broken
                // registration blocks a new one; drop it and try once more.
                await (
                    await registration.pushManager.getSubscription()
                )?.unsubscribe();
                subscription =
                    await registration.pushManager.subscribe(options);
            }

            await send(
                'POST',
                pushSubscriptions.store().url,
                subscriptionPayload(subscription),
            );
            setStatus('subscribed');

            return true;
        } finally {
            setBusy(false);
        }
    }, [vapidPublicKey]);

    // A device may have subscribed before signing in, or under another
    // account; link it to whoever is signed in now so chat messages arrive.
    useEffect(() => {
        if (status !== 'subscribed' || !userId || pushLinked) {
            return;
        }

        navigator.serviceWorker.ready
            .then((registration) => registration.pushManager.getSubscription())
            .then((subscription) =>
                subscription
                    ? send(
                          'POST',
                          pushSubscriptions.store().url,
                          subscriptionPayload(subscription),
                      )
                    : undefined,
            )
            .catch(() => undefined);
    }, [status, userId, pushLinked]);

    const unsubscribe = useCallback(async () => {
        setBusy(true);

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription =
                await registration.pushManager.getSubscription();

            if (subscription) {
                await send('DELETE', pushSubscriptions.destroy().url, {
                    endpoint: subscription.endpoint,
                });
                await subscription.unsubscribe();
            }

            setStatus('idle');
        } finally {
            setBusy(false);
        }
    }, []);

    return { status, busy, subscribe, unsubscribe };
}
