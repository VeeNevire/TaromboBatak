import { Download } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import {
    clearInstallPrompt,
    getInstallPrompt,
    onInstallPromptChange,
} from '@/lib/pwa';

/** "Install Aplikasi" for browsers that offer a PWA install prompt. */
export function InstallAppButton() {
    const promptEvent = useSyncExternalStore(
        onInstallPromptChange,
        getInstallPrompt,
        () => null,
    );

    if (!promptEvent) {
        return null;
    }

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={async () => {
                await promptEvent.prompt();
                await promptEvent.userChoice;
                clearInstallPrompt();
            }}
        >
            <Download className="size-4" />
            Install Aplikasi
        </Button>
    );
}
