import { Link } from '@inertiajs/react';
import { Apple, Facebook, Instagram, Music2, Play, Youtube } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { footerNav } from '@/data/landing';


const socialLinks = [
    { label: 'Facebook', icon: Facebook },
    { label: 'Instagram', icon: Instagram },
    { label: 'Youtube', icon: Youtube },
    { label: 'TikTok', icon: Music2 },
];

export function SiteFooter() {
    return (
        <footer className="border-t border-tb-outline-variant bg-tb-surface-bright px-6 pb-8 pt-16">
            <div className="mx-auto mb-12 grid max-w-7xl grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-5">
                <div className="lg:col-span-2">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="flex h-8 w-8 items-center justify-center overflow-hidden rounded bg-tb-primary">
                            <BrandLogo className="size-6" />
                        </div>
                        <div>
                            <h2 className="font-display text-lg font-bold leading-none">Tarombo Batak</h2>
                            <p className="text-[10px] text-tb-on-surface-variant">Silsilah • Budaya • Identitas</p>
                        </div>
                    </div>
                    <p className="mb-6 max-w-xs text-sm leading-relaxed text-tb-on-surface-variant">
                        Platform digital untuk melestarikan silsilah dan budaya Batak untuk generasi kini dan masa depan.
                    </p>
                    <div className="flex gap-4">
                        {socialLinks.map((social) => (
                            <a
                                key={social.label}
                                href="#"
                                aria-label={social.label}
                                className="text-lg text-tb-outline transition-colors hover:text-tb-primary"
                            >
                                <social.icon className="h-5 w-5" />
                            </a>
                        ))}
                    </div>
                </div>
                <div>
                    <h3 className="mb-4 text-sm font-bold">Navigasi</h3>
                    <ul className="space-y-3 text-sm text-tb-on-surface-variant">
                       {footerNav.navigasi.map((item) => (
        <li key={item.label}>
            <Link href={item.href} className="transition-colors hover:text-tb-primary">
                {item.label}
            </Link>
        </li>
    ))}
                    </ul>
                </div>
                <div>
                    <h3 className="mb-4 text-sm font-bold">Bantuan</h3>
                    <ul className="space-y-3 text-sm text-tb-on-surface-variant">
                        {footerNav.bantuan.map((item) => (
                            <li key={item}>
                                <a href="#" className="transition-colors hover:text-tb-primary">
                                    {item}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
                <div>
                    <h3 className="mb-4 text-sm font-bold">Unduh Aplikasi</h3>
                    <p className="mb-4 text-xs text-tb-on-surface-variant">Telusuri tarombo di mana saja, kapan saja.</p>
                    <div className="space-y-3">
                        <a
                            href="#"
                            className="flex w-fit items-center gap-3 rounded-lg bg-tb-on-surface px-4 py-2 text-white transition-colors hover:bg-black"
                        >
                            <Apple className="h-6 w-6" />
                            <div className="text-left">
                                <p className="text-[8px] uppercase leading-none">Download on the</p>
                                <p className="mt-1 text-sm font-semibold leading-none">App Store</p>
                            </div>
                        </a>
                        <a
                            href="#"
                            className="flex w-fit items-center gap-3 rounded-lg bg-tb-on-surface px-4 py-2 text-white transition-colors hover:bg-black"
                        >
                            <Play className="h-5 w-5" />
                            <div className="text-left">
                                <p className="text-[8px] uppercase leading-none">GET IT ON</p>
                                <p className="mt-1 text-sm font-semibold leading-none">Google Play</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div className="mx-auto max-w-7xl border-t border-tb-outline-variant pt-6 text-center text-xs text-tb-outline">
                © 2026 Tarombo Batak. All rights reserved.
            </div>
        </footer>
    );
}
