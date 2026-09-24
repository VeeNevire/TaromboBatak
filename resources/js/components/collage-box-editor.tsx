import { ImagePlus, X } from 'lucide-react';
import { type RefObject, useEffect, useRef } from 'react';
import {
    CANVAS_HEIGHT,
    CANVAS_WIDTH,
    type CollageFormat,
    renderCollage,
} from '@/lib/collage';

export function CollageBoxEditor({
    format,
    boxFiles,
    canvasRef,
    onSetBoxFile,
}: {
    format: CollageFormat;
    boxFiles: (File | null)[];
    canvasRef: RefObject<HTMLCanvasElement | null>;
    onSetBoxFile: (index: number, file: File | null) => void;
}) {
    const boxInputRefs = useRef<(HTMLInputElement | null)[]>([]);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const cancelledRef = { current: false };
        renderCollage(ctx, format, boxFiles, cancelledRef);

        return () => {
            cancelledRef.current = true;
        };
    }, [canvasRef, format, boxFiles]);

    return (
        <div className="relative aspect-video w-full overflow-hidden rounded-lg border border-tb-outline-variant">
            <canvas
                ref={canvasRef}
                width={CANVAS_WIDTH}
                height={CANVAS_HEIGHT}
                className="absolute inset-0 h-full w-full"
            />
            {format.boxes.map((box, index) => (
                <div
                    key={index}
                    className="absolute"
                    style={{
                        left: `${box.x * 100}%`,
                        top: `${box.y * 100}%`,
                        width: `${box.w * 100}%`,
                        height: `${box.h * 100}%`,
                    }}
                >
                    <button
                        type="button"
                        onClick={() => boxInputRefs.current[index]?.click()}
                        className="flex h-full w-full items-center justify-center bg-tb-on-surface/0 text-tb-surface-bright opacity-0 transition-opacity hover:bg-tb-primary/40 hover:opacity-100"
                    >
                        <ImagePlus className="size-6" />
                    </button>
                    {boxFiles[index] && (
                        <button
                            type="button"
                            onClick={(event) => {
                                event.stopPropagation();
                                onSetBoxFile(index, null);
                            }}
                            className="absolute top-1 right-1 rounded-full bg-tb-on-surface/70 p-1 text-tb-surface-bright hover:bg-tb-on-surface/90"
                        >
                            <X className="size-3" />
                        </button>
                    )}
                    <input
                        ref={(element) => {
                            boxInputRefs.current[index] = element;
                        }}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(event) =>
                            onSetBoxFile(
                                index,
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                </div>
            ))}
        </div>
    );
}
