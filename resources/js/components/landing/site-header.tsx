import { Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { User } from 'lucide-react';
import { useEffect } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import { ThemeToggle } from '@/components/landing/theme-toggle';
import { navLinks } from '@/data/landing';
import { toUrl } from '@/lib/utils';
import { home, login } from '@/routes';
import { dashboard } from '@/routes';
import marga from '@/routes/marga';
import tarombo from '@/routes/tarombo';

function pageHref(label: string): string {
    switch (label) {
        case 'Beranda':
            return toUrl(dashboard());
        case 'Tarombo':
            return toUrl(tarombo.view());
        case 'Marga':
            return toUrl(marga.view());
        case 'Budaya':
            return '/budaya';
        case 'Komunitas':
            return '/komunitas';
        case 'Tentang':
            return '/tentang';
        default:
            return '#';
    }
}

export function SiteHeader() {
    const { url } = usePage();
    const pathname = url.split('?')[0];
    const isDashboardLanding = pathname === '/dashboard';

    const activeKey = navLinks.find(
        (l) => pageHref(l.label) === pathname,
    )?.label;

    useEffect(() => {
        const scrollToHash = () => {
            const hash = window.location.hash;

            if (!hash) {
                return;
            }

            requestAnimationFrame(() => {
                document
                    .querySelector(hash)
                    ?.scrollIntoView({ behavior: 'smooth' });
            });
        };

        scrollToHash();
        window.addEventListener('hashchange', scrollToHash);

        return () => window.removeEventListener('hashchange', scrollToHash);
    }, []);

    const renderLink = (label: string) => {
        const itemHref = pageHref(label);
        const isActive = label === activeKey;

        const inner = (
            <>
                <span>{label}</span>
                {isActive && (
                    <motion.span
                        layoutId="nav-active"
                        className="absolute inset-x-0 -bottom-1 h-0.5 bg-tb-primary"
                        transition={{
                            type: 'spring',
                            stiffness: 380,
                            damping: 30,
                        }}
                    />
                )}
            </>
        );

        const className = isActive
            ? 'relative pb-1 text-tb-primary'
            : 'relative pb-1 text-tb-on-surface transition-colors hover:text-tb-primary';

        return (
            <Link key={label} href={itemHref} className={className}>
                {inner}
            </Link>
        );
    };

    return (
        <header className="sticky top-0 z-50 border-b border-tb-outline-variant bg-tb-surface-bright/90 px-6 py-4 backdrop-blur-md">
            <div className="mx-auto flex max-w-7xl items-center justify-between">
                <Link href={home()} className="flex items-center gap-2">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded bg-tb-primary">
                            <BrandLogo className="size-7" />
                        </div>
                        <div>
                            <h1 className="font-display text-xl leading-tight font-bold">
                                Tarombo Batak
                            </h1>
                            <p className="text-xs text-tb-on-surface-variant">
                                Silsilah • Budaya • Identitas
                            </p>
                        </div>
                    </div>
                </Link>

                <nav className="hidden items-center gap-8 text-sm font-medium md:flex">
                    {navLinks.map((link) => renderLink(link.label))}
                </nav>
                <div className="flex items-center gap-3">
                    <ThemeToggle />
                    {!isDashboardLanding && (
                        <Link
                            href={login()}
                            className="flex items-center gap-2 rounded-full bg-tb-primary px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-tb-primary-light"
                        >
                            <User className="h-4 w-4" /> Masuk / Daftar
                        </Link>
                    )}
                </div>
            </div>
        </header>
    );
}
