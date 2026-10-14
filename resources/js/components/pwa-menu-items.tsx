import { Bell, BellOff, Download, Smartphone } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { toast } from 'sonner';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import {
    clearInstallPrompt,
    getInstallPrompt,
    onInstallPromptChange,
} from '@/lib/pwa';

/** App install and news notification switches for the user menu. */
export function PwaMenuItems() {
    const { status, busy, subscribe, unsubscribe } = usePushNotifications();
    const installPrompt = useSyncExternalStore(
        onInstallPromptChange,
        getInstallPrompt,
        () => null,
    );
    const showPush = status !== 'loading' && status !== 'unsupported';

    if (!showPush && !installPrompt) {
        return null;
    }

    const subscribed = status === 'subscribed';

    const togglePush = () =>
        (subscribed ? unsubscribe() : subscribe())
            .then(() =>
                toast.success(
                    subscribed
                        ? 'Notifikasi berita dimatikan di perangkat ini.'
                        : 'Notifikasi berita aktif di perangkat ini.',
                ),
            )
            .catch(() =>
                toast.error('Notifikasi gagal diatur. Coba lagi sebentar.'),
            );

    return (
        <>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {installPrompt && (
                    <DropdownMenuItem
                        className="cursor-pointer"
                        onSelect={async () => {
                            await installPrompt.prompt();
                            await installPrompt.userChoice;
                            clearInstallPrompt();
                        }}
                    >
                        <Download className="mr-2" />
                        Install Aplikasi
                    </DropdownMenuItem>
                )}
                {status === 'needs-install' && (
                    <DropdownMenuItem
                        className="cursor-pointer"
                        onSelect={() =>
                            toast.info(
                                'Di iPhone: ketuk Bagikan → Tambahkan ke Layar Utama, lalu buka Tarombo dari layar utama untuk mengaktifkan notifikasi.',
                                { duration: 8000 },
                            )
                        }
                    >
                        <Smartphone className="mr-2" />
                        Notifikasi (iPhone)
                    </DropdownMenuItem>
                )}
                {status === 'denied' && (
                    <DropdownMenuItem disabled>
                        <BellOff className="mr-2" />
                        Notifikasi diblokir browser
                    </DropdownMenuItem>
                )}
                {(status === 'idle' || subscribed) && (
                    <DropdownMenuItem
                        className="cursor-pointer"
                        disabled={busy}
                        onSelect={(event) => {
                            event.preventDefault();
                            void togglePush();
                        }}
                    >
                        {subscribed ? (
                            <BellOff className="mr-2" />
                        ) : (
                            <Bell className="mr-2" />
                        )}
                        {subscribed
                            ? 'Matikan Notifikasi'
                            : 'Aktifkan Notifikasi'}
                    </DropdownMenuItem>
                )}
            </DropdownMenuGroup>
        </>
    );
}
