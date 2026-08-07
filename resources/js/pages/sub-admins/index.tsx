import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash } from 'lucide-react';
import { useState } from 'react';
import { AppAvatar } from '@/components/app-avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import subAdmins from '@/routes/sub-admins';

type SubAdminItem = {
    id: number;
    name: string;
    email: string;
    marga: string | null;
    marga_id: number | null;
    created_at: string | null;
};

type Paginated = {
    data: SubAdminItem[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Props = {
    subAdmins: Paginated;
};

export default function SubAdminsIndex({ subAdmins: page }: Props) {
    const [toDelete, setToDelete] = useState<SubAdminItem | null>(null);
    const deleteForm = useForm({});

    const confirmDelete = () => {
        if (!toDelete) {
            return;
        }

        deleteForm.delete(subAdmins.destroy(toDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setToDelete(null),
        });
    };

    return (
        <>
            <Head title="Sub Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Sub Admin
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Kelola akun sub admin yang membantu mengelola data silsilah.
                        </p>
                    </div>
                    <Button asChild className="rounded-full bg-tb-primary hover:bg-tb-primary-light">
                        <Link href={subAdmins.create()}>
                            <Plus className="size-4" /> Tambah Sub Admin
                        </Link>
                    </Button>
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="overflow-x-auto py-0">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr className="border-b border-tb-outline-variant text-left text-xs text-tb-on-surface-variant">
                                    <th className="px-3 py-3 font-medium">Nama</th>
                                    <th className="px-3 py-3 font-medium">Email</th>
                                    <th className="px-3 py-3 font-medium">Marga</th>
                                    <th className="px-3 py-3 font-medium">Dibuat</th>
                                    <th className="px-3 py-3 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-tb-outline-variant">
                                {page.data.map((subAdmin) => (
                                    <tr key={subAdmin.id} className="hover:bg-tb-surface-container/40">
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-3">
                                                <AppAvatar name={subAdmin.name} />
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-tb-on-surface">{subAdmin.name}</p>
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-tb-surface-container px-2 py-0.5 text-[11px] font-medium text-tb-on-surface-variant">
                                                        <ShieldCheck className="h-3 w-3" /> Sub Admin
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">{subAdmin.email}</td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {subAdmin.marga ?? '-'}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {subAdmin.created_at ?? '-'}
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    title="Edit"
                                                    asChild
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-tb-primary hover:bg-tb-surface-container"
                                                >
                                                    <Link href={subAdmins.edit(subAdmin.id)}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    title="Hapus"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                                    onClick={() => setToDelete(subAdmin)}
                                                >
                                                    <Trash className="size-4" />
                                                </Button>
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
                                            Belum ada sub admin.
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
                            <DialogTitle className="text-tb-on-surface">Hapus Sub Admin</DialogTitle>
                            <DialogDescription>
                                Yakin ingin menghapus <strong>{toDelete?.name}</strong>? Akun tersebut tidak
                                akan bisa login lagi.
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

SubAdminsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sub Admin', href: subAdmins.index() },
    ],
};

function Pagination({ page }: { page: Paginated }) {
    const prevUrl = page.prev_page_url;
    const nextUrl = page.next_page_url;

    return (
        <div className="flex flex-col items-center justify-between gap-3 text-sm text-tb-on-surface-variant sm:flex-row">
            <p>
                Menampilkan {page.from ?? 0}–{page.to ?? 0} dari {page.total} sub admin
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
