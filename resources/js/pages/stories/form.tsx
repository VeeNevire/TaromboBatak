import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import stories from '@/routes/stories';

type StoryFormValue = {
    id: number;
    title: string;
    description: string;
    image: string | null;
    published: boolean;
    classification: 'umum' | 'marga';
    marga_id: number | null;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
};

type Props = {
    story: StoryFormValue | null;
    margas: { id: number; name: string }[];
    lockedMarga: { id: number; name: string } | null;
    canPublish: boolean;
    canChooseMarga: boolean;
    canCreateMarga: boolean;
};

export default function StoryForm({
    story,
    margas,
    lockedMarga,
    canPublish,
    canChooseMarga,
    canCreateMarga,
}: Props) {
    const isEdit = story !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        title: story?.title ?? '',
        description: story?.description ?? '',
        image: story?.image ?? '',
        published: story?.published ?? canPublish,
        classification: story?.classification ?? 'umum',
        marga_id: story?.marga_id ? String(story.marga_id) : '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && story) {
            put(stories.update(story.id).url);
        } else {
            post(stories.store().url);
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Cerita' : 'Tambah Cerita'} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="w-fit text-tb-on-surface-variant"
                    >
                        <Link href={stories.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Cerita
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {isEdit ? 'Ubah Cerita' : 'Tambah Cerita'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            {canPublish
                                ? 'Cerita yang disetujui dapat ditampilkan di halaman publik.'
                                : 'Cerita akan ditinjau Kontributor sebelum tampil ke publik.'}
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Detail Cerita
                            </CardTitle>
                            <CardDescription>
                                Judul, deskripsi, dan gambar cerita.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            {!canPublish && (
                                <div className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                                    Cerita dari User Biasa akan berstatus
                                    menunggu sampai disetujui Kontributor.
                                </div>
                            )}
                            {story?.status === 'rejected' && (
                                <div className="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                                    Cerita sebelumnya ditolak
                                    {story.rejection_reason
                                        ? `: ${story.rejection_reason}`
                                        : '.'}{' '}
                                    Simpan perubahan untuk mengajukan ulang.
                                </div>
                            )}

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="classification"
                                    className="text-tb-on-surface"
                                >
                                    Klasifikasi{' '}
                                    <span className="text-red-600">*</span>
                                </Label>
                                <Select
                                    value={data.classification}
                                    onValueChange={(value: 'umum' | 'marga') =>
                                        setData('classification', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="classification"
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright"
                                    >
                                        <SelectValue placeholder="Pilih klasifikasi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="umum">
                                            Umum
                                        </SelectItem>
                                        <SelectItem
                                            value="marga"
                                            disabled={!canCreateMarga}
                                        >
                                            Marga
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.classification} />
                                <p className="text-xs text-tb-on-surface-variant">
                                    Umum berlaku untuk seluruh komunitas. Marga
                                    khusus untuk marga yang dipilih.
                                </p>
                            </div>

                            {data.classification === 'marga' &&
                                (lockedMarga ? (
                                    <div className="grid gap-1.5">
                                        <Label className="text-tb-on-surface">
                                            Marga
                                        </Label>
                                        <div className="flex h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                            {lockedMarga.name}
                                        </div>
                                    </div>
                                ) : canChooseMarga ? (
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="marga_id"
                                            className="text-tb-on-surface"
                                        >
                                            Marga{' '}
                                            <span className="text-red-600">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={data.marga_id}
                                            onValueChange={(value) =>
                                                setData('marga_id', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="marga_id"
                                                className="w-full border-tb-outline-variant bg-tb-surface-bright"
                                                aria-invalid={!!errors.marga_id}
                                            >
                                                <SelectValue placeholder="Pilih marga cerita" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {margas.map((marga) => (
                                                    <SelectItem
                                                        key={marga.id}
                                                        value={String(marga.id)}
                                                    >
                                                        {marga.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.marga_id} />
                                    </div>
                                ) : (
                                    <div className="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                                        Akun Anda belum memiliki marga. Pilih
                                        klasifikasi Umum untuk mengajukan
                                        cerita.
                                    </div>
                                ))}

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="title"
                                    className="text-tb-on-surface"
                                >
                                    Judul{' '}
                                    <span className="text-red-600">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                    placeholder="Mis. Asal Usul Marga Sitorus"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="description"
                                    className="text-tb-on-surface"
                                >
                                    Deskripsi{' '}
                                    <span className="text-red-600">*</span>
                                </Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    rows={5}
                                    placeholder="Ringkasan cerita leluhur atau budaya..."
                                    className="w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm shadow-xs outline-none focus:border-tb-primary focus:ring-tb-primary/20 focus-visible:ring-[3px]"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="image"
                                    className="text-tb-on-surface"
                                >
                                    URL Gambar
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

                            {canPublish && (
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="published"
                                        checked={data.published}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'published',
                                                checked === true,
                                            )
                                        }
                                        className="rounded border-tb-outline-variant text-tb-primary focus:ring-tb-primary"
                                    />
                                    <Label
                                        htmlFor="published"
                                        className="cursor-pointer text-sm text-tb-on-surface"
                                    >
                                        Tampilkan di halaman utama
                                    </Label>
                                </div>
                            )}
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
                                : isEdit
                                  ? 'Simpan Perubahan'
                                  : canPublish
                                    ? 'Tambah Cerita'
                                    : 'Ajukan Cerita'}
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-tb-on-surface-variant"
                        >
                            <Link href={stories.index()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

StoryForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cerita Leluhur & Budaya', href: stories.index() },
        { title: 'Form Cerita', href: stories.create() },
    ],
};
