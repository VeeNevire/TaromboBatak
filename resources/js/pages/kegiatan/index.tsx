import { Head, Link, router } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import { ArrowRight, Calendar, MapPin, Search } from 'lucide-react';
import { useState } from 'react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { home } from '@/routes';
import kegiatan from '@/routes/kegiatan';

type EventItem = {
    id: number;
    title: string;
    description: string;
    location: string | null;
    date: string;
    month: string;
    day: string;
    is_past: boolean;
};

type Paginated = {
    data: EventItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Props = {
    events: Paginated;
    filters: { search: string };
};

export default function KegiatanIndex({ events: page, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilter = (value: string) => {
        router.get(
            kegiatan.index().url,
            { search: value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <MotionConfig reducedMotion="user">
            <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
                <Head title="Event & Kegiatan Komunitas" />
                <SiteHeader />

                <main>
                    <section className="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6 py-16 text-center md:py-24">
                        <Reveal>
                            <div className="inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-2 text-sm font-medium text-tb-primary">
                                <Calendar className="h-4 w-4" />
                                Event & Kegiatan
                            </div>
                        </Reveal>
                        <Reveal delay={0.1}>
                            <h1 className="font-display text-4xl font-bold leading-tight md:text-5xl lg:text-6xl">
                                Event & Kegiatan Komunitas
                            </h1>
                        </Reveal>
                        <Reveal delay={0.2}>
                            <p className="mx-auto max-w-3xl text-lg leading-relaxed text-tb-on-surface-variant">
                                Temukan berbagai kegiatan dan acara komunitas Batak. Mari bersama-sama melestarikan budaya dan mempererat tali persaudaraan.
                            </p>
                        </Reveal>
                    </section>

                    <section className="mx-auto max-w-7xl px-6 pb-16">
                        <div className="mb-8">
                            <div className="relative mx-auto max-w-xl">
                                <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            applyFilter(search);
                                        }
                                    }}
                                    placeholder="Cari event..."
                                    className="h-12 border-tb-outline-variant bg-tb-surface-bright pl-12 focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                            </div>
                        </div>

                        {page.data.length === 0 && (
                            <div className="py-16 text-center">
                                <Calendar className="mx-auto h-12 w-12 text-tb-outline" />
                                <p className="mt-4 text-tb-on-surface-variant">
                                    {filters.search ? 'Tidak ada event yang cocok dengan pencarian Anda.' : 'Belum ada event untuk ditampilkan.'}
                                </p>
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {page.data.map((event, index) => (
                                <Reveal key={event.id} delay={index * 0.08}>
                                    <Link
                                        href={kegiatan.show(event.id)}
                                        className={`group flex h-full flex-col overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright transition-all hover:shadow-lg hover:shadow-tb-primary/10 ${event.is_past ? 'opacity-60' : ''}`}
                                    >
                                        <div className="flex items-start gap-4 p-6">
                                            <div className={`flex w-16 shrink-0 flex-col items-center justify-center rounded-lg py-3 ${event.is_past ? 'bg-tb-surface-container text-tb-outline' : 'bg-tb-primary text-white'}`}>
                                                <span className="text-xs font-bold uppercase">{event.month}</span>
                                                <span className="text-2xl font-bold">{event.day}</span>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="mb-2 font-display text-lg font-bold transition-colors group-hover:text-tb-primary">
                                                    {event.title}
                                                </h3>
                                                <p className="mb-3 line-clamp-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                                    {event.description}
                                                </p>
                                                {event.location && (
                                                    <div className="flex items-center gap-1 text-xs text-tb-outline">
                                                        <MapPin className="h-3.5 w-3.5" />
                                                        {event.location}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="mt-auto border-t border-tb-outline-variant px-6 py-3">
                                            {event.is_past ? (
                                                <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                                    Sudah Lewat
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">
                                                    Akan Datang
                                                </span>
                                            )}
                                        </div>
                                    </Link>
                                </Reveal>
                            ))}
                        </div>

                        {page.total > 0 && (
                            <div className="mt-12 flex flex-col items-center justify-between gap-4 text-sm text-tb-on-surface-variant sm:flex-row">
                                <p>
                                    Menampilkan {page.from ?? 0}–{page.to ?? 0} dari {page.total} event
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                                        disabled={!page.prev_page_url}
                                        onClick={() => page.prev_page_url && router.get(page.prev_page_url, {}, { preserveState: true })}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                                        disabled={!page.next_page_url}
                                        onClick={() => page.next_page_url && router.get(page.next_page_url, {}, { preserveState: true })}
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}

                        <div className="mt-16">
                            <Reveal>
                                <div className="rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-8 text-center md:p-12">
                                    <h2 className="font-display text-2xl font-bold md:text-3xl">
                                        Jelajahi Lebih Banyak
                                    </h2>
                                    <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                        Telusuri silsilah keluarga Anda atau pelajari tentang kekayaan budaya Batak.
                                    </p>
                                    <div className="mt-8 flex flex-wrap justify-center gap-4">
                                        <Link
                                            href={home()}
                                            className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                        >
                                            Kembali ke Beranda <ArrowRight className="h-4 w-4" />
                                        </Link>
                                    </div>
                                </div>
                            </Reveal>
                        </div>
                    </section>
                </main>

                <SiteFooter />
            </div>
        </MotionConfig>
    );
}
