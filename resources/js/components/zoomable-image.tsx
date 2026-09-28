import { Minus, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

const MIN_SCALE = 1;
const MAX_SCALE = 8;
const STEP = 1.25;

type View = { scale: number; x: number; y: number };

const FIT: View = { scale: 1, x: 0, y: 0 };

/**
 * An image that fits its box and can be zoomed with the mouse wheel (around
 * the cursor), the +/- buttons or a double click, and panned by dragging.
 */
export function ZoomableImage({
    src,
    alt,
    className,
    children,
}: {
    src: string;
    alt: string;
    className?: string;
    /** Overlays drawn above the image that do not zoom (e.g. a watermark). */
    children?: ReactNode;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const dragRef = useRef<{ pointerX: number; pointerY: number } | null>(null);
    const [view, setView] = useState<View>(FIT);
    const [dragging, setDragging] = useState(false);

    // Keep the image from being dragged out of the box.
    const clamp = (next: View): View => {
        const container = containerRef.current;

        if (!container || next.scale <= MIN_SCALE) {
            return FIT;
        }

        const maxX = ((next.scale - 1) * container.clientWidth) / 2;
        const maxY = ((next.scale - 1) * container.clientHeight) / 2;

        return {
            scale: next.scale,
            x: Math.min(maxX, Math.max(-maxX, next.x)),
            y: Math.min(maxY, Math.max(-maxY, next.y)),
        };
    };

    // Zoom so the point under (clientX, clientY) stays where it is; without a
    // point the centre of the box is kept.
    const zoomAround = (
        current: View,
        scale: number,
        clientX?: number,
        clientY?: number,
    ): View => {
        const container = containerRef.current;

        if (!container) {
            return current;
        }

        const nextScale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale));
        const rect = container.getBoundingClientRect();
        const pointX =
            clientX === undefined ? 0 : clientX - rect.left - rect.width / 2;
        const pointY =
            clientY === undefined ? 0 : clientY - rect.top - rect.height / 2;
        const ratio = nextScale / current.scale;

        return clamp({
            scale: nextScale,
            x: pointX - (pointX - current.x) * ratio,
            y: pointY - (pointY - current.y) * ratio,
        });
    };

    const zoomAroundRef = useRef(zoomAround);

    useEffect(() => {
        zoomAroundRef.current = zoomAround;
    });

    // React's wheel listener is passive, so the page would scroll as well.
    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const handleWheel = (event: WheelEvent) => {
            event.preventDefault();
            const factor = event.deltaY < 0 ? 1.15 : 1 / 1.15;

            setView((current) =>
                zoomAroundRef.current(
                    current,
                    current.scale * factor,
                    event.clientX,
                    event.clientY,
                ),
            );
        };

        container.addEventListener('wheel', handleWheel, { passive: false });

        return () => container.removeEventListener('wheel', handleWheel);
    }, []);

    const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (view.scale <= MIN_SCALE || event.button !== 0) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        dragRef.current = { pointerX: event.clientX, pointerY: event.clientY };
        setDragging(true);
    };

    const handlePointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;

        if (!drag) {
            return;
        }

        const deltaX = event.clientX - drag.pointerX;
        const deltaY = event.clientY - drag.pointerY;

        dragRef.current = { pointerX: event.clientX, pointerY: event.clientY };
        setView((current) =>
            clamp({ ...current, x: current.x + deltaX, y: current.y + deltaY }),
        );
    };

    const endDrag = () => {
        dragRef.current = null;
        setDragging(false);
    };

    const zoomed = view.scale > MIN_SCALE;

    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-xl bg-tb-surface-container select-none',
                className,
            )}
        >
            <div
                ref={containerRef}
                role="img"
                aria-label={alt}
                onPointerDown={handlePointerDown}
                onPointerMove={handlePointerMove}
                onPointerUp={endDrag}
                onPointerCancel={endDrag}
                onDoubleClick={(event) =>
                    setView((current) =>
                        current.scale > MIN_SCALE
                            ? FIT
                            : zoomAround(
                                  current,
                                  2.5,
                                  event.clientX,
                                  event.clientY,
                              ),
                    )
                }
                onDragStart={(event) => event.preventDefault()}
                className={cn(
                    'flex h-full w-full touch-none items-center justify-center',
                    zoomed
                        ? dragging
                            ? 'cursor-grabbing'
                            : 'cursor-grab'
                        : 'cursor-zoom-in',
                )}
            >
                <img
                    src={src}
                    alt=""
                    draggable={false}
                    className="pointer-events-none max-h-full max-w-full object-contain select-none"
                    style={{
                        transform: `translate(${view.x}px, ${view.y}px) scale(${view.scale})`,
                        transition: dragging
                            ? 'none'
                            : 'transform 120ms ease-out',
                    }}
                />
            </div>
            {children}
            <div className="absolute bottom-3 left-3 z-10 flex items-center overflow-hidden rounded-lg border border-tb-outline-variant bg-tb-surface-bright/95 shadow-md backdrop-blur">
                <button
                    type="button"
                    onClick={() =>
                        setView((current) =>
                            zoomAround(current, current.scale / STEP),
                        )
                    }
                    disabled={!zoomed}
                    aria-label="Perkecil gambar"
                    title="Perkecil"
                    className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container disabled:opacity-40"
                >
                    <Minus className="size-4" />
                </button>
                <button
                    type="button"
                    onClick={() => setView(FIT)}
                    aria-label="Kembalikan ukuran gambar"
                    title="Pas ke layar"
                    className="h-9 min-w-14 border-x border-tb-outline-variant px-2 text-xs font-medium text-tb-on-surface-variant tabular-nums transition-colors hover:bg-tb-surface-container"
                >
                    {Math.round(view.scale * 100)}%
                </button>
                <button
                    type="button"
                    onClick={() =>
                        setView((current) =>
                            zoomAround(current, current.scale * STEP),
                        )
                    }
                    disabled={view.scale >= MAX_SCALE}
                    aria-label="Perbesar gambar"
                    title="Perbesar"
                    className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container disabled:opacity-40"
                >
                    <Plus className="size-4" />
                </button>
            </div>
        </div>
    );
}
