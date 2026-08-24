import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookOpen, Pencil, Plus, Search, Trash } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { dashboard } from '@/routes';
import stories from '@/routes/stories';

type StoryItem = {
    id: number;
    title: string;
    description: string;
    image: string | null;
    published: boolean;
    classification: 'umum' | 'marga';
    marga: string | null;
    creator: string | null;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    can_edit: boolean;
    can_delete: boolean;
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
    canCreate: boolean;
};

export default function StoriesIndex({
    stories: page,
    filters,
    canCreate,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [toDelete, setToDelete] = useState<StoryItem | null>(null);
    const deleteForm = useForm({});

    const applyFilter = (value: string) => {
        router.get(
            stories.index().url,
            { search: value },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!toDelete) {
            return;
        }

        deleteForm.delete(stories.destroy(toDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setToDelete(null),
        });
    };

    return (
        <>
            <Head title="Cerita Leluhur & Budaya" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Cerita Leluhur & Budaya
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Kelola cerita yang tampil di halaman utama.
                        </p>
                    </div>
                    {canCreate && (
                        <Button
                            asChild
                            className="rounded-full bg-tb-primary hover:bg-tb-primary-light"
                        >
                            <Link href={stories.create()}>
                                <Plus className="size-4" /> Tambah Cerita
                            </Link>
                        </Button>
                    )}
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="py-4">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-tb-outline" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyFilter(search);
                                    }
                                }}
                                placeholder="Cari judul cerita..."
                                className="border-tb-outline-variant bg-tb-surface-bright pl-10 focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="overflow-x-auto py-0">
                        <table className="w-full min-w-[820px] text-sm">
                            <thead>
                                <tr className="border-b border-tb-outline-variant text-left text-xs text-tb-on-surface-variant">
                                    <th className="px-3 py-3 font-medium">
                                        Cerita
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Klasifikasi
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Pembuat
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-tb-outline-variant">
                                {page.data.map((story) => (
                                    <tr
                                        key={story.id}
                                        className="hover:bg-tb-surface-container/40"
                                    >
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-tb-outline-variant bg-tb-surface-container">
                                                    {story.image ? (
                                                        <img
                                                            src={story.image}
                                                            alt={story.title}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <BookOpen className="size-5 text-tb-outline" />
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="font-medium text-tb-on-surface">
                                                        {story.title}
                                                    </p>
                                                    <p className="line-clamp-1 text-xs text-tb-on-surface-variant">
                                                        {story.description}
                                                    </p>
                                                    {story.rejection_reason && (
                                                        <p className="mt-1 text-xs text-red-600 dark:text-red-300">
                                                            Alasan:{' '}
                                                            {
                                                                story.rejection_reason
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3">
                                            <Badge variant="outline">
                                                {story.classification === 'umum'
                                                    ? 'Umum'
                                                    : (story.marga ?? 'Marga')}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-3">
                                            {story.status === 'approved' ? (
                                                <Badge className="bg-[#3e6b48] text-white">
                                                    Disetujui
                                                </Badge>
                                            ) : story.status === 'pending' ? (
                                                <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200">
                                                    Menunggu
                                                </Badge>
                                            ) : (
                                                <Badge className="bg-red-100 text-red-800 hover:bg-red-100 dark:bg-red-950 dark:text-red-200">
                                                    Ditolak
                                                </Badge>
                                            )}
                                            {story.status === 'approved' && (
                                                <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                    {story.published
                                                        ? 'Tampil di publik'
                                                        : 'Disembunyikan'}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            <p>
                                                {story.creator ?? 'Data lama'}
                                            </p>
                                            <p className="text-xs">
                                                {story.created_at ?? '-'}
                                            </p>
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="flex justify-end gap-1">
                                                {story.can_edit && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-tb-primary hover:bg-tb-surface-container"
                                                    >
                                                        <Link
                                                            href={stories.edit(
                                                                story.id,
                                                            )}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {story.can_delete && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                                        onClick={() =>
                                                            setToDelete(story)
                                                        }
                                                    >
                                                        <Trash className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {page.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-3 py-10 text-center text-tb-on-surface-variant"
                                        >
                                            Belum ada cerita.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Pagination page={page} />

                <Dialog
                    open={toDelete !== null}
                    onOpenChange={(open) => !open && setToDelete(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="text-tb-on-surface">
                                Hapus Cerita
                            </DialogTitle>
                            <DialogDescription>
                                Yakin ingin menghapus cerita{' '}
                                <strong>{toDelete?.title}</strong>? Cerita tidak
                                akan lagi tampil di halaman utama.
                            </DialogDescription>
                        </DialogHeader>
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

StoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cerita Leluhur & Budaya', href: stories.index() },
    ],
};

function Pagination({ page }: { page: Paginated }) {
    const prevUrl = page.prev_page_url;
    const nextUrl = page.next_page_url;

    return (
        <div className="flex flex-col items-center justify-between gap-3 text-sm text-tb-on-surface-variant sm:flex-row">
            <p>
                Menampilkan {page.from ?? 0}–{page.to ?? 0} dari {page.total}{' '}
                cerita
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
