import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    ArrowLeft,
    CheckCheck,
    MessageCircle,
    RefreshCw,
    Search,
    Send,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import telegramMessages from '@/routes/telegram-messages';

type Dialog = {
    id: number;
    type: string;
    title: string;
    username: string | null;
    last_message_at: string | null;
    unread_count: number;
};
type SelectedDialog = Dialog & { telegram_peer_id: number };
type Message = {
    id: number;
    body: string | null;
    sender_name: string | null;
    media_type: string | null;
    is_outgoing: boolean;
    sent_at: string | null;
};
type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Props = {
    connected: boolean;
    dialogs: Paginated<Dialog>;
    selectedDialog: SelectedDialog | null;
    messages: Paginated<Message>;
    search: string;
};

const time = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : '';
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: '2-digit',
              month: 'short',
          }).format(new Date(value))
        : '';

export default function TelegramMessages({
    connected,
    dialogs,
    selectedDialog,
    messages,
    search,
}: Props) {
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;
    const [query, setQuery] = useState(search);
    const bottomRef = useRef<HTMLDivElement>(null);
    const ordered = useMemo(
        () => [...messages.data].reverse(),
        [messages.data],
    );

    useEffect(() => {
        if (!selectedDialog) {
return;
}

        if (selectedDialog.unread_count > 0) {
router.post(
                telegramMessages.read(selectedDialog.id).url,
                {},
                { preserveScroll: true, preserveState: true },
            );
}

        bottomRef.current?.scrollIntoView({ behavior: 'instant' });
    }, [selectedDialog?.id]);

    useEcho<{ dialog_id: number }>(
        `users.${auth.user.id}`,
        '.telegram.message.received',
        () =>
            router.reload({
                only: ['dialogs', 'selectedDialog', 'messages'],
                preserveScroll: true,
                preserveState: true,
            }),
        [selectedDialog?.id],
    );

    useEffect(() => {
        if (!selectedDialog) {
return;
}

        const timer = window.setInterval(
            () =>
                router.reload({
                    only: ['dialogs', 'selectedDialog', 'messages'],
                    preserveScroll: true,
                    preserveState: true,
                }),
            8000,
        );

        return () => window.clearInterval(timer);
    }, [selectedDialog?.id]);

    const url = (id?: number) =>
        telegramMessages.index({
            query: { dialog_id: id, search: query || undefined },
        }).url;
    const paginationUrl = (value: string | null) => {
        if (!value) {
return null;
}

        const next = new URL(value, window.location.origin);

        if (selectedDialog) {
next.searchParams.set('dialog_id', String(selectedDialog.id));
}

        if (query) {
next.searchParams.set('search', query);
}

        return next.toString();
    };
    const dialogItems = dialogs.data;
    const visibleDialogItems =
        selectedDialog &&
        !dialogItems.some((dialog) => dialog.id === selectedDialog.id)
            ? [selectedDialog, ...dialogItems]
            : dialogItems;

    return (
        <>
            <Head title="Pesan Telegram" />
            <div className="space-y-5">
                <Heading
                    variant="small"
                    title="Pesan Telegram"
                    description="Baca dan balas pesan dari chat, grup, dan channel Telegram Anda."
                />
                {!connected ? (
                    <Card className="flex flex-col items-center gap-3 p-10 text-center">
                        <MessageCircle className="size-10 text-tb-outline" />
                        <p className="font-semibold">
                            Telegram belum terhubung
                        </p>
                        <p className="text-sm text-tb-on-surface-variant">
                            Hubungkan akun Telegram terlebih dahulu untuk
                            melihat pesan.
                        </p>
                        <Button asChild>
                            <Link href="/settings/telegram/mtproto">
                                Hubungkan Telegram
                            </Link>
                        </Button>
                    </Card>
                ) : (
                    <div className="mx-auto grid min-h-[min(760px,calc(100vh-11rem))] max-w-7xl overflow-hidden rounded-xl border border-tb-outline-variant bg-tb-surface-bright shadow-sm md:grid-cols-[21rem_minmax(0,1fr)]">
                        <aside
                            className={`${selectedDialog ? 'hidden md:flex' : 'flex'} min-h-0 flex-col border-tb-outline-variant md:border-r`}
                        >
                            <header className="border-b border-tb-outline-variant px-4 py-4">
                                <div className="flex items-center gap-3">
                                    <div className="grid size-10 place-items-center rounded-lg bg-tb-primary text-white">
                                        <MessageCircle className="size-5" />
                                    </div>
                                    <div>
                                        <h1 className="font-display text-xl font-bold">
                                            Pesan Telegram
                                        </h1>
                                        <p className="text-xs text-tb-on-surface-variant">
                                            {dialogs.total} percakapan · halaman{' '}
                                            {dialogs.current_page}
                                        </p>
                                    </div>
                                    <Form
                                        action={telegramMessages.sync().url}
                                        method="post"
                                        options={{ preserveScroll: true }}
                                        className="ml-auto"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                size="icon"
                                                variant="ghost"
                                                className="rounded-full"
                                                title="Perbarui dialog"
                                                disabled={processing}
                                            >
                                                <RefreshCw
                                                    className={
                                                        processing
                                                            ? 'size-4 animate-spin'
                                                            : 'size-4'
                                                    }
                                                />
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        router.get(url(selectedDialog?.id));
                                    }}
                                    className="relative mt-4"
                                >
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-outline" />
                                    <Input
                                        type="search"
                                        value={query}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        placeholder="Cari chat, grup, atau channel"
                                        className="bg-tb-surface-container-low h-10 border-tb-outline-variant pl-9"
                                    />
                                </form>
                            </header>
                            <div className="min-h-0 flex-1 overflow-y-auto">
                                <div className="divide-y divide-tb-outline-variant">
                                    {visibleDialogItems.length === 0 ? (
                                        <div className="p-6 text-center text-sm text-tb-on-surface-variant">
                                            Belum ada percakapan. Klik tombol
                                            perbarui untuk mengambil data
                                            Telegram.
                                        </div>
                                    ) : (
                                        visibleDialogItems.map((dialog) => (
                                            <Link
                                                key={dialog.id}
                                                href={url(dialog.id)}
                                                className={`flex min-h-20 items-center gap-3 border-l-3 px-4 py-3 transition-colors ${selectedDialog?.id === dialog.id ? 'border-l-tb-primary bg-tb-primary/8' : 'hover:bg-tb-surface-container-low border-l-transparent'}`}
                                            >
                                                <div className="grid size-11 shrink-0 place-items-center rounded-full bg-tb-primary/10 text-tb-primary">
                                                    <Users className="size-5" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-baseline justify-between gap-2">
                                                        <p className="truncate text-sm font-semibold">
                                                            {dialog.title}
                                                        </p>
                                                        <time className="shrink-0 text-[11px] text-tb-outline">
                                                            {date(
                                                                dialog.last_message_at,
                                                            )}
                                                        </time>
                                                    </div>
                                                    <p className="mt-1 truncate text-xs text-tb-on-surface-variant capitalize">
                                                        {dialog.type}
                                                        {dialog.username
                                                            ? ` · @${dialog.username}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                {dialog.unread_count > 0 && (
                                                    <span className="grid size-5 shrink-0 place-items-center rounded-full bg-tb-primary text-[10px] font-semibold text-white">
                                                        {dialog.unread_count >
                                                        99
                                                            ? '99+'
                                                            : dialog.unread_count}
                                                    </span>
                                                )}
                                            </Link>
                                        ))
                                    )}
                                </div>
                                {dialogs.last_page > 1 && (
                                    <div className="flex items-center justify-between border-t border-tb-outline-variant px-3 py-2 text-xs text-tb-on-surface-variant">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={!dialogs.prev_page_url}
                                            onClick={() =>
                                                paginationUrl(
                                                    dialogs.prev_page_url,
                                                ) &&
                                                router.get(
                                                    paginationUrl(
                                                        dialogs.prev_page_url,
                                                    )!,
                                                )
                                            }
                                        >
                                            Sebelumnya
                                        </Button>
                                        <span>
                                            {dialogs.from}–{dialogs.to} dari{' '}
                                            {dialogs.total}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={!dialogs.next_page_url}
                                            onClick={() =>
                                                paginationUrl(
                                                    dialogs.next_page_url,
                                                ) &&
                                                router.get(
                                                    paginationUrl(
                                                        dialogs.next_page_url,
                                                    )!,
                                                )
                                            }
                                        >
                                            Berikutnya
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </aside>
                        <section
                            className={`${selectedDialog ? 'flex' : 'hidden md:flex'} bg-tb-surface-container-low/40 min-h-0 min-w-0 flex-col`}
                        >
                            {selectedDialog ? (
                                <>
                                    <header className="flex h-17 shrink-0 items-center gap-3 border-b border-tb-outline-variant bg-tb-surface-bright px-3 md:px-5">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                            className="rounded-full md:hidden"
                                        >
                                            <Link href={url()}>
                                                <ArrowLeft />
                                            </Link>
                                        </Button>
                                        <div className="grid size-10 place-items-center rounded-full bg-tb-primary/10 text-tb-primary">
                                            <Users className="size-5" />
                                        </div>
                                        <div className="min-w-0">
                                            <h2 className="truncate text-sm font-semibold">
                                                {selectedDialog.title}
                                            </h2>
                                            <p className="text-xs text-tb-on-surface-variant capitalize">
                                                {selectedDialog.type}
                                                {selectedDialog.username
                                                    ? ` · @${selectedDialog.username}`
                                                    : ''}
                                            </p>
                                        </div>
                                    </header>
                                    <div className="flex min-h-0 flex-1 flex-col overflow-y-auto bg-[#edf4f8] p-4 dark:bg-tb-surface-container/40">
                                        {messages.current_page <
                                            messages.last_page && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="mx-auto mb-3 rounded-full bg-white/80"
                                                onClick={() =>
                                                    router.get(
                                                        telegramMessages.index({
                                                            query: {
                                                                dialog_id:
                                                                    selectedDialog.id,
                                                                page:
                                                                    messages.current_page +
                                                                    1,
                                                                search:
                                                                    query ||
                                                                    undefined,
                                                            },
                                                        }).url,
                                                    )
                                                }
                                            >
                                                Muat pesan sebelumnya
                                            </Button>
                                        )}
                                        {ordered.length === 0 ? (
                                            <div className="m-auto flex flex-col items-center gap-2 text-center text-tb-on-surface-variant">
                                                <MessageCircle className="size-9" />
                                                <p>
                                                    Belum ada pesan di
                                                    percakapan ini.
                                                </p>
                                                <p className="text-xs">
                                                    Pesan baru akan muncul
                                                    otomatis saat listener
                                                    aktif.
                                                </p>
                                            </div>
                                        ) : (
                                            <div className="space-y-2">
                                                {ordered.map((message) => (
                                                    <div
                                                        key={message.id}
                                                        className={`flex ${message.is_outgoing ? 'justify-end' : 'justify-start'}`}
                                                    >
                                                        <div
                                                            className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm shadow-sm md:max-w-[70%] ${message.is_outgoing ? 'rounded-br-sm bg-tb-primary text-white' : 'rounded-bl-sm bg-white text-tb-on-surface'}`}
                                                        >
                                                            {!message.is_outgoing &&
                                                                message.sender_name && (
                                                                    <p className="mb-1 text-xs font-semibold text-tb-primary">
                                                                        {
                                                                            message.sender_name
                                                                        }
                                                                    </p>
                                                                )}
                                                            {message.media_type && (
                                                                <p
                                                                    className={`mb-1 text-xs ${message.is_outgoing ? 'text-white/75' : 'text-tb-on-surface-variant'}`}
                                                                >
                                                                    📎{' '}
                                                                    {
                                                                        message.media_type
                                                                    }
                                                                </p>
                                                            )}
                                                            {message.body ? (
                                                                <p className="break-words whitespace-pre-wrap">
                                                                    {
                                                                        message.body
                                                                    }
                                                                </p>
                                                            ) : (
                                                                !message.media_type && (
                                                                    <p className="italic opacity-70">
                                                                        Pesan
                                                                        tanpa
                                                                        teks
                                                                    </p>
                                                                )
                                                            )}
                                                            <div
                                                                className={`mt-1 flex items-center justify-end gap-1 text-[10px] ${message.is_outgoing ? 'text-white/70' : 'text-tb-outline'}`}
                                                            >
                                                                <span>
                                                                    {time(
                                                                        message.sent_at,
                                                                    )}
                                                                </span>
                                                                {message.is_outgoing && (
                                                                    <CheckCheck className="size-3" />
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                                <div ref={bottomRef} />
                                            </div>
                                        )}
                                    </div>
                                    <Form
                                        action={
                                            telegramMessages.reply(
                                                selectedDialog.id,
                                            ).url
                                        }
                                        method="post"
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing, errors }) => (
                                            <div className="border-t border-tb-outline-variant bg-tb-surface-bright p-3 md:p-4">
                                                <div className="bg-tb-surface-container-low flex items-end gap-2 rounded-2xl border border-tb-outline-variant p-1.5 focus-within:border-tb-primary">
                                                    <textarea
                                                        name="body"
                                                        rows={1}
                                                        maxLength={4096}
                                                        placeholder="Tulis balasan..."
                                                        className="min-h-10 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm outline-none"
                                                        required
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="icon"
                                                        className="rounded-xl"
                                                        disabled={processing}
                                                        title="Kirim"
                                                    >
                                                        <Send className="size-4" />
                                                    </Button>
                                                </div>
                                                <InputError
                                                    message={errors.body}
                                                    className="mt-1"
                                                />
                                            </div>
                                        )}
                                    </Form>
                                </>
                            ) : (
                                <div className="m-auto flex max-w-sm flex-col items-center gap-3 p-8 text-center text-tb-on-surface-variant">
                                    <MessageCircle className="size-12" />
                                    <h2 className="font-semibold text-tb-on-surface">
                                        Pilih percakapan
                                    </h2>
                                    <p className="text-sm">
                                        Pilih chat, grup, atau channel di
                                        sebelah kiri untuk membaca pesan.
                                    </p>
                                </div>
                            )}
                        </section>
                    </div>
                )}
            </div>
        </>
    );
}

TelegramMessages.layout = {
    breadcrumbs: [{ title: 'Pesan Telegram', href: telegramMessages.index() }],
};
