import { Link, router } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
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
import margaRoutes from '@/routes/marga';

type MargaSummary = {
    id: number;
    name: string;
    description: string | null;
    people_count: number;
    contributors: {
        id: number;
        name: string;
        role: 'contributor_main' | 'contributor_member';
    }[];
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
    canSendMessage,
    openMessageOnMount = false,
    onClose,
}: {
    marga: MargaSummary;
    canSendMessage: boolean;
    openMessageOnMount?: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<ContentTab>('stories');
    const [page, setPage] = useState(1);
    const [retry, setRetry] = useState(0);
    const [messageRecipientId, setMessageRecipientId] = useState<number | null>(null);
    const [messageBody, setMessageBody] = useState('');
    const [sendingMessage, setSendingMessage] = useState(false);
    const recipient = marga.contributors.find(
        (contributor) => contributor.id === messageRecipientId,
    );

    useEffect(() => {
        if (openMessageOnMount && marga.contributors.length > 0) {
            setMessageRecipientId(marga.contributors[0].id);
        }
    }, [marga.contributors, openMessageOnMount]);

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
                {canSendMessage && marga.contributors.length > 0 && (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-fit"
                        onClick={() =>
                            setMessageRecipientId(marga.contributors[0].id)
                        }
                    >
                        <MessageCircle className="size-4" /> Kirim Pesan
                    </Button>
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
                <Dialog
                    open={messageRecipientId !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setMessageRecipientId(null);
                            setMessageBody('');
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Kirim Pesan ke Kontributor</DialogTitle>
                            <DialogDescription>
                                Pesan akan dikirim ke Kontributor Utama atau Anggota untuk marga {marga.name}.
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();

                                if (!recipient || !messageBody.trim()) {
                                    return;
                                }

                                setSendingMessage(true);
                                router.post(
                                    margaRoutes.contributors.messages.store([
                                        marga.id,
                                        recipient.id,
                                    ]).url,
                                    { body: messageBody.trim() },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setMessageRecipientId(null);
                                            setMessageBody('');
                                        },
                                        onFinish: () => setSendingMessage(false),
                                    },
                                );
                            }}
                        >
                            <div className="grid gap-2">
                                <label htmlFor="marga-contributor" className="text-sm font-medium text-tb-on-surface">
                                    Penerima
                                </label>
                                <select
                                    id="marga-contributor"
                                    value={messageRecipientId ?? ''}
                                    onChange={(event) => setMessageRecipientId(Number(event.target.value))}
                                    className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface"
                                >
                                    {marga.contributors.map((contributor) => (
                                        <option key={contributor.id} value={contributor.id}>
                                            {contributor.name} ({contributor.role === 'contributor_main' ? 'Kontributor Utama' : 'Kontributor Anggota'})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <label htmlFor="marga-message" className="text-sm font-medium text-tb-on-surface">
                                    Pesan
                                </label>
                                <textarea
                                    id="marga-message"
                                    value={messageBody}
                                    onChange={(event) => setMessageBody(event.target.value)}
                                    maxLength={2000}
                                    rows={5}
                                    placeholder="Tulis pesan untuk kontributor..."
                                    className="resize-none rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm text-tb-on-surface"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setMessageRecipientId(null)}>
                                    Batal
                                </Button>
                                <Button type="submit" disabled={sendingMessage || !messageBody.trim()}>
                                    {sendingMessage ? 'Mengirim...' : 'Kirim Pesan'}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
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
