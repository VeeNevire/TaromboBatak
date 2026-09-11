import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Images,
    PanelsTopLeft,
    ShieldCheck,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';

type Snapshot = {
    id: number;
    view: 'diagram' | 'tree';
    center_person_name: string | null;
    image_url: string;
    created_at: string | null;
};

type SnapshotPage = {
    data: Snapshot[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Frame = {
    id: number;
    name: string;
    image_url: string;
};

export default function TaromboSnapshots({
    snapshots,
    snapshotOptions,
    activeFrames,
    accountName,
}: {
    snapshots: SnapshotPage;
    snapshotOptions: Snapshot[];
    activeFrames: Frame[];
    accountName: string;
}) {
    const [selectedSnapshot, setSelectedSnapshot] = useState<Snapshot | null>(
        null,
    );
    const [sourceSnapshot, setSourceSnapshot] = useState<Snapshot | null>(null);
    const [selectedFrame, setSelectedFrame] = useState<Frame | null>(null);
    const [sourcePickerOpen, setSourcePickerOpen] = useState(false);
    const [framePickerOpen, setFramePickerOpen] = useState(false);
    const [generating, setGenerating] = useState(false);
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeStyle: 'short',
    });

    const removeSnapshot = (snapshot: Snapshot) => {
        if (!window.confirm('Hapus gambar Tarombo tersimpan ini?')) {
            return;
        }

        router.delete(tarombo.snapshots.destroy(snapshot.id).url, {
            preserveScroll: true,
        });
    };

    const generateFrame = () => {
        if (!sourceSnapshot || !selectedFrame || generating) {
            return;
        }

        setGenerating(true);
        router.post(
            tarombo.snapshots.generate().url,
            {
                snapshot_id: sourceSnapshot.id,
                frame_id: selectedFrame.id,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSourceSnapshot(null);
                    setSelectedFrame(null);
                },
                onError: (errors) => {
                    window.alert(
                        errors.frame_id ??
                            'Generator AI gagal membuat gambar. Silakan coba lagi.',
                    );
                },
                onFinish: () => setGenerating(false),
            },
        );
    };

    return (
        <>
            <Head title="Tarombo Tersimpan" />

            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                onContextMenu={(event) => event.preventDefault()}
            >
                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Tarombo Tersimpan
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Galeri privat gambar pohon yang tersimpan pada akun
                            Anda.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setSourcePickerOpen(true)}
                            className="max-w-52 justify-start"
                        >
                            <Images className="size-4 shrink-0" />
                            <span className="truncate">
                                {sourceSnapshot
                                    ? (sourceSnapshot.center_person_name ??
                                      'Pohon Tarombo')
                                    : 'Pilih Gambar'}
                            </span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setFramePickerOpen(true)}
                            className="max-w-52 justify-start"
                        >
                            <PanelsTopLeft className="size-4 shrink-0" />
                            <span className="truncate">
                                {selectedFrame?.name ?? 'Pilih Frame'}
                            </span>
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                !sourceSnapshot || !selectedFrame || generating
                            }
                            onClick={generateFrame}
                            className="bg-tb-primary hover:bg-tb-primary-light"
                        >
                            <Sparkles className="size-4" />
                            {generating ? 'Membuat...' : 'Gen AI'}
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={tarombo.index()}>
                                <ArrowLeft className="size-4" /> Pohon Tarombo
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex items-start gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4 text-sm text-tb-on-surface-variant">
                    <ShieldCheck className="mt-0.5 size-5 shrink-0 text-tb-primary" />
                    <p>
                        Gambar dilayani melalui akses privat, tanpa tombol
                        download, serta tidak dapat diklik kanan atau ditarik
                        dari galeri. Saat Gen AI dipilih, gambar Tarombo dan
                        frame dikirim sebagai dua referensi ke AI untuk
                        dianalisis dan disatukan secara proporsional.
                    </p>
                </div>

                {snapshots.data.length === 0 ? (
                    <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="flex flex-col items-center gap-3 py-14 text-center">
                            <Images className="size-10 text-tb-outline" />
                            <div>
                                <p className="font-semibold text-tb-on-surface">
                                    Belum ada Tarombo tersimpan
                                </p>
                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                    Buka Pohon Tarombo fullscreen lalu tekan
                                    tombol Simpan.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {snapshots.data.map((snapshot) => (
                            <Card
                                key={snapshot.id}
                                className="overflow-hidden border-tb-outline-variant bg-tb-surface-bright"
                            >
                                <button
                                    type="button"
                                    className="group relative block aspect-video w-full cursor-zoom-in overflow-hidden bg-tb-surface-container text-left select-none focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:outline-none"
                                    onClick={() =>
                                        setSelectedSnapshot(snapshot)
                                    }
                                    onDragStart={(event) =>
                                        event.preventDefault()
                                    }
                                    aria-label={`Perbesar Tarombo ${snapshot.center_person_name ?? 'tersimpan'}`}
                                >
                                    <img
                                        src={snapshot.image_url}
                                        alt={`Tarombo ${snapshot.center_person_name ?? 'tersimpan'}`}
                                        draggable={false}
                                        className="pointer-events-none size-full object-contain transition-transform duration-200 select-none group-hover:scale-[1.02]"
                                    />
                                    <div className="pointer-events-none absolute right-2 bottom-2">
                                        <span className="rounded bg-black/45 px-2 py-1 text-[9px] font-medium text-white/80 shadow-sm">
                                            Tarombo Batak · {accountName}
                                        </span>
                                    </div>
                                </button>
                                <CardContent className="flex items-start justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="truncate text-sm font-semibold text-tb-on-surface">
                                                {snapshot.center_person_name ??
                                                    'Pohon Tarombo'}
                                            </p>
                                            <Badge variant="outline">
                                                {snapshot.view === 'tree'
                                                    ? 'Vertikal'
                                                    : 'Radial'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                            {snapshot.created_at
                                                ? dateFormatter.format(
                                                      new Date(
                                                          snapshot.created_at,
                                                      ),
                                                  )
                                                : 'Waktu tidak tersedia'}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => removeSnapshot(snapshot)}
                                        aria-label="Hapus gambar Tarombo"
                                        className="shrink-0 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {snapshots.last_page > 1 && (
                    <div className="flex items-center justify-between gap-3 border-t border-tb-outline-variant pt-4">
                        {snapshots.prev_page_url ? (
                            <Button asChild variant="outline">
                                <Link href={snapshots.prev_page_url}>
                                    Sebelumnya
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" disabled>
                                Sebelumnya
                            </Button>
                        )}
                        <span className="text-sm text-tb-on-surface-variant">
                            Halaman {snapshots.current_page} dari{' '}
                            {snapshots.last_page}
                        </span>
                        {snapshots.next_page_url ? (
                            <Button asChild variant="outline">
                                <Link href={snapshots.next_page_url}>
                                    Berikutnya
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" disabled>
                                Berikutnya
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <Dialog
                open={selectedSnapshot !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedSnapshot(null);
                    }
                }}
            >
                <DialogContent
                    className="max-h-[95dvh] overflow-hidden border-tb-outline-variant bg-tb-surface-bright p-4 sm:max-w-[95vw] md:p-5"
                    onContextMenu={(event) => event.preventDefault()}
                >
                    <DialogHeader className="pr-8">
                        <DialogTitle className="font-display text-tb-on-surface">
                            {selectedSnapshot?.center_person_name ??
                                'Pohon Tarombo'}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedSnapshot?.view === 'tree'
                                ? 'Tampilan silsilah vertikal'
                                : 'Tampilan diagram radial'}
                        </DialogDescription>
                    </DialogHeader>
                    {selectedSnapshot && (
                        <div
                            className="relative flex max-h-[78dvh] min-h-0 items-center justify-center overflow-auto rounded-xl bg-tb-surface-container select-none"
                            onDragStart={(event) => event.preventDefault()}
                        >
                            <img
                                src={selectedSnapshot.image_url}
                                alt={`Tarombo ${selectedSnapshot.center_person_name ?? 'tersimpan'}`}
                                draggable={false}
                                className="pointer-events-none max-h-[78dvh] max-w-full object-contain select-none"
                            />
                            <span className="pointer-events-none absolute right-3 bottom-3 rounded bg-black/45 px-2 py-1 text-[10px] font-medium text-white/80 shadow-sm">
                                Tarombo Batak · {accountName}
                            </span>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={sourcePickerOpen} onOpenChange={setSourcePickerOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Pilih Gambar Tarombo</DialogTitle>
                        <DialogDescription>
                            Hanya gambar Tarombo milik akun Anda yang dapat
                            digunakan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        {snapshotOptions.map((snapshot) => (
                            <button
                                key={snapshot.id}
                                type="button"
                                onClick={() => {
                                    setSourceSnapshot(snapshot);
                                    setSourcePickerOpen(false);
                                }}
                                className="overflow-hidden rounded-xl border border-tb-outline-variant text-left transition-colors hover:border-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:outline-none"
                            >
                                <img
                                    src={snapshot.image_url}
                                    alt={
                                        snapshot.center_person_name ??
                                        'Pohon Tarombo'
                                    }
                                    className="aspect-video w-full bg-tb-surface-container object-contain"
                                />
                                <p className="truncate px-3 py-2 text-sm font-medium text-tb-on-surface">
                                    {snapshot.center_person_name ??
                                        'Pohon Tarombo'}
                                </p>
                            </button>
                        ))}
                        {snapshotOptions.length === 0 && (
                            <p className="col-span-full py-6 text-center text-sm text-tb-on-surface-variant">
                                Belum ada gambar Tarombo yang dapat dipilih.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={framePickerOpen} onOpenChange={setFramePickerOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Pilih Template Frame</DialogTitle>
                        <DialogDescription>
                            AI akan menganalisis area konten dari template ini,
                            lalu menempatkan gambar Tarombo tanpa menutupi
                            ornamen frame.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        {activeFrames.map((frame) => (
                            <button
                                key={frame.id}
                                type="button"
                                onClick={() => {
                                    setSelectedFrame(frame);
                                    setFramePickerOpen(false);
                                }}
                                className="overflow-hidden rounded-xl border border-tb-outline-variant text-left transition-colors hover:border-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:outline-none"
                            >
                                <img
                                    src={frame.image_url}
                                    alt={frame.name}
                                    className="aspect-video w-full bg-tb-surface-container object-contain"
                                />
                                <p className="truncate px-3 py-2 text-sm font-medium text-tb-on-surface">
                                    {frame.name}
                                </p>
                            </button>
                        ))}
                        {activeFrames.length === 0 && (
                            <p className="col-span-full py-6 text-center text-sm text-tb-on-surface-variant">
                                Belum ada frame aktif. Hubungi admin untuk
                                menambah template.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

TaromboSnapshots.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pohon Tarombo', href: tarombo.index() },
        { title: 'Tarombo Tersimpan', href: tarombo.snapshots.index() },
    ],
};
