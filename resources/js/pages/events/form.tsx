import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import events from '@/routes/events';

type EventFormValue = {
    id: number;
    title: string;
    description: string;
    location: string | null;
    date: string;
    published: boolean;
};

type Props = {
    event: EventFormValue | null;
};

export default function EventForm({ event }: Props) {
    const isEdit = event !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        title: event?.title ?? '',
        description: event?.description ?? '',
        location: event?.location ?? '',
        date: event?.date ?? '',
        published: event?.published ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && event) {
            put(events.update(event.id).url);
        } else {
            post(events.store().url);
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Event' : 'Tambah Event'} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit text-tb-on-surface-variant">
                        <Link href={events.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Event
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {isEdit ? 'Ubah Event' : 'Tambah Event'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Event dengan status "Tampil" akan muncul di halaman utama.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Detail Event
                            </CardTitle>
                            <CardDescription>Informasi event & kegiatan komunitas.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="grid gap-1.5">
                                <Label htmlFor="title" className="text-tb-on-surface">
                                    Judul Event <span className="text-red-600">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Mis. Pesta Bona Taon Sitorus"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="date" className="text-tb-on-surface">
                                        Tanggal <span className="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        id="date"
                                        type="date"
                                        value={data.date}
                                        onChange={(e) => setData('date', e.target.value)}
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                    <InputError message={errors.date} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="location" className="text-tb-on-surface">
                                        Lokasi
                                    </Label>
                                    <Input
                                        id="location"
                                        value={data.location}
                                        onChange={(e) => setData('location', e.target.value)}
                                        placeholder="Mis. Balige, Toba"
                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                    />
                                    <InputError message={errors.location} />
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="description" className="text-tb-on-surface">
                                    Deskripsi <span className="text-red-600">*</span>
                                </Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={4}
                                    placeholder="Keterangan singkat event..."
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                />
                                <InputError message={errors.description} />
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
                            {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Event'}
                        </Button>
                        <Button asChild variant="ghost" className="text-tb-on-surface-variant">
                            <Link href={events.index()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

EventForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Event & Kegiatan', href: events.index() },
        { title: 'Form Event', href: events.create() },
    ],
};
