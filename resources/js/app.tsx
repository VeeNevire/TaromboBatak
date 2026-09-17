import { createInertiaApp, router } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { MotionConfig } from 'framer-motion';
import type { ComponentType, ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppDashboardLayout from '@/layouts/app/app-dashboard-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { trackGoogleAnalyticsPageView } from '@/lib/google-analytics';

if (!import.meta.env.SSR) {
    configureEcho({
        broadcaster: 'reverb',
    });

    router.on('navigate', (event) => {
        trackGoogleAnalyticsPageView(event.detail.page.url);
    });

    trackGoogleAnalyticsPageView(window.location.href);
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

type LayoutProps = {
    children: ReactNode;
    [key: string]: unknown;
};

function baseLayout(
    name: string,
): ComponentType<LayoutProps> | ComponentType<LayoutProps>[] | null {
    switch (true) {
        case name === 'welcome':
        case name === 'home':
        case name === 'tarombo/public':
        case name === 'tarombo/fullscreen':
        case name === 'tarombo/public-fullscreen':
        case name === 'traffic-monitor/public':
        case name === 'marga/public':
        case name === 'budaya/index':
        case name === 'cerita/index':
        case name === 'cerita/show':
        case name === 'kegiatan/index':
        case name === 'kegiatan/show':
        case name === 'komunitas/index':
        case name === 'tentang/index':
        case name === 'auth/login':
        case name === 'auth/complete-google-registration':
            return null;
        case name.startsWith('Error/'):
            return null;
        case name === 'auth/forgot-password':
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        case name === 'dashboard':
            return AppDashboardLayout;
        default:
            return AppLayout;
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Return stable component references so Inertia can preserve page state.
    layout: baseLayout,
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <MotionConfig reducedMotion="user">{app}</MotionConfig>
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--color-tb-primary)',
    },
});

// This will set light / dark mode on load...
initializeTheme();
