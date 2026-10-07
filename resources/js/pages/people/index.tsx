import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    NotebookPen,
    Pencil,
    Plus,
    Route,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
    creator: string | null;
    editor: string | null;
    edited_at: string | null;
    editable: boolean;
    version_tree_id: number | null;
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

type SortKey =
    | 'name'
    | 'marga'
    | 'parent'
    | 'birth_year'
    | 'creator'
    | 'created_at'
    | 'editor'
    | 'edited_at';

type SortDirection = 'asc' | 'desc';

function SortHeader({
    label,
    column,
    sort,
    direction,
    onSort,
}: {
    label: string;
    column: SortKey;
    sort: SortKey;
    direction: SortDirection;
    onSort: (column: SortKey) => void;
}) {
    const active = sort === column;
    const Icon = !active
        ? ArrowUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <th
            className="px-3 py-3 font-medium"
            aria-sort={
                active
                    ? direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            <button
                type="button"
                onClick={() => onSort(column)}
                className={`inline-flex items-center gap-1 whitespace-nowrap hover:text-tb-on-surface ${active ? 'text-tb-on-surface' : ''}`}
            >
                {label}
                <Icon
                    className={`size-3.5 ${active ? 'text-tb-primary' : 'opacity-40'}`}
                />
            </button>
        </th>
    );
}

type Props = {
    people: Paginated;
    filters: {
        search: string;
        marga_id: string | null;
        sort: SortKey;
        direction: SortDirection;
    };
    margas: MargaOption[];
    canManage: boolean;
    hasMarga: boolean;
    isGuest: boolean;
};

export default function PeopleIndex({
    people: page,
    filters,
    margas,
    canManage,
    hasMarga,
    isGuest,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [margaFilter, setMargaFilter] = useState(filters.marga_id ?? 'all');
    const [toDelete, setToDelete] = useState<PersonItem | null>(null);
    const deleteForm = useForm<{ person?: string }>({});
    const paginatedPeople = page.data;

    useEffect(() => {
        if (
            search === (filters.search ?? '') &&
            margaFilter === (filters.marga_id ?? 'all')
        ) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                people.index.url(),
                {
                    search,
                    ...(margaFilter !== 'all' ? { marga_id: margaFilter } : {}),
                    sort: filters.sort,
                    direction: filters.direction,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => window.clearTimeout(timer);
    }, [
        search,
        margaFilter,
        filters.search,
        filters.marga_id,
        filters.sort,
        filters.direction,
    ]);

    const sortBy = (column: SortKey) => {
        router.get(
            people.index.url(),
            {
                ...(filters.search ? { search: filters.search } : {}),
                ...(filters.marga_id ? { marga_id: filters.marga_id } : {}),
                sort: column,
                direction:
                    filters.sort === column && filters.direction === 'asc'
                        ? 'desc'
                        : 'asc',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sortProps = {
        sort: filters.sort,
        direction: filters.direction,
        onSort: sortBy,
    };

    const showActions =
        !isGuest && (canManage || page.data.some((person) => person.editable));

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
                            {isGuest || canManage
                                ? 'Data Anggota'
                                : 'Silsilah Keluarga Saya'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            {isGuest
                                ? 'Daftar anggota silsilah yang tersedia untuk publik.'
                                : canManage
                                  ? 'Kelola anggota silsilah keluarga dalam tarombo.'
                                  : 'Anggota silsilah dari marga keluarga Anda.'}
                        </p>
                    </div>
                    {!isGuest && canManage && (
                        <Button
                            asChild
                            className="rounded-full bg-tb-primary hover:bg-tb-primary-light"
                        >
                            <Link href={people.create()}>
                                <Plus className="size-4" /> Tambah Anggota
                            </Link>
                        </Button>
                    )}
                    {!isGuest && !canManage && hasMarga && (
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

                {!isGuest && (
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="flex flex-col gap-3 py-4 md:flex-row md:items-center">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-tb-outline" />
                                <Input
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                    }}
                                    placeholder="Cari nama, alias, atau marga..."
                                    className="border-tb-outline-variant bg-tb-surface-bright pl-10 focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                            </div>
                            {canManage && margas.length > 1 && (
                                <Select
                                    value={margaFilter}
                                    onValueChange={(value) => {
                                        setMargaFilter(value);
                                    }}
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
                )}

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="overflow-x-auto py-0">
                        <table className="w-full min-w-[640px] text-sm lg:min-w-[1100px]">
                            <thead>
                                <tr className="border-b border-tb-outline-variant text-left text-xs text-tb-on-surface-variant">
                                    <SortHeader
                                        label="Anggota"
                                        column="name"
                                        {...sortProps}
                                    />
                                    <SortHeader
                                        label="Marga"
                                        column="marga"
                                        {...sortProps}
                                    />
                                    <SortHeader
                                        label="Orang Tua"
                                        column="parent"
                                        {...sortProps}
                                    />
                                    <SortHeader
                                        label="Lahir"
                                        column="birth_year"
                                        {...sortProps}
                                    />
                                    {!isGuest && (
                                        <>
                                            <SortHeader
                                                label="Kontributor"
                                                column="creator"
                                                {...sortProps}
                                            />
                                            <SortHeader
                                                label="Created Date"
                                                column="created_at"
                                                {...sortProps}
                                            />
                                            <SortHeader
                                                label="Editor"
                                                column="editor"
                                                {...sortProps}
                                            />
                                            <SortHeader
                                                label="Edited Date"
                                                column="edited_at"
                                                {...sortProps}
                                            />
                                        </>
                                    )}
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
                                                        color={
                                                            person.marga_color
                                                        }
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
                                                                No.{' '}
                                                                {person.chain}
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
                                            {!isGuest && (
                                                <>
                                                    <td className="px-3 py-3 text-tb-on-surface-variant">
                                                        {person.creator ?? '-'}
                                                    </td>
                                                    <td className="px-3 py-3 whitespace-nowrap text-tb-on-surface-variant">
                                                        {person.created_at ??
                                                            '-'}
                                                    </td>
                                                    <td className="px-3 py-3 text-tb-on-surface-variant">
                                                        {person.editor ?? '-'}
                                                    </td>
                                                    <td className="px-3 py-3 whitespace-nowrap text-tb-on-surface-variant">
                                                        {person.edited_at ??
                                                            '-'}
                                                    </td>
                                                </>
                                            )}
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
                                                                            person.version_tree_id
                                                                                ? {
                                                                                      query: {
                                                                                          version_tree:
                                                                                              person.version_tree_id,
                                                                                      },
                                                                                  }
                                                                                : undefined,
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
                                                                            person.version_tree_id
                                                                                ? {
                                                                                      query: {
                                                                                          version_tree:
                                                                                              person.version_tree_id,
                                                                                      },
                                                                                  }
                                                                                : undefined,
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
                                {page.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={
                                                (showActions ? 5 : 4) +
                                                (isGuest ? 0 : 4)
                                            }
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

                {!isGuest && (
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
                                    <strong>{toDelete?.name}</strong> dari
                                    database?
                                    {toDelete && toDelete.children_count > 0 ? (
                                        <span className="mt-2 block font-medium text-red-700 dark:text-red-300">
                                            Anggota ini masih memiliki{' '}
                                            {toDelete.children_count} keturunan.
                                            Hapus keturunan paling bawah
                                            terlebih dahulu.
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
                )}
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
