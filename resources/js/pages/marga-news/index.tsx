import { Head, router } from '@inertiajs/react';
import { ExternalLink, Globe, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    MargaChips,
    Pager,
    formatNewsDate,
} from '@/components/marga-news/news-parts';
import type {
    MargaNewsItem,
    Paginated,
} from '@/components/marga-news/news-parts';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import margaNews from '@/routes/marga-news';

type MargaOption = { id: number; name: string };

export default function MargaNewsIndex({
    news,
    filters,
    margas,
    myMargaId,
}: {
    news: Paginated<MargaNewsItem>;
    filters: { marga: number | null; q: string };
    margas: MargaOption[];
    myMargaId: number | null;
}) {
    const [search, setSearch] = useState(filters.q);
    const myMarga = margas.find((marga) => marga.id === myMargaId);

    const visit = (next: { marga?: number | null; q?: string }) => {
        const marga = next.marga === undefined ? filters.marga : next.marga;
        const q = next.q === undefined ? filters.q : next.q;

        router.get(
            margaNews.index.url({
                query: {
                    ...(marga ? { marga } : {}),
                    ...(q ? { q } : {}),
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search.trim() });
    };

    const chipClass = (active: boolean) =>
        cn(
            'rounded-full border px-3 py-1 text-xs font-semibold transition-colors',
            active
                ? 'text-tb-on-primary border-tb-primary bg-tb-primary'
                : 'border-tb-outline-variant text-tb-on-surface hover:bg-tb-surface-container',
        );

    return (
        <>
            <Head title="Berita Marga-Marga" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        <Globe className="size-6 text-tb-primary" />
                        Berita Marga-Marga
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Kabar kegiatan punguan, parsadaan, dan pomparan marga
                        dari berbagai portal berita. Klik judul untuk membaca
                        artikel aslinya.
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    <form onSubmit={submitSearch} className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-on-surface-variant" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari judul berita..."
                            aria-label="Cari judul berita"
                            className="border-tb-outline-variant bg-tb-surface-bright pl-9"
                        />
                    </form>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => visit({ marga: null })}
                            className={chipClass(filters.marga === null)}
                        >
                            Semua marga
                        </button>
                        {myMarga && (
                            <button
                                type="button"
                                onClick={() => visit({ marga: myMarga.id })}
                                className={chipClass(
                                    filters.marga === myMarga.id,
                                )}
                            >
                                Marga saya: {myMarga.name}
                            </button>
                        )}
                        {margas.length > 0 && (
                            <select
                                value={filters.marga ?? ''}
                                onChange={(event) =>
                                    visit({
                                        marga: event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    })
                                }
                                aria-label="Filter marga"
                                className="rounded-full border border-tb-outline-variant bg-tb-surface-bright px-3 py-1 text-xs font-semibold text-tb-on-surface"
                            >
                                <option value="">Pilih marga…</option>
                                {margas.map((marga) => (
                                    <option key={marga.id} value={marga.id}>
                                        {marga.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                {news.data.length === 0 ? (
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="py-10 text-center text-sm text-tb-on-surface-variant">
                            Belum ada berita
                            {filters.marga || filters.q
                                ? ' untuk filter ini.'
                                : '. Berita baru akan muncul setelah disetujui admin.'}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex flex-col gap-3">
                        {news.data.map((item) => (
                            <Card
                                key={item.id}
                                className="border-tb-outline-variant bg-tb-surface-bright"
                            >
                                <CardContent className="flex flex-col gap-2 p-4">
                                    <div className="flex flex-wrap items-center gap-x-2 text-xs text-tb-on-surface-variant">
                                        {item.publisher && (
                                            <span className="font-semibold text-tb-primary">
                                                {item.publisher}
                                            </span>
                                        )}
                                        {item.publisher &&
                                            item.published_at && <span>·</span>}
                                        {formatNewsDate(item.published_at)}
                                    </div>
                                    <a
                                        href={item.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="group inline-flex items-start gap-1.5 font-display text-lg leading-snug font-bold text-tb-on-surface hover:text-tb-primary"
                                    >
                                        {item.title}
                                        <ExternalLink className="mt-1 size-4 shrink-0 opacity-50 group-hover:opacity-100" />
                                    </a>
                                    {(item.summary ?? item.excerpt) && (
                                        <p className="text-sm text-tb-on-surface-variant">
                                            {item.summary ?? item.excerpt}
                                        </p>
                                    )}
                                    <MargaChips margas={item.margas} />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Pager page={news} />
            </div>
        </>
    );
}

MargaNewsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Berita Marga-Marga', href: margaNews.index() },
    ],
};
