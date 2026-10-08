import { LoaderCircle, Maximize2, Minus, Plus } from 'lucide-react';
import { Component, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Preview = {
    node: HTMLElement;
    box: { x: number; y: number; width: number; height: number };
    contentWidth: number;
    contentHeight: number;
    backgroundColor: string;
};

type Props = {
    preview: Preview | null;
    paper: { width: number; height: number };
    treeScale: number;
    readingScale?: number;
    transparent: boolean;
    busy: boolean;
    current: boolean;
};

class PreviewBoundary extends Component<
    { children: ReactNode },
    { failed: boolean }
> {
    state = { failed: false };

    static getDerivedStateFromError() {
        return { failed: true };
    }

    render() {
        if (this.state.failed) {
            return (
                <div
                    role="alert"
                    className="rounded-lg border border-tb-outline-variant p-4 text-sm"
                >
                    Preview gagal ditampilkan. Tutup modal dan coba lagi.
                </div>
            );
        }

        return this.props.children;
    }
}

export function SnapshotPreview(props: Props) {
    return (
        <PreviewBoundary>
            <SnapshotPreviewContent {...props} />
        </PreviewBoundary>
    );
}

function SnapshotPreviewContent({
    preview,
    paper,
    treeScale,
    readingScale = 1,
    transparent,
    busy,
    current,
}: Props) {
    const viewport = useRef<HTMLDivElement>(null);
    const content = useRef<HTMLDivElement>(null);
    const [viewportWidth, setViewportWidth] = useState(600);
    const [viewportHeight, setViewportHeight] = useState(400);
    const [zoom, setZoom] = useState(1);
    const [readable, setReadable] = useState(false);

    useEffect(() => {
        const element = viewport.current;

        if (!element) {
            return;
        }

        const update = () => {
            setViewportWidth(element.clientWidth);
            setViewportHeight(element.clientHeight);
        };
        update();

        if (typeof ResizeObserver === 'undefined') {
            window.addEventListener('resize', update);

            return () => window.removeEventListener('resize', update);
        }

        const observer = new ResizeObserver(update);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const host = content.current;

        if (!host || !preview) {
            return;
        }

        const node = preview.node;
        Object.assign(node.style, {
            width: `${preview.contentWidth}px`,
            height: `${preview.contentHeight}px`,
            margin: '0',
            overflow: 'visible',
            maxHeight: 'none',
        });
        node.setAttribute('inert', '');
        host.replaceChildren(node);

        return () => host.replaceChildren();
    }, [preview]);

    const margin = Math.round(Math.min(paper.width, paper.height) * 0.04);
    const scale = preview
        ? Math.min(
              (paper.width - margin * 2) / preview.box.width,
              (paper.height - margin * 2) / preview.box.height,
          ) * treeScale
        : 1;
    const fitScale = Math.min(
        Math.max(1, viewportWidth - 32) / paper.width,
        Math.max(1, viewportHeight - 32) / paper.height,
    );
    const screenScale =
        (readable && preview ? readingScale / scale : fitScale) * zoom;
    const width = paper.width * screenScale;
    const height = paper.height * screenScale;

    const offsetX = preview
        ? (paper.width - preview.box.width * scale) / 2 - preview.box.x * scale
        : 0;
    const offsetY = preview
        ? (paper.height - preview.box.height * scale) / 2 -
          preview.box.y * scale
        : 0;

    useEffect(() => {
        const element = viewport.current;

        if (!element || !preview) {
            return;
        }

        const frame = requestAnimationFrame(() => {
            element.scrollLeft = Math.max(
                0,
                (element.scrollWidth - element.clientWidth) / 2,
            );
            element.scrollTop = readable
                ? Math.max(0, offsetY * screenScale - 16)
                : 0;
        });

        return () => cancelAnimationFrame(frame);
    }, [preview, readable, width, height, offsetY, screenScale]);

    return (
        <section className="grid gap-2" aria-label="Preview gambar pohon">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-medium">Preview gambar</h3>
                <div className="flex flex-wrap items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Zoom out preview"
                        disabled={!preview || zoom <= 0.25}
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
                        disabled={!preview || zoom >= 4}
                        onClick={() =>
                            setZoom((value) => Math.min(4, value + 0.25))
                        }
                    >
                        <Plus className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={!readable ? 'default' : 'outline'}
                        size="sm"
                        aria-pressed={!readable}
                        disabled={!preview}
                        onClick={() => {
                            setReadable(false);
                            setZoom(1);
                        }}
                    >
                        <Maximize2 className="size-4" />
                        Seluruh pohon
                    </Button>
                    <Button
                        type="button"
                        variant={readable ? 'default' : 'outline'}
                        size="sm"
                        aria-pressed={readable}
                        disabled={!preview}
                        onClick={() => {
                            setReadable(true);
                            setZoom(1);
                        }}
                    >
                        Ukuran baca
                    </Button>
                </div>
            </div>
            <div
                ref={viewport}
                className="relative h-[min(50dvh,32rem)] min-h-64 overflow-auto rounded-lg border border-tb-outline-variant bg-tb-surface-container"
                aria-busy={busy}
            >
                <div
                    className="grid place-items-center p-4"
                    style={{
                        width: Math.max(viewportWidth, width + 32),
                        minHeight: Math.max(viewportHeight, height + 32),
                    }}
                >
                    <div
                        className="relative overflow-hidden shadow-sm"
                        role="img"
                        aria-label="Preview seluruh pohon tarombo yang akan disimpan"
                        style={{
                            width,
                            height,
                            backgroundColor: transparent
                                ? 'white'
                                : preview?.backgroundColor,
                            backgroundImage: transparent
                                ? 'conic-gradient(#e5e7eb 25%, white 0 50%, #e5e7eb 0 75%, white 0)'
                                : undefined,
                            backgroundSize: '16px 16px',
                        }}
                    >
                        <div
                            ref={content}
                            aria-hidden="true"
                            className="pointer-events-none absolute top-0 left-0"
                            style={{
                                transformOrigin: 'top left',
                                transform: `translate(${offsetX * screenScale}px, ${offsetY * screenScale}px) scale(${scale * screenScale})`,
                            }}
                        />
                        {!preview && (
                            <div className="grid h-full place-items-center p-3 text-center text-sm text-tb-on-surface-variant">
                                {busy
                                    ? 'Menyiapkan preview seluruh ranting...'
                                    : 'Preview belum tersedia. Coba lagi preview.'}
                            </div>
                        )}
                    </div>
                </div>
                {busy && (
                    <div
                        className="sticky bottom-2 mx-auto flex w-fit items-center gap-2 rounded-lg bg-tb-surface-bright px-3 py-2 text-sm shadow"
                        role="status"
                    >
                        <LoaderCircle className="size-4 animate-spin" />
                        Memperbarui preview...
                    </div>
                )}
            </div>
            <p className="text-xs text-tb-on-surface-variant">
                {current
                    ? readable
                        ? 'Ukuran baca: geser preview untuk melihat ranting lainnya. Gambar yang disimpan tetap memuat seluruh pohon.'
                        : 'Seluruh pohon ditampilkan. Gunakan Ukuran baca untuk membaca nama anggota.'
                    : 'Preview diperbarui otomatis mengikuti pilihan pohon dan pengaturan.'}
            </p>
        </section>
    );
}
