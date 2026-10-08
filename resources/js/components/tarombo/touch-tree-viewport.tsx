import { useEffect, useRef } from 'react';
import type { ReactNode, TouchEvent } from 'react';

export function TouchTreeViewport({
    children,
    zoom,
    onZoom,
    capture = false,
}: {
    children: ReactNode;
    zoom: number;
    onZoom: (zoom: number) => void;
    capture?: boolean;
}) {
    const gesture = useRef<{ distance: number; zoom: number } | null>(null);
    const viewport = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const element = viewport.current;
        const preventPageZoom = (event: globalThis.TouchEvent) => {
            if (event.touches.length === 2 && event.cancelable) {
                event.preventDefault();
            }
        };
        element?.addEventListener('touchmove', preventPageZoom, {
            passive: false,
        });

        return () => element?.removeEventListener('touchmove', preventPageZoom);
    }, []);
    const distance = (event: TouchEvent<HTMLDivElement>) =>
        Math.hypot(
            event.touches[0].clientX - event.touches[1].clientX,
            event.touches[0].clientY - event.touches[1].clientY,
        );

    return (
        <div
            ref={viewport}
            className={
                capture
                    ? 'max-h-none w-full min-w-0 overflow-visible overscroll-contain'
                    : 'max-h-[70dvh] w-full min-w-0 overflow-auto overscroll-contain'
            }
            style={{ touchAction: 'pan-x pan-y' }}
            onTouchStart={(event) => {
                if (event.touches.length === 2) {
                    gesture.current = { distance: distance(event), zoom };
                }
            }}
            onTouchMove={(event) => {
                if (event.touches.length === 2 && gesture.current) {
                    onZoom(
                        Math.max(
                            0.2,
                            Math.min(
                                3,
                                (gesture.current.zoom * distance(event)) /
                                    Math.max(1, gesture.current.distance),
                            ),
                        ),
                    );
                }
            }}
            onTouchEnd={() => {
                gesture.current = null;
            }}
            onTouchCancel={() => {
                gesture.current = null;
            }}
        >
            {children}
        </div>
    );
}
