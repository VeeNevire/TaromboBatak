import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronsUpDown,
    ImagePlus,
    Pencil,
    Plus,
    Shapes,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { PersonTreePickerDialog } from '@/components/tarombo/person-tree-picker-dialog';
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
import type { TaromboPerson } from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import marga from '@/routes/marga';

type MargaItem = {
    id: number;
    name: string;
    description: string | null;
    color: string | null;
    image: string | null;
    image_url: string | null;
    people_count: number;
    identity_person_id: number | null;
    identity_person_name: string | null;
};

type Props = {
    margas: MargaItem[];
    identityPersonOptions: IdentityPersonOption[];
};

type IdentityPersonOption = {
    id: number;
    name: string;
    chain: string;
    generation: number;
};

function identityTreePeople(options: IdentityPersonOption[]): TaromboPerson[] {
    const idByChain = new Map(
        options.map((option) => [option.chain, String(option.id)]),
    );

    return options.map((option) => {
        const chainParts = option.chain.split('-');
        const parentChain = chainParts.slice(0, -1).join('-');

        return {
            id: String(option.id),
            name: option.name,
            marga: 'Batak',
            generation: option.generation,
            parentId: parentChain ? (idByChain.get(parentChain) ?? null) : null,
            birthOrder:
                chainParts.length > 1
                    ? Number(chainParts[chainParts.length - 1])
                    : null,
            chain: option.chain,
        };
    });
}

function MargaAvatar({ m, className }: { m: MargaItem; className?: string }) {
    if (m.image_url) {
        return (
            <img
                src={m.image_url}
                alt={m.name}
                className={cn('object-cover', className)}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex items-center justify-center rounded-xl text-sm font-bold text-white',
                className,
            )}
            style={{ backgroundColor: m.color ?? 'var(--color-tb-primary)' }}
        >
            {m.name.charAt(0)}
        </div>
    );
}

function ImageInput({
    value,
    onChange,
    error,
}: {
    value: string | File;
    onChange: (value: string | File) => void;
    error?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const preview =
        typeof value === 'string' ? value : URL.createObjectURL(value);

    return (
        <div className="grid gap-1.5">
            <Label className="text-tb-on-surface">
                Gambar Marga (opsional)
            </Label>

            <div className="flex items-center gap-3">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-tb-outline-variant bg-tb-surface-container text-xs text-tb-on-surface-variant">
                    {preview ? (
                        <img
                            src={preview}
                            alt="Pratinjau marga"
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <ImagePlus className="size-6" />
                    )}
                </div>

                <div className="flex flex-1 flex-col gap-2">
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => inputRef.current?.click()}
                        >
                            Pilih File
                        </Button>
                        {preview && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-tb-on-surface-variant hover:text-red-600"
                                onClick={() => {
                                    onChange('');

                                    if (inputRef.current) {
                                        inputRef.current.value = '';
                                    }
                                }}
                            >
                                <X className="size-4" /> Hapus
                            </Button>
                        )}
                    </div>
                    {typeof value !== 'string' && (
                        <p className="truncate text-xs text-tb-on-surface-variant">
                            {value.name}
                        </p>
                    )}
                </div>

                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
                    className="hidden"
                    onChange={(e) => {
                        const file = e.target.files?.[0] ?? null;
                        onChange(file ?? '');
                    }}
                />
            </div>

            <div className="grid gap-1">
                <Input
                    value={typeof value === 'string' ? value : ''}
                    onChange={(e) => {
                        onChange(e.target.value);

                        if (inputRef.current) {
                            inputRef.current.value = '';
                        }
                    }}
                    placeholder="Atau tempel URL gambar (https://...)"
                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                />
                {error && <InputError message={error} />}
            </div>
        </div>
    );
}

