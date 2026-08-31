import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
 import { AnimatePresence, motion } from 'framer-motion';
  import {
      ChevronLeft,
      ChevronRight,
      NotebookPen,
      Pencil,
      Plus,
      Route,
      Search,
      Trash2,
  } from 'lucide-react';
  import { useEffect, useMemo, useState } from 'react';
  import { toast } from 'sonner';

import { toast } from 'sonner';
import { AppAvatar } from '@/components/app-avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type PersonItem = {
    id: number;
    name: string;
    alias: string | null;
    marga: string | null;
    marga_id: number | null;
    marga_color: string | null;
    parent: string | null;
    children_count: number;
    birth_year: string | null;
    chain: string | null;
    pending: boolean;
    created_at: string | null;
    editable: boolean;
};

type Paginated = {
    data: PersonItem[];
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url?: string | null;
    next_page_url?: string | null;
};

type MargaOption = { id: number; name: string };

type Props = {
    people: Paginated;
    filters: { search: string; marga_id: string | null };
    margas: MargaOption[];
    canManage: boolean;
    hasMarga: boolean;
};

export default function PeopleIndex({
    people: page,
    filters,
    margas,
    canManage,
    hasMarga,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [margaFilter, setMargaFilter] = useState(filters.marga_id ?? 'all');
    const [currentPage, setCurrentPage] = useState(1);
    const [toDelete, setToDelete] = useState<PersonItem | null>(null);
    const deleteForm = useForm<{ person?: string }>({});
    const filteredPeople = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();

        return page.data.filter((person) => {
            const matchesSearch =
                query === '' ||
                [person.name, person.alias ?? '', person.marga ?? ''].some(
                    (value) => value.toLocaleLowerCase().includes(query),
                );
            const matchesMarga =
                margaFilter === 'all' || String(person.marga_id) === margaFilter;

            return matchesSearch && matchesMarga;
        });
    }, [margaFilter, page, search]);
    const pageSize = 12;
    const totalPages = Math.max(1, Math.ceil(filteredPeople.length / pageSize));
    const paginatedPeople = useMemo(() => {
        const start = (currentPage - 1) * pageSize;

        return filteredPeople.slice(start, start + pageSize);
    }, [currentPage, filteredPeople]);

    useEffect(() => {
        setCurrentPage(1);
    }, [margaFilter, search]);

    useEffect(() => {
        setCurrentPage((current) => Math.min(current, totalPages));
    }, [totalPages]);
 const showActions = canManage || page.data.some((person) => person.editable);
    const showActions = canManage || page.data.some((person) => person.editable);

    const confirmDelete = () => {
        if (!toDelete) {
            return;
        }

        deleteForm.delete(people.destroy(toDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setToDelete(null),
            onError: () => {
                toast.error(
                    deleteForm.errors.person ??
                        'Anggota gagal dihapus dari database.',
                );
            },
        });
    };

    return (
        <>
            <Head title="Data Anggota" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {canManage
                                ? 'Data Anggota'
                                : 'Silsilah Keluarga Saya'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            {canManage
                                ? 'Kelola anggota silsilah keluarga dalam tarombo.'
                                : 'Anggota silsilah dari marga keluarga Anda.'}
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            asChild
                            className="rounded-full bg-tb-primary hover:bg-tb-primary-light"
                        >
                            <Link href={people.create()}>
                                <Plus className="size-4" /> Tambah Anggota
                            </Link>
                        </Button>
                    )}
                    {!canManage && hasMarga && (
                        <Button
                            asChild
                            className="rounded-full bg-tb-primary hover:bg-tb-primary-light"
                        >
                            <Link href={people.create()}>
                                <Plus className="size-4" /> Tambah Keluarga
                            </Link>
                        </Button>
                    )}
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col gap-3 py-4 md:flex-row md:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-tb-outline" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama, alias, atau marga..."
                                className="border-tb-outline-variant bg-tb-surface-bright pl-10 focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                        </div>
                        {canManage && margas.length > 1 && (
                            <Select
                                value={margaFilter}
                                onValueChange={setMargaFilter}
                            >
                                <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright md:w-56">
                                    <SelectValue placeholder="Semua marga" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua marga
                                    </SelectItem>
                                    {margas.map((marga) => (
                                        <SelectItem
                                            key={marga.id}
                                            value={String(marga.id)}
                                        >
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
                                    <th className="px-3 py-3 font-medium">
                                        Anggota
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Marga
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Orang Tua
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Lahir
                                    </th>
                                    {showActions && (
                                        <th className="px-3 py-3 text-right font-medium">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-tb-outline-variant">
                                <AnimatePresence initial={false}>
                                    {paginatedPeople.map((person) => (
                                        <motion.tr
                                            key={person.id}
                                            layout
                                            initial={{ opacity: 0, y: 8 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -8 }}
                                            transition={{
                                                duration: 0.18,
                                                ease: 'easeOut',
                                            }}
                                            className="hover:bg-tb-surface-container/40"
                                        >
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-3">
                                                <AppAvatar
                                                    name={person.name}
                                                    color={person.marga_color}
                                                />
                                                <div>
                                                    <p className="font-medium text-tb-on-surface">
                                                        {person.name}
                                                    </p>
                                                    {person.alias && (
                                                        <p className="text-xs text-tb-on-surface-variant">
                                                            {person.alias}
                                                        </p>
                                                    )}
                                                    {person.chain ? (
                                                        <p className="mt-0.5 text-[11px] font-medium text-tb-primary">
                                                            No. {person.chain}
                                                        </p>
                                                    ) : person.pending ? (
                                                        <p className="mt-0.5 text-[11px] font-medium text-tb-outline">
                                                            —
                                                        </p>
                                                    ) : null}
                                                    {person.pending && (
                                                        <span className="mt-0.5 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-px text-[10px] font-semibold text-amber-700">
                                                            Belum tersambung
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3">
                                            {person.marga ? (
                                                <span
                                                    className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                                    style={{
                                                        backgroundColor:
                                                            person.marga_color ??
                                                            'var(--color-tb-primary)',
                                                    }}
                                                >
                                                    {person.marga}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-tb-on-surface-variant">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {person.parent ?? '-'}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {person.birth_year ?? '-'}
                                        </td>
                                        {showActions && (
                                            <td className="px-3 py-3">
                                                <div className="flex justify-end gap-1">
                                                    {canManage ? (
                                                        <>
                                                            <Button
                                                                title="Edit"
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-tb-primary hover:bg-tb-surface-container"
                                                            >
                                                                <Link
                                                                    href={people.edit(
                                                                        person.id,
                                                                    )}
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                title="Detail silsilah keluarga"
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950"
                                                            >
                                                                <Link
                                                                    href={people.show(
                                                                        person.id,
                                                                    )}
                                                                >
                                                                    <NotebookPen className="size-4" />
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                title="Buka silsilah keluarga"
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-green-600 hover:bg-green-50 dark:hover:bg-green-950"
                                                            >
                                                                <Link
                                                                    href={people.silsilah(
                                                                        person.id,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noopener"
                                                                >
                                                                    <Route className="size-4" />
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                title="Hapus dari database"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                                                onClick={() => {
                                                                    deleteForm.clearErrors();
                                                                    setToDelete(
                                                                        person,
                                                                    );
                                                                }}
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </>
                                                    ) : (
                                                        person.editable && (
                                                            <Button
                                                                title="Ubah keluarga yang Anda buat"
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-tb-primary hover:bg-tb-surface-container"
                                                            >
                                                                <Link
                                                                    href={people.edit(
                                                                        person.id,
                                                                    )}
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Link>
                                                            </Button>
                                                        )
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                        </motion.tr>
                                    ))}
                                </AnimatePresence>
                                {filteredPeople.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={showActions ? 5 : 4}
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

                <div className="text-sm text-tb-on-surface-variant">
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <span>
                            Menampilkan{' '}
                            {filteredPeople.length === 0
                                ? 0
                                : (currentPage - 1) * pageSize + 1}
                            –
                            {Math.min(
                                currentPage * pageSize,
                                filteredPeople.length,
                            )}{' '}
                            dari {filteredPeople.length} anggota
                        </span>
                        {totalPages > 1 && (
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="border-tb-outline-variant bg-tb-surface-bright"
                                    disabled={currentPage === 1}
                                    onClick={() =>
                                        setCurrentPage((current) =>
                                            Math.max(1, current - 1),
                                        )
                                    }
                                    aria-label="Halaman sebelumnya"
                                >
                                    <ChevronLeft className="size-4" />
                                    Sebelumnya
                                </Button>
                                <span className="min-w-20 text-center text-xs font-medium">
                                    Halaman {currentPage} dari {totalPages}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="border-tb-outline-variant bg-tb-surface-bright"
                                    disabled={currentPage === totalPages}
                                    onClick={() =>
                                        setCurrentPage((current) =>
                                            Math.min(totalPages, current + 1),
                                        )
                                    }
                                    aria-label="Halaman berikutnya"
                                >
                                    Berikutnya
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                <Dialog
                    open={toDelete !== null}
                    onOpenChange={(open) => !open && setToDelete(null)}
                >
                    <DialogContent className="border-tb-outline-variant bg-tb-surface-bright sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="text-tb-on-surface">
                                Hapus Anggota
                            </DialogTitle>
                            <DialogDescription>
                                Yakin ingin menghapus{' '}
                                <strong>{toDelete?.name}</strong> dari database?
                                {toDelete && toDelete.children_count > 0 ? (
                                    <span className="mt-2 block font-medium text-red-700 dark:text-red-300">
                                        Anggota ini masih memiliki{' '}
                                        {toDelete.children_count} keturunan.
                                        Hapus keturunan paling bawah terlebih
                                        dahulu.
                                    </span>
                                ) : (
                                    ' Anggota yang masih menjadi orang tua tidak dapat dihapus.'
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        {deleteForm.errors.person && (
                            <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                                {deleteForm.errors.person}
                            </div>
                        )}
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setToDelete(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={confirmDelete}
                                disabled={deleteForm.processing}
                            >
                                {deleteForm.processing
                                    ? 'Menghapus...'
                                    : 'Ya, Hapus'}
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

export function Pagination({ page }: { page: Paginated }) {
    const prevUrl = page.prev_page_url;
    const nextUrl = page.next_page_url;

    return (
        <div className="flex flex-col items-center justify-between gap-3 text-sm text-tb-on-surface-variant sm:flex-row">
            <p>
                Menampilkan {page.from ?? 0}–{page.to ?? 0} dari {page.total}{' '}
                anggota
            </p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                    disabled={!prevUrl}
                    onClick={() =>
                        prevUrl &&
                        router.get(prevUrl, {}, { preserveState: true })
                    }
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                    disabled={!nextUrl}
                    onClick={() =>
                        nextUrl &&
                        router.get(nextUrl, {}, { preserveState: true })
                    }
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    );
}
