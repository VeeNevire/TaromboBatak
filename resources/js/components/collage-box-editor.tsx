import { ImagePlus, LoaderCircle, X } from 'lucide-react';
import { type RefObject, useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    CANVAS_HEIGHT,
    CANVAS_WIDTH,
    type CollageFormat,
    renderCollage,
} from '@/lib/collage';

type FrameOption = {
    id: number;
    name: string;
    image_url: string;
};

export function CollageBoxEditor({
    format,
    boxFiles,
    canvasRef,
    onSetBoxFile,
    frameOptions,
}: {
    format: CollageFormat;
    boxFiles: (File | null)[];
    canvasRef: RefObject<HTMLCanvasElement | null>;
    onSetBoxFile: (index: number, file: File | null) => void;
    frameOptions?: FrameOption[];
}) {
    const boxInputRefs = useRef<(HTMLInputElement | null)[]>([]);
    const [selectedBox, setSelectedBox] = useState<number | null>(null);
    const [loadingFrameId, setLoadingFrameId] = useState<number | null>(null);
    const [frameError, setFrameError] = useState<string | null>(null);

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

    const chooseFrame = async (frame: FrameOption) => {
        if (selectedBox === null) {
            return;
        }

        setLoadingFrameId(frame.id);
        setFrameError(null);

        try {
            const response = await fetch(frame.image_url);

            if (!response.ok) {
                throw new Error('Gambar frame tidak dapat dimuat.');
            }

            const image = await response.blob();
            onSetBoxFile(
                selectedBox,
                new File([image], `${frame.name}.jpg`, {
                    type: image.type || 'image/jpeg',
                }),
            );
            setSelectedBox(null);
        } catch {
            setFrameError('Gambar frame tidak dapat dimuat. Coba lagi.');
        } finally {
            setLoadingFrameId(null);
        }
    };

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
                        onClick={() =>
                            frameOptions
                                ? setSelectedBox(index)
                                : boxInputRefs.current[index]?.click()
                        }
                        className={`flex h-full w-full flex-col items-center justify-center gap-1 bg-tb-primary/10 text-tb-primary transition-colors hover:bg-tb-primary/40 hover:text-tb-surface-bright ${
                            boxFiles[index]
                                ? 'opacity-0 hover:opacity-100'
                                : 'opacity-100'
                        }`}
                    >
                        <ImagePlus className="size-6" />
                        {!boxFiles[index] && (
                            <span className="text-xs font-medium">
                                Pilih frame
                            </span>
                        )}
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
                            onSetBoxFile(index, event.target.files?.[0] ?? null)
                        }
                    />
                </div>
            ))}
            {frameOptions && (
                <Dialog
                    open={selectedBox !== null}
                    onOpenChange={(open) => !open && setSelectedBox(null)}
                >
                    <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>Pilih Template Frame</DialogTitle>
                            <DialogDescription>
                                Pilih gambar frame biasa untuk mengisi kotak
                                ini.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                            {frameOptions.map((frame) => (
                                <button
                                    key={frame.id}
                                    type="button"
                                    disabled={loadingFrameId !== null}
                                    onClick={() => chooseFrame(frame)}
                                    className="overflow-hidden rounded-xl border border-tb-outline-variant text-left transition-colors hover:border-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:outline-none disabled:cursor-wait disabled:opacity-60"
                                >
                                    <div className="relative aspect-video bg-tb-surface-container">
                                        <img
                                            src={frame.image_url}
                                            alt={frame.name}
                                            className="size-full object-contain"
                                        />
                                        {loadingFrameId === frame.id && (
                                            <div className="absolute inset-0 flex items-center justify-center bg-tb-surface/80">
                                                <LoaderCircle className="size-5 animate-spin text-tb-primary" />
                                            </div>
                                        )}
                                    </div>
                                    <p className="truncate px-3 py-2 text-sm font-medium text-tb-on-surface">
                                        {frame.name}
                                    </p>
                                </button>
                            ))}
                            {frameOptions.length === 0 && (
                                <p className="col-span-full py-6 text-center text-sm text-tb-on-surface-variant">
                                    Belum ada template frame biasa yang aktif.
                                </p>
                            )}
                        </div>
                        {frameError && (
                            <p className="text-sm text-red-600">{frameError}</p>
                        )}
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
