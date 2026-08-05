import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type MargaOption = { id: number; name: string };
type ParentOption = { id: number; name: string };

type PersonFormValue = {
    id: number;
    name: string;
    alias: string | null;
    marga_id: number | null;
    parent_id: number | null;
    birth_year: string | null;
    death_year: string | null;
    image: string | null;
    bio: string | null;
};

type Props = {
    person: PersonFormValue | null;
    margas: MargaOption[];
    parents: ParentOption[];
};

export default function PersonForm({ person, margas, parents }: Props) {
    const isEdit = person !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: person?.name ?? '',
        alias: person?.alias ?? '',
        marga_id: person?.marga_id ?? null,
        parent_id: person?.parent_id ?? null,
        birth_year: person?.birth_year ?? '',
        death_year: person?.death_year ?? '',
        image: person?.image ?? '',
        bio: person?.bio ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && person) {
            put(people.update(person.id).url);
        } else {
            post(people.store().url);
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Anggota' : 'Tambah Anggota'} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit text-tb-on-surface-variant">
                        <Link href={people.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Data Anggota
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {isEdit ? 'Ubah Anggota' : 'Tambah Anggota'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Lengkapi informasi anggota untuk dimasukkan ke silsilah tarombo.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Informasi Pribadi
                            </CardTitle>
                            <CardDescription>Data dasar anggota keluarga.</CardDescription>
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

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="alias" className="text-tb-on-surface">
                                        Alias / Gelar
                                    </Label>
                                    <Input
                                        id="alias"
                                        value={data.alias}
                                        onChange={(e) => setData('alias', e.target.value)}
                                        placeholder="Mis. Tuan Sorba Dibanua"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                    <InputError message={errors.alias} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label className="text-tb-on-surface">Marga</Label>
                                    <Select
                                        value={data.marga_id ? String(data.marga_id) : ''}
                                        onValueChange={(value) =>
                                            setData('marga_id', value ? Number(value) : null)
                                        }
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

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label className="text-tb-on-surface">Orang Tua</Label>
                                    <Select
                                        value={data.parent_id ? String(data.parent_id) : ''}
                                        onValueChange={(value) =>
                                            setData('parent_id', value ? Number(value) : null)
                                        }
                                    >
                                        <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                            <SelectValue placeholder="Tidak ada (leluhur)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {parents.map((parent) => (
                                                <SelectItem key={parent.id} value={String(parent.id)}>
                                                    {parent.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.parent_id} />
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

                    <div className="flex items-center gap-3 pb-6">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-tb-primary px-6 hover:bg-tb-primary-light"
                        >
                            {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Anggota'}
                        </Button>
                        <Button asChild variant="ghost" className="text-tb-on-surface-variant">
                            <Link href={people.index()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

PersonForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Form Anggota', href: people.create() },
    ],
};
