import { Head, Link, router } from '@inertiajs/react';
import { Check, ExternalLink, Newspaper, Pencil, X } from 'lucide-react';
import { useState } from 'react';
import {
    MargaChips,
    Pager,
    formatNewsDate,
} from '@/components/marga-news/news-parts';
import type {
    MargaNewsItem,
    Paginated,
} from '@/components/marga-news/news-parts';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import margaNews from '@/routes/marga-news';

type Status = 'pending' | 'approved' | 'rejected';

type ReviewItem = MargaNewsItem & {
    topic: string | null;
    submitted_by: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    created_at: string | null;
};

type MargaOption = { id: number; name: string };

const TABS: { status: Status; label: string }[] = [
    { status: 'pending', label: 'Menunggu' },
    { status: 'approved', label: 'Disetujui' },
    { status: 'rejected', label: 'Ditolak' },
];

function MargaTagEditor({
    item,
    margas,
    onDone,
}: {
    item: ReviewItem;
    margas: MargaOption[];
    onDone: () => void;
}) {
    const [ids, setIds] = useState(item.margas.map((marga) => marga.id));
    const [saving, setSaving] = useState(false);
    const byId = new Map(margas.map((marga) => [marga.id, marga]));

    const save = () => {
        setSaving(true);
        router.put(
            margaNews.margas.update.url({ margaNews: item.id }),
            { marga_ids: ids },
            {
                preserveScroll: true,
                onSuccess: onDone,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-2 rounded-lg border border-tb-outline-variant p-3">
            <div className="flex flex-wrap gap-1.5">
                {ids.length === 0 && (
                    <span className="text-xs text-tb-on-surface-variant">
                        Belum ada marga.
                    </span>
                )}
                {ids.map((id) => (
                    <span
                        key={id}
                        className="inline-flex items-center gap-1 rounded-full bg-tb-surface-container px-2 py-0.5 text-xs font-semibold text-tb-on-surface"
                    >
                        {byId.get(id)?.name ?? id}
                        <button
                            type="button"
                            onClick={() =>
                                setIds((current) =>
                                    current.filter((value) => value !== id),
                                )
                            }
                            aria-label={`Hapus ${byId.get(id)?.name ?? ''}`}
                            className="text-tb-on-surface-variant hover:text-red-600"
                        >
                            <X className="size-3" />
                        </button>
                    </span>
                ))}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <select
                    value=""
                    onChange={(event) => {
                        const id = Number(event.target.value);

                        if (id && !ids.includes(id)) {
                            setIds((current) => [...current, id]);
                        }
                    }}
                    aria-label="Tambah marga"
                    className="rounded-md border border-tb-outline-variant bg-tb-surface-bright px-2 py-1 text-xs"
                >
                    <option value="">+ Tambah marga…</option>
                    {margas
                        .filter((marga) => !ids.includes(marga.id))
                        .map((marga) => (
                            <option key={marga.id} value={marga.id}>
                                {marga.name}
                            </option>
                        ))}
                </select>
                <Button
                    type="button"
                    size="sm"
                    onClick={save}
                    disabled={saving}
                >
                    Simpan
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onDone}
                >
                    Batal
                </Button>
            </div>
        </div>
    );
}

export default function MargaNewsReview({
    status,
    counts,
    pendingHermesCount,
    news,
    margas,
}: {
    status: Status;
    counts: Partial<Record<Status, number>>;
    pendingHermesCount: number;
    news: Paginated<ReviewItem>;
    margas: MargaOption[];
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const allSelected =
        news.data.length > 0 && selected.length === news.data.length;

    const decide = (action: 'approve' | 'reject', ids: number[]) => {
        if (ids.length === 0) {
            return;
        }

        setBusy(true);
        router.post(
            margaNews.decide.url(),
            { action, ids },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
                onFinish: () => setBusy(false),
            },
        );
    };

    const toggle = (id: number, checked: boolean) =>
        setSelected((current) =>
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );

    return (
        <>
            <Head title="Review Berita Marga" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        <Newspaper className="size-6 text-tb-primary" />
                        Review Berita Marga
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Berita yang ditemukan agen. Hanya yang disetujui tampil
                        di{' '}
                        <Link
                            href={margaNews.index()}
                            className="font-semibold text-tb-primary hover:underline"
                        >
                            Berita Marga-Marga
                        </Link>
                        .
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {TABS.map((tab) => (
                        <Link
                            key={tab.status}
                            href={margaNews.review.url({
                                query: { status: tab.status },
                            })}
                            preserveScroll
                            className={cn(
                                'rounded-full border px-3 py-1.5 text-sm font-semibold transition-colors',
                                tab.status === status
                                    ? 'text-tb-on-primary border-tb-primary bg-tb-primary'
                                    : 'border-tb-outline-variant text-tb-on-surface hover:bg-tb-surface-container',
                            )}
                        >
                            {tab.label} ({counts[tab.status] ?? 0})
                        </Link>
                    ))}
                </div>

                {status === 'pending' && pendingHermesCount > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/30">
                        <p className="text-sm text-tb-on-surface">
                            Ada {pendingHermesCount} berita hasil pencarian
                            Hermes yang menunggu review.
                        </p>
                        <Button
                            type="button"
                            disabled={busy}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `Setujui semua ${pendingHermesCount} berita yang ditemukan Hermes?`,
                                    )
                                ) {
                                    setBusy(true);
                                    router.post(
                                        margaNews.approveAllHermes.url(),
                                        {},
                                        {
                                            preserveScroll: true,
                                            onFinish: () => setBusy(false),
                                        },
                                    );
                                }
                            }}
                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <Check className="size-4" />
                            {busy
                                ? 'Memproses…'
                                : `Setujui semua berita Hermes (${pendingHermesCount})`}
                        </Button>
                    </div>
                )}

                {news.data.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 py-2">
                        <label className="flex items-center gap-2 text-sm text-tb-on-surface">
                            <Checkbox
                                checked={allSelected}
                                onCheckedChange={(value) =>
                                    setSelected(
                                        value === true
                                            ? news.data.map((item) => item.id)
                                            : [],
                                    )
                                }
                            />
                            Pilih semua ({selected.length} dipilih)
                        </label>
                        <div className="ml-auto flex gap-2">
                            {status !== 'approved' && (
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={busy || selected.length === 0}
                                    onClick={() => decide('approve', selected)}
                                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                                >
                                    <Check className="size-4" /> Setujui
                                </Button>
                            )}
                            {status !== 'rejected' && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={busy || selected.length === 0}
                                    onClick={() => decide('reject', selected)}
                                >
                                    <X className="size-4" /> Tolak
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                {news.data.length === 0 ? (
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="py-10 text-center text-sm text-tb-on-surface-variant">
                            Tidak ada berita di tab ini.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex flex-col gap-3">
                        {news.data.map((item) => (
                            <Card
                                key={item.id}
                                className="border-tb-outline-variant bg-tb-surface-bright"
                            >
                                <CardContent className="flex gap-3 p-4">
                                    <Checkbox
                                        checked={selected.includes(item.id)}
                                        onCheckedChange={(value) =>
                                            toggle(item.id, value === true)
                                        }
                                        aria-label={`Pilih ${item.title}`}
                                        className="mt-1"
                                    />
                                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                                        {item.image_url && (
                                            <img
                                                src={item.image_url}
                                                alt={`Gambar berita: ${item.title}`}
                                                loading="lazy"
                                                className="max-h-72 w-full rounded-lg object-cover"
                                            />
                                        )}
                                        <div className="flex flex-wrap items-center gap-x-2 text-xs text-tb-on-surface-variant">
                                            {item.publisher && (
                                                <span className="font-semibold text-tb-primary">
                                                    {item.publisher}
                                                </span>
                                            )}
                                            {formatNewsDate(
                                                item.published_at,
                                            ) && (
                                                <span>
                                                    ·{' '}
                                                    {formatNewsDate(
                                                        item.published_at,
                                                    )}
                                                </span>
                                            )}
                                            {item.topic && (
                                                <span>
                                                    · topik “{item.topic}”
                                                </span>
                                            )}
                                            {item.submitted_by && (
                                                <span>
                                                    · dari {item.submitted_by}
                                                </span>
                                            )}
                                        </div>
                                        <a
                                            href={item.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="group inline-flex items-start gap-1.5 font-semibold text-tb-on-surface hover:text-tb-primary"
                                        >
                                            {item.title}
                                            <ExternalLink className="mt-1 size-3.5 shrink-0 opacity-50 group-hover:opacity-100" />
                                        </a>
                                        {(item.summary ?? item.excerpt) && (
                                            <p className="text-sm text-tb-on-surface-variant">
                                                {item.summary ?? item.excerpt}
                                            </p>
                                        )}
                                        {item.content && (
                                            <details className="rounded-lg border border-tb-outline-variant px-3 py-2">
                                                <summary className="cursor-pointer text-sm font-semibold text-tb-primary">
                                                    Baca isi lengkap artikel
                                                    (200+ kata)
                                                </summary>
                                                <p className="mt-3 text-sm leading-7 whitespace-pre-line text-tb-on-surface-variant">
                                                    {item.content}
                                                </p>
                                            </details>
                                        )}
                                        {editingId === item.id ? (
                                            <MargaTagEditor
                                                item={item}
                                                margas={margas}
                                                onDone={() =>
                                                    setEditingId(null)
                                                }
                                            />
                                        ) : (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <MargaChips
                                                    margas={item.margas}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setEditingId(item.id)
                                                    }
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-tb-primary hover:underline"
                                                >
                                                    <Pencil className="size-3" />
                                                    {item.margas.length > 0
                                                        ? 'Ubah marga'
                                                        : 'Tandai marga'}
                                                </button>
                                            </div>
                                        )}
                                        {item.reviewer && (
                                            <p className="text-[11px] text-tb-on-surface-variant">
                                                Direview oleh {item.reviewer}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 flex-col gap-2">
                                        {status !== 'approved' && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                disabled={busy}
                                                onClick={() =>
                                                    decide('approve', [item.id])
                                                }
                                                aria-label="Setujui"
                                                title="Setujui"
                                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                                            >
                                                <Check className="size-4" />
                                            </Button>
                                        )}
                                        {status !== 'rejected' && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                disabled={busy}
                                                onClick={() =>
                                                    decide('reject', [item.id])
                                                }
                                                aria-label="Tolak"
                                                title="Tolak"
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Pager page={news} />
            </div>
        </>
    );
}

MargaNewsReview.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Review Berita Marga', href: margaNews.review() },
    ],
};
