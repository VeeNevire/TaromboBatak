import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, UserPlus, Users } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import familyTrees from '@/routes/family-trees';
import people from '@/routes/people';

type NodeOption = { id: number; name: string; chain: string | null };

type MemberRow = {
    uid: string;
    name: string;
    alias: string;
    gender: string;
    birth_year: string;
    death_year: string;
    spouse: string;
    spouse_marga: string;
};

type Props = {
    familyTree: { id: number; name: string; requires_approval: boolean };
    fatherOptions: NodeOption[];
    motherOptionsByFather: Record<string, NodeOption[]>;
    initialFatherNodeId: number | null;
};

const MAX_EXTRA_ROWS = 20;

const optionLabel = (option: NodeOption) => option.name;

let uidCounter = 0;

const createUid = (): string => {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `row-${Date.now().toString(36)}-${(uidCounter++).toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
};

const toMemberPayload = (row: MemberRow) => ({
    name: row.name,
    alias: row.alias,
    gender: row.gender,
    birth_year: row.birth_year,
    death_year: row.death_year,
    spouse: row.spouse,
    spouse_marga: row.spouse_marga,
});

const emptyMemberRow = (): MemberRow => ({
    uid: createUid(),
    name: '',
    alias: '',
    gender: '',
    birth_year: '',
    death_year: '',
    spouse: '',
    spouse_marga: '',
});

export default function SharedTreePersonForm({
    familyTree,
    fatherOptions,
    motherOptionsByFather,
    initialFatherNodeId,
}: Props) {
    const { data, setData, post, transform, processing, errors } = useForm({
        name: '',
        alias: '',
        gender: '',
        birth_order: '',
        birth_year: '',
        death_year: '',
        bio: '',
        father_node_id: initialFatherNodeId?.toString() ?? '',
        mother_node_id: '',
        spouse: '',
        spouse_marga: '',
        children: [] as MemberRow[],
        siblings: [] as MemberRow[],
    });

    const motherOptions =
        motherOptionsByFather[data.father_node_id] ?? [];

    const addRow = (kind: 'children' | 'siblings') => {
        setData(kind, [...data[kind], emptyMemberRow()]);
    };

    const removeRow = (kind: 'children' | 'siblings', index: number) => {
        setData(
            kind,
            data[kind].filter((_, rowIndex) => rowIndex !== index),
        );
    };

    const setRow = (
        kind: 'children' | 'siblings',
        index: number,
        field: keyof Omit<MemberRow, 'uid'>,
        value: string,
    ) => {
        setData(
            kind,
            data[kind].map((row, rowIndex) =>
                rowIndex === index ? { ...row, [field]: value } : row,
            ),
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            children: formData.children
                .filter((row) => row.name.trim() !== '')
                .map((row) => toMemberPayload(row)),
            siblings: formData.siblings
                .filter((row) => row.name.trim() !== '')
                .map((row) => toMemberPayload(row)),
        }));

        post(familyTrees.people.store.url(familyTree.id));
    };

    const renderMemberRow = (
        kind: 'children' | 'siblings',
        row: MemberRow,
        index: number,
    ) => (
        <div
            key={row.uid}
            className="grid gap-4 rounded-lg border border-tb-outline-variant p-4"
        >
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-tb-on-surface">
                    {kind === 'children' ? 'Anak' : 'Saudara'} #{index + 1}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => removeRow(kind, index)}
                    className="text-red-600 hover:bg-red-50 hover:text-red-700"
                >
                    <Trash2 className="size-4" /> Hapus
                </Button>
            </div>
            <div className="grid gap-1.5">
                <Label
                    htmlFor={`${kind}-${index}-name`}
                    className="text-tb-on-surface"
                >
                    Nama Lengkap{' '}
                    <span className="text-red-600">*</span>
                </Label>
                <Input
                    id={`${kind}-${index}-name`}
                    value={row.name}
                    onChange={(e) =>
                        setRow(kind, index, 'name', e.target.value)
                    }
                    placeholder="Nama lengkap"
                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                />
                <InputError message={errors[`${kind}.${index}.name`]} />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${kind}-${index}-alias`}
                        className="text-tb-on-surface"
                    >
                        Alias / Gelar
                    </Label>
                    <Input
                        id={`${kind}-${index}-alias`}
                        value={row.alias}
                        onChange={(e) =>
                            setRow(kind, index, 'alias', e.target.value)
                        }
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    <InputError
                        message={errors[`${kind}.${index}.alias`]}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${kind}-${index}-gender`}
                        className="text-tb-on-surface"
                    >
                        Jenis Kelamin
                    </Label>
                    <select
                        id={`${kind}-${index}-gender`}
                        value={row.gender}
                        onChange={(e) =>
                            setRow(kind, index, 'gender', e.target.value)
                        }
                        className="h-10 w-full rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                    >
                        <option value="">Pilih</option>
                        <option value="L">Laki-laki (L)</option>
                        <option value="P">Perempuan (P)</option>
                    </select>
                    <InputError
                        message={errors[`${kind}.${index}.gender`]}
                    />
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor={`${kind}-${index}-birth_year`}
                            className="text-tb-on-surface"
                        >
                            Tahun Lahir
                        </Label>
                        <Input
                            id={`${kind}-${index}-birth_year`}
                            value={row.birth_year}
                            onChange={(e) =>
                                setRow(
                                    kind,
                                    index,
                                    'birth_year',
                                    e.target.value,
                                )
                            }
                            inputMode="numeric"
                            maxLength={4}
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                        <InputError
                            message={errors[`${kind}.${index}.birth_year`]}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor={`${kind}-${index}-death_year`}
                            className="text-tb-on-surface"
                        >
                            Tahun Wafat
                        </Label>
                        <Input
                            id={`${kind}-${index}-death_year`}
                            value={row.death_year}
                            onChange={(e) =>
                                setRow(
                                    kind,
                                    index,
                                    'death_year',
                                    e.target.value,
                                )
                            }
                            inputMode="numeric"
                            maxLength={4}
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                        <InputError
                            message={errors[`${kind}.${index}.death_year`]}
                        />
                    </div>
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${kind}-${index}-spouse`}
                        className="text-tb-on-surface"
                    >
                        Nama Pasangan
                    </Label>
                    <Input
                        id={`${kind}-${index}-spouse`}
                        value={row.spouse}
                        onChange={(e) =>
                            setRow(kind, index, 'spouse', e.target.value)
                        }
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    <InputError
                        message={errors[`${kind}.${index}.spouse`]}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${kind}-${index}-spouse_marga`}
                        className="text-tb-on-surface"
                    >
                        Marga Pasangan
                    </Label>
                    <Input
                        id={`${kind}-${index}-spouse_marga`}
                        value={row.spouse_marga}
                        onChange={(e) =>
                            setRow(
                                kind,
                                index,
                                'spouse_marga',
                                e.target.value,
                            )
                        }
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    <InputError
                        message={errors[`${kind}.${index}.spouse_marga`]}
                    />
                </div>
            </div>
        </div>
    );

    return (
        <>
            <Head title={`Tambah Anggota - ${familyTree.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="w-fit text-tb-on-surface-variant"
                >
                    <Link href={familyTrees.show(familyTree.id)}>
                        <ArrowLeft className="size-4" /> Kembali ke Silsilah
                    </Link>
                </Button>

                <Card className="mx-auto w-full max-w-3xl border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 font-display text-xl text-tb-on-surface">
                            <UserPlus className="size-5 text-tb-primary" />{' '}
                            Tambah Anggota
                        </CardTitle>
                        <CardDescription>
                            {familyTree.requires_approval
                                ? `Pengajuan anggota baru untuk ${familyTree.name} akan dikirim kepada pemilik silsilah untuk disetujui.`
                                : `Tambahkan anggota baru ke ${familyTree.name}. Data dan struktur anggota yang sudah ada tidak dapat diubah dari akses berbagi.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-5">
                            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                <CardHeader>
                                    <CardTitle className="font-display text-lg text-tb-on-surface">
                                        Informasi Anggota
                                    </CardTitle>
                                    <CardDescription>
                                        Data dasar anggota yang sedang
                                        ditambahkan ke silsilah.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="name"
                                            className="text-tb-on-surface"
                                        >
                                            Nama Lengkap{' '}
                                            <span className="text-red-600">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) =>
                                                setData('name', e.target.value)
                                            }
                                            required
                                            placeholder="Mis. Ompu Sitorus"
                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                        <InputError
                                            message={errors.name}
                                        />
                                    </div>
                                    <div className="grid gap-5 sm:grid-cols-3">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="alias"
                                                className="text-tb-on-surface"
                                            >
                                                Alias / Gelar
                                            </Label>
                                            <Input
                                                id="alias"
                                                value={data.alias}
                                                onChange={(e) =>
                                                    setData(
                                                        'alias',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Tuan Sorba Dibanua"
                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                            />
                                            <InputError
                                                message={errors.alias}
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="gender"
                                                className="text-tb-on-surface"
                                            >
                                                Jenis Kelamin
                                            </Label>
                                            <select
                                                id="gender"
                                                value={data.gender}
                                                onChange={(e) =>
                                                    setData(
                                                        'gender',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 w-full rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                                            >
                                                <option value="">
                                                    Pilih
                                                </option>
                                                <option value="L">
                                                    Laki-laki (L)
                                                </option>
                                                <option value="P">
                                                    Perempuan (P)
                                                </option>
                                            </select>
                                            <InputError
                                                message={errors.gender}
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="birth_order"
                                                className="text-tb-on-surface"
                                            >
                                                Urutan Lahir
                                            </Label>
                                            <Input
                                                id="birth_order"
                                                type="number"
                                                min="1"
                                                value={data.birth_order}
                                                onChange={(e) =>
                                                    setData(
                                                        'birth_order',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Otomatis"
                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                            />
                                            <InputError
                                                message={
                                                    errors.birth_order
                                                }
                                            />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="birth_year"
                                                className="text-tb-on-surface"
                                            >
                                                Tahun Lahir
                                            </Label>
                                            <Input
                                                id="birth_year"
                                                value={data.birth_year}
                                                onChange={(e) =>
                                                    setData(
                                                        'birth_year',
                                                        e.target.value,
                                                    )
                                                }
                                                inputMode="numeric"
                                                maxLength={4}
                                                placeholder="1920"
                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                            />
                                            <InputError
                                                message={
                                                    errors.birth_year
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="death_year"
                                                className="text-tb-on-surface"
                                            >
                                                Tahun Wafat
                                            </Label>
                                            <Input
                                                id="death_year"
                                                value={data.death_year}
                                                onChange={(e) =>
                                                    setData(
                                                        'death_year',
                                                        e.target.value,
                                                    )
                                                }
                                                inputMode="numeric"
                                                maxLength={4}
                                                placeholder="2001"
                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                            />
                                            <InputError
                                                message={
                                                    errors.death_year
                                                }
                                            />
                                        </div>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="bio"
                                            className="text-tb-on-surface"
                                        >
                                            Biografi Singkat
                                        </Label>
                                        <textarea
                                            id="bio"
                                            value={data.bio}
                                            onChange={(e) =>
                                                setData('bio', e.target.value)
                                            }
                                            rows={4}
                                            className="min-h-24 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                                        />
                                        <InputError message={errors.bio} />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 font-display text-lg text-tb-on-surface">
                                            <Users className="size-4 text-tb-primary" />{' '}
                                            Daftar Anak
                                        </CardTitle>
                                        <CardDescription>
                                            Anak dari anggota yang sedang
                                            ditambahkan (opsional). Marga anak
                                            akan mengikuti marga ayah secara
                                            otomatis.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        {data.children.map((row, index) =>
                                            renderMemberRow(
                                                'children',
                                                row,
                                                index,
                                            ),
                                        )}
                                        {errors.children && (
                                            <InputError
                                                message={errors.children}
                                            />
                                        )}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                data.children.length >=
                                                MAX_EXTRA_ROWS
                                            }
                                            onClick={() => addRow('children')}
                                            className="w-fit"
                                        >
                                            <Plus className="size-4" />{' '}
                                            Tambah Anak
                                        </Button>
                                    </CardContent>
                            </Card>

                            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                <CardHeader>
                                    <CardTitle className="font-display text-lg text-tb-on-surface">
                                        Hubungan Keluarga
                                    </CardTitle>
                                    <CardDescription>
                                        Pilih orang tua anggota dari data yang
                                        sudah ada di silsilah ini.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="father_node_id"
                                            className="text-tb-on-surface"
                                        >
                                            Ayah di Dalam Silsilah{' '}
                                            <span className="text-red-600">
                                                *
                                            </span>
                                        </Label>
                                        <select
                                            id="father_node_id"
                                            required
                                            value={data.father_node_id}
                                            onChange={(e) => {
                                                setData(
                                                    'father_node_id',
                                                    e.target.value,
                                                );
                                                setData('mother_node_id', '');
                                            }}
                                            className="h-10 w-full rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                                        >
                                            <option value="">
                                                Pilih Ayah...
                                            </option>
                                            {fatherOptions.map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {optionLabel(option)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                errors.father_node_id
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="mother_node_id"
                                            className="text-tb-on-surface"
                                        >
                                            Ibu di Dalam Silsilah (Opsional)
                                        </Label>
                                        <select
                                            id="mother_node_id"
                                            value={data.mother_node_id}
                                            onChange={(e) =>
                                                setData(
                                                    'mother_node_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-10 w-full rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                                        >
                                            <option value="">
                                                Belum dipilih
                                            </option>
                                            {motherOptions.map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {optionLabel(option)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                errors.mother_node_id
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="spouse"
                                            className="text-tb-on-surface"
                                        >
                                            Nama Pasangan
                                        </Label>
                                        <Input
                                            id="spouse"
                                            value={data.spouse}
                                            onChange={(e) =>
                                                setData(
                                                    'spouse',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                        <InputError
                                            message={errors.spouse}
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="spouse_marga"
                                            className="text-tb-on-surface"
                                        >
                                            Marga Pasangan
                                        </Label>
                                        <Input
                                            id="spouse_marga"
                                            value={data.spouse_marga}
                                            onChange={(e) =>
                                                setData(
                                                    'spouse_marga',
                                                    e.target.value,
                                                )
                                            }
                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                        <InputError
                                            message={
                                                errors.spouse_marga
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 font-display text-lg text-tb-on-surface">
                                        <Users className="size-4 text-tb-primary" />{' '}
                                        Data Saudara
                                    </CardTitle>
                                    <CardDescription>
                                        Saudara kandung dari anggota yang
                                        sedang ditambahkan (opsional). Ayah dan
                                        ibu akan sama dengan yang dipilih di
                                        atas.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    {data.siblings.map((row, index) =>
                                        renderMemberRow(
                                            'siblings',
                                            row,
                                            index,
                                        ),
                                    )}
                                    {errors.siblings && (
                                        <InputError
                                            message={errors.siblings}
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            data.siblings.length >=
                                            MAX_EXTRA_ROWS
                                        }
                                        onClick={() => addRow('siblings')}
                                        className="w-fit"
                                    >
                                        <Plus className="size-4" /> Tambah
                                        Saudara
                                    </Button>
                                </CardContent>
                            </Card>

                            <div className="flex justify-end gap-2 border-t border-tb-outline-variant pt-4">
                                <Button
                                    asChild
                                    type="button"
                                    variant="outline"
                                >
                                    <Link
                                        href={familyTrees.show(
                                            familyTree.id,
                                        )}
                                    >
                                        Batal
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <UserPlus className="size-4" />{' '}
                                    {processing
                                        ? 'Mengirim...'
                                        : familyTree.requires_approval
                                          ? 'Kirim Pengajuan'
                                          : 'Tambah Anggota'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SharedTreePersonForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Tambah Anggota Berbagi', href: people.index() },
    ],
};
