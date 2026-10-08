import { LoaderCircle, Maximize2, Minus, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

export function SnapshotPreview({
    url,
    busy,
    current,
}: {
    url?: string;
    busy: boolean;
    current: boolean;
}) {
    const viewport = useRef<HTMLDivElement>(null);
    const [viewportWidth, setViewportWidth] = useState(600);
    const [size, setSize] = useState({ width: 1, height: 1 });
    const [zoom, setZoom] = useState(1);
    const [failedUrl, setFailedUrl] = useState<string | undefined>();
    const failed = !!url && failedUrl === url;

    useEffect(() => {
        const element = viewport.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(() =>
            setViewportWidth(element.clientWidth),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const fit = Math.min((viewportWidth - 32) / size.width, 288 / size.height);
    const width = Math.max(1, size.width * fit * zoom);
    const height = Math.max(1, size.height * fit * zoom);

    return (
        <section className="grid gap-2" aria-label="Preview gambar pohon">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-medium">Preview gambar</h3>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Zoom out preview"
                        disabled={!url || zoom <= 0.25}
                        onClick={() =>
                            setZoom((value) => Math.max(0.25, value - 0.25))
                        }
                    >
                        <Minus className="size-4" />
                    </Button>
                    <span className="w-12 text-center text-xs">
                        {Math.round(zoom * 100)}%
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Zoom in preview"
                        disabled={!url || zoom >= 4}
                        onClick={() =>
                            setZoom((value) => Math.min(4, value + 0.25))
                        }
                    >
                        <Plus className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!url}
                        onClick={() => setZoom(1)}
                    >
                        <Maximize2 className="size-4" />
                        Pas ke layar
                    </Button>
                </div>
            </div>
            <div
                ref={viewport}
                className="relative h-80 overflow-auto rounded-lg border border-tb-outline-variant bg-tb-surface-container"
                aria-busy={busy}
            >
                {url && !failed ? (
                    <div
                        className="grid place-items-center p-4"
                        style={{
                            width: Math.max(viewportWidth, width + 32),
                            minHeight: Math.max(318, height + 32),
                        }}
                    >
                        <img
                            src={url}
                            alt="Preview seluruh pohon tarombo yang akan disimpan"
                            draggable={false}
                            onLoad={(event) =>
                                setSize({
                                    width: event.currentTarget.naturalWidth,
                                    height: event.currentTarget.naturalHeight,
                                })
                            }
                            onError={() => setFailedUrl(url)}
                            className="block shrink-0 bg-white shadow-sm"
                            style={{
                                width,
                                height,
                                maxWidth: 'none',
                                backgroundImage:
                                    'conic-gradient(#e5e7eb 25%, white 0 50%, #e5e7eb 0 75%, white 0)',
                                backgroundSize: '16px 16px',
                            }}
                        />
                    </div>
                ) : (
                    <div className="grid h-full place-items-center px-4 text-center text-sm text-tb-on-surface-variant">
                        {failed
                            ? 'Preview tidak dapat ditampilkan. Coba lagi preview.'
                            : busy
                              ? 'Menyiapkan preview seluruh ranting...'
                              : 'Preview seluruh pohon akan tampil otomatis setelah pilihan berubah.'}
                    </div>
                )}
                {busy && (
                    <div
                        className="sticky bottom-2 mx-auto flex w-fit items-center gap-2 rounded-lg bg-tb-surface-bright px-3 py-2 text-sm shadow"
                        role="status"
                    >
                        <LoaderCircle className="size-4 animate-spin" />
                        Memproses gambar...
                    </div>
                )}
            </div>
            {url && (
                <p className="text-xs text-tb-on-surface-variant">
                    {current
                        ? 'Gambar ini yang akan disimpan. Zoom preview hanya mengubah tampilan pemeriksaan.'
                        : 'Preview diperbarui otomatis mengikuti pilihan pohon dan pengaturan.'}
                </p>
            )}
        </section>
    );
}
