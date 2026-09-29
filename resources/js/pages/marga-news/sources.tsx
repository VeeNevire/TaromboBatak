import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, Globe, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import margaNewsSources from '@/routes/marga-news-sources';

type TopicOption = { id: number; keyword: string };

type Source = {
    id: number;
    name: string;
    website_url: string;
    domain: string;
    is_active: boolean;
    applies_to_all_topics: boolean;
    topic_ids: number[];
    topics: string[];
    notes: string | null;
    news_count: number;
};

const emptyForm = {
    name: '',
    website_url: '',
    is_active: true,
    applies_to_all_topics: true,
    topic_ids: [] as number[],
    notes: '',
};

export default function MargaNewsSources({
    sources,
    topics,
}: {
    sources: Source[];
    topics: TopicOption[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm(emptyForm);

    const reset = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
    };

    const startEdit = (source: Source) => {
        setEditingId(source.id);
        form.setData({
            name: source.name,
            website_url: source.website_url,
            is_active: source.is_active,
            applies_to_all_topics: source.applies_to_all_topics,
            topic_ids: source.topic_ids,
            notes: source.notes ?? '',
        });
        form.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };

        if (editingId !== null) {
            form.put(
                margaNewsSources.update.url({ source: editingId }),
                options,
            );
        } else {
            form.post(margaNewsSources.store.url(), options);
        }
    };

    const remove = (source: Source) => {
        if (
            window.confirm(
                `Hapus sumber ${source.name}? Berita yang sudah masuk tetap tersimpan.`,
            )
        ) {
            router.delete(margaNewsSources.destroy.url({ source: source.id }), {
                preserveScroll: true,
            });
        }
    };

    const toggleTopic = (topicId: number, checked: boolean) => {
        form.setData(
            'topic_ids',
            checked
                ? [...form.data.topic_ids, topicId]
                : form.data.topic_ids.filter((id) => id !== topicId),
        );
    };

    return (
        <>
            <Head title="Sumber Website Berita" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        <Globe className="size-6 text-tb-primary" />
                        Sumber Website Berita
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Daftarkan website yang boleh dibaca Hermes. Hermes
                        membuka website dan artikel langsung, tanpa RSS. Semua
                        hasil tetap menunggu review admin.
                    </p>
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="p-4">
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="source-name">
                                        Nama website
                                    </Label>
                                    <Input
                                        id="source-name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Mis. Harian SIB"
                                        maxLength={120}
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="source-url">
                                        URL website
                                    </Label>
                                    <Input
                                        id="source-url"
                                        type="text"
                                        value={form.data.website_url}
                                        onChange={(event) =>
                                            form.setData(
                                                'website_url',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="contoh.id atau https://contoh.id"
                                        maxLength={2048}
                                    />
                                    <p className="text-xs text-tb-on-surface-variant">
                                        Jika protokol tidak diisi, alamat akan
                                        disimpan dengan https://.
                                    </p>
                                    <InputError
                                        message={form.errors.website_url}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="source-notes">
                                    Catatan untuk Hermes (opsional)
                                </Label>
                                <Input
                                    id="source-notes"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Mis. fokus berita adat dan kegiatan punguan"
                                    maxLength={1000}
                                />
                                <InputError message={form.errors.notes} />
                            </div>

                            <div className="flex flex-wrap gap-x-6 gap-y-3">
                                <label className="flex items-center gap-2 text-sm text-tb-on-surface">
                                    <Checkbox
                                        checked={form.data.is_active}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'is_active',
                                                checked === true,
                                            )
                                        }
                                    />
                                    Aktif sebagai sumber
                                </label>
                                <label className="flex items-center gap-2 text-sm text-tb-on-surface">
                                    <Checkbox
                                        checked={
                                            form.data.applies_to_all_topics
                                        }
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'applies_to_all_topics',
                                                checked === true,
                                            )
                                        }
                                    />
                                    Berlaku untuk semua topik
                                </label>
                            </div>
                            <InputError
                                message={form.errors.applies_to_all_topics}
                            />

                            {!form.data.applies_to_all_topics && (
                                <fieldset className="grid gap-2 rounded-lg border border-tb-outline-variant p-3">
                                    <legend className="px-1 text-sm font-medium text-tb-on-surface">
                                        Pilih topik
                                    </legend>
                                    {topics.length === 0 ? (
                                        <p className="text-sm text-tb-on-surface-variant">
                                            Buat topik terlebih dahulu di menu
                                            Topik Berita Marga.
                                        </p>
                                    ) : (
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {topics.map((topic) => (
                                                <label
                                                    key={topic.id}
                                                    className="flex items-center gap-2 text-sm text-tb-on-surface"
                                                >
                                                    <Checkbox
                                                        checked={form.data.topic_ids.includes(
                                                            topic.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleTopic(
                                                                topic.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    {topic.keyword}
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </fieldset>
                            )}
                            <InputError message={form.errors.topic_ids} />

                            <div className="flex justify-end gap-2">
                                {editingId !== null && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={reset}
                                    >
                                        Batal
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {editingId !== null ? (
                                        'Simpan perubahan'
                                    ) : (
                                        <>
                                            <Plus className="size-4" />
                                            Tambah website
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-2">
                    {sources.length === 0 ? (
                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardContent className="p-6 text-center text-sm text-tb-on-surface-variant">
                                Belum ada website sumber. Tambahkan website
                                tepercaya agar Hermes mulai mencari berita.
                            </CardContent>
                        </Card>
                    ) : (
                        sources.map((source) => (
                            <Card
                                key={source.id}
                                className="border-tb-outline-variant bg-tb-surface-bright"
                            >
                                <CardContent className="flex flex-wrap items-center gap-3 p-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-semibold text-tb-on-surface">
                                            {source.name}
                                        </p>
                                        <a
                                            href={source.website_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-xs break-all text-tb-primary hover:underline"
                                        >
                                            {source.domain}
                                            <ExternalLink className="size-3 shrink-0" />
                                        </a>
                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                            {source.applies_to_all_topics
                                                ? 'Semua topik'
                                                : source.topics.join(', ')}{' '}
                                            · {source.news_count} berita
                                            {source.notes
                                                ? ` · ${source.notes}`
                                                : ''}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            source.is_active
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        {source.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </Badge>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => startEdit(source)}
                                        aria-label={`Ubah ${source.name}`}
                                    >
                                        <Pencil className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => remove(source)}
                                        aria-label={`Hapus ${source.name}`}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

MargaNewsSources.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sumber Website Berita', href: margaNewsSources.index() },
    ],
};
