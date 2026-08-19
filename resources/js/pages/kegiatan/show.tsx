import { Head, Link } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import {
    ArrowLeft,
    ArrowRight,
    Calendar,
    ExternalLink,
    MapPin,
} from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { home } from '@/routes';
import kegiatan from '@/routes/kegiatan';

type EventDetail = {
    id: number;
    title: string;
    description: string;
    location: string | null;
    registration_url: string | null;
    date: string;
    month: string;
    day: string;
    is_past: boolean;
};

type Props = {
    event: EventDetail;
};

export default function KegiatanShow({ event }: Props) {
    return (
        <MotionConfig reducedMotion="user">
            <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
                <Head title={event.title} />
                <SiteHeader />

                <main>
                    <article className="mx-auto max-w-4xl px-6 py-12 md:py-16">
                        <Reveal>
                            <Link
                                href={kegiatan.index()}
                                className="inline-flex items-center gap-2 text-sm font-medium text-tb-outline transition-colors hover:text-tb-primary"
                            >
                                <ArrowLeft className="h-4 w-4" /> Kembali ke
                                Event
                            </Link>

                            <div className="mt-8 inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-2 text-sm font-medium text-tb-primary">
                                <Calendar className="h-4 w-4" />
                                Event & Kegiatan Komunitas
                            </div>

                            <h1 className="mt-6 font-display text-3xl leading-tight font-bold md:text-5xl">
                                {event.title}
                            </h1>
                        </Reveal>

                        <Reveal delay={0.1}>
                            <div className="mt-8 flex flex-wrap items-center gap-6">
                                <div
                                    className={`flex w-24 flex-col items-center justify-center rounded-xl py-4 ${event.is_past ? 'bg-tb-surface-container text-tb-outline' : 'bg-tb-primary text-white'}`}
                                >
                                    <span className="text-sm font-bold uppercase">
                                        {event.month}
                                    </span>
                                    <span className="text-4xl font-bold">
                                        {event.day}
                                    </span>
                                </div>
                                <div className="flex-1">
                                    <div className="flex items-center gap-2 text-sm text-tb-on-surface-variant">
                                        <Calendar className="h-4 w-4" />
                                        {event.date}
                                    </div>
                                    {event.location && (
                                        <div className="mt-2 flex items-center gap-2 text-sm text-tb-on-surface-variant">
                                            <MapPin className="h-4 w-4" />
                                            {event.location}
                                        </div>
                                    )}
                                    <div className="mt-3">
                                        {event.is_past ? (
                                            <span className="inline-flex items-center rounded-full bg-gray-100 px-4 py-1.5 text-sm font-medium text-gray-600">
                                                Sudah Lewat
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-green-100 px-4 py-1.5 text-sm font-medium text-green-700">
                                                Akan Datang
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </Reveal>

                        <Reveal delay={0.15}>
                            <div className="prose prose-lg mt-10 max-w-none text-base leading-8 whitespace-pre-line text-tb-on-surface">
                                {event.description}
                            </div>
                        </Reveal>

                        {event.registration_url && !event.is_past && (
                            <Reveal delay={0.2}>
                                <div className="mt-10 rounded-2xl border border-tb-primary/20 bg-tb-primary/5 p-6 md:p-8">
                                    <h3 className="font-display text-xl font-bold">
                                        Tertarik untuk bergabung?
                                    </h3>
                                    <p className="mt-2 text-sm text-tb-on-surface-variant">
                                        Daftarkan diri Anda sekarang untuk
                                        mengikuti kegiatan ini.
                                    </p>
                                    <a
                                        href={event.registration_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="mt-4 inline-flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                    >
                                        Daftar Sekarang{' '}
                                        <ExternalLink className="h-4 w-4" />
                                    </a>
                                </div>
                            </Reveal>
                        )}

                        <Reveal delay={0.25}>
                            <div className="mt-16 rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-8 text-center md:p-10">
                                <h2 className="font-display text-xl font-bold md:text-2xl">
                                    Lihat Event Lainnya
                                </h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-tb-on-surface-variant">
                                    Jelajahi kegiatan dan acara komunitas Batak
                                    lainnya yang menarik.
                                </p>
                                <div className="mt-8 flex flex-wrap justify-center gap-4">
                                    <Link
                                        href={kegiatan.index()}
                                        className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                    >
                                        Lihat Semua Event{' '}
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                    <Link
                                        href={home()}
                                        className="flex items-center gap-2 rounded-full border border-tb-primary px-6 py-3 font-medium transition-colors hover:bg-tb-primary hover:text-white"
                                    >
                                        Kembali ke Beranda
                                    </Link>
                                </div>
                            </div>
                        </Reveal>
                    </article>
                </main>

                <SiteFooter />
            </div>
        </MotionConfig>
    );
}
