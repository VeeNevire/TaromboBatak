import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Copy,
    Download,
    Images,
    LayoutGrid,
    Pencil,
    ShieldCheck,
    Trash2,
    Wand2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CollageDialog } from '@/components/collage-dialog';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ZoomableImage } from '@/components/zoomable-image';
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';
import compileDrafts from '@/routes/tarombo/compile-drafts';

type Snapshot = {
    id: number;
    view: 'diagram' | 'tree';
    title: string | null;
    center_person_name: string | null;
    owner_name: string | null;
    image_url: string;
    download_url?: string | null;
    can_delete?: boolean;
    size_bytes?: number | null;
    created_at: string | null;
    // This account saved a Compile Gambar arrangement for this image.
    has_compile_draft?: boolean;
    // A Produce result that can be reopened in Compile Gambar and replaced.
    editable_result?: boolean;
    // A card of the "Hasil Simpan" tab: a saved Compile Gambar arrangement.
    saved_compile?: boolean;
    draft_id?: number;
    draft_name?: string | null;
    // False for arrangements saved before the composed preview existed.
    has_preview?: boolean;
};

type SnapshotPage = {
    data: Snapshot[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type SnapshotFilter = 'all' | 'compiled' | 'original' | 'saved';

type FrameOption = {
    id: number;
    name: string;
    image_url: string;
};

export default function TaromboSnapshots({
    snapshots,
    filter,
    snapshotOptions,
    frames,
    accountName,
    canDownload,
}: {
    snapshots: SnapshotPage;
    filter: SnapshotFilter;
    snapshotOptions: Snapshot[];
    frames: FrameOption[];
    accountName: string;
    canDownload: boolean;
}) {
    const [selectedSnapshot, setSelectedSnapshot] = useState<Snapshot | null>(
        null,
    );
    const [sourceSnapshot, setSourceSnapshot] = useState<Snapshot | null>(null);
    const [sourcePickerOpen, setSourcePickerOpen] = useState(false);
    const [collageOpen, setCollageOpen] = useState(false);
    // Name entry for duplicating or renaming a saved compile.
    const [nameDialog, setNameDialog] = useState<{
        mode: 'duplicate' | 'rename';
        snapshot: Snapshot;
    } | null>(null);
    const [nameInput, setNameInput] = useState('');
    const [nameError, setNameError] = useState<string | null>(null);
    const [savingName, setSavingName] = useState(false);
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeStyle: 'short',
    });

    const snapshotLabel = (snapshot: Snapshot) =>
        snapshot.title ?? snapshot.center_person_name ?? 'Pohon Tarombo';

    const formatSize = (bytes?: number | null) =>
        bytes ? `${(bytes / (1024 * 1024)).toFixed(2)} MB` : null;

    const removeSnapshot = (snapshot: Snapshot) => {
        if (!window.confirm('Hapus gambar Tarombo tersimpan ini?')) {
            return;
        }

        router.delete(tarombo.snapshots.destroy(snapshot.id).url, {
            preserveScroll: true,
        });
    };

    const removeSavedCompile = (snapshot: Snapshot) => {
        if (
            !snapshot.draft_id ||
            !window.confirm('Hapus simpanan Compile Gambar ini?')
        ) {
            return;
        }

        router.delete(compileDrafts.destroy.url(snapshot.draft_id), {
            preserveScroll: true,
        });
    };

    const openNameDialog = (
        mode: 'duplicate' | 'rename',
        snapshot: Snapshot,
    ) => {
        setNameDialog({ mode, snapshot });
        setNameInput(
            mode === 'duplicate'
                ? `${snapshotLabel(snapshot)} (Salinan)`.slice(0, 120)
                : snapshotLabel(snapshot),
        );
        setNameError(null);
    };

    const submitName = (event: FormEvent) => {
        event.preventDefault();

        const draftId = nameDialog?.snapshot.draft_id;

        if (!nameDialog || !draftId || savingName) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setSavingName(true),
            onFinish: () => setSavingName(false),
            onSuccess: () => setNameDialog(null),
            onError: (errors: Record<string, string>) =>
                setNameError(errors.name ?? 'Nama gagal disimpan.'),
        };

        if (nameDialog.mode === 'duplicate') {
            router.post(
                compileDrafts.duplicate.url(draftId),
                { name: nameInput },
                options,
            );
        } else {
            router.patch(
                compileDrafts.rename.url(draftId),
                { name: nameInput },
                options,
            );
        }
    };

    const applyFilter = (value: string) => {
        router.get(
            tarombo.snapshots.index.url(
                value === 'all' ? undefined : { query: { filter: value } },
            ),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
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
                                    ? snapshotLabel(sourceSnapshot)
                                    : 'Pilih Gambar'}
                            </span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setCollageOpen(true)}
                        >
                            <LayoutGrid className="size-4" />
                            Pilih Format Frame
                        </Button>
                        {sourceSnapshot ? (
                            <Button
                                asChild
                                className="bg-tb-primary hover:bg-tb-primary-light"
                            >
                                <Link
                                    href={tarombo.snapshots.compile(
                                        sourceSnapshot.id,
                                    )}
                                >
                                    <Wand2 className="size-4" />
                                    {sourceSnapshot.has_compile_draft
                                        ? 'Lanjutkan Compile'
                                        : 'Compile Gambar'}
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                disabled
                                title="Pilih gambar terlebih dahulu"
                                className="bg-tb-primary hover:bg-tb-primary-light"
                            >
                                <Wand2 className="size-4" />
                                Compile Gambar
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={tarombo.index()}>
                                <ArrowLeft className="size-4" /> Pohon Tarombo
                            </Link>
                        </Button>
                    </div>
                </div>

                <Tabs
                    value={filter}
                    defaultValue="all"
                    onValueChange={applyFilter}
                >
                    <TabsList>
                        <TabsTrigger value="all">Semua</TabsTrigger>
                        <TabsTrigger value="compiled">
                            Hasil Compile
                        </TabsTrigger>
                        <TabsTrigger value="original">
                            Gambar Original
                        </TabsTrigger>
                        <TabsTrigger value="saved">Hasil Simpan</TabsTrigger>
                    </TabsList>
                </Tabs>

                <div className="flex items-start gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4 text-sm text-tb-on-surface-variant">
                    <ShieldCheck className="mt-0.5 size-5 shrink-0 text-tb-primary" />
                    {canDownload ? (
                        <p>
                            Gambar dilayani melalui akses privat. Sebagai staff,
                            Anda dapat melihat dan mengunduh gambar Tarombo
                            seluruh akun. Setiap aksi unduh tercatat pada Log
                            Aktivitas. Pilih gambar lalu tekan Compile Gambar
                            untuk menempatkan Tarombo utuh di dalam frame.
                        </p>
                    ) : (
                        <p>
                            Gambar dilayani melalui akses privat, tanpa tombol
                            download, serta tidak dapat diklik kanan atau
                            ditarik dari galeri. Pilih gambar lalu tekan Compile
                            Gambar untuk menempatkan Tarombo utuh di dalam
                            frame.
                        </p>
                    )}
                </div>

                {snapshots.data.length === 0 ? (
                    <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="flex flex-col items-center gap-3 py-14 text-center">
                            <Images className="size-10 text-tb-outline" />
                            <div>
                                <p className="font-semibold text-tb-on-surface">
                                    {filter === 'saved'
                                        ? 'Belum ada Compile Gambar yang disimpan'
                                        : 'Belum ada Tarombo tersimpan'}
                                </p>
                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                    {filter === 'saved'
                                        ? 'Buka Compile Gambar, pilih frame, lalu tekan tombol Simpan.'
                                        : 'Buka Pohon Tarombo fullscreen lalu tekan tombol Simpan.'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {snapshots.data.map((snapshot) => (
                            <Card
                                key={snapshot.draft_id ?? snapshot.id}
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
                                    {snapshot.has_compile_draft &&
                                        !snapshot.saved_compile && (
                                            <span className="text-tb-on-primary pointer-events-none absolute top-2 left-2 rounded-full bg-tb-primary px-2 py-0.5 text-[10px] font-semibold shadow-sm">
                                                Compile tersimpan
                                            </span>
                                        )}
                                </button>
                                <CardContent className="flex items-start justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="truncate text-sm font-semibold text-tb-on-surface">
                                                {snapshotLabel(snapshot)}
                                            </p>
                                            {snapshot.saved_compile && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openNameDialog(
                                                            'rename',
                                                            snapshot,
                                                        )
                                                    }
                                                    aria-label="Ubah nama gambar"
                                                    title="Ubah nama"
                                                    className="-ml-1 rounded p-1 text-tb-on-surface-variant hover:bg-tb-surface-container hover:text-tb-on-surface"
                                                >
                                                    <Pencil className="size-3.5" />
                                                </button>
                                            )}
                                            <Badge variant="outline">
                                                {snapshot.view === 'tree'
                                                    ? 'Vertikal'
                                                    : 'Radial'}
                                            </Badge>
                                            {snapshot.saved_compile && (
                                                <Badge variant="outline">
                                                    Tersimpan
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                            {snapshot.owner_name
                                                ? `Milik ${snapshot.owner_name} · `
                                                : ''}
                                            {formatSize(snapshot.size_bytes) &&
                                                `${formatSize(snapshot.size_bytes)} · `}
                                            {snapshot.created_at
                                                ? dateFormatter.format(
                                                      new Date(
                                                          snapshot.created_at,
                                                      ),
                                                  )
                                                : 'Waktu tidak tersedia'}
                                        </p>
                                        {snapshot.saved_compile &&
                                            !snapshot.has_preview && (
                                                <p className="mt-1 text-xs text-tb-on-surface-variant italic">
                                                    Buka & Simpan lagi untuk
                                                    memperbarui pratinjau.
                                                </p>
                                            )}
                                        {snapshot.saved_compile && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="mt-2"
                                                onClick={() =>
                                                    openNameDialog(
                                                        'duplicate',
                                                        snapshot,
                                                    )
                                                }
                                            >
                                                <Copy className="size-4" />
                                                Duplikat
                                            </Button>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        {snapshot.editable_result ? (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={tarombo.snapshots.compile(
                                                        snapshot.id,
                                                    )}
                                                    title="Edit hasil ini di Compile Gambar lalu perbarui gambarnya"
                                                >
                                                    <Pencil className="size-4" />
                                                    Edit Compile
                                                </Link>
                                            </Button>
                                        ) : (
                                            snapshot.has_compile_draft && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={tarombo.snapshots.compile(
                                                            snapshot.id,
                                                            snapshot.draft_id
                                                                ? {
                                                                      query: {
                                                                          draft: snapshot.draft_id,
                                                                      },
                                                                  }
                                                                : undefined,
                                                        )}
                                                        title="Buka Compile Gambar yang tersimpan"
                                                    >
                                                        <Wand2 className="size-4" />
                                                        Lanjutkan Compile
                                                    </Link>
                                                </Button>
                                            )
                                        )}
                                        {snapshot.download_url && (
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                            >
                                                <a
                                                    href={snapshot.download_url}
                                                    aria-label="Unduh gambar Tarombo"
                                                    title="Unduh"
                                                >
                                                    <Download className="size-4" />
                                                </a>
                                            </Button>
                                        )}
                                        {snapshot.saved_compile && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeSavedCompile(snapshot)
                                                }
                                                aria-label="Hapus simpanan Compile Gambar"
                                                title="Hapus simpanan"
                                                className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                        {snapshot.can_delete && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeSnapshot(snapshot)
                                                }
                                                aria-label="Hapus gambar Tarombo"
                                                className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </div>
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
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <DialogTitle className="font-display text-tb-on-surface">
                                    {selectedSnapshot
                                        ? snapshotLabel(selectedSnapshot)
                                        : 'Pohon Tarombo'}
                                </DialogTitle>
                                <DialogDescription>
                                    {selectedSnapshot?.view === 'tree'
                                        ? 'Tampilan silsilah vertikal'
                                        : 'Tampilan diagram radial'}
                                </DialogDescription>
                            </div>
                            {selectedSnapshot?.download_url && (
                                <Button asChild variant="outline">
                                    <a href={selectedSnapshot.download_url}>
                                        <Download className="size-4" /> Unduh
                                    </a>
                                </Button>
                            )}
                        </div>
                    </DialogHeader>
                    {selectedSnapshot && (
                        <ZoomableImage
                            key={selectedSnapshot.id}
                            src={selectedSnapshot.image_url}
                            alt={`Tarombo ${selectedSnapshot.center_person_name ?? 'tersimpan'}`}
                            className="h-[78dvh]"
                        >
                            <span className="pointer-events-none absolute right-3 bottom-3 rounded bg-black/45 px-2 py-1 text-[10px] font-medium text-white/80 shadow-sm">
                                Tarombo Batak · {accountName}
                            </span>
                        </ZoomableImage>
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
                                    alt={snapshotLabel(snapshot)}
                                    className="aspect-video w-full bg-tb-surface-container object-contain"
                                />
                                <p className="truncate px-3 py-2 text-sm font-medium text-tb-on-surface">
                                    {snapshotLabel(snapshot)}
                                    {snapshot.owner_name
                                        ? ` · ${snapshot.owner_name}`
                                        : ''}
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

            <Dialog
                open={nameDialog !== null}
                onOpenChange={(open) => !open && setNameDialog(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={submitName} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {nameDialog?.mode === 'duplicate'
                                    ? 'Duplikat Gambar'
                                    : 'Ubah Nama Gambar'}
                            </DialogTitle>
                            <DialogDescription>
                                {nameDialog?.mode === 'duplicate'
                                    ? 'Salinan dibuat dengan susunan yang sama dan bisa diedit terpisah. Beri nama untuk gambar duplikat ini.'
                                    : 'Nama ini ditampilkan pada daftar Hasil Simpan.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <label
                                htmlFor="saved-compile-name"
                                className="text-sm font-medium text-tb-on-surface"
                            >
                                Nama gambar
                            </label>
                            <Input
                                id="saved-compile-name"
                                value={nameInput}
                                maxLength={120}
                                autoFocus
                                onChange={(event) =>
                                    setNameInput(event.target.value)
                                }
                            />
                            {nameError && (
                                <p className="text-sm text-red-600">
                                    {nameError}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setNameDialog(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={savingName || !nameInput.trim()}
                                className="bg-tb-primary hover:bg-tb-primary-light"
                            >
                                {nameDialog?.mode === 'duplicate'
                                    ? 'Duplikat'
                                    : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <CollageDialog
                open={collageOpen}
                onClose={() => setCollageOpen(false)}
                frames={frames}
            />
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
