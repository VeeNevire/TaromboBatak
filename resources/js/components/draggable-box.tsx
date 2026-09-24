import { useRef } from 'react';

export type Box = { x: number; y: number; width: number; height: number };

type Handle = 'move' | 'nw' | 'ne' | 'sw' | 'se';

const clamp = (value: number, min: number, max: number) =>
    Math.min(Math.max(value, min), Math.max(min, max));

/**
 * A move/resize box drawn over an element whose coordinate space is spaceWidth × spaceHeight.
 * Place it inside a relatively positioned container that shows that whole space.
 * With bounded={false} the box may grow past the space; minSize of it always stays inside.
 */
export function DraggableBox({
    spaceWidth,
    spaceHeight,
    box,
    onChange,
    lockAspect = false,
    dimOutside = false,
    minSize = 50,
    bounded = true,
}: {
    spaceWidth: number;
    spaceHeight: number;
    box: Box;
    onChange: (box: Box) => void;
    lockAspect?: boolean;
    dimOutside?: boolean;
    minSize?: number;
    bounded?: boolean;
}) {
    const layerRef = useRef<HTMLDivElement>(null);
    const dragRef = useRef<{
        handle: Handle;
        startX: number;
        startY: number;
        start: Box;
    } | null>(null);

    // Pointer deltas are in displayed pixels; the box is stored in space units.
    const toSpaceDelta = (dx: number, dy: number) => {
        const rect = layerRef.current?.getBoundingClientRect();

        if (!rect || rect.width === 0 || rect.height === 0) {
            return { dx: 0, dy: 0 };
        }

        return {
            dx: (dx * spaceWidth) / rect.width,
            dy: (dy * spaceHeight) / rect.height,
        };
    };

    const startDrag = (handle: Handle, event: React.PointerEvent) => {
        event.preventDefault();
        event.stopPropagation();
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
        dragRef.current = {
            handle,
            startX: event.clientX,
            startY: event.clientY,
            start: box,
        };
    };

    const onPointerMove = (event: React.PointerEvent) => {
        const drag = dragRef.current;

        if (!drag) {
            return;
        }

        const { dx, dy } = toSpaceDelta(
            event.clientX - drag.startX,
            event.clientY - drag.startY,
        );
        const { start } = drag;

        if (drag.handle === 'move') {
            onChange({
                ...start,
                x: Math.round(
                    bounded
                        ? clamp(start.x + dx, 0, spaceWidth - start.width)
                        : clamp(
                              start.x + dx,
                              minSize - start.width,
                              spaceWidth - minSize,
                          ),
                ),
                y: Math.round(
                    bounded
                        ? clamp(start.y + dy, 0, spaceHeight - start.height)
                        : clamp(
                              start.y + dy,
                              minSize - start.height,
                              spaceHeight - minSize,
                          ),
                ),
            });

            return;
        }

        const west = drag.handle.includes('w');
        const north = drag.handle.includes('n');
        // The corner opposite the dragged handle stays in place.
        const anchorX = west ? start.x + start.width : start.x;
        const anchorY = north ? start.y + start.height : start.y;
        const maxWidth = bounded
            ? west
                ? anchorX
                : spaceWidth - anchorX
            : Infinity;
        const maxHeight = bounded
            ? north
                ? anchorY
                : spaceHeight - anchorY
            : Infinity;
        let width = clamp(start.width + (west ? -dx : dx), minSize, maxWidth);
        let height = clamp(
            start.height + (north ? -dy : dy),
            minSize,
            maxHeight,
        );

        if (lockAspect) {
            const ratio = start.width / start.height;
            width = Math.min(width, maxHeight * ratio);
            height = width / ratio;
        }

        onChange({
            x: Math.round(west ? anchorX - width : anchorX),
            y: Math.round(north ? anchorY - height : anchorY),
            width: Math.round(width),
            height: Math.round(height),
        });
    };

    const endDrag = () => {
        dragRef.current = null;
    };

    const percent = (value: number, total: number) =>
        `${(value / total) * 100}%`;

    return (
        <div
            ref={layerRef}
            className={`absolute inset-0 touch-none select-none ${
                dimOutside ? 'overflow-hidden' : ''
            }`}
            onPointerMove={onPointerMove}
            onPointerUp={endDrag}
            onPointerCancel={endDrag}
        >
            <div
                className={`absolute cursor-move border-2 border-dashed border-tb-primary ${
                    dimOutside
                        ? 'bg-tb-primary/15 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]'
                        : 'hover:bg-tb-primary/10'
                }`}
                style={{
                    left: percent(box.x, spaceWidth),
                    top: percent(box.y, spaceHeight),
                    width: percent(box.width, spaceWidth),
                    height: percent(box.height, spaceHeight),
                }}
                onPointerDown={(event) => startDrag('move', event)}
            >
                {(['nw', 'ne', 'sw', 'se'] as const).map((handle) => (
                    <span
                        key={handle}
                        onPointerDown={(event) => startDrag(handle, event)}
                        className={`absolute size-4 rounded-full border-2 border-white bg-tb-primary shadow ${
                            handle.includes('n') ? '-top-2' : '-bottom-2'
                        } ${handle.includes('w') ? '-left-2' : '-right-2'} ${
                            handle === 'nw' || handle === 'se'
                                ? 'cursor-nwse-resize'
                                : 'cursor-nesw-resize'
                        }`}
                    />
                ))}
            </div>
        </div>
    );
}
