import { Download } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CollageBoxEditor } from '@/components/collage-box-editor';
import { FormatThumbnail } from '@/components/format-thumbnail';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { COLLAGE_FORMATS, composeCanvasToFile } from '@/lib/collage';
import type { CollageFormat } from '@/lib/collage';

export function CollageDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const [collageFormat, setCollageFormat] = useState<CollageFormat | null>(
        null,
    );
    const [collageBoxFiles, setCollageBoxFiles] = useState<(File | null)[]>([]);
    const [collageError, setCollageError] = useState<string | null>(null);
    const [collagePreviewUrl, setCollagePreviewUrl] = useState<string | null>(
        null,
    );
    const [showPreview, setShowPreview] = useState(false);
    const collageCanvasRef = useRef<HTMLCanvasElement>(null);
    const step =
        showPreview && collagePreviewUrl
            ? 'preview'
            : collageFormat
              ? 'boxes'
              : 'format';

    useEffect(() => {
        return () => {
            if (collagePreviewUrl) {
                URL.revokeObjectURL(collagePreviewUrl);
            }
        };
    }, [collagePreviewUrl]);

    const closeCollage = () => {
        setCollageFormat(null);
        setCollageBoxFiles([]);
        setCollageError(null);
        setShowPreview(false);

        if (collagePreviewUrl) {
            URL.revokeObjectURL(collagePreviewUrl);
        }

        setCollagePreviewUrl(null);
        onClose();
    };

    const pickCollageFormat = (format: CollageFormat) => {
        setCollageFormat(format);
        setCollageBoxFiles(new Array(format.boxes.length).fill(null));
        setCollageError(null);
    };

    const setCollageBoxFile = (index: number, file: File | null) => {
        setCollageBoxFiles((current) => {
            const next = [...current];
            next[index] = file;

            return next;
        });
        setCollageError(null);
    };

    const previewCollage = async () => {
        if (!collageFormat) {
            return;
        }

        if (collageBoxFiles.some((file) => !file)) {
            setCollageError('Isi semua kotak dengan gambar terlebih dahulu.');

            return;
        }

        const file = collageCanvasRef.current
            ? await composeCanvasToFile(
                  collageCanvasRef.current,
                  'kolase-tarombo.jpg',
              )
            : null;

        if (!file) {
            setCollageError('Gagal membuat gambar kolase, coba lagi.');

            return;
        }

        if (collagePreviewUrl) {
            URL.revokeObjectURL(collagePreviewUrl);
        }

        setCollagePreviewUrl(URL.createObjectURL(file));
        setShowPreview(true);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => !isOpen && closeCollage()}
        >
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {step === 'format' && 'Pilih Format Frame'}
                        {step === 'boxes' && 'Isi Kotak dengan Gambar'}
                        {step === 'preview' && 'Pratinjau Kolase'}
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'format' &&
                            'Pilih salah satu format untuk membuat kolase gambar.'}
                        {step === 'boxes' &&
                            'Klik tiap kotak untuk memasukkan gambar, lalu lihat pratinjaunya.'}
                        {step === 'preview' &&
                            'Kolase siap. Unduh sebagai satu file gambar.'}
                    </DialogDescription>
                </DialogHeader>

                {step === 'format' && (
                    <div className="grid max-w-xl grid-cols-2 gap-6">
                        {COLLAGE_FORMATS.map((format) => (
                            <button
                                key={format.id}
                                type="button"
                                onClick={() => pickCollageFormat(format)}
                                className="group flex flex-col items-center gap-2 text-tb-primary"
                            >
                                <FormatThumbnail
                                    boxes={format.boxes}
                                    className="aspect-video w-full transition-transform group-hover:scale-[1.02]"
                                />
                                <span className="text-sm font-bold">
                                    {format.label}
                                </span>
                            </button>
                        ))}
                    </div>
                )}

                {step === 'boxes' && collageFormat && (
                    <div className="grid gap-3">
                        <CollageBoxEditor
                            format={collageFormat}
                            boxFiles={collageBoxFiles}
                            canvasRef={collageCanvasRef}
                            onSetBoxFile={setCollageBoxFile}
                        />
                        {collageError && (
                            <p className="text-xs text-red-600">
                                {collageError}
                            </p>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCollageFormat(null)}
                            >
                                Ganti Format
                            </Button>
                            <Button
                                type="button"
                                onClick={previewCollage}
                                className="bg-tb-primary hover:bg-tb-primary-light"
                            >
                                Lihat Pratinjau
                            </Button>
                        </DialogFooter>
                    </div>
                )}

                {step === 'preview' && collagePreviewUrl && (
                    <div className="grid gap-4">
                        <img
                            src={collagePreviewUrl}
                            alt="Pratinjau kolase"
                            className="w-full rounded-lg border border-tb-outline-variant"
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowPreview(false)}
                            >
                                Ubah Lagi
                            </Button>
                            <Button
                                asChild
                                className="bg-tb-primary hover:bg-tb-primary-light"
                            >
                                <a
                                    href={collagePreviewUrl}
                                    download="kolase-tarombo.jpg"
                                    onClick={() =>
                                        setTimeout(closeCollage, 100)
                                    }
                                >
                                    <Download className="size-4" /> Unduh Gambar
                                </a>
                            </Button>
                        </DialogFooter>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
