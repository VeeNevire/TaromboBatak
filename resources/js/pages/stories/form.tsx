import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import stories from '@/routes/stories';

type StoryFormValue = {
    id: number;
    title: string;
    description: string;
    image: string | null;
    published: boolean;
};

type Props = {
    story: StoryFormValue | null;
};

export default function StoryForm({ story }: Props) {
    const isEdit = story !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        title: story?.title ?? '',
        description: story?.description ?? '',
        image: story?.image ?? '',
        published: story?.published ?? true,
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
                    <Button asChild variant="ghost" size="sm" className="w-fit text-tb-on-surface-variant">
                        <Link href={stories.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Cerita
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {isEdit ? 'Ubah Cerita' : 'Tambah Cerita'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Cerita akan tampil di halaman utama bila statusnya "Tampil".
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Detail Cerita
                            </CardTitle>
                            <CardDescription>Judul, deskripsi, dan gambar cerita.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="grid gap-1.5">
                                <Label htmlFor="title" className="text-tb-on-surface">
                                    Judul <span className="text-red-600">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Mis. Asal Usul Marga Sitorus"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="description" className="text-tb-on-surface">
                                    Deskripsi <span className="text-red-600">*</span>
                                </Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={5}
                                    placeholder="Ringkasan cerita leluhur atau budaya..."
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="image" className="text-tb-on-surface">
                                    URL Gambar
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

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="published"
                                    checked={data.published}
                                    onCheckedChange={(checked) => setData('published', checked === true)}
                                    className="rounded border-tb-outline-variant text-tb-primary focus:ring-tb-primary"
                                />
                                <Label htmlFor="published" className="text-sm text-tb-on-surface cursor-pointer">
                                    Tampilkan di halaman utama
                                </Label>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3 pb-6">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-tb-primary px-6 hover:bg-tb-primary-light"
                        >
                            {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Cerita'}
                        </Button>
                        <Button asChild variant="ghost" className="text-tb-on-surface-variant">
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
