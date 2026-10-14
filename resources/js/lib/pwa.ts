export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

let deferredPrompt: BeforeInstallPromptEvent | null = null;
const listeners = new Set<() => void>();

function notify(): void {
    listeners.forEach((listener) => listener());
}

export function getInstallPrompt(): BeforeInstallPromptEvent | null {
    return deferredPrompt;
}

export function clearInstallPrompt(): void {
    deferredPrompt = null;
    notify();
}

export function onInstallPromptChange(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Register the service worker that makes Tarombo installable and receives
 * push notifications. The install prompt can fire before React renders, so
 * it is captured here and read later by InstallAppButton.
 */
export function initializePwa(): void {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        notify();
    });
    window.addEventListener('appinstalled', clearInstallPrompt);

    if (!('serviceWorker' in navigator)) {
        return;
    }

    const register = () =>
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .catch(() => undefined);

    if (document.readyState === 'complete') {
        void register();
    } else {
        window.addEventListener('load', () => void register(), { once: true });
    }
}
