import { Bell, BellOff, LoaderCircle } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { usePushNotifications } from '@/hooks/use-push-notifications';

export function PushNotificationToggle() {
    const { status, busy, subscribe, unsubscribe } = usePushNotifications();

    if (status === 'loading' || status === 'unsupported') {
        return null;
    }

    if (status === 'needs-install') {
        return (
            <p className="rounded-lg border border-tb-outline-variant bg-tb-surface-container/50 px-3 py-2 text-xs text-tb-on-surface-variant">
                Untuk notifikasi berita di iPhone: ketuk{' '}
                <span className="font-semibold">Bagikan</span> lalu{' '}
                <span className="font-semibold">Tambahkan ke Layar Utama</span>,
                kemudian buka Tarombo dari layar utama.
            </p>
        );
    }

    if (status === 'denied') {
        return (
            <p className="text-xs text-tb-on-surface-variant">
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
            disabled={busy}
            onClick={() =>
                (subscribed ? unsubscribe() : subscribe())
                    .then(() => {
                        if (!subscribed) {
                            toast.success(
                                'Notifikasi berita aktif di perangkat ini.',
                            );
                        }
                    })
                    .catch(() =>
                        toast.error(
                            'Notifikasi gagal diatur. Coba lagi sebentar.',
                        ),
                    )
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
