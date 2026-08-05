import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash } from 'lucide-react';
import { useState } from 'react';
import { AppAvatar } from '@/components/app-avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type PersonItem = {
    id: number;
    name: string;
    alias: string | null;
    marga: string | null;
    marga_color: string | null;
    parent: string | null;
    birth_year: string | null;
    created_at: string | null;
};

type Paginated = {
    data: PersonItem[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type MargaOption = { id: number; name: string };

type Props = {
    people: Paginated;
    filters: { search: string; marga_id: string | null };
    margas: MargaOption[];
    canManage: boolean;
};

export default function PeopleIndex({ people: page, filters, margas, canManage }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [toDelete, setToDelete] = useState<PersonItem | null>(null);
    const deleteForm = useForm({});

    const applyFilter = (updates: { search?: string; marga_id?: string | null }) => {
        router.get(
            people.index().url,
            {
                search: updates.search !== undefined ? updates.search : filters.search,
                marga_id: updates.marga_id !== undefined ? updates.marga_id : filters.marga_id,
            },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!toDelete) {
            return;
        }

        deleteForm.delete(people.destroy(toDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setToDelete(null),
        });
    };

    return (
        <>
            <Head title="Data Anggota" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {canManage ? 'Data Anggota' : 'Silsilah Keluarga Saya'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            {canManage
                                ? 'Kelola anggota silsilah keluarga dalam tarombo.'
                                : 'Anggota silsilah dari marga keluarga Anda.'}
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild className="rounded-full bg-tb-primary hover:bg-tb-primary-light">
                            <Link href={people.create()}>
                                <Plus className="size-4" /> Tambah Anggota
                            </Link>
                        </Button>
                    )}
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col gap-3 py-4 md:flex-row md:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-tb-outline" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyFilter({ search });
                                    }
                                }}
                                placeholder="Cari nama atau alias..."
                                className="border-tb-outline-variant bg-tb-surface-bright pl-10 focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                        </div>
                        {canManage && margas.length > 1 && (
                            <Select
                                value={filters.marga_id ?? 'all'}
                                onValueChange={(value) =>
                                    applyFilter({ marga_id: value === 'all' ? null : value })
                                }
                            >
                                <SelectTrigger className="w-full md:w-56 border-tb-outline-variant bg-tb-surface-bright">
                                    <SelectValue placeholder="Semua marga" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua marga</SelectItem>
                                    {margas.map((marga) => (
                                        <SelectItem key={marga.id} value={String(marga.id)}>
                                            {marga.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="overflow-x-auto py-0">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr className="border-b border-tb-outline-variant text-left text-xs text-tb-on-surface-variant">
                                    <th className="px-3 py-3 font-medium">Anggota</th>
                                    <th className="px-3 py-3 font-medium">Marga</th>
                                    <th className="px-3 py-3 font-medium">Orang Tua</th>
                                    <th className="px-3 py-3 font-medium">Lahir</th>
                                    {canManage && (
                                        <th className="px-3 py-3 text-right font-medium">Aksi</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-tb-outline-variant">
                                {page.data.map((person) => (
                                    <tr key={person.id} className="hover:bg-tb-surface-container/40">
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-3">
                                                <AppAvatar name={person.name} color={person.marga_color} />
                                                <div>
                                                    <p className="font-medium text-tb-on-surface">{person.name}</p>
                                                    {person.alias && (
                                                        <p className="text-xs text-tb-on-surface-variant">{person.alias}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3">
                                            {person.marga ? (
                                                <span
                                                    className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                                    style={{ backgroundColor: person.marga_color ?? 'var(--color-tb-primary)' }}
                                                >
                                                    {person.marga}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-tb-on-surface-variant">-</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {person.parent ?? '-'}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {person.birth_year ?? '-'}
                                        </td>
                                        {canManage && (
                                            <td className="px-3 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-tb-primary hover:bg-tb-surface-container"
                                                    >
                                                        <Link href={people.edit(person.id)}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                                        onClick={() => setToDelete(person)}
                                                    >
                                                        <Trash className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {page.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 5 : 4}
                                            className="px-3 py-10 text-center text-tb-on-surface-variant"
                                        >
                                            Tidak ada anggota yang cocok.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Pagination page={page} />

                <Dialog open={toDelete !== null} onOpenChange={(open) => !open && setToDelete(null)}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="text-tb-on-surface">Hapus Anggota</DialogTitle>
                            <DialogDescription>
                                Yakin ingin menghapus <strong>{toDelete?.name}</strong> dari silsilah? Tindakan ini tidak dapat dibatalkan.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setToDelete(null)}>
                                Batal
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={confirmDelete}
                                disabled={deleteForm.processing}
                            >
                                {deleteForm.processing ? 'Menghapus...' : 'Ya, Hapus'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

PeopleIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
    ],
};

function Pagination({ page }: { page: Paginated }) {
    const prevUrl = page.prev_page_url;
    const nextUrl = page.next_page_url;

    return (
        <div className="flex flex-col items-center justify-between gap-3 text-sm text-tb-on-surface-variant sm:flex-row">
            <p>
                Menampilkan {page.from ?? 0}–{page.to ?? 0} dari {page.total} anggota
            </p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                    disabled={!prevUrl}
                    onClick={() => prevUrl && router.get(prevUrl, {}, { preserveState: true })}
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                    disabled={!nextUrl}
                    onClick={() => nextUrl && router.get(nextUrl, {}, { preserveState: true })}
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    );
}
