import { Link, usePage } from '@inertiajs/react';
import { User } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { ThemeToggle } from '@/components/landing/theme-toggle';
import { navLinks } from '@/data/landing';
import { home, login } from '@/routes';
import marga from '@/routes/marga';
import tarombo from '@/routes/tarombo';

export function SiteHeader() {
    const { url } = usePage();
    const pathname = url.split('?')[0];

    const navItems = navLinks.map((link) => {
        const href =
            link.label === 'Beranda'
                ? home()
                : link.label === 'Tarombo'
                  ? tarombo.view()
                  : link.label === 'Marga'
                    ? marga.view()
                    : link.href;

        return { ...link, href };
    });

    return (
        <header className="sticky top-0 z-50 border-b border-tb-outline-variant bg-tb-surface-bright/90 px-6 py-4 backdrop-blur-md">
            <div className="mx-auto flex max-w-7xl items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded bg-tb-primary">
                        <BrandLogo className="size-7" />
                    </div>
                    <div>
                        <h1 className="font-display text-xl font-bold leading-tight">Tarombo Batak</h1>
                        <p className="text-xs text-tb-on-surface-variant">Silsilah • Budaya • Identitas</p>
                    </div>
                </div>
                <nav className="hidden items-center gap-8 text-sm font-medium md:flex">
                    {navItems.map((link) => {
                        const href = typeof link.href === 'string' ? link.href : link.href.url;
                        const isActive = href !== '#' && pathname === href;

                        return (
                            <Link
                                key={link.label}
                                href={link.href}
                                className={
                                    isActive
                                        ? 'border-b-2 border-tb-primary pb-1 text-tb-primary'
                                        : 'border-b-2 border-transparent pb-1 text-tb-on-surface transition-colors hover:text-tb-primary'
                                }
                            >
                                {link.label}
                            </Link>
                        );
                    })}
                </nav>
                <div className="flex items-center gap-3">
                    <ThemeToggle />
                    <Link
                        href={login()}
                        className="flex items-center gap-2 rounded-full bg-tb-primary px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-tb-primary-light"
                    >
                        <User className="h-4 w-4" /> Masuk / Daftar
                    </Link>
                </div>
            </div>
        </header>
    );
}
