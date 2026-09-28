import { Minus, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';
import { DraggableBox } from '@/components/draggable-box';
import type { Box } from '@/components/draggable-box';

// The crop box lives in a fixed "view" space this many units wide; the image
// is zoomed and moved underneath it.
const VIEW_WIDTH = 1000;
const MAX_ZOOM = 8;

type ImageView = { zoom: number; x: number; y: number };

/**
 * Crop like a profile photo: the crop box stays where it is placed while the
 * image is zoomed (wheel, buttons, slider) and dragged underneath it. Reports
 * the crop in image pixels through `onChange`.
 */
export function CropEditor({
    url,
    imageWidth,
    imageHeight,
    initialCrop,
    onChange,
}: {
    url: string;
    imageWidth: number;
    imageHeight: number;
    initialCrop: Box;
    onChange: (crop: Box) => void;
}) {
    const viewHeight = (VIEW_WIDTH * imageHeight) / imageWidth;
    const toView = VIEW_WIDTH / imageWidth;
    const viewportRef = useRef<HTMLDivElement>(null);
    const panRef = useRef<{ clientX: number; clientY: number } | null>(null);
    const [frame, setFrame] = useState<Box>(() => ({
        x: initialCrop.x * toView,
        y: initialCrop.y * toView,
        width: initialCrop.width * toView,
        height: initialCrop.height * toView,
    }));
    const [image, setImage] = useState<ImageView>({ zoom: 1, x: 0, y: 0 });

    // The part of the image under the crop box, in image pixels.
    useEffect(() => {
        const scale = imageWidth / (VIEW_WIDTH * image.zoom);
        const left = Math.max(0, (frame.x - image.x) * scale);
        const top = Math.max(0, (frame.y - image.y) * scale);
        const right = Math.min(
            imageWidth,
            (frame.x + frame.width - image.x) * scale,
        );
        const bottom = Math.min(
            imageHeight,
            (frame.y + frame.height - image.y) * scale,
        );

        onChange({
            x: Math.round(left),
            y: Math.round(top),
            width: Math.max(1, Math.round(right - left)),
            height: Math.max(1, Math.round(bottom - top)),
        });
    }, [frame, image, imageWidth, imageHeight, onChange]);

    // Keep at least part of the image inside the view.
    const clampImage = (next: ImageView): ImageView => {
        const width = VIEW_WIDTH * next.zoom;
        const height = viewHeight * next.zoom;
        const margin = 50;

        return {
            zoom: next.zoom,
            x: Math.min(VIEW_WIDTH - margin, Math.max(margin - width, next.x)),
            y: Math.min(viewHeight - margin, Math.max(margin - height, next.y)),
        };
    };

    // Zoom around a point given in view units (the view centre by default).
    const zoomAround = (
        current: ImageView,
        zoom: number,
        pointX = VIEW_WIDTH / 2,
        pointY = viewHeight / 2,
    ): ImageView => {
        const nextZoom = Math.min(MAX_ZOOM, Math.max(1, zoom));
        const ratio = nextZoom / current.zoom;

        return clampImage({
            zoom: nextZoom,
            x: pointX - (pointX - current.x) * ratio,
            y: pointY - (pointY - current.y) * ratio,
        });
    };

    const zoomAroundRef = useRef(zoomAround);

    useEffect(() => {
        zoomAroundRef.current = zoomAround;
    });

    const pixelsToView = () => {
        const rect = viewportRef.current?.getBoundingClientRect();

        return rect && rect.width > 0 ? VIEW_WIDTH / rect.width : 1;
    };

    // React's wheel listener is passive, so the dialog would scroll as well.
    useEffect(() => {
        const viewport = viewportRef.current;

        if (!viewport) {
            return;
        }

        const handleWheel = (event: WheelEvent) => {
            event.preventDefault();
            const rect = viewport.getBoundingClientRect();
            const factor = rect.width > 0 ? VIEW_WIDTH / rect.width : 1;
            const pointX = (event.clientX - rect.left) * factor;
            const pointY = (event.clientY - rect.top) * factor;
            const step = event.deltaY < 0 ? 1.15 : 1 / 1.15;

            setImage((current) =>
                zoomAroundRef.current(
                    current,
                    current.zoom * step,
                    pointX,
                    pointY,
                ),
            );
        };

        viewport.addEventListener('wheel', handleWheel, { passive: false });

        return () => viewport.removeEventListener('wheel', handleWheel);
    }, []);

    // Dragging anywhere outside the crop box moves the image.
    const startPan = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        panRef.current = { clientX: event.clientX, clientY: event.clientY };
    };

    const movePan = (event: ReactPointerEvent<HTMLDivElement>) => {
        const pan = panRef.current;

        if (!pan) {
            return;
        }

        const factor = pixelsToView();
        const deltaX = (event.clientX - pan.clientX) * factor;
        const deltaY = (event.clientY - pan.clientY) * factor;

        panRef.current = { clientX: event.clientX, clientY: event.clientY };
        setImage((current) =>
            clampImage({
                ...current,
                x: current.x + deltaX,
                y: current.y + deltaY,
            }),
        );
    };

    const endPan = () => {
        panRef.current = null;
    };

    const percent = (value: number, total: number) =>
        `${(value / total) * 100}%`;

    return (
        <div className="grid gap-3">
            <div
                ref={viewportRef}
                className="relative mx-auto max-h-[65dvh] w-full cursor-grab touch-none overflow-hidden rounded-lg bg-[repeating-conic-gradient(#d4d4d4_0_25%,#f5f5f5_0_50%)] bg-[length:20px_20px] select-none active:cursor-grabbing"
                style={{
                    aspectRatio: `${imageWidth} / ${imageHeight}`,
                    maxWidth: `calc(65dvh * ${imageWidth / imageHeight})`,
                }}
                onPointerDown={startPan}
                onPointerMove={movePan}
                onPointerUp={endPan}
                onPointerCancel={endPan}
            >
                <img
                    src={url}
                    alt="Gambar yang dipotong"
                    draggable={false}
                    className="pointer-events-none absolute max-w-none object-fill"
                    style={{
                        left: percent(image.x, VIEW_WIDTH),
                        top: percent(image.y, viewHeight),
                        width: `${image.zoom * 100}%`,
                        height: `${image.zoom * 100}%`,
                    }}
                />
                <DraggableBox
                    spaceWidth={VIEW_WIDTH}
                    spaceHeight={viewHeight}
                    box={frame}
                    onChange={setFrame}
                    minSize={20}
                    dimOutside
                />
            </div>
            <div className="flex items-center justify-center gap-3">
                <button
                    type="button"
                    onClick={() =>
                        setImage((current) =>
                            zoomAround(current, current.zoom / 1.25),
                        )
                    }
                    disabled={image.zoom <= 1}
                    aria-label="Perkecil gambar"
                    title="Perkecil gambar"
                    className="inline-flex size-9 items-center justify-center rounded-lg border border-tb-outline-variant text-tb-on-surface hover:bg-tb-surface-container disabled:opacity-40"
                >
                    <Minus className="size-4" />
                </button>
                <input
                    type="range"
                    min={1}
                    max={MAX_ZOOM}
                    step={0.05}
                    value={image.zoom}
                    onChange={(event) =>
                        setImage((current) =>
                            zoomAround(current, Number(event.target.value)),
                        )
                    }
                    aria-label="Ukuran gambar"
                    className="w-48 accent-tb-primary"
                />
                <button
                    type="button"
                    onClick={() =>
                        setImage((current) =>
                            zoomAround(current, current.zoom * 1.25),
                        )
                    }
                    disabled={image.zoom >= MAX_ZOOM}
                    aria-label="Perbesar gambar"
                    title="Perbesar gambar"
                    className="inline-flex size-9 items-center justify-center rounded-lg border border-tb-outline-variant text-tb-on-surface hover:bg-tb-surface-container disabled:opacity-40"
                >
                    <Plus className="size-4" />
                </button>
                <span className="w-12 text-sm text-tb-on-surface-variant tabular-nums">
                    {Math.round(image.zoom * 100)}%
                </span>
            </div>
            <p className="text-center text-xs text-tb-on-surface-variant">
                Perbesar/perkecil gambar dengan scroll atau slider, geser gambar
                di luar kotak. Bagian di dalam kotak menjadi hasil potongan.
            </p>
        </div>
    );
}
