import { router } from '@inertiajs/react';
import { Download, Share, X } from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import { useIsMobile } from '@/hooks/use-mobile';
import { isStandalone } from '@/hooks/use-push-notifications';
import {
    clearInstallPrompt,
    getInstallPrompt,
    onInstallPromptChange,
} from '@/lib/pwa';

const DISMISSED_KEY = 'tarombo-install-banner-dismissed-at';
const SNOOZE_MS = 14 * 24 * 60 * 60 * 1000;
const SHOW_DELAY_MS = 4000;
// Pages where a bottom banner would cover the main work area.
const HIDDEN_PATHS = [/\/fullscreen/, /^\/login/, /^\/register/, /^\/auth\//];

type Platform = 'prompt' | 'ios' | 'in-app' | 'android' | null;

function wasDismissedRecently(): boolean {
    try {
        const at = Number(localStorage.getItem(DISMISSED_KEY));

        return Number.isFinite(at) && Date.now() - at < SNOOZE_MS;
    } catch {
        return false;
    }
}

function rememberDismissal(): void {
    try {
        localStorage.setItem(DISMISSED_KEY, String(Date.now()));
    } catch {
        // Private mode: the banner simply returns on the next visit.
    }
}

function detectPlatform(hasPrompt: boolean): Platform {
    const ua = navigator.userAgent;

    if (hasPrompt) {
        return 'prompt';
    }

    // Facebook, Instagram, TikTok, Line and other in-app browsers cannot
    // install a web app; the visitor must reopen the page in a real browser.
    if (/FBAN|FBAV|Instagram|Line\/|TikTok|; wv\)/i.test(ua)) {
        return 'in-app';
    }

    if (/iphone|ipad|ipod/i.test(ua)) {
        return 'ios';
    }

    if (/android/i.test(ua)) {
        return 'android';
    }

    return null;
}

/**
 * Suggests installing Tarombo as an app to phone visitors who are still in
 * the browser, with the steps that fit their browser.
 */
export function InstallAppBanner() {
    const isMobile = useIsMobile();
    const installPrompt = useSyncExternalStore(
        onInstallPromptChange,
        getInstallPrompt,
        () => null,
    );
    const [ready, setReady] = useState(false);
    const [dismissed, setDismissed] = useState(
        () =>
            typeof window === 'undefined' ||
            isStandalone() ||
            wasDismissedRecently(),
    );
    const [path, setPath] = useState(() =>
        typeof window === 'undefined' ? '/' : window.location.pathname,
    );

    useEffect(() => {
        const timer = window.setTimeout(() => setReady(true), SHOW_DELAY_MS);
        const stopListening = router.on('navigate', (event) =>
            setPath(
                new URL(event.detail.page.url, window.location.origin).pathname,
            ),
        );

        return () => {
            window.clearTimeout(timer);
            stopListening();
        };
    }, []);

    const platform = detectPlatform(installPrompt !== null);

    if (
        !isMobile ||
        !ready ||
        dismissed ||
        platform === null ||
        HIDDEN_PATHS.some((pattern) => pattern.test(path))
    ) {
        return null;
    }

    const dismiss = () => {
        rememberDismissal();
        setDismissed(true);
    };

    return (
        <div
            role="dialog"
            aria-label="Pasang aplikasi Tarombo"
            className="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-50 rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4 shadow-xl"
        >
            <button
                type="button"
                onClick={dismiss}
                aria-label="Tutup"
                className="absolute top-2 right-2 rounded-full p-1.5 text-tb-on-surface-variant hover:bg-tb-surface-container"
            >
                <X className="size-4" />
            </button>
            <div className="flex items-start gap-3 pr-6">
                <img
                    src="/icons/icon-192.png"
                    alt=""
                    className="size-12 shrink-0 rounded-xl border border-tb-outline-variant"
                />
                <div className="min-w-0">
                    <p className="font-semibold text-tb-on-surface">
                        Pasang aplikasi Tarombo
                    </p>
                    <p className="mt-0.5 text-xs text-tb-on-surface-variant">
                        Buka lebih cepat dari layar utama dan dapat notifikasi
                        berita marga terbaru.
                    </p>
                </div>
            </div>

            {platform === 'prompt' && installPrompt && (
                <div className="mt-3 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={dismiss}
                    >
                        Nanti saja
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={async () => {
                            await installPrompt.prompt();
                            const { outcome } = await installPrompt.userChoice;
                            clearInstallPrompt();

                            if (outcome === 'dismissed') {
                                dismiss();
                            }
                        }}
                    >
                        <Download className="size-4" />
                        Pasang
                    </Button>
                </div>
            )}

            {platform === 'ios' && (
                <p className="mt-3 rounded-lg bg-tb-surface-container/60 px-3 py-2 text-xs text-tb-on-surface">
                    Ketuk <Share className="inline size-3.5 -translate-y-px" />{' '}
                    <span className="font-semibold">Bagikan</span> di Safari,
                    lalu pilih{' '}
                    <span className="font-semibold">
                        Tambahkan ke Layar Utama
                    </span>
                    .
                </p>
            )}

            {platform === 'android' && (
                <p className="mt-3 rounded-lg bg-tb-surface-container/60 px-3 py-2 text-xs text-tb-on-surface">
                    Di Chrome, ketuk menu{' '}
                    <span className="font-semibold">⋮</span> lalu pilih{' '}
                    <span className="font-semibold">Instal aplikasi</span> atau{' '}
                    <span className="font-semibold">
                        Tambahkan ke layar utama
                    </span>
                    .
                </p>
            )}

            {platform === 'in-app' && (
                <p className="mt-3 rounded-lg bg-tb-surface-container/60 px-3 py-2 text-xs text-tb-on-surface">
                    Ketuk menu <span className="font-semibold">⋯</span> lalu{' '}
                    <span className="font-semibold">
                        Buka di browser (Chrome/Safari)
                    </span>
                    , kemudian pasang aplikasi dari sana.
                </p>
            )}
        </div>
    );
}
