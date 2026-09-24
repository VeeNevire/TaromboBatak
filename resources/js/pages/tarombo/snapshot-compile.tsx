import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Crop,
    LayoutGrid,
    LoaderCircle,
    PanelsTopLeft,
    RotateCcw,
    Wand2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CollageDialog } from '@/components/collage-dialog';
import { DraggableBox } from '@/components/draggable-box';
import type { Box } from '@/components/draggable-box';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { composeCanvasToFile, loadImageUrl } from '@/lib/collage';
import {
    composeOnFrame,
    fitInArea,
    snapshotCanvas,
} from '@/lib/tarombo-compose';
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';

type Snapshot = {
    id: number;
    view: 'diagram' | 'tree';
    title: string | null;
    center_person_name: string | null;
    image_url: string;
};

type Frame = {
    id: number;
    name: string;
    image_url: string;
    canvas_width: number;
    canvas_height: number;
    area_x: number;
    area_y: number;
    area_width: number;
    area_height: number;
};

type Assets = {
    key: string;
    frameImage: HTMLImageElement;
    tree: HTMLCanvasElement;
};

// The on-screen preview is drawn smaller so dragging stays smooth; Produce renders full size.
const PREVIEW_MAX_SIDE = 1600;

export default function TaromboSnapshotCompile({
    snapshot,
    frames,
    accountName,
}: {
    snapshot: Snapshot;
    frames: Frame[];
    accountName: string;
}) {
    const [selectedFrame, setSelectedFrame] = useState<Frame | null>(
        frames[0] ?? null,
    );
    const [framePickerOpen, setFramePickerOpen] = useState(false);
    const [collageOpen, setCollageOpen] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [producing, setProducing] = useState(false);
    const [removeBackground, setRemoveBackground] = useState(true);
    const [assets, setAssets] = useState<Assets | null>(null);
    const [crop, setCrop] = useState<Box | null>(null);
    const [placement, setPlacement] = useState<Box | null>(null);
    const [cropDraft, setCropDraft] = useState<Box | null>(null);
    const [cropImageUrl, setCropImageUrl] = useState<string | null>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const treeCacheRef = useRef(new Map<boolean, HTMLCanvasElement>());
    const label =
        snapshot.title ?? snapshot.center_person_name ?? 'Pohon Tarombo';
    const loadKey = selectedFrame
        ? `${selectedFrame.id}-${removeBackground}`
        : null;
    const ready = assets !== null && assets.key === loadKey;
    const treeSource: Box | null = ready
        ? (crop ?? {
              x: 0,
              y: 0,
              width: assets.tree.width,
              height: assets.tree.height,
          })
        : null;
    const treeSpot =
        selectedFrame && treeSource
            ? (placement ??
              fitInArea(selectedFrame, treeSource.width, treeSource.height))
            : null;

    useEffect(() => {
        if (!selectedFrame || !loadKey) {
            return;
        }

        let cancelled = false;

        Promise.all([
            loadImageUrl(selectedFrame.image_url),
            loadImageUrl(snapshot.image_url),
        ])
            .then(([frameImage, snapshotImage]) => {
                if (cancelled) {
                    return;
                }

                let tree = treeCacheRef.current.get(removeBackground);

                if (!tree) {
                    tree = snapshotCanvas(snapshotImage, removeBackground);
                    treeCacheRef.current.set(removeBackground, tree);
                }

                setAssets({ key: loadKey, frameImage, tree });
            })
            .catch(() => {
                if (!cancelled) {
                    setPreviewError('Gambar Tarombo atau frame gagal dimuat.');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [selectedFrame, snapshot.image_url, removeBackground, loadKey]);

    useEffect(() => {
        if (!ready || !selectedFrame || !canvasRef.current) {
            return;
        }

        composeOnFrame(
            canvasRef.current,
            assets.frameImage,
            assets.tree,
            selectedFrame,
            { crop, placement, maxSide: PREVIEW_MAX_SIDE },
        );
    }, [ready, assets, selectedFrame, crop, placement]);

    const pickFrame = (frame: Frame) => {
        setPreviewError(null);
        setSelectedFrame(frame);
        setPlacement(null);
        setFramePickerOpen(false);
    };

    const toggleRemoveBackground = (checked: boolean) => {
        // The trimmed tree changes size, so an earlier crop no longer fits.
        setRemoveBackground(checked);
        setCrop(null);
        setPlacement(null);
    };

    const resetLayout = () => {
        setCrop(null);
        setPlacement(null);
    };

    const openCrop = () => {
        if (!ready || !treeSource) {
            return;
        }

        setCropImageUrl(assets.tree.toDataURL('image/png'));
        setCropDraft(treeSource);
    };

    const applyCrop = () => {
        setCrop(cropDraft);
        setPlacement(null);
        setCropImageUrl(null);
    };

    const produce = async () => {
        if (!selectedFrame || producing || !ready) {
            return;
        }

        setProducing(true);

        const output = document.createElement('canvas');
        composeOnFrame(output, assets.frameImage, assets.tree, selectedFrame, {
            crop,
            placement,
        });

        const image = await composeCanvasToFile(output, 'tarombo-frame.jpg');

        if (!image) {
            setProducing(false);
            setPreviewError('Gambar gagal dibuat. Silakan coba lagi.');

            return;
        }

        router.post(
            tarombo.snapshots.generate().url,
            { snapshot_id: snapshot.id, frame_id: selectedFrame.id, image },
            {
                forceFormData: true,
                onError: (errors) => {
                    setPreviewError(
                        errors.image ??
                            errors.frame_id ??
                            'Gambar gagal disimpan. Silakan coba lagi.',
                    );
                },
                onFinish: () => setProducing(false),
            },
        );
    };

    return (
        <>
            <Head title="Compile Gambar" />

            <div
                className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6"
                onContextMenu={(event) => event.preventDefault()}
            >
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="min-w-0">
                        <h1 className="font-display text-xl font-bold text-tb-on-surface md:text-2xl">
                            Compile Gambar
                        </h1>
                        <p className="truncate text-sm text-tb-on-surface-variant">
                            {label}
                            {selectedFrame ? ` · ${selectedFrame.name}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="flex h-9 items-center gap-2 rounded-md border border-tb-outline-variant px-3 text-sm text-tb-on-surface">
                            <input
                                type="checkbox"
                                checked={removeBackground}
                                onChange={(event) =>
                                    toggleRemoveBackground(event.target.checked)
                                }
                            />
                            Hapus latar gambar
                        </label>
                        <Button asChild variant="outline">
                            <Link href={tarombo.snapshots.index()}>
                                <ArrowLeft className="size-4" /> Kembali
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setFramePickerOpen(true)}
                            className="max-w-52 justify-start"
                        >
                            <PanelsTopLeft className="size-4 shrink-0" />
                            <span className="truncate">Pilih Frame</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!ready}
                            onClick={openCrop}
                        >
                            <Crop className="size-4" />
                            Potong
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!ready || (!crop && !placement)}
                            onClick={resetLayout}
                        >
                            <RotateCcw className="size-4" />
                            Reset
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setCollageOpen(true)}
                        >
                            <LayoutGrid className="size-4" />
                            Pilih Format Frame
                        </Button>
                        <Button
                            type="button"
                            disabled={!ready || producing}
                            onClick={produce}
                            className="bg-tb-primary hover:bg-tb-primary-light"
                        >
                            {producing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Wand2 className="size-4" />
                            )}
                            {producing ? 'Memproses...' : 'Produce'}
                        </Button>
                    </div>
                </div>

                {previewError ? (
                    <p className="text-sm text-red-600">{previewError}</p>
                ) : (
                    <p className="text-xs text-tb-on-surface-variant">
                        Geser kotak untuk memindahkan pohon dan tarik sudutnya
                        untuk mengubah ukuran. Gunakan Potong untuk membuang
                        bagian pohon yang tidak ingin ditampilkan.
                    </p>
                )}

                <div className="flex min-h-0 flex-1 items-center justify-center rounded-xl bg-tb-surface-container p-3 select-none">
                    {selectedFrame ? (
                        <div
                            className={`relative ${ready ? '' : 'min-h-40 min-w-60'}`}
                        >
                            {!ready && (
                                <div className="absolute inset-0 z-10 flex items-center justify-center gap-2 bg-black/30 text-sm text-white">
                                    <LoaderCircle className="size-4 animate-spin" />
                                    Memproses gambar...
                                </div>
                            )}
                            <canvas
                                ref={canvasRef}
                                className="block max-h-[calc(100dvh-15rem)] max-w-full shadow-md"
                            />
                            {ready && treeSpot && (
                                <DraggableBox
                                    spaceWidth={selectedFrame.canvas_width}
                                    spaceHeight={selectedFrame.canvas_height}
                                    box={treeSpot}
                                    onChange={setPlacement}
                                    lockAspect
                                    minSize={20}
                                />
                            )}
                            <span className="pointer-events-none absolute right-3 bottom-3 rounded bg-black/45 px-2 py-1 text-[10px] font-medium text-white/80 shadow-sm">
                                Tarombo Batak · {accountName}
                            </span>
                        </div>
                    ) : (
                        <p className="py-16 text-center text-sm text-tb-on-surface-variant">
                            Belum ada frame aktif. Hubungi admin untuk menambah
                            template frame.
                        </p>
                    )}
                </div>
            </div>

            <Dialog open={framePickerOpen} onOpenChange={setFramePickerOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Pilih Template Frame</DialogTitle>
                        <DialogDescription>
                            Gambar Tarombo akan ditempatkan utuh dan
                            proporsional di area konten frame.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        {frames.map((frame) => (
                            <button
                                key={frame.id}
                                type="button"
                                onClick={() => pickFrame(frame)}
                                className={`overflow-hidden rounded-xl border text-left transition-colors hover:border-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:outline-none ${
                                    frame.id === selectedFrame?.id
                                        ? 'border-tb-primary ring-2 ring-tb-primary/40'
                                        : 'border-tb-outline-variant'
                                }`}
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
                        {frames.length === 0 && (
                            <p className="col-span-full py-6 text-center text-sm text-tb-on-surface-variant">
                                Belum ada frame aktif. Hubungi admin untuk
                                menambah template.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={cropImageUrl !== null}
                onOpenChange={(open) => !open && setCropImageUrl(null)}
            >
                <DialogContent className="max-h-[95dvh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Potong Gambar Pohon</DialogTitle>
                        <DialogDescription>
                            Atur kotak untuk memilih bagian pohon yang akan
                            ditempatkan di frame.
                        </DialogDescription>
                    </DialogHeader>
                    {ready && cropImageUrl && cropDraft && (
                        <div
                            className="relative mx-auto max-h-[65dvh] w-full overflow-hidden rounded-lg bg-[repeating-conic-gradient(#d4d4d4_0_25%,#f5f5f5_0_50%)] bg-[length:20px_20px] select-none"
                            style={{
                                aspectRatio: `${assets.tree.width} / ${assets.tree.height}`,
                                maxWidth: `calc(65dvh * ${assets.tree.width / assets.tree.height})`,
                            }}
                        >
                            <img
                                src={cropImageUrl}
                                alt="Pohon Tarombo"
                                draggable={false}
                                className="pointer-events-none size-full object-fill"
                            />
                            <DraggableBox
                                spaceWidth={assets.tree.width}
                                spaceHeight={assets.tree.height}
                                box={cropDraft}
                                onChange={setCropDraft}
                                dimOutside
                            />
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                ready &&
                                setCropDraft({
                                    x: 0,
                                    y: 0,
                                    width: assets.tree.width,
                                    height: assets.tree.height,
                                })
                            }
                        >
                            Seluruh Gambar
                        </Button>
                        <Button
                            type="button"
                            onClick={applyCrop}
                            className="bg-tb-primary hover:bg-tb-primary-light"
                        >
                            Terapkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <CollageDialog
                open={collageOpen}
                onClose={() => setCollageOpen(false)}
            />
        </>
    );
}

TaromboSnapshotCompile.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pohon Tarombo', href: tarombo.index() },
        { title: 'Tarombo Tersimpan', href: tarombo.snapshots.index() },
        { title: 'Compile Gambar', href: '#' },
    ],
};
