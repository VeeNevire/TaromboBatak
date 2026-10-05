import { Head, Link } from '@inertiajs/react';
import { Folder, Search } from 'lucide-react';
import { useState } from 'react';
import margaRoutes from '@/routes/marga';

type Marga = {
    id: number;
    name: string;
    color?: string | null;
    documents_count?: number;
};

export default function SelectMarga({
    feature,
    margas,
}: {
    feature: 'documents' | 'ai';
    margas: Marga[];
}) {
    const isAi = feature === 'ai';
    const [search, setSearch] = useState('');
    const filteredMargas = margas.filter((marga) =>
        marga.name
            .toLocaleLowerCase('id')
            .includes(search.trim().toLocaleLowerCase('id')),
    );

    return (
        <>
            <Head title={isAi ? 'Tanya Ito Tarombo' : 'Dokumen Marga'} />
            <div className="min-h-full flex-1 bg-tb-surface px-4 py-6 text-tb-on-surface sm:px-6 lg:px-8">
                <h1 className="font-display text-2xl font-bold">
                    {isAi ? 'Pilih marga untuk bertanya' : 'Dokumen Marga'}
                </h1>
                <p className="mt-1 text-sm text-tb-on-surface-variant">
                    {isAi
                        ? 'Pilih marga untuk melanjutkan.'
                        : 'Pilih folder marga untuk mengelola dokumennya.'}
                </p>
                <div className="mt-6">
                    <label htmlFor="search-marga" className="sr-only">
                        Cari marga
                    </label>
                    <div className="relative max-w-xl">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-tb-on-surface-variant"
                        />
                        <input
                            id="search-marga"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari marga…"
                            className="h-12 w-full rounded-xl border border-tb-outline-variant bg-tb-surface-bright pr-4 pl-11 text-sm focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                        />
                    </div>
                    <p
                        className="mt-2 text-sm text-tb-on-surface-variant"
                        role="status"
                    >
                        {filteredMargas.length} marga ditemukan
                    </p>
                    <div
                        className={
                            isAi
                                ? 'mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
                                : 'mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6'
                        }
                    >
                        {filteredMargas.map((marga) =>
                            isAi ? (
                                <Link
                                    key={marga.id}
                                    href={margaRoutes.ai.show(marga.id).url}
                                    className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-5 shadow-sm transition hover:border-tb-primary hover:shadow-md focus-visible:ring-2 focus-visible:ring-tb-primary"
                                >
                                    <span className="font-medium">
                                        {marga.name}
                                    </span>
                                </Link>
                            ) : (
                                <Link
                                    key={marga.id}
                                    href={
                                        margaRoutes.documents.index(marga.id)
                                            .url
                                    }
                                    className="group flex flex-col items-center gap-2 rounded-2xl border border-tb-outline-variant bg-tb-surface-bright px-3 py-5 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-tb-primary hover:shadow-md focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:outline-none"
                                >
                                    <Folder
                                        aria-hidden
                                        strokeWidth={1.5}
                                        className="size-14 transition group-hover:scale-105"
                                        style={{
                                            color:
                                                marga.color ??
                                                'var(--color-tb-primary)',
                                            fill: `color-mix(in srgb, ${marga.color ?? 'var(--color-tb-primary)'} 25%, transparent)`,
                                        }}
                                    />
                                    <span className="line-clamp-2 text-sm font-semibold">
                                        {marga.name}
                                    </span>
                                    <span className="text-xs text-tb-on-surface-variant">
                                        {marga.documents_count ?? 0} dokumen
                                    </span>
                                </Link>
                            ),
                        )}
                        {filteredMargas.length === 0 && (
                            <p className="col-span-full rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-8 text-center text-sm text-tb-on-surface-variant">
                                Marga tidak ditemukan. Coba nama lain.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

SelectMarga.layout = { breadcrumbs: [{ title: 'Kelola Data', href: '#' }] };
