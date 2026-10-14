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

/**
 * Subscribe this browser to Tarombo news notifications through the service
 * worker registered in app.tsx.
 */
export function usePushNotifications() {
    const { vapidPublicKey } = usePage().props;
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

    const subscribe = useCallback(async () => {
        if (!vapidPublicKey) {
            return;
        }

        setBusy(true);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setStatus(permission === 'denied' ? 'denied' : 'idle');

                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription =
                (await registration.pushManager.getSubscription()) ??
                (await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                }));
            const json = subscription.toJSON();

            await send('POST', pushSubscriptions.store().url, {
                endpoint: json.endpoint,
                keys: json.keys,
                content_encoding: (
                    PushManager as unknown as {
                        supportedContentEncodings?: string[];
                    }
                ).supportedContentEncodings?.includes('aes128gcm')
                    ? 'aes128gcm'
                    : 'aesgcm',
            });
            setStatus('subscribed');
        } finally {
            setBusy(false);
        }
    }, [vapidPublicKey]);

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
