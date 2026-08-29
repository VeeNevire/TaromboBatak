import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash, UserRound } from 'lucide-react';
import { useState } from 'react';
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
import { dashboard } from '@/routes';
import accounts from '@/routes/accounts';

type Account = {
    id: number;
    name: string;
    email: string;
    role: string;
    current_person: string | null;
    marga: string | null;
    created_at: string | null;
};

type Page = {
    data: Account[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    subadmin: 'Sub Admin',
    contributor_main: 'Kontributor Utama',
    contributor_member: 'Anggota Kontributor',
    user: 'Pengguna',
};

export default function AccountsIndex({
    accounts: page,
    filters,
}: {
    accounts: Page;
    filters: { search: string; role: string };
}) {
    const [search, setSearch] = useState(filters.search);
    const [toDelete, setToDelete] = useState<Account | null>(null);
    const deleteForm = useForm({});

    const applyFilters = (nextSearch = search, nextRole = filters.role) => {
        router.get(
            accounts.index(),
            { search: nextSearch || undefined, role: nextRole || undefined },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!toDelete) {
return;
}

        deleteForm.delete(accounts.destroy(toDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setToDelete(null),
        });
    };

    return (
        <>
            <Head title="Data Akun" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Data Akun
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Kelola seluruh akun pengguna dalam aplikasi.
                        </p>
                    </div>
                    <Button
                        asChild
                        className="rounded-full bg-tb-primary hover:bg-tb-primary-light"
                    >
                        <Link href={accounts.create()}>
                            <Plus className="size-4" /> Tambah Akun
                        </Link>
                    </Button>
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col gap-3 p-4 sm:flex-row">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) =>
                                event.key === 'Enter' && applyFilters()
                            }
                            placeholder="Cari nama atau email..."
                            className="sm:max-w-sm"
                        />
                        <select
                            value={filters.role}
                            onChange={(event) =>
                                applyFilters(search, event.target.value)
                            }
                            className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                        >
                            <option value="">Semua peran</option>
                            {Object.entries(roleLabels).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                        <Button
                            variant="outline"
                            onClick={() => applyFilters()}
                        >
                            Cari
                        </Button>
                    </CardContent>
                </Card>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="overflow-x-auto py-0">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead>
                                <tr className="border-b border-tb-outline-variant text-left text-xs text-tb-on-surface-variant">
                                    <th className="px-3 py-3 font-medium">
                                        Nama
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Peran
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Saya adalah
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Marga
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Dibuat
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-tb-outline-variant">
                                {page.data.map((account) => (
                                    <tr
                                        key={account.id}
                                        className="hover:bg-tb-surface-container/40"
                                    >
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-3">
                                                <AppAvatar
                                                    name={account.name}
                                                />
                                                <span className="font-medium text-tb-on-surface">
                                                    {account.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {account.email}
                                        </td>
                                        <td className="px-3 py-3">
                                            <span className="inline-flex items-center gap-1 rounded-full bg-tb-surface-container px-2 py-1 text-xs font-medium text-tb-on-surface-variant">
                                                {account.role === 'admin' ||
                                                account.role === 'subadmin' ? (
                                                    <ShieldCheck className="size-3" />
                                                ) : (
                                                    <UserRound className="size-3" />
                                                )}
                                                {roleLabels[account.role] ??
                                                    account.role}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {account.current_person ?? '-'}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {account.marga ?? '-'}
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {account.created_at ?? '-'}
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Edit"
                                                    className="size-8 text-tb-primary hover:bg-tb-surface-container"
                                                >
                                                    <Link
                                                        href={accounts.edit(
                                                            account.id,
                                                        )}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Hapus"
                                                    className="size-8 text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                                    onClick={() =>
                                                        setToDelete(account)
                                                    }
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
                                            colSpan={7}
                                            className="px-3 py-10 text-center text-tb-on-surface-variant"
                                        >
                                            Belum ada akun yang sesuai.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <div className="flex flex-col items-center justify-between gap-3 text-sm text-tb-on-surface-variant sm:flex-row">
                    <p>
                        Menampilkan {page.from ?? 0}–{page.to ?? 0} dari{' '}
                        {page.total} akun
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!page.prev_page_url}
                            onClick={() =>
                                page.prev_page_url &&
                                router.get(page.prev_page_url)
                            }
                        >
                            Sebelumnya
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!page.next_page_url}
                            onClick={() =>
                                page.next_page_url &&
                                router.get(page.next_page_url)
                            }
                        >
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={toDelete !== null}
                onOpenChange={(open) => !open && setToDelete(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus Akun</DialogTitle>
                        <DialogDescription>
                            Yakin ingin menghapus{' '}
                            <strong>{toDelete?.name}</strong>? Akun tersebut
                            tidak akan bisa login lagi.
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
        </>
    );
}

AccountsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Akun', href: accounts.index() },
    ],
};
