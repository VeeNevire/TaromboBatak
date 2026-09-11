import { Head, router, useForm } from '@inertiajs/react';
import { ImagePlus, Pencil, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
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
    const imageInputRef = useRef<HTMLInputElement>(null);
    const form = useForm(emptyForm);
    const preview = form.data.image
        ? URL.createObjectURL(form.data.image)
        : editingFrame && editingFrame !== 'create'
          ? editingFrame.image_url
          : null;

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditingFrame('create');
    };

    const openEdit = (frame: Frame) => {
        form.setData({
            name: frame.name,
            image: null,
            is_active: frame.is_active,
        });
        form.clearErrors();
        setEditingFrame(frame);
    };

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        if (editingFrame === 'create') {
            form.post(taromboFrames.store().url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setEditingFrame(null),
            });

            return;
        }

        if (editingFrame) {
            form.transform((data) => ({ ...data, _method: 'put' }));
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
                    <Button
                        onClick={openCreate}
                        className="bg-tb-primary hover:bg-tb-primary-light"
                    >
                        <Plus className="size-4" /> Tambah Frame
                    </Button>
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
            </div>

            <Dialog
                open={editingFrame !== null}
                onOpenChange={(open) => !open && setEditingFrame(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    <form onSubmit={save} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle>
                                {editingFrame === 'create'
                                    ? 'Tambah Frame Tarombo'
                                    : 'Ubah Frame Tarombo'}
                            </DialogTitle>
                            <DialogDescription>
                                Upload frame JPG. AI akan mengenali area yang
                                tepat untuk gambar Tarombo pada setiap template.
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
                            <div className="grid gap-2">
                                <Label>
                                    File JPG frame{' '}
                                    {editingFrame === 'create'
                                        ? ''
                                        : '(kosongkan bila tidak diganti)'}
                                </Label>
                                <div className="flex items-center gap-3">
                                    {preview ? (
                                        <img
                                            src={preview}
                                            alt="Pratinjau frame"
                                            className="h-16 w-24 rounded-lg object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-16 w-24 items-center justify-center rounded-lg bg-tb-surface-container">
                                            <ImagePlus className="size-5 text-tb-on-surface-variant" />
                                        </div>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            imageInputRef.current?.click()
                                        }
                                    >
                                        Pilih JPG
                                    </Button>
                                    <input
                                        ref={imageInputRef}
                                        type="file"
                                        accept="image/jpeg"
                                        className="hidden"
                                        onChange={(event) =>
                                            form.setData(
                                                'image',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </div>
                                <InputError message={form.errors.image} />
                            </div>
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
        </>
    );
}

TaromboFrames.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Template Frame Tarombo', href: taromboFrames.index() },
    ],
};
