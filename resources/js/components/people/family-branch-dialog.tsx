import { useForm } from '@inertiajs/react';
import { Plus, Trash2, UserPlus, Users } from 'lucide-react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import familyTrees from '@/routes/family-trees';

type NameRow = {
    id: string;
    name: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    familyTree: {
        id: number;
        requiresApproval: boolean;
    };
    father: {
        nodeId: number;
        name: string;
    };
};

const MAX_ROWS = 20;

const createRow = (): NameRow => ({
    id:
        typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
            ? crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    name: '',
});

export function FamilyBranchDialog({
    open,
    onOpenChange,
    familyTree,
    father,
}: Props) {
    const {
        data,
        setData,
        post,
        transform,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({
        name: '',
        gender: 'L',
        father_node_id: father.nodeId,
        children: [] as NameRow[],
        siblings: [] as NameRow[],
    });

    useEffect(() => {
        if (!open) {
            reset();
            clearErrors();
            setData('father_node_id', father.nodeId);
        }
    }, [clearErrors, father.nodeId, open, reset, setData]);

    const updateRow = (
        kind: 'children' | 'siblings',
        id: string,
        name: string,
    ) => {
        setData(
            kind,
            data[kind].map((row) => (row.id === id ? { ...row, name } : row)),
        );
    };

    const addRow = (kind: 'children' | 'siblings') => {
        if (data[kind].length < MAX_ROWS) {
            setData(kind, [...data[kind], createRow()]);
        }
    };

    const removeRow = (kind: 'children' | 'siblings', id: string) => {
        setData(
            kind,
            data[kind].filter((row) => row.id !== id),
        );
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((formData) => ({
            ...formData,
            children: formData.children
                .filter((row) => row.name.trim() !== '')
                .map((row) => ({ name: row.name.trim() })),
            siblings: formData.siblings
                .filter((row) => row.name.trim() !== '')
                .map((row) => ({ name: row.name.trim() })),
        }));

        post(familyTrees.people.store.url(familyTree.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
            },
        });
    };

    const renderRows = (kind: 'children' | 'siblings') => (
        <div className="grid gap-3">
            {data[kind].map((row, index) => (
                <div key={row.id} className="flex items-end gap-2">
                    <div className="grid min-w-0 flex-1 gap-1.5">
                        <Label htmlFor={`${kind}-${row.id}`}>
                            Nama Lengkap {index + 1}
                        </Label>
                        <Input
                            id={`${kind}-${row.id}`}
                            value={row.name}
                            onChange={(event) =>
                                updateRow(kind, row.id, event.target.value)
                            }
                            placeholder="Nama lengkap"
                        />
                        <InputError message={errors[`${kind}.${index}.name`]} />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Hapus ${kind === 'children' ? 'anak' : 'saudara'} ${index + 1}`}
                        onClick={() => removeRow(kind, row.id)}
                        className="shrink-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ))}
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto border-tb-outline-variant bg-tb-surface-bright p-0 sm:max-w-3xl">
                <DialogHeader className="border-b border-tb-outline-variant px-6 py-5">
                    <DialogTitle className="flex items-center gap-2 font-display text-xl text-tb-on-surface">
                        <UserPlus className="size-5 text-tb-primary" /> Tambah
                        Anggota Ranting
                    </DialogTitle>
                    <DialogDescription>
                        Tambahkan anggota baru yang terhubung ke {father.name}.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-5 px-6 pb-6">
                    <section className="grid gap-3 rounded-xl border border-tb-outline-variant p-4">
                        <div>
                            <h3 className="font-display text-lg text-tb-on-surface">
                                Informasi Anggota
                            </h3>
                            <p className="text-sm text-tb-on-surface-variant">
                                Data dasar anggota yang ditambahkan.
                            </p>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="branch-member-name">
                                Nama Lengkap{' '}
                                <span className="text-red-600">*</span>
                            </Label>
                            <Input
                                id="branch-member-name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder="Nama lengkap"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                    </section>

                    <section className="grid gap-3 rounded-xl border border-tb-outline-variant p-4">
                        <div className="flex items-start gap-2">
                            <Users className="mt-0.5 size-4 text-tb-primary" />
                            <div>
                                <h3 className="font-display text-lg text-tb-on-surface">
                                    Daftar Anak
                                </h3>
                                <p className="text-sm text-tb-on-surface-variant">
                                    Opsional. Cukup masukkan nama anak tanpa
                                    memilih anggota lain.
                                </p>
                            </div>
                        </div>
                        {renderRows('children')}
                        <InputError message={errors.children} />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-fit"
                            disabled={data.children.length >= MAX_ROWS}
                            onClick={() => addRow('children')}
                        >
                            <Plus className="size-4" /> Tambah Anak
                        </Button>
                    </section>

                    <section className="grid gap-3 rounded-xl border border-tb-outline-variant p-4">
                        <h3 className="font-display text-lg text-tb-on-surface">
                            Hubungan Keluarga
                        </h3>
                        <div className="grid gap-1.5">
                            <Label htmlFor="branch-father">
                                Ayah di Dalam Silsilah
                            </Label>
                            <Input
                                id="branch-father"
                                value={father.name}
                                readOnly
                            />
                            <p className="text-sm text-tb-on-surface-variant">
                                Otomatis mengikuti anggota yang dipilih.
                            </p>
                        </div>
                    </section>

                    <section className="grid gap-3 rounded-xl border border-tb-outline-variant p-4">
                        <div className="flex items-start gap-2">
                            <Users className="mt-0.5 size-4 text-tb-primary" />
                            <div>
                                <h3 className="font-display text-lg text-tb-on-surface">
                                    Data Saudara
                                </h3>
                                <p className="text-sm text-tb-on-surface-variant">
                                    Opsional. Saudara otomatis memakai ayah yang
                                    sama.
                                </p>
                            </div>
                        </div>
                        {renderRows('siblings')}
                        <InputError message={errors.siblings} />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-fit"
                            disabled={data.siblings.length >= MAX_ROWS}
                            onClick={() => addRow('siblings')}
                        >
                            <Plus className="size-4" /> Tambah Saudara
                        </Button>
                    </section>

                    <DialogFooter className="border-t border-tb-outline-variant pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <UserPlus className="size-4" />
                            {processing
                                ? 'Menyimpan...'
                                : familyTree.requiresApproval
                                  ? 'Kirim Pengajuan'
                                  : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
