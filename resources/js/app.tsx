import { createInertiaApp } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import type { ComponentType, ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppDashboardLayout from '@/layouts/app/app-dashboard-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

type LayoutProps = {
    children: ReactNode;
    [key: string]: unknown;
};

function baseLayout(name: string): ComponentType<LayoutProps> | ComponentType<LayoutProps>[] | null {
    switch (true) {
        case name === 'welcome':
        case name === 'home':
        case name === 'tarombo/public':
        case name === 'marga/public':
        case name === 'budaya/index':
        case name === 'cerita/index':
        case name === 'cerita/show':
        case name === 'komunitas/index':
        case name === 'tentang/index':
        case name === 'auth/login':
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
    layout: (name) => {
        const base = baseLayout(name);

        return function TransitionLayout({ children, ...props }: LayoutProps) {
            let content = children;

            if (base) {
                const layouts = Array.isArray(base) ? base : [base];

                content = layouts.reduceRight<ReactNode>(
                    (acc, Layout) => (
                        <Layout {...props}>{acc}</Layout>
                    ),
                    children,
                );
            }

            return content;
        };
    },
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
