import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, MapPin, Pencil, Plus, Search, Trash } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import events from '@/routes/events';

type EventItem = {
    id: number;
    title: string;
    description: string;
    location: string | null;
    date: string;
    published: boolean;
    created_at: string | null;
};

type Paginated = {
    data: EventItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Props = {
    events: Paginated;
    filters: { search: string };
};

export default function EventsIndex({ events: page, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [toDelete, setToDelete] = useState<EventItem | null>(null);
    const deleteForm = useForm({});

    const applyFilter = (value: string) => {
        router.get(
            events.index().url,
            { search: value },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!toDelete) {
            return;
        }

        deleteForm.delete(events.destroy(toDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setToDelete(null),
        });
    };

    return (
        <>
            <Head title="Event & Kegiatan" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Event & Kegiatan
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Kelola event komunitas yang tampil di halaman utama.
                        </p>
                    </div>
                    <Button asChild className="rounded-full bg-tb-primary hover:bg-tb-primary-light">
                        <Link href={events.create()}>
                            <Plus className="size-4" /> Tambah Event
                        </Link>
                    </Button>
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="py-4">
                        <div className="relative">
                            <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-tb-outline" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyFilter(search);
                                    }
                                }}
                                placeholder="Cari judul event..."
                                className="border-tb-outline-variant bg-tb-surface-bright pl-10 focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="overflow-x-auto py-0">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr className="border-b border-tb-outline-variant text-left text-xs text-tb-on-surface-variant">
                                    <th className="px-3 py-3 font-medium">Event</th>
                                    <th className="px-3 py-3 font-medium">Tanggal</th>
                                    <th className="px-3 py-3 font-medium">Lokasi</th>
                                    <th className="px-3 py-3 font-medium">Status</th>
                                    <th className="px-3 py-3 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-tb-outline-variant">
                                {page.data.map((event) => (
                                    <tr key={event.id} className="hover:bg-tb-surface-container/40">
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-tb-surface-container text-tb-primary">
                                                    <CalendarDays className="size-5" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="font-medium text-tb-on-surface">{event.title}</p>
                                                    <p className="line-clamp-1 text-xs text-tb-on-surface-variant">
                                                        {event.description}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">{event.date}</td>
                                        <td className="px-3 py-3 text-tb-on-surface-variant">
                                            {event.location ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <MapPin className="size-3.5" /> {event.location}
                                                </span>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="px-3 py-3">
                                            {event.published ? (
                                                <Badge className="bg-[#3e6b48] text-white">Tampil</Badge>
                                            ) : (
                                                <Badge variant="outline" className="border-tb-outline-variant text-tb-on-surface-variant">
                                                    Disembunyikan
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-tb-primary hover:bg-tb-surface-container"
                                                >
                                                    <Link href={events.edit(event.id)}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                                    onClick={() => setToDelete(event)}
                                                >
                                                    <Trash className="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {page.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-3 py-10 text-center text-tb-on-surface-variant">
                                            Belum ada event.
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
                            <DialogTitle className="text-tb-on-surface">Hapus Event</DialogTitle>
                            <DialogDescription>
                                Yakin ingin menghapus event <strong>{toDelete?.title}</strong>?
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setToDelete(null)}>
                                Batal
                            </Button>
                            <Button variant="destructive" onClick={confirmDelete} disabled={deleteForm.processing}>
                                {deleteForm.processing ? 'Menghapus...' : 'Ya, Hapus'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Event & Kegiatan', href: events.index() },
    ],
};

function Pagination({ page }: { page: Paginated }) {
    const prevUrl = page.prev_page_url;
    const nextUrl = page.next_page_url;

    return (
        <div className="flex flex-col items-center justify-between gap-3 text-sm text-tb-on-surface-variant sm:flex-row">
            <p>
                Menampilkan {page.from ?? 0}–{page.to ?? 0} dari {page.total} event
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
