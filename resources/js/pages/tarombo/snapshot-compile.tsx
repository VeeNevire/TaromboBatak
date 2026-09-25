import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    ChevronsDown,
    ChevronsUp,
    Copy,
    Crop,
    ImagePlus,
    Layers,
    LayoutGrid,
    LoaderCircle,
    PanelsTopLeft,
    RotateCcw,
    Trash2,
    Wand2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
    canvasThumbnail,
    composeLayers,
    defaultLayerPlacement,
    fitInArea,
    imageFileToCanvas,
    snapshotCanvas,
    treeUpscale,
} from '@/lib/tarombo-compose';
import type { ComposeItem, LayerKind } from '@/lib/tarombo-compose';
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

type Layer = {
    id: string;
    name: string;
    kind: LayerKind;
    source: HTMLCanvasElement;
    thumb: string;
    crop: Box | null;
    placement: Box;
};

type StackItem = {
    id: string;
    name: string;
    thumb: string;
    source: HTMLCanvasElement;
    crop: Box | null;
    placement: Box;
    kind: LayerKind | 'tree';
};

type MoveAction = 'up' | 'down' | 'front' | 'back';

// The on-screen preview is drawn smaller so dragging stays smooth; Produce renders full size.
const PREVIEW_MAX_SIDE = 1600;
const TREE_ID = 'tree';

const fullBox = (canvas: HTMLCanvasElement): Box => ({
    x: 0,
    y: 0,
    width: canvas.width,
    height: canvas.height,
});

// Bottom-to-top draw list: the tree sits in the same stack as the extra images.
function composeItems(
    order: string[],
    layers: Layer[],
    tree: HTMLCanvasElement,
    treeCrop: Box | null,
    treePlacement: Box | null,
    frame: Frame,
): ComposeItem[] {
    const items: ComposeItem[] = [];

    for (const id of order) {
        if (id === TREE_ID) {
            const source = treeCrop ?? fullBox(tree);

            items.push({
                source: tree,
                crop: treeCrop,
                placement:
                    treePlacement ??
                    fitInArea(frame, source.width, source.height),
            });

            continue;
        }

        const layer = layers.find((item) => item.id === id);

        if (layer) {
            items.push({
                source: layer.source,
                crop: layer.crop,
                placement: layer.placement,
            });
        }
    }

    return items;
}

