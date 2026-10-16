import { Bell, BellOff, LoaderCircle } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { cn } from '@/lib/utils';

// Says why turning notifications on failed, so the cause can be fixed
// instead of retried blindly.
function pushErrorMessage(error: unknown): string {
    const name = error instanceof Error ? error.name : '';
    const message = error instanceof Error ? error.message : String(error);

    if (name === 'NotAllowedError') {
        return 'Notifikasi diblokir browser. Izinkan notifikasi untuk situs ini lalu coba lagi.';
    }

    if (name === 'AbortError' || /push service/i.test(message)) {
        return 'Layanan notifikasi browser tidak bisa dihubungi. Pastikan internet aktif; di Brave, nyalakan "Use Google services for push messaging".';
    }

    if (/request failed: 419/.test(message)) {
        return 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    }

    return `Notifikasi gagal diatur: ${message}`;
}

export function PushNotificationToggle({ className }: { className?: string }) {
    const { status, busy, subscribe, unsubscribe } = usePushNotifications();

    if (status === 'loading' || status === 'unsupported') {
        return null;
    }

    if (status === 'needs-install') {
        return (
            <p
                className={cn(
                    'rounded-lg border border-tb-outline-variant bg-tb-surface-container/50 px-3 py-2 text-xs text-tb-on-surface-variant',
                    className,
                )}
            >
                Untuk notifikasi pesan dan berita di iPhone: ketuk{' '}
                <span className="font-semibold">Bagikan</span> lalu{' '}
                <span className="font-semibold">Tambahkan ke Layar Utama</span>,
                kemudian buka Tarombo dari layar utama.
            </p>
        );
    }

    if (status === 'denied') {
        return (
            <p className={cn('text-xs text-tb-on-surface-variant', className)}>
                Notifikasi diblokir. Izinkan notifikasi untuk situs ini di
                pengaturan browser.
            </p>
        );
    }

    const subscribed = status === 'subscribed';

    return (
        <Button
            type="button"
            variant={subscribed ? 'outline' : 'default'}
            size="sm"
            className={className}
            disabled={busy}
            onClick={() =>
                (subscribed ? unsubscribe() : subscribe())
                    .then((activated) => {
                        if (activated) {
                            toast.success(
                                'Notifikasi pesan dan berita aktif di perangkat ini.',
                            );
                        }
                    })
                    .catch((error: unknown) => {
                        console.error('Push notification setup failed', error);
                        toast.error(pushErrorMessage(error));
                    })
            }
        >
            {busy ? (
                <LoaderCircle className="size-4 animate-spin" />
            ) : subscribed ? (
                <BellOff className="size-4" />
            ) : (
                <Bell className="size-4" />
            )}
            {subscribed ? 'Matikan Notifikasi' : 'Aktifkan Notifikasi'}
        </Button>
    );
}
