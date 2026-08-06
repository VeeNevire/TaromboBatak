import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NameCombobox } from '@/components/ui/name-combobox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import people from '@/routes/people';

export type ChildRow = {
    id?: number | null;
    name: string;
    gender: string;
    spouse: string;
    spouse_marga: string;
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
    birth_year: string;
    death_year: string;
    image: string;
    bio: string;
    father: ParentEntry | null;
    mother: ParentEntry | null;
    children: ChildRow[];
};

type MargaOption = { id: number; name: string };

type Props = {
    person: FamilyData | null;
    margas: MargaOption[];
    nameSuggestions: string[];
};

const emptyRow = (): ChildRow => ({ id: null, name: '', gender: '', spouse: '', spouse_marga: '' });
const emptyParent = (): ParentEntry => ({ name: '', birth_year: '', death_year: '' });

export default function FamilyForm({ person, margas, nameSuggestions }: Props) {
    const isEdit = person !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: person?.name ?? '',
        gender: person?.gender ?? '',
        alias: person?.alias ?? '',
        marga_id: person?.marga_id ?? null,
        birth_order: person?.birth_order ?? 1,
        sibling_count: person?.sibling_count ?? Math.max(person?.children.length ?? 1, 1),
        nomor: person?.nomor ?? '',
        birth_year: person?.birth_year ?? '',
        death_year: person?.death_year ?? '',
        image: person?.image ?? '',
        bio: person?.bio ?? '',
        father: person?.father ?? emptyParent(),
        mother: person?.mother ?? emptyParent(),
        children:
            person?.children && person.children.length > 0
                ? person.children.map((child) => ({
                      id: child.id ?? null,
                      name: child.name ?? '',
                      gender: child.gender ?? '',
                      spouse: child.spouse ?? '',
                      spouse_marga: child.spouse_marga ?? '',
                  }))
                : [emptyRow()],
    });

    const birthOrder = Number(data.birth_order) || 1;
    const siblingCount = Number(data.sibling_count) || 1;

    useEffect(() => {
        const needed = Math.max(siblingCount, birthOrder);

        if (data.children.length < needed) {
            const toAdd = needed - data.children.length;
            setData('children', [...data.children, ...Array.from({ length: toAdd }, emptyRow)]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [birthOrder, siblingCount]);

    useEffect(() => {
        if (data.children.length === 0) {
            return;
        }

        const focusIndex = Math.min(birthOrder - 1, data.children.length - 1);

        setData('children', data.children.map((child, index) =>
            index === focusIndex
                ? {
                      ...child,
                      id: person?.id ?? child.id,
                      name: data.name,
                      gender: data.gender,
                      alias: data.alias,
                      nomor: data.nomor || null,
                      birth_year: data.birth_year || null,
                      death_year: data.death_year || null,
                      image: data.image || null,
                      bio: data.bio || null,
                  }
                : child,
        ));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        data.name,
        data.gender,
        data.alias,
        data.nomor,
        data.birth_year,
        data.death_year,
        data.image,
        data.bio,
        birthOrder,
        person?.id,
    ]);

    const setChild = (index: number, key: keyof ChildRow, value: string) => {
        const next = data.children.map((child, i) => (i === index ? { ...child, [key]: value } : child));
        setData('children', next);
    };

    const addChild = () => {
        setData('children', [...data.children, emptyRow()]);
    };

    const setParentEntry = (key: 'father' | 'mother', field: keyof ParentEntry, value: string) => {
        setData(key, { ...data[key], [field]: value });
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
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-1.5">
                    <Label className="text-tb-on-surface">Tahun Lahir</Label>
                    <Input
                        value={data[key].birth_year}
                        onChange={(e) => setParentEntry(key, 'birth_year', e.target.value)}
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
                        onChange={(e) => setParentEntry(key, 'death_year', e.target.value)}
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
            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader>
                    <CardTitle className="font-display text-lg text-tb-on-surface">Informasi Pribadi</CardTitle>
                    <CardDescription>Data dasar anggota yang sedang dicatat dalam jejak keluarga.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5">
                    <div className="grid gap-1.5">
                        <Label htmlFor="name" className="text-tb-on-surface">
                            Nama Lengkap <span className="text-red-600">*</span>
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Mis. Ompu Sitorus"
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-5 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="alias" className="text-tb-on-surface">
                                Alias / Gelar
                            </Label>
                            <Input
                                id="alias"
                                value={data.alias}
                                onChange={(e) => setData('alias', e.target.value)}
                                placeholder="Tuan Sorba Dibanua"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.alias} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-tb-on-surface">Jenis Kelamin</Label>
                            <Select
                                value={data.gender || ''}
                                onValueChange={(value) => setData('gender', value)}
                            >
                                <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                    <SelectValue placeholder="Pilih" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="L">Laki-laki (L)</SelectItem>
                                    <SelectItem value="P">Perempuan (P)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.gender} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-tb-on-surface">Marga</Label>
                            <Select
                                value={data.marga_id ? String(data.marga_id) : ''}
                                onValueChange={(value) => setData('marga_id', value ? Number(value) : null)}
                            >
                                <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                    <SelectValue placeholder="Pilih marga" />
                                </SelectTrigger>
                                <SelectContent>
                                    {margas.map((marga) => (
                                        <SelectItem key={marga.id} value={String(marga.id)}>
                                            {marga.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.marga_id} />
                        </div>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label className="text-tb-on-surface">Anak ke</Label>
                            <Input
                                type="number"
                                min={1}
                                value={data.birth_order ?? ''}
                                onChange={(e) => setData('birth_order', Number(e.target.value))}
                                placeholder="2"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.birth_order} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-tb-on-surface">dari total bersaudara</Label>
                            <Input
                                type="number"
                                min={1}
                                value={data.sibling_count ?? ''}
                                onChange={(e) => setData('sibling_count', Number(e.target.value))}
                                placeholder="5"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.sibling_count} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="nomor" className="text-tb-on-surface">
                                Nomor Silsilah
                            </Label>
                            <Input
                                id="nomor"
                                value={data.nomor}
                                onChange={(e) => setData('nomor', e.target.value)}
                                placeholder="001"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.nomor} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="birth_year" className="text-tb-on-surface">
                                Tahun Lahir
                            </Label>
                            <Input
                                id="birth_year"
                                value={data.birth_year}
                                onChange={(e) => setData('birth_year', e.target.value)}
                                placeholder="1920"
                                maxLength={4}
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.birth_year} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="death_year" className="text-tb-on-surface">
                                Tahun Wafat
                            </Label>
                            <Input
                                id="death_year"
                                value={data.death_year}
                                onChange={(e) => setData('death_year', e.target.value)}
                                placeholder="2001"
                                maxLength={4}
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.death_year} />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="image" className="text-tb-on-surface">
                            URL Foto
                        </Label>
                        <Input
                            id="image"
                            type="url"
                            value={data.image}
                            onChange={(e) => setData('image', e.target.value)}
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
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                        />
                        <InputError message={errors.bio} />
                    </div>
                </CardContent>
            </Card>

            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader>
                    <CardTitle className="font-display text-lg text-tb-on-surface">Orang Tua</CardTitle>
                    <CardDescription>Ayah dan ibu dari anak-anak yang dicatat di bawah.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 lg:grid-cols-2">
                    {renderParentBlock('father', 'Ayah', '1950', '2020')}
                    {renderParentBlock('mother', 'Ibu', '1955', '2025')}
                </CardContent>
            </Card>

            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="font-display text-lg text-tb-on-surface">Daftar Saudara</CardTitle>
                        <CardDescription>
                            Semua anak dari Ayah di atas ({siblingCount} baris). Baris "Anak ke {birthOrder}" di-highlight
                            sebagai orang yang sedang dicatat.
                        </CardDescription>
                    </div>
                    <div className="text-xs text-tb-on-surface-variant">Urut 01, 02, …</div>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {data.children.map((child, index) => {
                        const focused = index === birthOrder - 1;

                        return (
                            <div
                                key={index}
                                className={cn(
                                    'grid gap-3 rounded-lg border p-3 md:grid-cols-12',
                                    focused
                                        ? 'border-tb-primary/50 bg-tb-primary/5'
                                        : 'border-tb-outline-variant bg-tb-surface-bright',
                                )}
                            >
                                <div className="flex items-center gap-2 md:col-span-2 md:flex-col md:items-start">
                                    <span
                                        className={cn(
                                            'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                            focused
                                                ? 'bg-tb-primary text-white'
                                                : 'bg-tb-surface-container text-tb-on-surface-variant',
                                        )}
                                    >
                                        Urut {String(index + 1).padStart(2, '0')}
                                    </span>
                                    {focused && (
                                        <span className="text-[11px] font-medium text-tb-primary">
                                            (Anda sedang diedit)
                                        </span>
                                    )}
                                </div>
                                <div className="grid gap-1.5 md:col-span-4">
                                    <Label>{focused ? 'Nama (ini Anda)' : 'Nama'}</Label>
                                    {focused ? (
                                        <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm font-medium text-tb-on-surface">
                                            {data.name || '—'}
                                        </div>
                                    ) : (
                                        <NameCombobox
                                            value={child.name}
                                            onChange={(value) => setChild(index, 'name', value)}
                                            suggestions={nameSuggestions}
                                            placeholder="Nama saudara"
                                            allowNa
                                        />
                                    )}
                                </div>
                                <div className="grid gap-1.5 md:col-span-2">
                                    <Label>Jenis Kelamin</Label>
                                    {focused ? (
                                        <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                            {data.gender || '—'}
                                        </div>
                                    ) : (
                                        <Select
                                            value={child.gender || ''}
                                            onValueChange={(value) => setChild(index, 'gender', value)}
                                        >
                                            <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                                <SelectValue placeholder="L/P" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="L">L</SelectItem>
                                                <SelectItem value="P">P</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    )}
                                </div>
                                <div className="grid gap-1.5 md:col-span-2">
                                    <Label>Pasangan</Label>
                                    <Input
                                        value={child.spouse}
                                        onChange={(e) => setChild(index, 'spouse', e.target.value)}
                                        placeholder="Nama pasangan"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                </div>
                                <div className="grid gap-1.5 md:col-span-2">
                                    <Label>Marga/Suku Lain</Label>
                                    <Input
                                        value={child.spouse_marga}
                                        onChange={(e) => setChild(index, 'spouse_marga', e.target.value)}
                                        placeholder="Marga pasangan"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
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
                    {processing ? 'Menyimpan...' : person ? 'Simpan Perubahan' : 'Tambah Keluarga'}
                </Button>
            </div>
        </form>
    );
}