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
    transparent,
    busy,
    current,
}: Props) {
    const viewport = useRef<HTMLDivElement>(null);
    const content = useRef<HTMLDivElement>(null);
    const [viewportWidth, setViewportWidth] = useState(600);
    const [zoom, setZoom] = useState(1);

    useEffect(() => {
        const element = viewport.current;

        if (!element) {
            return;
        }

        const update = () => setViewportWidth(element.clientWidth);
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

    const screenScale =
        Math.min(
            Math.max(1, viewportWidth - 32) / paper.width,
            288 / paper.height,
        ) * zoom;
    const width = paper.width * screenScale;
    const height = paper.height * screenScale;
    const margin = Math.round(Math.min(paper.width, paper.height) * 0.04);
    const scale = preview
        ? Math.min(
              (paper.width - margin * 2) / preview.box.width,
              (paper.height - margin * 2) / preview.box.height,
          ) * treeScale
        : 1;
    const offsetX = preview
        ? (paper.width - preview.box.width * scale) / 2 - preview.box.x * scale
        : 0;
    const offsetY = preview
        ? (paper.height - preview.box.height * scale) / 2 -
          preview.box.y * scale
        : 0;

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
                        variant="outline"
                        size="sm"
                        disabled={!preview}
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
                <div
                    className="grid place-items-center p-4"
                    style={{
                        width: Math.max(viewportWidth, width + 32),
                        minHeight: Math.max(318, height + 32),
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
                    ? 'Preview mengikuti tata letak gambar yang akan disimpan. Zoom hanya mengubah tampilan pemeriksaan.'
                    : 'Preview diperbarui otomatis mengikuti pilihan pohon dan pengaturan.'}
            </p>
        </section>
    );
}
