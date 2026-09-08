import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { relatedContent } from '@/actions/App/Http/Controllers/MargaController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import cerita from '@/routes/cerita';
import kegiatan from '@/routes/kegiatan';

type MargaSummary = {
    id: number;
    name: string;
    description: string | null;
    people_count: number;
};
type ContentTab = 'stories' | 'events' | 'statuses';
type ContentResult = {
    items: {
        id: number;
        title: string | null;
        body: string;
        author: string;
        date: string | null;
        location: string | null;
    }[];
    current_page: number;
    last_page: number;
    total: number;
};
const tabs = [
    { value: 'stories', label: 'Cerita Leluhur & Budaya' },
    { value: 'events', label: 'Event & Kegiatan' },
    { value: 'statuses', label: 'News Feed' },
] as const;

export default function MargaDetailDialog({
    marga,
    onClose,
}: {
    marga: MargaSummary;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<ContentTab>('stories');
    const [page, setPage] = useState(1);
    const [retry, setRetry] = useState(0);

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[85vh] overflow-y-auto border-tb-outline-variant bg-tb-surface-bright sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="font-display text-2xl text-tb-on-surface">
                        Marga {marga.name}
                    </DialogTitle>
                    <DialogDescription>
                        {marga.people_count} anggota · Cerita, kegiatan, dan
                        kabar terkait marga ini.
                    </DialogDescription>
                </DialogHeader>
                {marga.description && (
                    <p className="text-sm whitespace-pre-line text-tb-on-surface-variant">
                        {marga.description}
                    </p>
                )}
                <Tabs
                    defaultValue="stories"
                    value={tab}
                    onValueChange={(value) => {
                        setTab(value as ContentTab);
                        setPage(1);
                    }}
                >
                    <TabsList className="h-auto w-full flex-wrap justify-start gap-1">
                        {tabs.map((item) => (
                            <TabsTrigger
                                key={item.value}
                                value={item.value}
                                className="flex-1 text-xs sm:text-sm"
                            >
                                {item.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                    {tabs.map((item) => (
                        <TabsContent key={item.value} value={item.value}>
                            <RelatedContent
                                key={`${marga.id}-${tab}-${page}-${retry}`}
                                margaId={marga.id}
                                tab={tab}
                                page={page}
                                onPageChange={setPage}
                                onRetry={() => setRetry((value) => value + 1)}
                            />
                        </TabsContent>
                    ))}
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}

function RelatedContent({
    margaId,
    tab,
    page,
    onPageChange,
    onRetry,
}: {
    margaId: number;
    tab: ContentTab;
    page: number;
    onPageChange: (page: number) => void;
    onRetry: () => void;
}) {
    const [result, setResult] = useState<ContentResult | null>(null);
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        const controller = new AbortController();
        fetch(relatedContent(margaId, { query: { tab, page } }).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Konten tidak dapat dimuat');
                }

                return response.json() as Promise<ContentResult>;
            })
            .then((data) => {
                if (!controller.signal.aborted) {
                    setResult(data);
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setFailed(true);
                }
            });

        return () => controller.abort();
    }, [margaId, tab, page]);

    if (failed) {
        return (
            <div
                role="alert"
                className="grid justify-items-center gap-3 py-8 text-sm"
            >
                <p>Konten belum dapat dimuat.</p>
                <Button variant="outline" onClick={onRetry}>
                    Coba lagi
                </Button>
            </div>
        );
    }

    if (!result) {
        return (
            <p
                role="status"
                className="py-10 text-center text-sm text-tb-on-surface-variant"
            >
                Memuat konten terkait...
            </p>
        );
    }

    return (
        <div className="grid gap-3 pt-3">
            {result.items.length === 0 && (
                <p className="rounded-lg border border-dashed border-tb-outline-variant px-4 py-10 text-center text-sm text-tb-on-surface-variant">
                    Belum ada konten terkait marga ini yang dapat ditampilkan.
                </p>
            )}
            {result.items.map((item) => (
                <article
                    key={item.id}
                    className="grid gap-2 rounded-xl border border-tb-outline-variant p-4"
                >
                    <p className="text-xs text-tb-on-surface-variant">
                        {item.author} · {item.date}
                        {item.location && ` · ${item.location}`}
                    </p>
                    {item.title && (
                        <h3 className="font-display font-semibold text-tb-on-surface">
                            {item.title}
                        </h3>
                    )}
                    <p className="line-clamp-3 text-sm whitespace-pre-line text-tb-on-surface">
                        {item.body}
                    </p>
                    {tab === 'statuses' ? (
                        <details className="text-sm text-tb-on-surface">
                            <summary className="w-fit cursor-pointer font-medium text-tb-primary">
                                Lihat selengkapnya
                            </summary>
                            <p className="pt-3 whitespace-pre-line">
                                {item.body}
                            </p>
                        </details>
                    ) : (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="w-fit"
                        >
                            <Link
                                href={
                                    tab === 'stories'
                                        ? cerita.show(item.id)
                                        : kegiatan.show(item.id)
                                }
                            >
                                Lihat selengkapnya
                            </Link>
                        </Button>
                    )}
                </article>
            ))}
            {result.last_page > 1 && (
                <div className="flex items-center justify-between gap-2 pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={page <= 1}
                        onClick={() => onPageChange(page - 1)}
                    >
                        Sebelumnya
                    </Button>
                    <span className="text-xs text-tb-on-surface-variant">
                        {page} / {result.last_page} · {result.total} konten
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={page >= result.last_page}
                        onClick={() => onPageChange(page + 1)}
                    >
                        Berikutnya
                    </Button>
                </div>
            )}
        </div>
    );
}
