import { useEffect, useRef } from 'react';

declare global {
    interface Window {
        turnstile?: {
            render: (
                element: HTMLElement,
                options: { sitekey: string; action?: string },
            ) => string;
            remove: (widgetId: string) => void;
        };
    }
}

let scriptPromise: Promise<void> | null = null;

function loadTurnstile(): Promise<void> {
    if (window.turnstile) return Promise.resolve();
    if (scriptPromise) return scriptPromise;

    scriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Turnstile gagal dimuat.'));
        document.head.appendChild(script);
    });

    return scriptPromise;
}

export default function TurnstileWidget({ siteKey }: { siteKey: string | null }) {
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);

    useEffect(() => {
        if (!siteKey || !containerRef.current) return;
        let cancelled = false;

        void loadTurnstile().then(() => {
            if (!cancelled && containerRef.current && window.turnstile) {
                widgetIdRef.current = window.turnstile.render(containerRef.current, {
                    sitekey: siteKey,
                    action: 'login',
                });
            }
        }).catch(() => undefined);

        return () => {
            cancelled = true;
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
                widgetIdRef.current = null;
            }
        };
    }, [siteKey]);

    return siteKey ? <div ref={containerRef} className="min-h-16" /> : null;
}
