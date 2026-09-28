import { Head, router, useForm } from '@inertiajs/react';
import { Bot, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
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
import margaNewsTopics from '@/routes/marga-news-topics';

type Topic = {
    id: number;
    keyword: string;
    marga_id: number | null;
    marga: string | null;
    is_active: boolean;
    notes: string | null;
    news_count: number;
};

type AgentStatus = {
    configured: boolean;
    last_seen: string | null;
    last_ingest: {
        at: string;
        agent: string | null;
        accepted: number;
        duplicates: number;
    } | null;
    tasks_url: string;
    ingest_url: string;
};

const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const emptyForm = {
    keyword: '',
    marga_id: null as number | null,
    is_active: true,
    notes: '',
};

export default function MargaNewsTopics({
    topics,
    margas,
    agent,
}: {
    topics: Topic[];
    margas: { id: number; name: string }[];
    agent: AgentStatus;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm(emptyForm);

    const startEdit = (topic: Topic) => {
        setEditingId(topic.id);
        form.setData({
            keyword: topic.keyword,
            marga_id: topic.marga_id,
            is_active: topic.is_active,
            notes: topic.notes ?? '',
        });
    };

    const reset = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };

        if (editingId) {
            form.put(margaNewsTopics.update.url({ topic: editingId }), options);
        } else {
            form.post(margaNewsTopics.store.url(), options);
        }
    };

    const remove = (topic: Topic) => {
        if (window.confirm(`Hapus topik “${topic.keyword}”?`)) {
            router.delete(margaNewsTopics.destroy.url({ topic: topic.id }), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Topik Berita Marga" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        <Tags className="size-6 text-tb-primary" />
                        Topik Berita Marga
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Kata kunci yang dicari agen berita (Hermes Agent).
                        Hasilnya masuk ke Review sebelum tampil.
                    </p>
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col gap-2 p-4 text-sm">
                        <div className="flex items-center gap-2 font-semibold text-tb-on-surface">
                            <Bot className="size-4 text-tb-primary" /> Status
                            agen
                            {agent.configured ? (
                                <Badge variant="outline">Token terpasang</Badge>
                            ) : (
                                <Badge variant="destructive">
                                    MARGA_NEWS_AGENT_TOKEN belum diisi
                                </Badge>
                            )}
                        </div>
                        <p className="text-tb-on-surface-variant">
                            Terakhir mengambil tugas:{' '}
                            {agent.last_seen
                                ? dateTime.format(new Date(agent.last_seen))
                                : 'belum pernah'}
                        </p>
                        <p className="text-tb-on-surface-variant">
                            Kiriman terakhir:{' '}
                            {agent.last_ingest
                                ? `${dateTime.format(new Date(agent.last_ingest.at))} · ${agent.last_ingest.accepted} baru, ${agent.last_ingest.duplicates} duplikat${agent.last_ingest.agent ? ` · ${agent.last_ingest.agent}` : ''}`
                                : 'belum ada'}
                        </p>
                        <div className="grid gap-1 font-mono text-xs text-tb-on-surface-variant">
                            <span>GET {agent.tasks_url}</span>
                            <span>POST {agent.ingest_url}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="p-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-3 md:grid-cols-[1fr_12rem]"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="topic-keyword">
                                    Kata kunci
                                </Label>
                                <Input
                                    id="topic-keyword"
                                    value={form.data.keyword}
                                    onChange={(event) =>
                                        form.setData(
                                            'keyword',
                                            event.target.value,
                                        )
                                    }
                                    placeholder='Mis. "Borsak Junjungan" Silaban'
                                    maxLength={150}
                                />
                                <InputError message={form.errors.keyword} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="topic-marga">
                                    Khusus marga (opsional)
                                </Label>
                                <select
                                    id="topic-marga"
                                    value={form.data.marga_id ?? ''}
                                    onChange={(event) =>
                                        form.setData(
                                            'marga_id',
                                            event.target.value
                                                ? Number(event.target.value)
                                                : null,
                                        )
                                    }
                                    className="h-9 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-2 text-sm"
                                >
                                    <option value="">Semua marga</option>
                                    {margas.map((marga) => (
                                        <option key={marga.id} value={marga.id}>
                                            {marga.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.marga_id} />
                            </div>
                            <div className="grid gap-1.5 md:col-span-2">
                                <Label htmlFor="topic-notes">
                                    Catatan untuk agen (opsional)
                                </Label>
                                <Input
                                    id="topic-notes"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Mis. fokus berita wilayah Medan"
                                    maxLength={500}
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-3 md:col-span-2">
                                <label className="flex items-center gap-2 text-sm text-tb-on-surface">
                                    <Checkbox
                                        checked={form.data.is_active}
                                        onCheckedChange={(value) =>
                                            form.setData(
                                                'is_active',
                                                value === true,
                                            )
                                        }
                                    />
                                    Aktif dicari agen
                                </label>
                                <div className="ml-auto flex gap-2">
                                    {editingId && (
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
                                        {editingId ? (
                                            'Simpan perubahan'
                                        ) : (
                                            <>
                                                <Plus className="size-4" />{' '}
                                                Tambah topik
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-2">
                    {topics.map((topic) => (
                        <Card
                            key={topic.id}
                            className="border-tb-outline-variant bg-tb-surface-bright"
                        >
                            <CardContent className="flex flex-wrap items-center gap-3 p-3">
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-tb-on-surface">
                                        {topic.keyword}
                                    </p>
                                    <p className="text-xs text-tb-on-surface-variant">
                                        {topic.marga
                                            ? `Marga ${topic.marga}`
                                            : 'Semua marga'}{' '}
                                        · {topic.news_count} berita
                                        {topic.notes ? ` · ${topic.notes}` : ''}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        topic.is_active ? 'default' : 'outline'
                                    }
                                >
                                    {topic.is_active ? 'Aktif' : 'Nonaktif'}
                                </Badge>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => startEdit(topic)}
                                    aria-label={`Ubah ${topic.keyword}`}
                                >
                                    <Pencil className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => remove(topic)}
                                    aria-label={`Hapus ${topic.keyword}`}
                                    className="text-red-600 hover:text-red-700"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

MargaNewsTopics.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Topik Berita Marga', href: margaNewsTopics.index() },
    ],
};
