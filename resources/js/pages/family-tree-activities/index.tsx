import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Filter,
    History,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import familyTreeActivities from '@/routes/family-tree-activities';

type Activity = {
    id: string;
    tree_name: string | null;
    father_name: string | null;
    member_name: string | null;
    action: string;
    description: string;
    actor: string;
    created_at: string | null;
};

export default function FamilyTreeActivitiesIndex({
    activities,
    accounts,
    filters,
    pagination,
}: {
    activities: Activity[];
    accounts: { id: number; name: string }[];
    filters: {
        search: string;
        account_id: number | null;
        date: string;
        order: string;
    };
    pagination: { current_page: number; last_page: number; total: number };
}) {
    const form = useForm({
        search: filters.search,
        account_id: filters.account_id ? String(filters.account_id) : '',
        date: filters.date,
        order: filters.order,
    });
    const { search, account_id, date, order } = form.data;
    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                familyTreeActivities.index().url,
                { search, account_id, date, order },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timer);
    }, [search, account_id, date, order, filters.search]);
    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        form.get(familyTreeActivities.index().url, { preserveScroll: true });
    };
    const actionLabel = (action: string) =>
        ({
            account_created: 'Pembuatan akun',
            added: 'Tambah',
            created: 'Tambah',
            updated: 'Edit',
            deleted: 'Hapus',
            downloaded: 'Unduh',
        })[action] ?? action;

    return (
        <>
            <Head title="Log Aktivitas Silsilah" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 font-display text-xl text-tb-on-surface">
                            <History className="size-5 text-tb-primary" /> Log
                            Aktivitas Silsilah
                        </CardTitle>
                        <CardDescription>
                            Riwayat sejak akun dibuat dan seluruh aktivitas
                            silsilah yang tercatat.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <form
                            onSubmit={applyFilters}
                            className="grid gap-4 rounded-xl border border-tb-outline-variant bg-tb-surface-container/40 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)]"
                        >
                            <div className="space-y-2 sm:col-span-2 lg:col-span-3">
                                <Label htmlFor="activity-search">
                                    Cari aktivitas
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-tb-on-surface-variant" />
                                    <Input
                                        id="activity-search"
                                        type="search"
                                        value={form.data.search}
                                        onChange={(event) =>
                                            form.setData(
                                                'search',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Cari nama anggota, keluarga, pelaku, atau isi aktivitas…"
                                        maxLength={255}
                                        className="pl-9"
                                    />
                                </div>
                                {form.errors.search && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.search}
                                    </p>
                                )}
                            </div>
                            <div className="min-w-0 space-y-2 sm:col-span-2 lg:col-span-1">
                                <Label htmlFor="activity-account">
                                    Pilih akun pelaku
                                </Label>
                                <select
                                    id="activity-account"
                                    value={form.data.account_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'account_id',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">Semua akun</option>
                                    {accounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.account_id && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.account_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="activity-date">
                                    Pilih tanggal (WIB)
                                </Label>
                                <Input
                                    id="activity-date"
                                    type="date"
                                    value={form.data.date}
                                    onChange={(event) =>
                                        form.setData('date', event.target.value)
                                    }
                                />
                                {form.errors.date && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.date}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="activity-order">Urutan</Label>
                                <select
                                    id="activity-order"
                                    value={form.data.order}
                                    onChange={(event) =>
                                        form.setData(
                                            'order',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="newest">
                                        Terbaru dahulu
                                    </option>
                                    <option value="oldest">
                                        Dari awal akun
                                    </option>
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-2 border-t border-tb-outline-variant pt-3 sm:col-span-2 sm:flex sm:justify-end lg:col-span-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-9 gap-2 sm:min-w-28"
                                    disabled={form.processing}
                                    onClick={() =>
                                        router.get(
                                            familyTreeActivities.index().url,
                                        )
                                    }
                                >
                                    <RotateCcw className="size-4" /> Reset
                                </Button>
                                <Button
                                    type="submit"
                                    className="h-9 gap-2 sm:min-w-36"
                                    disabled={form.processing}
                                >
                                    <Filter className="size-4" /> Terapkan
                                    filter
                                </Button>
                            </div>
                        </form>
                        <p className="text-xs text-tb-on-surface-variant">
                            {pagination.total} aktivitas tercatat
                        </p>
                        {activities.length === 0 ? (
                            <p className="py-10 text-center text-sm text-tb-on-surface-variant">
                                {filters.search
                                    ? 'Tidak ada aktivitas yang cocok dengan pencarian.'
                                    : 'Belum ada aktivitas silsilah tercatat.'}
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-tb-outline-variant">
                                <div className="divide-y divide-tb-outline-variant">
                                    {activities.map((activity) => (
                                        <div
                                            key={activity.id}
                                            className="grid gap-1 p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                                        >
                                            <div>
                                                <p className="text-sm font-semibold text-tb-on-surface">
                                                    {activity.description}
                                                </p>
                                                <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                    {activity.tree_name && (
                                                        <>
                                                            Nama Keluarga:{' '}
                                                            {activity.tree_name}{' '}
                                                            · Nama Anggota:{' '}
                                                            {activity.member_name ??
                                                                '-'}{' '}
                                                            ·{' '}
                                                        </>
                                                    )}
                                                    Aksi:{' '}
                                                    {actionLabel(
                                                        activity.action,
                                                    )}{' '}
                                                    · oleh {activity.actor}
                                                </p>
                                            </div>
                                            <time className="text-xs text-tb-on-surface-variant">
                                                {activity.created_at ?? '-'}
                                            </time>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                        {pagination.last_page > 1 && (
                            <div className="flex flex-col gap-3 border-t border-tb-outline-variant pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-sm text-tb-on-surface-variant">
                                    Halaman {pagination.current_page} dari{' '}
                                    {pagination.last_page}
                                </p>
                                <div className="grid grid-cols-2 gap-2 sm:flex">
                                    {pagination.current_page > 1 ? (
                                        <Button
                                            variant="outline"
                                            className="h-9 gap-1.5 sm:min-w-32"
                                            asChild
                                        >
                                            <Link
                                                href={familyTreeActivities.index(
                                                    {
                                                        query: {
                                                            ...filters,
                                                            page:
                                                                pagination.current_page -
                                                                1,
                                                        },
                                                    },
                                                )}
                                                preserveScroll
                                            >
                                                <ChevronLeft className="size-4" />{' '}
                                                Sebelumnya
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            className="h-9 gap-1.5 sm:min-w-32"
                                            disabled
                                        >
                                            <ChevronLeft className="size-4" />{' '}
                                            Sebelumnya
                                        </Button>
                                    )}
                                    {pagination.current_page <
                                    pagination.last_page ? (
                                        <Button
                                            variant="outline"
                                            className="h-9 gap-1.5 sm:min-w-32"
                                            asChild
                                        >
                                            <Link
                                                href={familyTreeActivities.index(
                                                    {
                                                        query: {
                                                            ...filters,
                                                            page:
                                                                pagination.current_page +
                                                                1,
                                                        },
                                                    },
                                                )}
                                                preserveScroll
                                            >
                                                Berikutnya{' '}
                                                <ChevronRight className="size-4" />
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            className="h-9 gap-1.5 sm:min-w-32"
                                            disabled
                                        >
                                            Berikutnya{' '}
                                            <ChevronRight className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

FamilyTreeActivitiesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Log Aktivitas', href: familyTreeActivities.index() },
    ],
};
