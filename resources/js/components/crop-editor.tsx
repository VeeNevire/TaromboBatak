import { Minus, Plus } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';
import { DraggableBox } from '@/components/draggable-box';
import type { Box } from '@/components/draggable-box';

const MAX_ZOOM = 8;

// A point of the viewport (in displayed pixels) that must stay under the same
// part of the image while the zoom changes.
type ZoomAnchor = { x: number; y: number; ratio: number };

/**
 * Crop with a zoomable canvas: zooming scales the whole image together with
 * the crop box, and the viewport scrolls horizontally and vertically once the
 * image is larger than it. Reports the crop in image pixels through `onChange`.
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
    const viewportRef = useRef<HTMLDivElement>(null);
    const panRef = useRef<{ clientX: number; clientY: number } | null>(null);
    const anchorRef = useRef<ZoomAnchor | null>(null);
    const [crop, setCrop] = useState<Box>(initialCrop);
    const [zoom, setZoom] = useState(1);
    // Width of the viewport, which is the image width at 100%.
    const [baseWidth, setBaseWidth] = useState(0);

    useEffect(() => {
        onChange(crop);
    }, [crop, onChange]);

    useEffect(() => {
        const viewport = viewportRef.current;

        if (!viewport) {
            return;
        }

        const measure = () =>
            setBaseWidth(viewport.getBoundingClientRect().width);

        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(viewport);

        return () => observer.disconnect();
    }, []);

    // Zoom around a viewport point (its centre by default).
    const zoomTo = (next: number, pointX?: number, pointY?: number) => {
        const viewport = viewportRef.current;
        const nextZoom = Math.min(MAX_ZOOM, Math.max(1, next));

        if (!viewport || nextZoom === zoom) {
            return;
        }

        anchorRef.current = {
            x: pointX ?? viewport.clientWidth / 2,
            y: pointY ?? viewport.clientHeight / 2,
            ratio: nextZoom / zoom,
        };
        setZoom(nextZoom);
    };

    const zoomToRef = useRef(zoomTo);
    const zoomRef = useRef(zoom);

    useEffect(() => {
        zoomToRef.current = zoomTo;
        zoomRef.current = zoom;
    });

    // Once the canvas has its new size, scroll so the anchor stays in place.
    useLayoutEffect(() => {
        const viewport = viewportRef.current;
        const anchor = anchorRef.current;

        if (!viewport || !anchor) {
            return;
        }

        anchorRef.current = null;
        viewport.scrollLeft =
            (viewport.scrollLeft + anchor.x) * anchor.ratio - anchor.x;
        viewport.scrollTop =
            (viewport.scrollTop + anchor.y) * anchor.ratio - anchor.y;
    }, [zoom]);

    // Ctrl/⌘ + wheel zooms; a plain wheel scrolls the viewport. React's wheel
    // listener is passive, so it cannot stop the browser zooming the page.
    useEffect(() => {
        const viewport = viewportRef.current;

        if (!viewport) {
            return;
        }

        const handleWheel = (event: WheelEvent) => {
            if (!event.ctrlKey && !event.metaKey) {
                return;
            }

            event.preventDefault();
            const rect = viewport.getBoundingClientRect();
            const step = event.deltaY < 0 ? 1.15 : 1 / 1.15;

            zoomToRef.current(
                zoomRef.current * step,
                event.clientX - rect.left,
                event.clientY - rect.top,
            );
        };

        viewport.addEventListener('wheel', handleWheel, { passive: false });

        return () => viewport.removeEventListener('wheel', handleWheel);
    }, []);

    // Dragging anywhere outside the crop box scrolls the image.
    const startPan = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        panRef.current = { clientX: event.clientX, clientY: event.clientY };
    };

    const movePan = (event: ReactPointerEvent<HTMLDivElement>) => {
        const pan = panRef.current;
        const viewport = viewportRef.current;

        if (!pan || !viewport) {
            return;
        }

        viewport.scrollLeft -= event.clientX - pan.clientX;
        viewport.scrollTop -= event.clientY - pan.clientY;
        panRef.current = { clientX: event.clientX, clientY: event.clientY };
    };

    const endPan = () => {
        panRef.current = null;
    };

    const canvasWidth = baseWidth * zoom;
    const canvasHeight = (canvasWidth * imageHeight) / imageWidth;

    return (
        <div className="grid gap-3">
            <div
                ref={viewportRef}
                className="mx-auto max-h-[65dvh] w-full overflow-auto rounded-lg bg-[repeating-conic-gradient(#d4d4d4_0_25%,#f5f5f5_0_50%)] bg-[length:20px_20px]"
                style={{
                    aspectRatio: `${imageWidth} / ${imageHeight}`,
                    maxWidth: `calc(65dvh * ${imageWidth / imageHeight})`,
                }}
            >
                <div
                    className="relative cursor-grab touch-none select-none active:cursor-grabbing"
                    style={{ width: canvasWidth, height: canvasHeight }}
                    onPointerDown={startPan}
                    onPointerMove={movePan}
                    onPointerUp={endPan}
                    onPointerCancel={endPan}
                >
                    <img
                        src={url}
                        alt="Gambar yang dipotong"
                        draggable={false}
                        className="pointer-events-none absolute inset-0 size-full max-w-none object-fill"
                    />
                    <DraggableBox
                        spaceWidth={imageWidth}
                        spaceHeight={imageHeight}
                        box={crop}
                        onChange={setCrop}
                        minSize={Math.max(1, Math.round(imageWidth * 0.02))}
                        dimOutside
                    />
                </div>
            </div>
            <div className="flex items-center justify-center gap-3">
                <button
                    type="button"
                    onClick={() => zoomTo(zoom / 1.25)}
                    disabled={zoom <= 1}
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
                    value={zoom}
                    onChange={(event) => zoomTo(Number(event.target.value))}
                    aria-label="Ukuran gambar"
                    className="w-48 accent-tb-primary"
                />
                <button
                    type="button"
                    onClick={() => zoomTo(zoom * 1.25)}
                    disabled={zoom >= MAX_ZOOM}
                    aria-label="Perbesar gambar"
                    title="Perbesar gambar"
                    className="inline-flex size-9 items-center justify-center rounded-lg border border-tb-outline-variant text-tb-on-surface hover:bg-tb-surface-container disabled:opacity-40"
                >
                    <Plus className="size-4" />
                </button>
                <span className="w-12 text-sm text-tb-on-surface-variant tabular-nums">
                    {Math.round(zoom * 100)}%
                </span>
            </div>
            <p className="text-center text-xs text-tb-on-surface-variant">
                Perbesar/perkecil gambar dengan slider, tombol, atau Ctrl +
                scroll. Saat diperbesar, gunakan scroll bar atau geser gambar di
                luar kotak. Bagian di dalam kotak menjadi hasil potongan.
            </p>
        </div>
    );
}
