import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { CollageBoxEditor } from '@/components/collage-box-editor';
import { FormatThumbnail } from '@/components/format-thumbnail';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { COLLAGE_FORMATS, composeCanvasToFile } from '@/lib/collage';
import { dashboard } from '@/routes';
import taromboFrames from '@/routes/tarombo-frames';

type Frame = {
    id: number;
    name: string;
    image_url: string;
    canvas_width: number;
    canvas_height: number;
    is_active: boolean;
};

const emptyForm = {
    name: '',
    image: null as File | null,
    is_active: true,
};

export default function TaromboFrames({ frames }: { frames: Frame[] }) {
    const [editingFrame, setEditingFrame] = useState<Frame | null | 'create'>(
        null,
    );
    const [upscaleDialogOpen, setUpscaleDialogOpen] = useState(false);
    const [upscalingId, setUpscalingId] = useState<number | null>(null);
    const [brokenImageIds, setBrokenImageIds] = useState<Set<number>>(
        new Set(),
    );
    const markImageBroken = (id: number) =>
        setBrokenImageIds((current) => new Set(current).add(id));
    const [useCollage, setUseCollage] = useState(true);
    const [formatId, setFormatId] = useState(COLLAGE_FORMATS[0].id);
    const [boxFiles, setBoxFiles] = useState<(File | null)[]>([null]);
    const [collageError, setCollageError] = useState<string | null>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const form = useForm(emptyForm);

    const currentFormat =
        COLLAGE_FORMATS.find((format) => format.id === formatId) ??
        COLLAGE_FORMATS[0];

    const startFormat = (id: string) => {
        const format =
            COLLAGE_FORMATS.find((item) => item.id === id) ??
            COLLAGE_FORMATS[0];
        form.reset();
        form.clearErrors();
        setUseCollage(true);
        setFormatId(format.id);
        setBoxFiles(new Array(format.boxes.length).fill(null));
        setCollageError(null);
        setEditingFrame('create');
    };

    const openCreate = () => startFormat(COLLAGE_FORMATS[0].id);

    const openEdit = (frame: Frame) => {
        form.setData({
            name: frame.name,
            image: null,
            is_active: frame.is_active,
        });
        form.clearErrors();
        setUseCollage(false);
        setFormatId(COLLAGE_FORMATS[0].id);
        setBoxFiles(new Array(COLLAGE_FORMATS[0].boxes.length).fill(null));
        setCollageError(null);
        setEditingFrame(frame);
    };

    const changeFormat = (id: string) => {
        const format = COLLAGE_FORMATS.find((item) => item.id === id);
        setFormatId(id);
        setBoxFiles(new Array(format?.boxes.length ?? 1).fill(null));
        setCollageError(null);
    };

    const setBoxFile = (index: number, file: File | null) => {
        setBoxFiles((current) => {
            const next = [...current];
            next[index] = file;
            return next;
        });
        setCollageError(null);
    };

    const save = async (event: React.FormEvent) => {
        event.preventDefault();

        let imageFile: File | null = null;

        if (useCollage) {
            if (boxFiles.some((file) => !file)) {
                setCollageError('Isi semua kotak dengan gambar terlebih dahulu.');
                return;
            }

            imageFile = canvasRef.current
                ? await composeCanvasToFile(canvasRef.current)
                : null;
            if (!imageFile) {
                setCollageError('Gagal membuat gambar kolase, coba lagi.');
                return;
            }
        }

        if (editingFrame === 'create') {
            form.transform((data) => ({ ...data, image: imageFile }));
            form.post(taromboFrames.store().url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setEditingFrame(null),
            });

            return;
        }

        if (editingFrame) {
            form.transform((data) => ({
                ...data,
                image: imageFile,
                _method: 'put',
            }));
            form.post(taromboFrames.update(editingFrame.id).url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setEditingFrame(null),
            });
        }
    };

    const remove = (frame: Frame) => {
        if (!window.confirm(`Hapus template frame ${frame.name}?`)) {
            return;
        }

        router.delete(taromboFrames.destroy(frame.id).url, {
            preserveScroll: true,
        });
    };

    const upscaleFrame = (frame: Frame) => {
        if (upscalingId !== null) {
            return;
        }

        setUpscalingId(frame.id);
        router.post(
            taromboFrames.upscale(frame.id).url,
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    toast.error(
                        errors.frame ?? 'Resolusi frame gagal ditingkatkan.',
                    );
                },
                onFinish: () => setUpscalingId(null),
            },
        );
    };

    return (
        <>
            <Head title="Template Frame Tarombo" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Template Frame Tarombo
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Kelola frame JPG. AI akan menganalisis area konten
                            pada setiap frame saat gambar dibuat.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setUpscaleDialogOpen(true)}
                        >
                            <Sparkles className="size-4" /> Tingkatkan Resolusi
                        </Button>
                        <Button
                            onClick={openCreate}
                            className="bg-tb-primary hover:bg-tb-primary-light"
                        >
                            <Plus className="size-4" /> Tambah Frame
                        </Button>
                    </div>
                </div>

                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {frames.map((frame) => (
                        <Card
                            key={frame.id}
                            className="overflow-hidden border-tb-outline-variant bg-tb-surface-bright"
                        >
                            <img
                                src={frame.image_url}
                                alt={frame.name}
                                onError={() => markImageBroken(frame.id)}
                                className="aspect-video w-full bg-tb-surface-container object-contain"
                            />
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-tb-on-surface">
                                            {frame.name}
                                        </p>
                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                            Kanvas {frame.canvas_width} ×{' '}
                                            {frame.canvas_height} · dianalisis
                                            otomatis oleh AI
                                        </p>
                                        {brokenImageIds.has(frame.id) && (
                                            <p className="mt-1 text-xs font-medium text-red-600">
                                                Gambar tidak ditemukan di
                                                server. Klik Ubah untuk
                                                mengganti gambarnya.
                                            </p>
                                        )}
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className={
                                            frame.is_active
                                                ? 'border-emerald-300 text-emerald-700'
                                                : ''
                                        }
                                    >
                                        {frame.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </div>
                                <div className="mt-4 flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => openEdit(frame)}
                                    >
                                        <Pencil className="size-3.5" /> Ubah
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => remove(frame)}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <Trash2 className="size-3.5" /> Hapus
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                    {frames.length === 0 && (
                        <Card className="col-span-full border-dashed border-tb-outline-variant bg-tb-surface-bright">
                            <CardContent className="py-12 text-center text-sm text-tb-on-surface-variant">
                                Belum ada template frame.
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="rounded-xl border border-tb-outline-variant bg-tb-surface-bright p-5 md:p-6">
                    <h2 className="font-display text-lg font-bold text-tb-primary md:text-xl">
                        Template Format Frame
                    </h2>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Pilih format kolase, lalu isi tiap kotak dengan gambar
                        untuk membuat frame baru.
                    </p>
                    <div className="mt-5 grid max-w-xl grid-cols-2 gap-6">
                        {COLLAGE_FORMATS.map((format) => (
                            <button
                                key={format.id}
                                type="button"
                                onClick={() => startFormat(format.id)}
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
                </div>
            </div>

            <Dialog
                open={editingFrame !== null}
                onOpenChange={(open) => !open && setEditingFrame(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                    <form onSubmit={save} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle>
                                {editingFrame === 'create'
                                    ? 'Tambah Frame Tarombo'
                                    : 'Ubah Frame Tarombo'}
                            </DialogTitle>
                            <DialogDescription>
                                Pilih Format Frame (kolase), lalu isi tiap
                                kotak dengan gambar. AI akan mengenali area
                                yang tepat untuk gambar Tarombo pada template
                                hasil kolase.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="frame-name">Nama frame</Label>
                                <Input
                                    id="frame-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            {editingFrame !== 'create' && !useCollage && (
                                <div className="grid gap-2">
                                    <Label>Gambar frame saat ini</Label>
                                    <div className="flex items-center gap-3">
                                        <img
                                            src={
                                                (editingFrame as Frame)
                                                    ?.image_url
                                            }
                                            alt="Frame saat ini"
                                            className="h-20 w-32 rounded-lg object-cover"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setUseCollage(true)}
                                        >
                                            Buat kolase baru
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {useCollage && (
                                <div className="grid gap-3">
                                    <div className="grid gap-1.5">
                                        <Label>Format Frame</Label>
                                        <div className="flex flex-wrap gap-2">
                                            {COLLAGE_FORMATS.map((format) => (
                                                <button
                                                    key={format.id}
                                                    type="button"
                                                    onClick={() =>
                                                        changeFormat(format.id)
                                                    }
                                                    className={`flex flex-col items-center gap-1.5 rounded-lg border p-2 text-xs transition-colors ${
                                                        format.id === formatId
                                                            ? 'border-tb-primary bg-tb-primary/5 text-tb-primary'
                                                            : 'border-tb-outline-variant text-tb-on-surface-variant hover:border-tb-primary/50'
                                                    }`}
                                                >
                                                    <FormatThumbnail
                                                        boxes={format.boxes}
                                                        className="h-8 w-12"
                                                    />
                                                    {format.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label>Isi tiap kotak</Label>
                                        <CollageBoxEditor
                                            format={currentFormat}
                                            boxFiles={boxFiles}
                                            canvasRef={canvasRef}
                                            onSetBoxFile={setBoxFile}
                                        />
                                        {collageError && (
                                            <p className="text-xs text-red-600">
                                                {collageError}
                                            </p>
                                        )}
                                        <InputError message={form.errors.image} />
                                    </div>
                                </div>
                            )}

                            <label className="flex items-center gap-2 text-sm text-tb-on-surface">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_active',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Frame aktif dan dapat dipilih pengguna
                            </label>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingFrame(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-tb-primary hover:bg-tb-primary-light"
                            >
                                {form.processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={upscaleDialogOpen}
                onOpenChange={(open) => {
                    if (!open && upscalingId !== null) {
                        return;
                    }

                    setUpscaleDialogOpen(open);
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Tingkatkan Resolusi Frame</DialogTitle>
                        <DialogDescription>
                            Pilih template frame yang ingin ditingkatkan
                            resolusinya. AI akan mempertajam detail ornamen
                            tanpa mengubah desain, lalu menggantikan gambar
                            frame yang lama.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        {frames.map((frame) => (
                            <div
                                key={frame.id}
                                className="overflow-hidden rounded-xl border border-tb-outline-variant"
                            >
                                <img
                                    src={frame.image_url}
                                    alt={frame.name}
                                    onError={() => markImageBroken(frame.id)}
                                    className="aspect-video w-full bg-tb-surface-container object-contain"
                                />
                                <div className="flex items-center justify-between gap-2 p-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-tb-on-surface">
                                            {frame.name}
                                        </p>
                                        {brokenImageIds.has(frame.id) ? (
                                            <p className="text-xs font-medium text-red-600">
                                                Gambar tidak ditemukan
                                            </p>
                                        ) : (
                                            <p className="text-xs text-tb-on-surface-variant">
                                                {frame.canvas_width} ×{' '}
                                                {frame.canvas_height}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={
                                            upscalingId !== null ||
                                            brokenImageIds.has(frame.id)
                                        }
                                        onClick={() => upscaleFrame(frame)}
                                        className="shrink-0 bg-tb-primary hover:bg-tb-primary-light"
                                    >
                                        {upscalingId === frame.id ? (
                                            <>
                                                <LoaderCircle className="size-3.5 animate-spin" />
                                                Proses...
                                            </>
                                        ) : (
                                            <>
                                                <Sparkles className="size-3.5" />
                                                Tingkatkan
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {frames.length === 0 && (
                            <p className="col-span-full py-6 text-center text-sm text-tb-on-surface-variant">
                                Belum ada template frame untuk ditingkatkan.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

TaromboFrames.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Template Frame Tarombo', href: taromboFrames.index() },
    ],
};
