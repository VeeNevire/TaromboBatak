import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import margaRoutes from '@/routes/marga';

type Member = {
    id: number;
    name: string;
    gender: string | null;
    birth_year: string | null;
};
type Props = {
    marga: { id: number; name: string };
    people: {
        data: Member[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
    };
    filters: { search: string };
};

export default function MargaNames({ marga, people, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const pageLink = (page: number) =>
        margaRoutes.names(marga.id, {
            query: { search: filters.search || undefined, page },
        });

    return (
        <>
            <Head title={`Daftar Nama ${marga.name}`} />
            <div className="flex min-h-full flex-1 flex-col gap-6 bg-tb-surface p-4 text-tb-on-surface md:p-6 lg:p-8">
                <div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={margaRoutes.index()}>
                            <ArrowLeft className="size-4" />
                            Kembali ke Marga
                        </Link>
                    </Button>
                    <h1 className="mt-4 font-display text-2xl font-bold">
                        Daftar Nama Marga {marga.name}
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        {people.total} nama tercatat
                        {filters.search
                            ? ` untuk pencarian “${filters.search}”`
                            : ''}
                        .
                    </p>
                </div>
                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            margaRoutes.names(marga.id).url,
                            { search },
                            { preserveState: true, preserveScroll: true },
                        );
                    }}
                >
                    <Input
                        aria-label="Cari nama"
                        placeholder="Cari nama…"
                        className="w-full sm:max-w-sm"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit">
                        <Search className="size-4" />
                        Cari
                    </Button>
                </form>
                <div className="overflow-x-auto rounded-xl border border-tb-outline-variant bg-tb-surface-bright">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-tb-surface-container text-tb-on-surface-variant">
                            <tr>
                                <th scope="col" className="px-4 py-3">
                                    No.
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Jenis Kelamin
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Tahun Lahir
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {people.data.map((person, index) => (
                                <tr
                                    key={person.id}
                                    className="border-t border-tb-outline-variant"
                                >
                                    <td className="px-4 py-3">
                                        {(people.from ?? 1) + index}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {person.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {person.gender === 'L'
                                            ? 'Laki-laki'
                                            : person.gender === 'P'
                                              ? 'Perempuan'
                                              : 'Belum dicatat'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {person.birth_year || 'Belum dicatat'}
                                    </td>
                                </tr>
                            ))}
                            {people.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-8 text-center text-tb-on-surface-variant"
                                    >
                                        {filters.search
                                            ? 'Tidak ada nama yang cocok dengan pencarian.'
                                            : 'Belum ada nama yang tercatat pada marga ini.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {people.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span>
                            {people.from}–{people.to} dari {people.total} nama
                        </span>
                        <div className="flex items-center gap-2">
                            {people.current_page > 1 && (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={pageLink(people.current_page - 1)}
                                    >
                                        Sebelumnya
                                    </Link>
                                </Button>
                            )}
                            <span>
                                Halaman {people.current_page} /{' '}
                                {people.last_page}
                            </span>
                            {people.current_page < people.last_page && (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={pageLink(people.current_page + 1)}
                                    >
                                        Berikutnya
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
