import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import margaRoutes from '@/routes/marga';

type Marga = { id: number; name: string };

export default function SelectMarga({
    feature,
    margas,
}: {
    feature: 'documents' | 'ai';
    margas: Marga[];
}) {
    const isAi = feature === 'ai';
    const [selectedMarga, setSelectedMarga] = useState('');
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
                        : 'Pilih marga dari form berikut untuk mengelola dokumennya.'}
                </p>
                {!isAi ? (
                    <div className="mt-6 max-w-2xl rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 shadow-sm">
                        <label htmlFor="marga" className="text-sm font-medium">
                            Marga
                        </label>
                        <select
                            id="marga"
                            value={selectedMarga}
                            onChange={(event) =>
                                setSelectedMarga(event.target.value)
                            }
                            className="mt-2 h-12 w-full rounded-xl border border-tb-outline-variant bg-tb-surface px-4 text-sm focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                        >
                            <option value="">Pilih marga</option>
                            {margas.map((marga) => (
                                <option key={marga.id} value={marga.id}>
                                    {marga.name}
                                </option>
                            ))}
                        </select>
                        <div className="mt-4">
                            {selectedMarga ? (
                                <Link
                                    href={
                                        margaRoutes.documents.index(
                                            Number(selectedMarga),
                                        ).url
                                    }
                                    className="inline-flex min-h-11 items-center rounded-xl bg-tb-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-tb-primary-light"
                                >
                                    Buka Dokumen
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    disabled
                                    className="min-h-11 rounded-xl bg-tb-surface-container px-5 py-3 text-sm font-medium text-tb-on-surface-variant"
                                >
                                    Buka Dokumen
                                </button>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="mt-6">
                        <label
                            htmlFor="search-marga"
                            className="text-sm font-medium"
                        >
                            Cari marga
                        </label>
                        <input
                            id="search-marga"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Ketik nama marga…"
                            className="mt-2 h-12 w-full max-w-xl rounded-xl border border-tb-outline-variant bg-tb-surface-bright px-4 text-sm focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                        />
                        <p
                            className="mt-2 text-sm text-tb-on-surface-variant"
                            role="status"
                        >
                            {filteredMargas.length} marga ditemukan
                        </p>
                        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredMargas.map((marga) => (
                                <Link
                                    key={marga.id}
                                    href={
                                        (isAi
                                            ? margaRoutes.ai.show(marga.id)
                                            : margaRoutes.documents.index(
                                                  marga.id,
                                              )
                                        ).url
                                    }
                                    className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-5 shadow-sm transition hover:border-tb-primary hover:shadow-md focus-visible:ring-2 focus-visible:ring-tb-primary"
                                >
                                    <span className="font-medium">
                                        {marga.name}
                                    </span>
                                </Link>
                            ))}
                            {filteredMargas.length === 0 && (
                                <p className="col-span-full rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-8 text-center text-sm text-tb-on-surface-variant">
                                    Marga tidak ditemukan. Coba nama lain.
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

SelectMarga.layout = { breadcrumbs: [{ title: 'Kelola Data', href: '#' }] };
