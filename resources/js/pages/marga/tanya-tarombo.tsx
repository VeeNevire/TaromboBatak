import { Head, Link, router, useForm } from '@inertiajs/react';
import { Bot, LoaderCircle, RotateCcw, Search, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import margaRoutes from '@/routes/marga';

type Message = { id: number; role: 'user' | 'assistant'; text: string };
type Marga = {
    id: number;
    name: string;
    color?: string | null;
    description?: string | null;
};

export default function TanyaTarombo({
    marga,
    margas,
    messages,
    conversationId,
    conversations,
}: {
    marga: Marga | null;
    margas: Marga[];
    messages: Message[];
    conversationId: number;
    conversations: { id: number; created_at: string }[];
}) {
    const form = useForm({ question: '', conversation_id: conversationId });
    const [tab, setTab] = useState<'chat' | 'margas'>('chat');
    const [search, setSearch] = useState('');
    const [pendingQuestion, setPendingQuestion] = useState('');
    const [startingNew, setStartingNew] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    const busy = form.processing || startingNew;
    const filteredMargas = margas.filter((row) =>
        row.name
            .toLocaleLowerCase('id')
            .includes(search.trim().toLocaleLowerCase('id')),
    );
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ block: 'nearest' });
    }, [messages.length, pendingQuestion, tab]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const question = form.data.question.trim();

        if (!question || busy) {
            return;
        }

        setPendingQuestion(question);
        form.transform((data) => ({
            ...data,
            question,
            conversation_id: conversationId,
        }));
        form.post(
            marga
                ? margaRoutes.ai.ask(marga.id).url
                : margaRoutes.ai.askAll().url,
            {
                preserveScroll: true,
                onSuccess: () => form.reset('question'),
                onFinish: () => setPendingQuestion(''),
            },
        );
    };
    const startNew = () => {
        if (busy) {
            return;
        }

        setStartingNew(true);
        router.post(
            marga
                ? margaRoutes.ai.newConversation(marga.id).url
                : margaRoutes.ai.newGeneralConversation().url,
            {},
            {
                preserveState: false,
                onFinish: () => setStartingNew(false),
            },
        );
    };
    const openConversation = (id: number) => {
        router.get(
            marga
                ? margaRoutes.ai.show(marga.id, { query: { conversation: id } })
                      .url
                : margaRoutes.ai.select({ query: { conversation: id } }).url,
        );
    };

    return (
        <>
            <Head title={`Tanya Ito Tarombo · ${marga?.name ?? 'Semua'}`} />
            <div className="flex min-h-full flex-1 flex-col gap-6 bg-tb-surface p-4 text-tb-on-surface md:p-6 lg:p-8">
                <div>
                    {marga && (
                        <Link
                            href={margaRoutes.documents.index(marga.id)}
                            className="text-sm text-tb-primary"
                        >
                            ← Dokumen {marga.name}
                        </Link>
                    )}
                    <h1 className="mt-2 flex items-center gap-2 font-display text-2xl font-bold">
                        <Bot className="size-6 text-tb-primary" /> Tanya Ito
                        Tarombo
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        {marga
                            ? `Tanyakan hal seputar tarombo dan marga ${marga.name}.`
                            : 'Tanyakan hal seputar tarombo dan semua marga yang dapat Anda akses.'}{' '}
                        Percakapan tersimpan di akun Anda.
                    </p>
                </div>
                <nav
                    aria-label="Lingkup percakapan"
                    className="flex gap-2 border-b border-tb-outline-variant pb-3"
                >
                    <Button
                        asChild
                        variant={
                            !marga && tab === 'chat' ? 'default' : 'outline'
                        }
                        disabled={busy}
                    >
                        <Link href={margaRoutes.ai.select()}>Semua</Link>
                    </Button>
                    <Button
                        type="button"
                        variant={
                            marga || tab === 'margas' ? 'default' : 'outline'
                        }
                        disabled={busy}
                        onClick={() => setTab('margas')}
                    >
                        Marga-Marga
                    </Button>
                    {marga && tab === 'margas' && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setTab('chat')}
                        >
                            Kembali ke {marga.name}
                        </Button>
                    )}
                </nav>
                {tab === 'margas' ? (
                    <section className="space-y-4">
                        <h2 className="font-display text-xl font-bold">
                            Pilih marga untuk bertanya
                        </h2>
                        <div className="relative max-w-xl">
                            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-tb-on-surface-variant" />
                            <Input
                                aria-label="Cari marga"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari marga…"
                                className="pl-9"
                            />
                        </div>
                        <p
                            className="text-sm text-tb-on-surface-variant"
                            role="status"
                        >
                            {filteredMargas.length} marga ditemukan
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredMargas.map((row) => (
                                <Link
                                    key={row.id}
                                    href={margaRoutes.ai.show(row.id)}
                                    className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-5 font-medium shadow-sm transition hover:border-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary"
                                >
                                    {row.name}
                                </Link>
                            ))}
                            {filteredMargas.length === 0 && (
                                <p className="col-span-full py-8 text-center text-sm text-tb-on-surface-variant">
                                    Marga tidak ditemukan.
                                </p>
                            )}
                        </div>
                    </section>
                ) : (
                    <Card className="flex min-h-[55vh] flex-1 flex-col rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardHeader className="flex flex-wrap items-center justify-between gap-3 sm:flex-row">
                            <CardTitle className="text-lg">
                                Percakapan · {marga?.name ?? 'Semua'}
                            </CardTitle>
                            <div className="flex flex-wrap items-center gap-2">
                                {conversations.length > 1 && (
                                    <select
                                        aria-label="Riwayat percakapan"
                                        value={conversationId}
                                        disabled={busy}
                                        onChange={(event) =>
                                            openConversation(
                                                Number(event.target.value),
                                            )
                                        }
                                        className="h-9 max-w-56 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-2 text-sm"
                                    >
                                        {conversations.map((conversation) => (
                                            <option
                                                key={conversation.id}
                                                value={conversation.id}
                                            >
                                                Percakapan {conversation.id} ·{' '}
                                                {new Date(
                                                    conversation.created_at,
                                                ).toLocaleDateString('id-ID')}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={startNew}
                                >
                                    <RotateCcw className="size-4" /> Percakapan
                                    baru
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col gap-4">
                            <div
                                className="max-h-[60vh] min-h-64 flex-1 space-y-3 overflow-y-auto rounded-xl bg-tb-surface p-4"
                                role="log"
                                aria-label="Pesan percakapan"
                                aria-live="polite"
                            >
                                {messages.length === 0 && !pendingQuestion && (
                                    <p className="py-16 text-center text-sm text-tb-on-surface-variant">
                                        Mulai dengan pertanyaan tentang{' '}
                                        {marga?.name ?? 'tarombo dan marga'}.
                                    </p>
                                )}
                                {messages.map((message) => (
                                    <div
                                        key={message.id}
                                        className={`w-fit max-w-[90%] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap sm:max-w-[75%] ${message.role === 'user' ? 'ml-auto bg-tb-primary text-white' : 'bg-tb-surface-bright text-tb-on-surface'}`}
                                    >
                                        {message.text}
                                    </div>
                                ))}
                                {pendingQuestion && (
                                    <>
                                        <div className="ml-auto w-fit max-w-[90%] rounded-2xl bg-tb-primary px-4 py-3 text-sm whitespace-pre-wrap text-white sm:max-w-[75%]">
                                            {pendingQuestion}
                                        </div>
                                        <p className="flex items-center gap-2 text-sm text-tb-on-surface-variant">
                                            <LoaderCircle className="size-4 animate-spin" />{' '}
                                            Ito Tarombo sedang menjawab…
                                        </p>
                                    </>
                                )}
                                <div ref={bottomRef} />
                            </div>
                            <form onSubmit={submit} className="flex gap-2">
                                <textarea
                                    aria-label="Pertanyaan untuk Ito Tarombo"
                                    className="min-h-16 min-w-0 flex-1 resize-none rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm"
                                    value={form.data.question}
                                    onChange={(event) =>
                                        form.setData(
                                            'question',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Tulis pertanyaan..."
                                    rows={2}
                                    maxLength={4000}
                                    disabled={busy}
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    disabled={
                                        busy || !form.data.question.trim()
                                    }
                                    aria-label="Kirim pertanyaan"
                                >
                                    <Send className="size-4" />
                                </Button>
                            </form>
                            {form.errors.question && (
                                <p className="text-sm text-destructive">
                                    {form.errors.question}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

TanyaTarombo.layout = {
    breadcrumbs: [
        { title: 'Tanya Ito Tarombo', href: margaRoutes.ai.select() },
    ],
};