export default function MargaIndex({ margas, identityPersonOptions }: Props) {
    const [dialog, setDialog] = useState<null | 'create' | MargaItem>(null);
    const [toDelete, setToDelete] = useState<MargaItem | null>(null);
    const [identityPickerOpen, setIdentityPickerOpen] = useState(false);
    const identityPeople = identityTreePeople(identityPersonOptions);

    const form = useForm({
        name: '',
        description: '',
        color: '#b34b1e',
        image: '' as string | File,
        identity_person_id: null as number | null,
    });

    const openCreate = () => {
        form.reset();
        setIdentityPickerOpen(false);
        setDialog('create');
    };

    const openEdit = (m: MargaItem) => {
        form.setData({
            name: m.name,
            description: m.description ?? '',
            color: m.color ?? '#b34b1e',
            image: m.image_url ?? '',
            identity_person_id: m.identity_person_id,
        });
        setIdentityPickerOpen(false);
        setDialog(m);
    };

    const selectedIdentity = identityPersonOptions.find(
        (person) => person.id === form.data.identity_person_id,
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (dialog === 'create') {
            form.transform((data) => data);
            form.post(marga.store().url, {
                forceFormData: true,
                onSuccess: () => setDialog(null),
            });
        } else if (dialog) {
            form.transform((data) => ({
                ...data,
                _method: 'put',
            }));
            form.post(marga.update(dialog.id).url, {
                forceFormData: true,
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
                            Klik kartu untuk mengubah.
                        </p>
                    </div>
                    <Button
                        className="rounded-full bg-tb-primary hover:bg-tb-primary-light"
                        onClick={openCreate}
                    >
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
                            role="button"
                            tabIndex={0}
                            onClick={() => openEdit(m)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    openEdit(m);
                                }
                            }}
                            className="group cursor-pointer border-tb-outline-variant bg-tb-surface-bright transition-shadow hover:shadow-md"
                        >
                            <CardContent className="gap-4 py-5">
                                <div className="flex items-start justify-between">
                                    <MargaAvatar
                                        m={m}
                                        className="h-11 w-11 rounded-xl"
                                    />
                                    <div className="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-tb-on-surface-variant hover:text-tb-primary"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                openEdit(m);
                                            }}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-tb-on-surface-variant hover:text-red-600"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setToDelete(m);
                                            }}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                <div>
                                    <h3 className="font-display text-lg font-bold text-tb-on-surface">
                                        {m.name}
                                    </h3>
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

                <Dialog
                    open={dialog !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setIdentityPickerOpen(false);
                            setDialog(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={submit} encType="multipart/form-data">
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
                                    <Label
                                        htmlFor="name"
                                        className="text-tb-on-surface"
                                    >
                                        Nama Marga{' '}
                                        <span className="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        placeholder="Mis. Sitompul"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor="identity_person_id"
                                        className="text-tb-on-surface"
                                    >
                                        Saya adalah
                                    </Label>
                                    <div className="flex gap-2">
                                        <Button
                                            id="identity_person_id"
                                            type="button"
                                            variant="outline"
                                            role="combobox"
                                            aria-haspopup="dialog"
                                            aria-expanded={identityPickerOpen}
                                            disabled={
                                                identityPersonOptions.length ===
                                                0
                                            }
                                            onClick={() =>
                                                setIdentityPickerOpen(true)
                                            }
                                            className="min-w-0 flex-1 justify-between border-tb-outline-variant bg-tb-surface-bright px-3 font-normal text-tb-on-surface hover:bg-tb-surface-container hover:text-tb-on-surface"
                                        >
                                            <span
                                                className={cn(
                                                    'truncate',
                                                    !selectedIdentity &&
                                                        'text-tb-on-surface-variant',
                                                )}
                                            >
                                                {selectedIdentity
                                                    ? `${selectedIdentity.chain} · ${selectedIdentity.name}`
                                                    : 'Belum dipilih'}
                                            </span>
                                            <ChevronsUpDown className="size-4 shrink-0 text-tb-on-surface-variant" />
                                        </Button>
                                        {selectedIdentity && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="Hapus pilihan identitas marga"
                                                title="Hapus pilihan"
                                                onClick={() =>
                                                    form.setData(
                                                        'identity_person_id',
                                                        null,
                                                    )
                                                }
                                                className="shrink-0 border-tb-outline-variant text-tb-on-surface-variant hover:text-red-600"
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                    {identityPersonOptions.length === 0 && (
                                        <p className="text-xs text-amber-700 dark:text-amber-300">
                                            Belum ada nama yang dapat dipilih
                                            dari pohon utama Si Raja Batak.
                                        </p>
                                    )}
                                    <p className="text-xs text-tb-on-surface-variant">
                                        Khusus identifikasi marga dari pohon
                                        utama Si Raja Batak, maksimal generasi
                                        ke-11.
                                    </p>
                                    <InputError
                                        message={form.errors.identity_person_id}
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor="description"
                                        className="text-tb-on-surface"
                                    >
                                        Deskripsi
                                    </Label>
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Asal usul singkat marga"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                    <InputError
                                        message={form.errors.description}
                                    />
                                </div>

                                <ImageInput
                                    value={form.data.image}
                                    onChange={(value) =>
                                        form.setData('image', value)
                                    }
                                    error={form.errors.image}
                                />

                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor="color"
                                        className="text-tb-on-surface"
                                    >
                                        Warna
                                    </Label>
                                    <div className="flex items-center gap-3">
                                        <Input
                                            id="color"
                                            type="color"
                                            value={form.data.color}
                                            onChange={(e) =>
                                                form.setData(
                                                    'color',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-10 w-16 cursor-pointer border-tb-outline-variant bg-tb-surface-bright p-1"
                                        />
                                        <Input
                                            value={form.data.color}
                                            onChange={(e) =>
                                                form.setData(
                                                    'color',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex-1 border-tb-outline-variant bg-tb-surface-bright font-mono text-xs focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                    </div>
                                    <InputError message={form.errors.color} />
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDialog(null)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-tb-primary hover:bg-tb-primary-light"
                                >
                                    {form.processing
                                        ? 'Menyimpan...'
                                        : dialog === 'create'
                                          ? 'Tambah'
                                          : 'Simpan'}
                                </Button>
                            </DialogFooter>
                        </form>

                        <PersonTreePickerDialog
                            open={identityPickerOpen}
                            onOpenChange={setIdentityPickerOpen}
                            people={identityPeople}
                            currentId={
                                form.data.identity_person_id === null
                                    ? null
                                    : String(form.data.identity_person_id)
                            }
                            description="Pilih nama pada pohon yang menjadi pemilik atau asal nama marga ini."
                            onSelect={(person) => {
                                form.setData(
                                    'identity_person_id',
                                    Number(person.id),
                                );
                                setIdentityPickerOpen(false);
                            }}
                        />
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={toDelete !== null}
                    onOpenChange={(open) => !open && setToDelete(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="text-tb-on-surface">
                                Hapus Marga
                            </DialogTitle>
                            <DialogDescription>
                                Yakin ingin menghapus marga{' '}
                                <strong>{toDelete?.name}</strong>? Anggota
                                dengan marga ini akan kehilangan keterkaitannya.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setToDelete(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={confirmDelete}
                            >
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
