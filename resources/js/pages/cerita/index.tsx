import { Head, Link, router } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import { ArrowRight, BookOpen, Search } from 'lucide-react';
import { useState } from 'react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { home } from '@/routes';
import cerita from '@/routes/cerita';

type StoryItem = {
    id: number;
    title: string;
    description: string;
    image: string | null;
    created_at: string | null;
};

type Paginated = {
    data: StoryItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Props = {
    stories: Paginated;
    filters: { search: string };
};

export default function CeritaIndex({ stories: page, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilter = (value: string) => {
        router.get(
            cerita.index().url,
            { search: value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <MotionConfig reducedMotion="user">
            <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
                <Head title="Cerita Leluhur & Budaya" />
                <SiteHeader />

                <main>
                    <section className="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6 py-16 text-center md:py-24">
                        <Reveal>
                            <div className="inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-2 text-sm font-medium text-tb-primary">
                                <BookOpen className="h-4 w-4" />
                                Warisan Leluhur
                            </div>
                        </Reveal>
                        <Reveal delay={0.1}>
                            <h1 className="font-display text-4xl leading-tight font-bold md:text-5xl lg:text-6xl">
                                Cerita Leluhur & Budaya
                            </h1>
                        </Reveal>
                        <Reveal delay={0.2}>
                            <p className="mx-auto max-w-3xl text-lg leading-relaxed text-tb-on-surface-variant">
                                Jelajahi kearifan, sejarah, dan nilai-nilai
                                luhur yang diwariskan turun-temurun dalam
                                cerita-cerita leluhur masyarakat Batak.
                            </p>
                        </Reveal>
                    </section>

                    <section className="mx-auto max-w-7xl px-6 pb-16">
                        <div className="mb-8">
                            <div className="relative mx-auto max-w-xl">
                                <Search className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            applyFilter(search);
                                        }
                                    }}
                                    placeholder="Cari cerita..."
                                    className="h-12 border-tb-outline-variant bg-tb-surface-bright pl-12 focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                            </div>
                        </div>

                        {page.data.length === 0 && (
                            <div className="py-16 text-center">
                                <BookOpen className="mx-auto h-12 w-12 text-tb-outline" />
                                <p className="mt-4 text-tb-on-surface-variant">
                                    {filters.search
                                        ? 'Tidak ada cerita yang cocok dengan pencarian Anda.'
                                        : 'Belum ada cerita untuk ditampilkan.'}
                                </p>
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {page.data.map((story, index) => (
                                <Reveal key={story.id} delay={index * 0.08}>
                                    <Link
                                        href={cerita.show(story.id)}
                                        className="group flex h-full flex-col overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright transition-all hover:shadow-lg hover:shadow-tb-primary/10"
                                    >
                                        {story.image ? (
                                            <div className="aspect-video w-full overflow-hidden bg-tb-surface-container">
                                                <img
                                                    src={story.image}
                                                    alt={story.title}
                                                    className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex aspect-video w-full items-center justify-center bg-tb-surface-container">
                                                <BookOpen className="h-12 w-12 text-tb-outline" />
                                            </div>
                                        )}
                                        <div className="flex flex-1 flex-col p-6">
                                            <h3 className="mb-2 font-display text-xl font-bold transition-colors group-hover:text-tb-primary">
                                                {story.title}
                                            </h3>
                                            <p className="mb-4 line-clamp-3 flex-1 text-sm leading-relaxed text-tb-on-surface-variant">
                                                {story.description}
                                            </p>
                                            {story.created_at && (
                                                <p className="text-xs text-tb-outline">
                                                    {story.created_at}
                                                </p>
                                            )}
                                        </div>
                                    </Link>
                                </Reveal>
                            ))}
                        </div>

                        {page.total > 0 && (
                            <div className="mt-12 flex flex-col items-center justify-between gap-4 text-sm text-tb-on-surface-variant sm:flex-row">
                                <p>
                                    Menampilkan {page.from ?? 0}–{page.to ?? 0}{' '}
                                    dari {page.total} cerita
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                                        disabled={!page.prev_page_url}
                                        onClick={() =>
                                            page.prev_page_url &&
                                            router.get(
                                                page.prev_page_url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                                        disabled={!page.next_page_url}
                                        onClick={() =>
                                            page.next_page_url &&
                                            router.get(
                                                page.next_page_url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
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
                                        Telusuri silsilah keluarga Anda atau
                                        pelajari tentang kekayaan budaya Batak.
                                    </p>
                                    <div className="mt-8 flex flex-wrap justify-center gap-4">
                                        <Link
                                            href={home()}
                                            className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                        >
                                            Kembali ke Beranda{' '}
                                            <ArrowRight className="h-4 w-4" />
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
