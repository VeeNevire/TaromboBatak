import { Link, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { NameCombobox } from '@/components/ui/name-combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import people from '@/routes/people';

export type ChildRow = {
    id?: number | null;
    name: string;
    gender: string;
    spouse: string;
    spouse_marga: string;
    marga_id?: number | null;
    new_marga?: string;
    alias?: string | null;
    nomor?: string | null;
    birth_year?: string | null;
    death_year?: string | null;
    image?: string | null;
    bio?: string | null;
};

type ParentEntry = {
    name: string;
    birth_year: string;
    death_year: string;
    marga_id?: number | null;
    new_marga?: string;
    nomor?: string | null;
};

export type FamilyData = {
    id?: number | null;
    name: string;
    gender: string;
    alias: string;
    marga_id: number | null;
    birth_order: number | null;
    sibling_count: number | null;
    nomor: string;
    nomor_manual?: boolean;
    birth_year: string;
    death_year: string;
    image: string;
    bio: string;
    new_marga?: string;
    father: ParentEntry | null;
    mother: ParentEntry | null;
    children: ChildRow[];
};

type MargaOption = { id: number; name: string };

type Props = {
    person: FamilyData | null;
    margas: MargaOption[];
    nameSuggestions: string[];
    nomorUsed: { nomor: string; name: string }[];
    lockedMarga?: { id: number; name: string } | null;
};

const VALUE_NONE = 'none';
const NEW_MARGA_VALUE = '__new__';

function isNameFilled(name: string): boolean {
    return name.trim() !== '' && name.trim().toUpperCase() !== 'N/A';
}

function parseNomor(nomor?: string | null): number[] | null {
    const trimmed = (nomor ?? '').trim();

    if (!trimmed) {
        return null;
    }

    const parts = trimmed.split('.').map((part) => parseInt(part, 10));

    return parts.every((part) => Number.isFinite(part)) ? parts : null;
}

function compareByNomor(a: ChildRow, b: ChildRow): number {
    const pa = parseNomor(a.nomor);
    const pb = parseNomor(b.nomor);

    if (pa === null && pb === null) {
        return 0;
    }

    if (pa === null) {
        return 1;
    }

    if (pb === null) {
        return -1;
    }

    const length = Math.max(pa.length, pb.length);

    for (let i = 0; i < length; i++) {
        const da = pa[i] ?? 0;
        const db = pb[i] ?? 0;

        if (da !== db) {
            return da - db;
        }
    }

    return 0;
}

function sortChildrenByNomor(children: ChildRow[]): ChildRow[] {
    return [...children].sort(compareByNomor);
}

function SiblingListCard({
    children,
    focusIndex,
    focusPersonId,
}: {
    children: ChildRow[];
    focusIndex: number;
    focusPersonId?: number | null;
}) {
    const numbered = children.filter((child) => child.nomor?.trim());

    return (
        <Card className="border-tb-outline-variant bg-tb-surface-bright">
            <CardHeader>
                <CardTitle className="font-display text-lg text-tb-on-surface">
                    List Silsilah
                </CardTitle>
                <CardDescription>
                    {numbered.length} bernomor. Baris tanpa nomor silsilah belum tampil dan akan diisi otomatis saat disimpan.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {numbered.length === 0 ? (
                    <p className="text-sm italic text-tb-on-surface-variant">
                        Isi nomor silsilah pada daftar saudara di bawah untuk mulai menyusun daftar.
                    </p>
                ) : (
                    <ul className="grid gap-2">
                        {numbered.map((child, index) => {
                            const filled = isNameFilled(child.name ?? '');
                            const isFocus = focusPersonId != null
                                ? child.id === focusPersonId
                                : index === focusIndex;

                            const rowContent = (
                                <span
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg border px-3 py-2',
                                        isFocus
                                            ? 'border-tb-primary/40 bg-tb-primary/5'
                                            : 'border-tb-outline-variant',
                                        child.id
                                            ? 'transition-colors hover:border-tb-primary'
                                            : '',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                            isFocus
                                                ? 'bg-tb-primary text-white'
                                                : 'bg-tb-surface-container text-tb-on-surface-variant',
                                        )}
                                    >
                                        {index + 1}
                                    </span>
                                    <span
                                        className={cn(
                                            'min-w-0 flex-1 truncate text-sm',
                                            filled ? 'text-tb-on-surface' : 'italic text-tb-on-surface-variant',
                                        )}
                                    >
                                        {filled ? child.name : 'Belum diisi'}
                                    </span>
                                    <span className="shrink-0 rounded-md bg-tb-surface-container px-1.5 py-0.5 text-[10px] font-semibold text-tb-on-surface-variant">
                                        No. {child.nomor?.trim()}
                                    </span>
                                    {isFocus && (
                                        <span className="shrink-0 text-[11px] font-medium text-tb-primary">Anda</span>
                                    )}
                                    {child.id && (
                                        <Pencil className="h-3.5 w-3.5 shrink-0 text-tb-outline" />
                                    )}
                                </span>
                            );

                            return (
                                <li key={child.id ?? index}>
                                    {child.id ? (
                                        <Link href={people.edit(child.id)} className="block">
                                            {rowContent}
                                        </Link>
                                    ) : (
                                        rowContent
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

const emptyRow = (): ChildRow => ({
    id: null,
    name: '',
    gender: '',
    spouse: '',
    spouse_marga: '',
    marga_id: null,
    new_marga: '',
    nomor: '',
});

const emptyParent = (): ParentEntry => ({
    name: '',
    birth_year: '',
    death_year: '',
    marga_id: null,
    new_marga: '',
    nomor: null,
});

function MargaField({
    value,
    newMarga,
    onValue,
    onNewMarga,
    margas,
    disabled = false,
    placeholder = 'Pilih marga',
}: {
    value: number | null;
    newMarga: string;
    onValue: (id: number | null) => void;
    onNewMarga: (name: string) => void;
    margas: MargaOption[];
    disabled?: boolean;
    placeholder?: string;
}) {
    const [creating, setCreating] = useState(false);

    if (disabled) {
        return (
            <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                {margas.find((marga) => marga.id === value)?.name ?? '—'}
            </div>
        );
    }

    if (creating) {
        return (
            <div className="flex gap-2">
                <Input
                    autoFocus
                    value={newMarga}
                    onChange={(e) => onNewMarga(e.target.value)}
                    placeholder="Nama marga baru"
                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        setCreating(false);
                        onNewMarga('');
                        onValue(null);
                    }}
                >
                    Batal
                </Button>
            </div>
        );
    }

    return (
        <Select
            value={value ? String(value) : VALUE_NONE}
            onValueChange={(selected) => {
                if (selected === NEW_MARGA_VALUE) {
                    setCreating(true);
                    onValue(null);
                } else {
                    onValue(selected === VALUE_NONE ? null : Number(selected));
                }
            }}
        >
            <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={VALUE_NONE}>— Pilih marga —</SelectItem>
                {margas.map((marga) => (
                    <SelectItem key={marga.id} value={String(marga.id)}>
                        {marga.name}
                    </SelectItem>
                ))}
                <SelectItem value={NEW_MARGA_VALUE}>
                    ＋ Buat marga baru…
                </SelectItem>
            </SelectContent>
        </Select>
    );
}

export default function FamilyForm({ person, margas, nameSuggestions, nomorUsed, lockedMarga }: Props) {
    const isEdit = person !== null;

    const { data, setData, transform, post, put, processing, errors } = useForm({
        name: person?.name ?? '',
        gender: person?.gender ?? '',
        alias: person?.alias ?? '',
        marga_id: person?.marga_id ?? lockedMarga?.id ?? null,
        new_marga: person?.new_marga ?? '',
        birth_order: person?.birth_order ?? 1,
        sibling_count:
            person?.sibling_count ?? Math.max(person?.children.length ?? 1, 1),
        nomor: person?.nomor_manual ? (person?.nomor ?? '') : '',
        birth_year: person?.birth_year ?? '',
        death_year: person?.death_year ?? '',
        image: person?.image ?? '',
        bio: person?.bio ?? '',
        father: person?.father
            ? {
                  name: person.father.name ?? '',
                  birth_year: person.father.birth_year ?? '',
                  death_year: person.father.death_year ?? '',
                  marga_id: person.father.marga_id ?? lockedMarga?.id ?? null,
                  new_marga: '',
                  nomor: person.father.nomor ?? null,
              }
            : emptyParent(),
        mother: person?.mother
            ? {
                  name: person.mother.name ?? '',
                  birth_year: person.mother.birth_year ?? '',
                  death_year: person.mother.death_year ?? '',
                  marga_id: person.mother.marga_id ?? null,
                  new_marga: '',
              }
            : emptyParent(),
        children:
            person?.children && person.children.length > 0
                ? person.children.map((child) => ({
                      id: child.id ?? null,
                      name: child.name ?? '',
                      gender: child.gender ?? '',
                      spouse: child.spouse ?? '',
                      spouse_marga: child.spouse_marga ?? '',
                      marga_id: child.marga_id ?? lockedMarga?.id ?? null,
                      new_marga: '',
                      nomor: child.nomor ?? '',
                  }))
                : [emptyRow()],
    });

    transform((currentData) => {
        const withMarga = lockedMarga
            ? {
                  ...currentData,
                  marga_id: lockedMarga.id,
                  new_marga: '',
                  father: currentData.father
                      ? { ...currentData.father, marga_id: lockedMarga.id, new_marga: '' }
                      : null,
                  children: (currentData.children ?? []).map((child) => ({
                      ...child,
                      marga_id: lockedMarga.id,
                      new_marga: '',
                  })),
              }
            : currentData;

        const sorted = sortChildrenByNomor(withMarga.children ?? []);
        const focusIndex =
            person?.id != null
                ? sorted.findIndex((child) => child.id === person.id)
                : (Number(withMarga.birth_order) || 1) - 1;

        return {
            ...withMarga,
            children: sorted,
            birth_order: Math.max(1, (focusIndex >= 0 ? focusIndex : 0) + 1),
        };
    });

    const birthOrder = Number(data.birth_order) || 1;
    const siblingCount = Number(data.sibling_count) || 1;

    const trimmedNomor = data.nomor.trim();
    const nomorConflict = trimmedNomor
        ? nomorUsed.find((entry) => entry.nomor === trimmedNomor)
        : undefined;

    useEffect(() => {
        if (data.children.length === 0) {
            return;
        }

        const focusIndex = person?.id != null
            ? data.children.findIndex((child) => child.id === person.id)
            : Math.min(birthOrder - 1, data.children.length - 1);

        if (focusIndex < 0) {
            return;
        }

        setData(
            'children',
            data.children.map((child, index) =>
                index === focusIndex
                    ? {
                          ...child,
                          id: person?.id ?? child.id,
                          name: data.name,
                          gender: data.gender,
                          alias: data.alias,
                          marga_id: data.marga_id,
                          new_marga: data.new_marga,
                          nomor: data.nomor || null,
                          birth_year: data.birth_year || null,
                          death_year: data.death_year || null,
                          image: data.image || null,
                          bio: data.bio || null,
                      }
                    : child,
            ),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        data.name,
        data.gender,
        data.alias,
        data.marga_id,
        data.new_marga,
        data.nomor,
        data.birth_year,
        data.death_year,
        data.image,
        data.bio,
        birthOrder,
        person?.id,
    ]);

    useEffect(() => {
        const sorted = sortChildrenByNomor(data.children);
        const changed = sorted.some((child, index) => child !== data.children[index]);

        if (changed) {
            setData('children', sorted);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.children]);

    useEffect(() => {
        const needed = Math.max(siblingCount, birthOrder);

        if (data.children.length < needed) {
            setData('children', [
                ...data.children,
                ...Array.from({ length: needed - data.children.length }, emptyRow),
            ]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [birthOrder, siblingCount]);

    const setChild = (
        index: number,
        key: 'name' | 'gender' | 'spouse' | 'spouse_marga' | 'nomor',
        value: string,
    ) => {
        const next = data.children.map((child, i) =>
            i === index ? { ...child, [key]: value } : child,
        );
        setData('children', next);
    };

    const setChildMarga = (index: number, margaId: number | null) => {
        const next = data.children.map((child, i) =>
            i === index ? { ...child, marga_id: margaId } : child,
        );
        setData('children', next);
    };

    const setChildNewMarga = (index: number, name: string) => {
        const next = data.children.map((child, i) =>
            i === index ? { ...child, new_marga: name } : child,
        );
        setData('children', next);
    };

    const addChild = () => {
        setData('children', [...data.children, emptyRow()]);
    };

    const removeChild = (index: number) => {
        setData(
            'children',
            data.children.filter((_, i) => i !== index),
        );
    };

    const setParentEntry = (
        key: 'father' | 'mother',
        field: 'name' | 'birth_year' | 'death_year',
        value: string,
    ) => {
        setData(key, { ...data[key], [field]: value });
    };

    const setParentMarga = (
        key: 'father' | 'mother',
        margaId: number | null,
    ) => {
        setData(key, { ...data[key], marga_id: margaId });
    };

    const setParentNewMarga = (key: 'father' | 'mother', name: string) => {
        setData(key, { ...data[key], new_marga: name });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && person?.id) {
            put(people.update(person.id).url);
        } else {
            post(people.store().url);
        }
    };

    const renderParentBlock = (
        key: 'father' | 'mother',
        label: string,
        birthPlace: string,
        deathPlace: string,
        showMarga = true,
    ) => (
        <div className="space-y-4 rounded-lg border border-tb-outline-variant p-4">
            <p className="text-sm font-medium text-tb-on-surface">{label}</p>
            <div className="grid gap-1.5">
                <Label htmlFor={`${key}-name`} className="text-tb-on-surface">
                    Nama {label}
                </Label>
                <NameCombobox
                    value={data[key].name}
                    onChange={(value) => setParentEntry(key, 'name', value)}
                    suggestions={nameSuggestions}
                    placeholder={`Nama ${label.toLowerCase()}`}
                    allowNa
                />
                <InputError message={errors[`${key}.name`]} />
            </div>
            {showMarga && (
                <div className="grid gap-1.5">
                    <Label className="text-tb-on-surface">Marga {label}</Label>
                    <MargaField
                        value={data[key].marga_id ?? null}
                        newMarga={data[key].new_marga ?? ''}
                        onValue={(value) => setParentMarga(key, value)}
                        onNewMarga={(value) => setParentNewMarga(key, value)}
                        margas={margas}
                        placeholder={`Marga ${label.toLowerCase()}`}
                        disabled={lockedMarga !== null}
                    />
                    <InputError message={errors[`${key}.marga_id`]} />
                </div>
            )}
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-1.5">
                    <Label className="text-tb-on-surface">Tahun Lahir</Label>
                    <Input
                        value={data[key].birth_year}
                        onChange={(e) =>
                            setParentEntry(key, 'birth_year', e.target.value)
                        }
                        placeholder={birthPlace}
                        maxLength={4}
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    <InputError message={errors[`${key}.birth_year`]} />
                </div>
                <div className="grid gap-1.5">
                    <Label className="text-tb-on-surface">Tahun Wafat</Label>
                    <Input
                        value={data[key].death_year}
                        onChange={(e) =>
                            setParentEntry(key, 'death_year', e.target.value)
                        }
                        placeholder={deathPlace}
                        maxLength={4}
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    <InputError message={errors[`${key}.death_year`]} />
                </div>
            </div>
        </div>
    );

    return (
        <form onSubmit={submit} className="grid max-w-4xl gap-6">
            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="font-display text-lg text-tb-on-surface">
                            Informasi Pribadi
                        </CardTitle>
                        <CardDescription>
                            Data dasar anggota yang sedang dicatat dalam jejak
                            keluarga.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="name"
                                className="text-tb-on-surface"
                            >
                                Nama Lengkap{' '}
                                <span className="text-red-600">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Mis. Ompu Sitorus"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.name} />
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
                                        setData('alias', e.target.value)
                                    }
                                    placeholder="Tuan Sorba Dibanua"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.alias} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    Jenis Kelamin
                                </Label>
                                <Select
                                    value={data.gender || ''}
                                    onValueChange={(value) =>
                                        setData('gender', value)
                                    }
                                >
                                    <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                        <SelectValue placeholder="Pilih" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="L">
                                            Laki-laki (L)
                                        </SelectItem>
                                        <SelectItem value="P">
                                            Perempuan (P)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.gender} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    Marga
                                </Label>
                                <MargaField
                                    value={data.marga_id}
                                    newMarga={data.new_marga}
                                    onValue={(value) =>
                                        setData('marga_id', value)
                                    }
                                    onNewMarga={(value) =>
                                        setData('new_marga', value)
                                    }
                                    margas={margas}
                                    disabled={lockedMarga !== null}
                                />
                                <InputError message={errors.marga_id} />
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    Anak ke
                                </Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={data.birth_order ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'birth_order',
                                            Number(e.target.value),
                                        )
                                    }
                                    placeholder="2"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.birth_order} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    dari total bersaudara
                                </Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={data.sibling_count ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'sibling_count',
                                            Number(e.target.value),
                                        )
                                    }
                                    placeholder="5"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.sibling_count} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="nomor"
                                    className="text-tb-on-surface"
                                >
                                    Nomor Silsilah
                                </Label>
                                <Input
                                    id="nomor"
                                    value={data.nomor}
                                    onChange={(e) =>
                                        setData('nomor', e.target.value)
                                    }
                                    placeholder="otomatis (mis. 1.2.1)"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <p className="text-xs text-tb-on-surface-variant">
                                    Kosongkan untuk nomor otomatis berjenjang;
                                    isi untuk koreksi manual.
                                </p>
                                {nomorConflict && (
                                    <p className="text-xs font-medium text-amber-600 dark:text-amber-400">
                                        Nomor silsilah &ldquo;{nomorConflict.nomor}&rdquo; sudah dipakai oleh{' '}
                                        {nomorConflict.name}.
                                    </p>
                                )}
                                <InputError message={errors.nomor} />
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
                                        setData('birth_year', e.target.value)
                                    }
                                    placeholder="1920"
                                    maxLength={4}
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.birth_year} />
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
                                        setData('death_year', e.target.value)
                                    }
                                    placeholder="2001"
                                    maxLength={4}
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.death_year} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="image"
                                className="text-tb-on-surface"
                            >
                                URL Foto
                            </Label>
                            <Input
                                id="image"
                                type="url"
                                value={data.image}
                                onChange={(e) =>
                                    setData('image', e.target.value)
                                }
                                placeholder="https://..."
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.image} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="bio" className="text-tb-on-surface">
                                Biografi
                            </Label>
                            <textarea
                                id="bio"
                                value={data.bio}
                                onChange={(e) => setData('bio', e.target.value)}
                                rows={4}
                                placeholder="Cerita singkat tentang anggota ini..."
                                className="w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm shadow-xs outline-none focus:border-tb-primary focus:ring-tb-primary/20 focus-visible:ring-[3px]"
                            />
                            <InputError message={errors.bio} />
                        </div>
                    </CardContent>
                </Card>
                <SiblingListCard
                    children={data.children}
                    focusIndex={birthOrder - 1}
                    focusPersonId={person?.id ?? null}
                />
            </div>

            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader>
                    <CardTitle className="font-display text-lg text-tb-on-surface">
                        Orang Tua
                    </CardTitle>
                    <CardDescription>
                        Ayah dan ibu dari anak-anak yang dicatat di bawah.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 lg:grid-cols-2">
                    {renderParentBlock('father', 'Ayah', '1950', '2020')}
                    {renderParentBlock('mother', 'Ibu', '1955', '2025', false)}
                </CardContent>
            </Card>

            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="font-display text-lg text-tb-on-surface">
                            Daftar Saudara
                        </CardTitle>
                        <CardDescription>
                            Total bersaudara: {siblingCount} — menambah total otomatis menambah baris di bawah.
                            Baris tanpa nomor silsilah tidak tampil di "List Silsilah" sampai disimpan (nomor kosong diisi otomatis).
                        </CardDescription>
                    </div>
                    <div className="text-xs text-tb-on-surface-variant">
                        Urut 01, 02, …
                    </div>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {data.children.map((child, index) => {
                        const focused =
                            person?.id != null
                                ? child.id === person.id
                                : index === birthOrder - 1;

                        return (
                            <div
                                key={child.id ?? `new-${index}`}
                                className={cn(
                                    'grid gap-3 rounded-lg border p-3',
                                    focused
                                        ? 'border-tb-primary/50 bg-tb-primary/5'
                                        : 'border-tb-outline-variant bg-tb-surface-bright',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span
                                        className={cn(
                                            'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                            focused
                                                ? 'bg-tb-primary text-white'
                                                : 'bg-tb-surface-container text-tb-on-surface-variant',
                                        )}
                                    >
                                        Urut{' '}
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        {focused && (
                                            <span className="text-[11px] font-medium text-tb-primary">
                                                (Anda sedang diedit)
                                            </span>
                                        )}
                                        {!child.id && (
                                            <button
                                                type="button"
                                                onClick={() => removeChild(index)}
                                                aria-label="Hapus baris"
                                                className="inline-flex h-6 w-6 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-950"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </span>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label>
                                            {focused
                                                ? 'Nama (ini Anda)'
                                                : 'Nama'}
                                        </Label>
                                        {focused ? (
                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm font-medium text-tb-on-surface">
                                                {data.name || '—'}
                                            </div>
                                        ) : (
                                            <NameCombobox
                                                value={child.name}
                                                onChange={(value) =>
                                                    setChild(
                                                        index,
                                                        'name',
                                                        value,
                                                    )
                                                }
                                                suggestions={nameSuggestions}
                                                placeholder="Nama saudara"
                                                allowNa
                                            />
                                        )}
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Jenis Kelamin</Label>
                                        {focused ? (
                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                {data.gender || '—'}
                                            </div>
                                        ) : (
                                            <Select
                                                value={child.gender || ''}
                                                onValueChange={(value) =>
                                                    setChild(
                                                        index,
                                                        'gender',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                                    <SelectValue placeholder="L/P" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="L">
                                                        L
                                                    </SelectItem>
                                                    <SelectItem value="P">
                                                        P
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label>Nomor Silsilah</Label>
                                        {focused ? (
                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                {data.nomor || '—'}
                                            </div>
                                        ) : (
                                            <Input
                                                value={child.nomor ?? ''}
                                                onChange={(e) =>
                                                    setChild(
                                                        index,
                                                        'nomor',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="otomatis (mis. 1.2.1)"
                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                            />
                                        )}
                                        <p className="text-xs text-tb-on-surface-variant">
                                            Kosongkan untuk nomor otomatis.
                                        </p>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Marga</Label>
                                        {focused ? (
                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                {margas.find(
                                                    (marga) =>
                                                        marga.id ===
                                                        data.marga_id,
                                                )?.name ??
                                                    (data.new_marga || '—')}
                                            </div>
                                        ) : (
                                            <MargaField
                                                value={child.marga_id ?? null}
                                                newMarga={child.new_marga ?? ''}
                                                onValue={(value) =>
                                                    setChildMarga(index, value)
                                                }
                                                onNewMarga={(value) =>
                                                    setChildNewMarga(
                                                        index,
                                                        value,
                                                    )
                                                }
                                                margas={margas}
                                                placeholder="Marga saudara"
                                                disabled={lockedMarga !== null}
                                            />
                                        )}
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Pasangan</Label>
                                        <Input
                                            value={child.spouse}
                                            onChange={(e) =>
                                                setChild(
                                                    index,
                                                    'spouse',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Nama pasangan"
                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Marga/Suku Lain</Label>
                                        <Input
                                            value={child.spouse_marga}
                                            onChange={(e) =>
                                                setChild(
                                                    index,
                                                    'spouse_marga',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Marga pasangan"
                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                    </div>
                                </div>
                            </div>
                        );
                    })}

                    <Button
                        type="button"
                        variant="outline"
                        onClick={addChild}
                        className="mt-1 w-full border-dashed border-tb-outline-variant text-tb-primary hover:bg-tb-primary/5"
                    >
                        <Plus className="size-4" /> Tambah Saudara
                    </Button>
                </CardContent>
            </Card>

            <div className="flex items-center gap-3 pb-6">
                <Button
                    type="submit"
                    disabled={processing}
                    className="rounded-full bg-tb-primary px-6 hover:bg-tb-primary-light"
                >
                    {processing
                        ? 'Menyimpan...'
                        : person
                          ? 'Simpan Perubahan'
                          : 'Tambah Keluarga'}
                </Button>
            </div>
        </form>
    );
}
