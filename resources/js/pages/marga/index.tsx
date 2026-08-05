import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Shapes, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import marga from '@/routes/marga';

type MargaItem = {
    id: number;
    name: string;
    description: string | null;
    color: string | null;
    people_count: number;
};

type Props = {
    margas: MargaItem[];
};

export default function MargaIndex({ margas }: Props) {
    const [dialog, setDialog] = useState<null | 'create' | MargaItem>(null);
    const [toDelete, setToDelete] = useState<MargaItem | null>(null);

    const form = useForm({
        name: '',
        description: '',
        color: '#b34b1e',
    });

    const openCreate = () => {
        form.reset();
        setDialog('create');
    };

    const openEdit = (m: MargaItem) => {
        form.setData({
            name: m.name,
            description: m.description ?? '',
            color: m.color ?? '#b34b1e',
        });
        setDialog(m);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (dialog === 'create') {
            form.post(marga.store().url, {
                onSuccess: () => setDialog(null),
            });
        } else if (dialog) {
            form.put(marga.update(dialog.id).url, {
                onSuccess: () => setDialog(null),
            });
        }
    };

    const confirmDelete = () => {
        if (!toDelete) {
            return;
        }

        router.delete(marga.destroy(toDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setToDelete(null),
        });
    };

    return (
        <>
            <Head title="Daftar Marga" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Daftar Marga
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Marga Batak yang tercatat beserta jumlah anggotanya.
                        </p>
                    </div>
                    <Button className="rounded-full bg-tb-primary hover:bg-tb-primary-light" onClick={openCreate}>
                        <Plus className="size-4" /> Tambah Marga
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {margas.length === 0 && (
                        <Card className="col-span-full border-tb-outline-variant bg-tb-surface-bright">
                            <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                                <Shapes className="size-8 text-tb-outline" />
                                <p className="text-sm text-tb-on-surface-variant">
                                    Belum ada marga yang terdaftar.
                                </p>
                            </CardContent>
                        </Card>
                    )}
                    {margas.map((m) => (
                        <Card
                            key={m.id}
                            className="group border-tb-outline-variant bg-tb-surface-bright transition-shadow hover:shadow-md"
                        >
                            <CardContent className="gap-4 py-5">
                                <div className="flex items-start justify-between">
                                    <div
                                        className="flex h-11 w-11 items-center justify-center rounded-xl text-sm font-bold text-white"
                                        style={{ backgroundColor: m.color ?? 'var(--color-tb-primary)' }}
                                    >
                                        {m.name.charAt(0)}
                                    </div>
                                    <div className="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-tb-on-surface-variant hover:text-tb-primary"
                                            onClick={() => openEdit(m)}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-tb-on-surface-variant hover:text-red-600"
                                            onClick={() => setToDelete(m)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                <div>
                                    <h3 className="font-display text-lg font-bold text-tb-on-surface">{m.name}</h3>
                                    {m.description && (
                                        <p className="mt-0.5 line-clamp-2 text-sm text-tb-on-surface-variant">
                                            {m.description}
                                        </p>
                                    )}
                                </div>
                                <p className="text-xs font-medium text-tb-primary">
                                    {m.people_count} anggota
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Dialog open={dialog !== null} onOpenChange={(open) => !open && setDialog(null)}>
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={submit}>
                            <DialogHeader>
                                <DialogTitle className="text-tb-on-surface">
                                    {dialog === 'create'
                                        ? 'Tambah Marga'
                                        : dialog
                                          ? `Ubah Marga ${dialog.name}`
                                          : ''}
                                </DialogTitle>
                                <DialogDescription>
                                    {dialog === 'create'
                                        ? 'Tambahkan marga baru ke dalam silsilah.'
                                        : 'Perbarui informasi marga.'}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-5 py-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name" className="text-tb-on-surface">
                                        Nama Marga <span className="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        placeholder="Mis. Sitompul"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="description" className="text-tb-on-surface">
                                        Deskripsi
                                    </Label>
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) => form.setData('description', e.target.value)}
                                        placeholder="Asal usul singkat marga"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                    <InputError message={form.errors.description} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="color" className="text-tb-on-surface">
                                        Warna
                                    </Label>
                                    <div className="flex items-center gap-3">
                                        <Input
                                            id="color"
                                            type="color"
                                            value={form.data.color}
                                            onChange={(e) => form.setData('color', e.target.value)}
                                            className="h-10 w-16 cursor-pointer border-tb-outline-variant bg-tb-surface-bright p-1"
                                        />
                                        <Input
                                            value={form.data.color}
                                            onChange={(e) => form.setData('color', e.target.value)}
                                            className="flex-1 border-tb-outline-variant bg-tb-surface-bright font-mono text-xs focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                    </div>
                                    <InputError message={form.errors.color} />
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setDialog(null)}>
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-tb-primary hover:bg-tb-primary-light"
                                >
                                    {form.processing ? 'Menyimpan...' : dialog === 'create' ? 'Tambah' : 'Simpan'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog open={toDelete !== null} onOpenChange={(open) => !open && setToDelete(null)}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="text-tb-on-surface">Hapus Marga</DialogTitle>
                            <DialogDescription>
                                Yakin ingin menghapus marga <strong>{toDelete?.name}</strong>? Anggota dengan marga ini akan kehilangan keterkaitannya.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setToDelete(null)}>
                                Batal
                            </Button>
                            <Button variant="destructive" onClick={confirmDelete}>
                                Ya, Hapus
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

MargaIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Daftar Marga', href: marga.index() },
    ],
};
