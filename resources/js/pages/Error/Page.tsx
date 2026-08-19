import { Head, Link } from '@inertiajs/react';
import { MotionConfig, motion } from 'framer-motion';
import { BookOpen, Home, Network, TreePine, Users } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { home } from '@/routes';
import budaya from '@/routes/budaya';
import komunitas from '@/routes/komunitas';
import marga from '@/routes/marga';
import tarombo from '@/routes/tarombo';

type ErrorCopy = {
    label: string;
    title: string;
    description: string;
};

const errorCopy: Record<number, ErrorCopy> = {
    401: {
        label: '401',
        title: 'Harus Masuk Dulu',
        description:
            'Halaman ini hanya dapat diakses setelah masuk. Silakan masuk terlebih dahulu untuk melanjutkan menelusuri silsilah keluarga.',
    },
    403: {
        label: '403',
        title: 'Akses Ditolak',
        description:
            'Halaman ini hanya terbuka untuk orang-orang tertentu dalam keluarga. Hubungi admin marga bila menurutmu akses ini keliru.',
    },
    404: {
        label: '404',
        title: 'Ada yang Hilang di Jalur Silsilah',
        description:
            'Sepertinya halaman yang kamu tuju telah berpindah, dihapus, atau tidak pernah ada. Mari kembali dan lanjutkan menelusuri sejarah leluhur.',
    },
    500: {
        label: '500',
        title: 'Terjadi Kendala di Pelupuh',
        description:
            'Ada sesuatu yang tidak beres di dalam sistem kami. Tim kami sedang memperbaikinya, coba beberapa saat lagi.',
    },
    503: {
        label: '503',
        title: 'Sedang dalam Perawatan',
        description:
            'Kami sedang merapikan tarombo. Silakan kembali beberapa saat lagi, ya.',
    },
};

const quickLinks = [
    { label: 'Telusuri Marga', href: marga.view(), icon: TreePine },
    { label: 'Budaya Batak', href: budaya.view(), icon: BookOpen },
    { label: 'Komunitas', href: komunitas.view(), icon: Users },
];

function LostAncestorMotif() {
    return (
        <motion.svg
            viewBox="0 0 140 140"
            className="h-44 w-44 md:h-56 md:w-56"
            fill="none"
            aria-hidden="true"
            initial={{ opacity: 0, scale: 0.85 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5, ease: 'easeOut' }}
        >
            <g stroke="var(--color-tb-outline)" strokeWidth="2">
                <path d="M70 50 V 58" />
                <path d="M70 58 H 40" />
                <path d="M70 58 H 100" />
                <path d="M40 58 V 66" />
                <path d="M100 58 V 66" />
            </g>

            <circle cx="70" cy="40" r="10" fill="var(--color-tb-primary)" />
            <circle
                cx="40"
                cy="80"
                r="10"
                fill="var(--color-tb-surface-container)"
                stroke="var(--color-tb-outline)"
            />

            <circle
                cx="100"
                cy="80"
                r="10"
                stroke="var(--color-tb-primary)"
                strokeWidth="2"
                strokeDasharray="4 3"
            />
            <circle
                cx="100"
                cy="80"
                r="10"
                stroke="var(--color-tb-primary)"
                strokeWidth="2"
                style={{
                    transformBox: 'fill-box',
                    transformOrigin: 'center',
                    animation: 'tb-pulse 1.8s ease-out infinite',
                }}
            />
            <text
                x="100"
                y="86"
                textAnchor="middle"
                fontSize="15"
                fontWeight="700"
                fill="var(--color-tb-primary)"
            >
                ?
            </text>
        </motion.svg>
    );
}

export default function ErrorPage({ status }: { status: number }) {
    const copy: ErrorCopy = errorCopy[status] ?? {
        label: String(status),
        title: 'Terjadi Kesalahan',
        description:
            'Ada sesuatu yang tidak beres. Silakan coba lagi beberapa saat.',
    };

    return (
        <MotionConfig reducedMotion="user">
            <div className="bg-tb-surface font-body text-tb-on-surface antialiased">
                <Head title={copy.title} />

                <SiteHeader />

                <main className="relative overflow-hidden">
                    <div
                        aria-hidden="true"
                        className="bg-tb-gorga absolute inset-0 opacity-60"
                    />
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(179,75,30,0.08),transparent_60%)]"
                    />

                    <section className="relative mx-auto flex min-h-[68vh] max-w-3xl flex-col items-center px-6 py-16 text-center md:py-24">
                        <Reveal variant="scaleUp">
                            <LostAncestorMotif />
                        </Reveal>

                        <Reveal delay={0.1}>
                            <div className="mt-8 inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-1.5 text-sm font-semibold tracking-wide text-tb-primary">
                                {copy.label} · Halaman Tidak Ditemukan
                            </div>
                        </Reveal>

                        <Reveal delay={0.15}>
                            <h1 className="mt-6 font-display text-4xl leading-tight font-bold md:text-5xl">
                                {copy.title}
                            </h1>
                        </Reveal>

                        <Reveal delay={0.2}>
                            <p className="mt-5 max-w-xl text-lg leading-relaxed text-tb-on-surface-variant">
                                {copy.description}
                            </p>
                        </Reveal>

                        <Reveal delay={0.3}>
                            <div className="mt-9 flex flex-wrap items-center justify-center gap-4">
                                <Link
                                    href={home()}
                                    className="flex items-center gap-2 rounded-full bg-tb-primary px-7 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                >
                                    <Home className="h-4 w-4" />
                                    Kembali ke Beranda
                                </Link>
                                <Link
                                    href={tarombo.view()}
                                    className="flex items-center gap-2 rounded-full border border-tb-outline bg-tb-surface-bright px-7 py-3 font-medium transition-colors hover:border-tb-primary hover:text-tb-primary"
                                >
                                    <Network className="h-4 w-4" />
                                    Telusuri Tarombo
                                </Link>
                            </div>
                        </Reveal>

                        <Reveal delay={0.35}>
                            <div className="mt-10 flex flex-wrap items-center justify-center gap-3">
                                {quickLinks.map((link) => (
                                    <Link
                                        key={link.label}
                                        href={link.href}
                                        className="inline-flex items-center gap-2 rounded-full border border-tb-outline-variant bg-tb-surface-bright/70 px-4 py-2 text-sm font-medium text-tb-on-surface-variant transition-colors hover:text-tb-primary"
                                    >
                                        <link.icon className="h-4 w-4" />
                                        {link.label}
                                    </Link>
                                ))}
                            </div>
                        </Reveal>
                    </section>
                </main>

                <SiteFooter />
            </div>
        </MotionConfig>
    );
}