const isTyping = (target: EventTarget | null) =>
    target instanceof HTMLElement &&
    (target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.isContentEditable);

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
    const [layers, setLayers] = useState<Layer[]>([]);
    const [order, setOrder] = useState<string[]>([TREE_ID]);
    const [selectedId, setSelectedId] = useState<string>(TREE_ID);
    const [cropDraft, setCropDraft] = useState<Box | null>(null);
    const [cropState, setCropState] = useState<{
        id: string;
        canvas: HTMLCanvasElement;
        url: string;
    } | null>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const rantingInputRef = useRef<HTMLInputElement>(null);
    const backgroundInputRef = useRef<HTMLInputElement>(null);
    const treeCacheRef = useRef(new Map<boolean, HTMLCanvasElement>());
    const layerCounter = useRef(0);
    const nameCounter = useRef({ ranting: 0, background: 0 });
    const copiedIdRef = useRef<string | null>(null);
    const label =
        snapshot.title ?? snapshot.center_person_name ?? 'Pohon Tarombo';
    const loadKey = selectedFrame
        ? `${selectedFrame.id}-${removeBackground}`
        : null;
    const ready = assets !== null && assets.key === loadKey;
    const treeSource: Box | null = ready ? (crop ?? fullBox(assets.tree)) : null;
    const fittedSpot =
        selectedFrame && treeSource
            ? fitInArea(selectedFrame, treeSource.width, treeSource.height)
            : null;
    const treeSpot = placement ?? fittedSpot;
    const upscale =
        ready && selectedFrame && treeSource && treeSpot
            ? treeUpscale(
                  assets.frameImage,
                  selectedFrame,
                  treeSource,
                  treeSpot,
              )
            : 1;
    const treeThumb = useMemo(
        () => (assets ? canvasThumbnail(assets.tree) : ''),
        [assets],
    );

    // Bottom to top, the same order they are drawn in.
    const stack: StackItem[] = [];

    for (const id of order) {
        if (id === TREE_ID) {
            if (ready && treeSpot) {
                stack.push({
                    id,
                    name: 'Pohon',
                    thumb: treeThumb,
                    source: assets.tree,
                    crop,
                    placement: treeSpot,
                    kind: 'tree',
                });
            }

            continue;
        }

        const layer = layers.find((item) => item.id === id);

        if (layer) {
            stack.push(layer);
        }
    }

    const selectedItem = stack.find((item) => item.id === selectedId) ?? null;
    // Size relative to the default size the selected image starts with.
    const baseWidth =
        selectedItem && selectedFrame
            ? selectedItem.kind === 'tree'
                ? (fittedSpot?.width ?? 1)
                : defaultLayerPlacement(
                      selectedItem.kind,
                      selectedFrame,
                      (selectedItem.crop ?? fullBox(selectedItem.source)).width,
                      (selectedItem.crop ?? fullBox(selectedItem.source))
                          .height,
                  ).width
            : 1;
    const zoom = selectedItem ? selectedItem.placement.width / baseWidth : 1;

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

        composeLayers(
            canvasRef.current,
            assets.frameImage,
            selectedFrame,
            composeItems(
                order,
                layers,
                assets.tree,
                crop,
                placement,
                selectedFrame,
            ),
            { maxSide: PREVIEW_MAX_SIDE },
        );
    }, [ready, assets, selectedFrame, crop, placement, layers, order]);

    const updateLayer = (id: string, patch: Partial<Layer>) =>
        setLayers((current) =>
            current.map((layer) =>
                layer.id === id ? { ...layer, ...patch } : layer,
            ),
        );

    const setItemPlacement = (id: string, box: Box) => {
        if (id === TREE_ID) {
            setPlacement(box);
        } else {
            updateLayer(id, { placement: box });
        }
    };

    const addLayer = (canvas: HTMLCanvasElement, kind: LayerKind) => {
        if (!selectedFrame) {
            return;
        }

        const id = `layer-${++layerCounter.current}`;
        const number = ++nameCounter.current[kind];

        setLayers((current) => [
            ...current,
            {
                id,
                name: `${kind === 'background' ? 'Background' : 'Ranting'} ${number}`,
                kind,
                source: canvas,
                thumb: canvasThumbnail(canvas),
                crop: null,
                placement: defaultLayerPlacement(
                    kind,
                    selectedFrame,
                    canvas.width,
                    canvas.height,
                ),
            },
        ]);
        // A background goes to the very back, other images just behind the tree.
        setOrder((current) => {
            if (kind === 'background') {
                return [id, ...current];
            }

            const next = [...current];
            next.splice(Math.max(0, next.indexOf(TREE_ID)), 0, id);

            return next;
        });
        setSelectedId(id);
    };

    const addFiles = async (files: File[], kind: LayerKind) => {
        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                continue;
            }

            try {
                addLayer(await imageFileToCanvas(file), kind);
            } catch {
                setPreviewError('Gambar gagal dimuat. Coba file lain.');
            }
        }
    };

    const duplicateItem = (id: string) => {
        const item = stack.find((entry) => entry.id === id);

        if (!item || !selectedFrame) {
            return;
        }

        const offset = Math.round(selectedFrame.canvas_width * 0.04);
        const newId = `layer-${++layerCounter.current}`;

        setLayers((current) => [
            ...current,
            {
                id: newId,
                name: `${item.name} (salinan)`,
                kind: item.kind === 'tree' ? 'ranting' : item.kind,
                source: item.source,
                thumb: item.thumb,
                crop: item.crop,
                placement: {
                    ...item.placement,
                    x: item.placement.x + offset,
                    y: item.placement.y + offset,
                },
            },
        ]);
        setOrder((current) => {
            const next = [...current];
            next.splice(next.indexOf(id) + 1, 0, newId);

            return next;
        });
        setSelectedId(newId);
    };

    const removeItem = (id: string) => {
        if (id === TREE_ID) {
            return;
        }

        setLayers((current) => current.filter((layer) => layer.id !== id));
        setOrder((current) => current.filter((entry) => entry !== id));
        setSelectedId((current) => (current === id ? TREE_ID : current));
    };

    const moveItem = (id: string, action: MoveAction) =>
        setOrder((current) => {
            const index = current.indexOf(id);

            if (index < 0) {
                return current;
            }

            const next = current.filter((entry) => entry !== id);
            const target =
                action === 'front'
                    ? next.length
                    : action === 'back'
                      ? 0
                      : action === 'up'
                        ? Math.min(next.length, index + 1)
                        : Math.max(0, index - 1);

            next.splice(target, 0, id);

            return next;
        });

    // The window listeners below always call the latest handlers through this ref.
    const actionsRef = useRef({ addFiles, duplicateItem, removeItem });
    const selectedIdRef = useRef(selectedId);

    useEffect(() => {
        actionsRef.current = { addFiles, duplicateItem, removeItem };
        selectedIdRef.current = selectedId;
    });

    useEffect(() => {
        const blocked = (target: EventTarget | null) =>
            isTyping(target) || document.querySelector('[role="dialog"]');

        const onPaste = (event: ClipboardEvent) => {
            if (blocked(event.target)) {
                return;
            }

            const files = Array.from(event.clipboardData?.files ?? []).filter(
                (file) => file.type.startsWith('image/'),
            );

            if (files.length > 0) {
                event.preventDefault();
                void actionsRef.current.addFiles(files, 'ranting');

                return;
            }

            if (copiedIdRef.current) {
                event.preventDefault();
                actionsRef.current.duplicateItem(copiedIdRef.current);
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (blocked(event.target)) {
                return;
            }

            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'c' &&
                !window.getSelection()?.toString()
            ) {
                copiedIdRef.current = selectedIdRef.current;
            } else if (event.key === 'Delete') {
                actionsRef.current.removeItem(selectedIdRef.current);
            }
        };

        window.addEventListener('paste', onPaste);
        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('paste', onPaste);
            window.removeEventListener('keydown', onKeyDown);
        };
    }, []);

    const pickFrame = (frame: Frame) => {
        setPreviewError(null);

        // Extra images keep their relative position on a frame with another canvas size.
        if (selectedFrame && frame.id !== selectedFrame.id) {
            const ratioX = frame.canvas_width / selectedFrame.canvas_width;
            const ratioY = frame.canvas_height / selectedFrame.canvas_height;

            setLayers((current) =>
                current.map((layer) => ({
                    ...layer,
                    placement: {
                        x: Math.round(layer.placement.x * ratioX),
                        y: Math.round(layer.placement.y * ratioY),
                        width: Math.round(layer.placement.width * ratioX),
                        height: Math.round(layer.placement.height * ratioY),
                    },
                })),
            );
        }

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

    const setZoom = (value: number) => {
        if (!selectedItem) {
            return;
        }

        // Grow or shrink around the current centre of the image.
        const { placement: spot } = selectedItem;
        const ratio = spot.height / Math.max(1, spot.width);
        const width = baseWidth * value;
        const height = width * ratio;

        setItemPlacement(selectedItem.id, {
            x: Math.round(spot.x + spot.width / 2 - width / 2),
            y: Math.round(spot.y + spot.height / 2 - height / 2),
            width: Math.round(width),
            height: Math.round(height),
        });
    };

    const resetSelected = () => {
        if (!selectedItem || !selectedFrame) {
            return;
        }

        if (selectedItem.kind === 'tree') {
            setCrop(null);
            setPlacement(null);

            return;
        }

        updateLayer(selectedItem.id, {
            crop: null,
            placement: defaultLayerPlacement(
                selectedItem.kind,
                selectedFrame,
                selectedItem.source.width,
                selectedItem.source.height,
            ),
        });
    };

    const openCrop = () => {
        if (!selectedItem) {
            return;
        }

        setCropState({
            id: selectedItem.id,
            canvas: selectedItem.source,
            url: selectedItem.source.toDataURL('image/png'),
        });
        setCropDraft(selectedItem.crop ?? fullBox(selectedItem.source));
    };

    const applyCrop = () => {
        if (!cropState || !cropDraft) {
            return;
        }

        if (cropState.id === TREE_ID) {
            setCrop(cropDraft);
            setPlacement(null);
        } else {
            const layer = layers.find((item) => item.id === cropState.id);

            if (layer) {
                // Keep the centre and width, follow the new proportions.
                const width = layer.placement.width;
                const height = Math.max(
                    1,
                    Math.round((width * cropDraft.height) / cropDraft.width),
                );

                updateLayer(layer.id, {
                    crop: cropDraft,
                    placement: {
                        x: layer.placement.x,
                        y: Math.round(
                            layer.placement.y +
                                (layer.placement.height - height) / 2,
                        ),
                        width,
                        height,
                    },
                });
            }
        }

        setCropState(null);
    };

    // Clicking the preview selects the topmost image under the pointer.
    const selectAtPoint = (event: React.PointerEvent) => {
        const canvas = canvasRef.current;

        if (!canvas || !selectedFrame) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const x =
            ((event.clientX - rect.left) / rect.width) *
            selectedFrame.canvas_width;
        const y =
            ((event.clientY - rect.top) / rect.height) *
            selectedFrame.canvas_height;
        const hit = [...stack].reverse().find(
            (item) =>
                x >= item.placement.x &&
                x <= item.placement.x + item.placement.width &&
                y >= item.placement.y &&
                y <= item.placement.y + item.placement.height,
        );

        if (hit) {
            setSelectedId(hit.id);
        }
    };

    const produce = async () => {
        if (!selectedFrame || producing || !ready) {
            return;
        }

        setProducing(true);

        const output = document.createElement('canvas');
        composeLayers(
            output,
            assets.frameImage,
            selectedFrame,
            composeItems(
                order,
                layers,
                assets.tree,
                crop,
                placement,
                selectedFrame,
            ),
        );

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

    const stackIndex = selectedItem
        ? stack.findIndex((item) => item.id === selectedItem.id)
        : -1;
    // `stack` is bottom to top; the list below is shown top first.
    const isTop = stackIndex === stack.length - 1;
    const isBottom = stackIndex === 0;

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
                            disabled={!selectedItem}
                            onClick={openCrop}
                        >
                            <Crop className="size-4" />
                            Potong
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!selectedItem}
                            onClick={resetSelected}
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

                <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    {previewError ? (
                        <p className="text-sm text-red-600">{previewError}</p>
                    ) : (
                        <p className="text-xs text-tb-on-surface-variant">
                            Klik gambar di pratinjau atau di daftar lapisan
                            untuk memilihnya, lalu geser kotaknya atau tarik
                            sudutnya. Tempel gambar dengan Ctrl+V, salin lapisan
                            dengan Ctrl+C lalu Ctrl+V, hapus dengan Delete.
                        </p>
                    )}
                    <label className="flex shrink-0 items-center gap-3 text-sm text-tb-on-surface">
                        Ukuran {selectedItem?.name ?? 'gambar'}
                        <input
                            type="range"
                            min={0.25}
                            max={4}
                            step={0.05}
                            value={Math.min(4, Math.max(0.25, zoom))}
                            disabled={!selectedItem}
                            onChange={(event) =>
                                setZoom(Number(event.target.value))
                            }
                            className="w-40 accent-tb-primary"
                        />
                        <span className="w-12 text-right tabular-nums">
                            {Math.round(zoom * 100)}%
                        </span>
                    </label>
                </div>

                {upscale > 1.5 && (
                    <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Pohon diperbesar {upscale.toFixed(1)}× dari resolusi
                        gambar aslinya, jadi tulisan node bisa terlihat buram.
                        Agar tetap tajam, simpan ulang pohon dengan resolusi
                        lebih tinggi (2160p atau 4320p) di Pohon Tarombo.
                    </p>
                )}

                <div className="flex min-h-0 flex-1 flex-col gap-4 md:flex-row">
                    <div className="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-xl bg-tb-surface-container p-6 select-none">
                        {selectedFrame ? (
                            <div
                                className={`relative ${ready ? '' : 'min-h-40 min-w-60'}`}
                                onPointerDown={selectAtPoint}
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
                                {ready && selectedItem && (
                                    <DraggableBox
                                        key={selectedItem.id}
                                        spaceWidth={selectedFrame.canvas_width}
                                        spaceHeight={selectedFrame.canvas_height}
                                        box={selectedItem.placement}
                                        onChange={(box) =>
                                            setItemPlacement(
                                                selectedItem.id,
                                                box,
                                            )
                                        }
                                        lockAspect
                                        bounded={false}
                                        minSize={20}
                                    />
                                )}
                                <span className="pointer-events-none absolute right-3 bottom-3 rounded bg-black/45 px-2 py-1 text-[10px] font-medium text-white/80 shadow-sm">
                                    Tarombo Batak · {accountName}
                                </span>
                            </div>
                        ) : (
                            <p className="py-16 text-center text-sm text-tb-on-surface-variant">
                                Belum ada frame aktif. Hubungi admin untuk
                                menambah template frame.
                            </p>
                        )}
                    </div>

                    <aside className="flex w-full shrink-0 flex-col gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-bright p-3 md:w-64">
                        <div className="flex items-center gap-2 text-sm font-semibold text-tb-on-surface">
                            <Layers className="size-4 text-tb-primary" />
                            Lapisan
                            <span className="ml-auto text-xs font-normal text-tb-on-surface-variant">
                                atas → bawah
                            </span>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!selectedFrame}
                                onClick={() => rantingInputRef.current?.click()}
                            >
                                <ImagePlus className="size-4" /> Ranting
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!selectedFrame}
                                onClick={() =>
                                    backgroundInputRef.current?.click()
                                }
                            >
                                <ImagePlus className="size-4" /> Background
                            </Button>
                        </div>
                        <input
                            ref={rantingInputRef}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={(event) => {
                                void addFiles(
                                    Array.from(event.target.files ?? []),
                                    'ranting',
                                );
                                event.target.value = '';
                            }}
                        />
                        <input
                            ref={backgroundInputRef}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={(event) => {
                                void addFiles(
                                    Array.from(event.target.files ?? []),
                                    'background',
                                );
                                event.target.value = '';
                            }}
                        />

                        <ul className="flex max-h-72 flex-col gap-1 overflow-y-auto">
                            {[...stack].reverse().map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedId(item.id)}
                                        className={`flex w-full items-center gap-2 rounded-lg border p-1.5 text-left text-sm transition-colors ${
                                            item.id === selectedItem?.id
                                                ? 'border-tb-primary bg-tb-primary/10'
                                                : 'border-tb-outline-variant hover:bg-tb-surface-container'
                                        }`}
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded bg-[repeating-conic-gradient(#d4d4d4_0_25%,#f5f5f5_0_50%)] bg-[length:10px_10px]">
                                            {item.thumb && (
                                                <img
                                                    src={item.thumb}
                                                    alt=""
                                                    draggable={false}
                                                    className="max-h-full max-w-full object-contain"
                                                />
                                            )}
                                        </span>
                                        <span className="min-w-0 flex-1 truncate text-tb-on-surface">
                                            {item.name}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>

                        <div className="flex flex-wrap gap-1 border-t border-tb-outline-variant pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Ke paling depan"
                                aria-label="Ke paling depan"
                                disabled={!selectedItem || isTop}
                                onClick={() =>
                                    selectedItem &&
                                    moveItem(selectedItem.id, 'front')
                                }
                            >
                                <ChevronsUp className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Naikkan satu lapis"
                                aria-label="Naikkan satu lapis"
                                disabled={!selectedItem || isTop}
                                onClick={() =>
                                    selectedItem &&
                                    moveItem(selectedItem.id, 'up')
                                }
                            >
                                <ArrowUp className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Turunkan satu lapis"
                                aria-label="Turunkan satu lapis"
                                disabled={!selectedItem || isBottom}
                                onClick={() =>
                                    selectedItem &&
                                    moveItem(selectedItem.id, 'down')
                                }
                            >
                                <ArrowDown className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Ke paling belakang"
                                aria-label="Ke paling belakang"
                                disabled={!selectedItem || isBottom}
                                onClick={() =>
                                    selectedItem &&
                                    moveItem(selectedItem.id, 'back')
                                }
                            >
                                <ChevronsDown className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Duplikat"
                                aria-label="Duplikat"
                                disabled={!selectedItem}
                                onClick={() =>
                                    selectedItem &&
                                    duplicateItem(selectedItem.id)
                                }
                            >
                                <Copy className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Hapus"
                                aria-label="Hapus"
                                disabled={
                                    !selectedItem || selectedItem.id === TREE_ID
                                }
                                onClick={() =>
                                    selectedItem && removeItem(selectedItem.id)
                                }
                                className="text-red-600 hover:text-red-700"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    </aside>
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
                open={cropState !== null}
                onOpenChange={(open) => !open && setCropState(null)}
            >
                <DialogContent className="max-h-[95dvh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Potong Gambar</DialogTitle>
                        <DialogDescription>
                            Atur kotak untuk memilih bagian gambar yang akan
                            ditempatkan di frame.
                        </DialogDescription>
                    </DialogHeader>
                    {cropState && cropDraft && (
                        <div
                            className="relative mx-auto max-h-[65dvh] w-full overflow-hidden rounded-lg bg-[repeating-conic-gradient(#d4d4d4_0_25%,#f5f5f5_0_50%)] bg-[length:20px_20px] select-none"
                            style={{
                                aspectRatio: `${cropState.canvas.width} / ${cropState.canvas.height}`,
                                maxWidth: `calc(65dvh * ${cropState.canvas.width / cropState.canvas.height})`,
                            }}
                        >
                            <img
                                src={cropState.url}
                                alt="Gambar yang dipotong"
                                draggable={false}
                                className="pointer-events-none size-full object-fill"
                            />
                            <DraggableBox
                                spaceWidth={cropState.canvas.width}
                                spaceHeight={cropState.canvas.height}
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
                                cropState &&
                                setCropDraft(fullBox(cropState.canvas))
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
