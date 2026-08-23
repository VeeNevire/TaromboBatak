import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import {
    ArrowLeft,
    MessageCircle,
    Search,
    Send,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { useDeferredValue, useEffect, useRef, useState } from 'react';
import { AppAvatar } from '@/components/app-avatar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import contacts from '@/routes/contacts';

type Contact = {
    id: number;
    name: string;
    color: string | null;
    role_label: string;
    latest_message: string | null;
    latest_message_at: string | null;
    unread_count: number;
};

type ChatMessage = {
    id: number;
    sender_id: number;
    body: string;
    created_at: string | null;
    read_at: string | null;
    is_mine: boolean;
};

type PendingMessage = {
    uid: string;
    body: string;
    sent_at: string;
    contact_id: number;
};

type ChatViewMessage = Omit<ChatMessage, 'id'> & { id: number | string };

type Props = {
    contacts: Contact[];
    selectedContact: Contact | null;
    messages: ChatMessage[];
};

export default function ContactsIndex({
    contacts: contactItems,
    selectedContact,
    messages,
}: Props) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState('');
    const [extraMessages, setExtraMessages] = useState<ChatMessage[]>([]);
    const [pendingMessages, setPendingMessages] = useState<PendingMessage[]>(
        [],
    );
    const deferredSearch = useDeferredValue(search.trim().toLocaleLowerCase());
    const connectionStatus = useConnectionStatus();
    const messageEndRef = useRef<HTMLDivElement>(null);
    const messageContainerRef = useRef<HTMLDivElement>(null);
    const prevContactIdRef = useRef<number | null | undefined>(undefined);
    const contactReloadTimerRef = useRef<number | null>(null);
    const messageForm = useForm({ body: '' });

    useEffect(() => {
        return () => {
            if (contactReloadTimerRef.current !== null) {
                window.clearTimeout(contactReloadTimerRef.current);
            }
        };
    }, []);

    // Badge/preview daftar kontak diperbarui paling cepat sekali per 2 detik
    // agar pesan beruntun tidak memicu reload partial berulang-ulang.
    const scheduleContactsReload = () => {
        if (contactReloadTimerRef.current !== null) {
            return;
        }

        contactReloadTimerRef.current = window.setTimeout(() => {
            contactReloadTimerRef.current = null;
            router.reload({ only: ['contacts'] });
        }, 2000);
    };

    const filteredContacts = contactItems.filter((contact) =>
        contact.name.toLocaleLowerCase().includes(deferredSearch),
    );
    const persistedMessageIds = new Set(messages.map((message) => message.id));
    const visibleMessages: ChatViewMessage[] = [
        ...messages,
        ...extraMessages.filter(
            (message) =>
                !persistedMessageIds.has(message.id) &&
                (selectedContact == null ||
                    message.sender_id === selectedContact.id ||
                    message.is_mine),
        ),
        ...pendingMessages
            .filter((pending) => pending.contact_id === selectedContact?.id)
            .map((pending) => ({
                id: `pending-${pending.uid}`,
                sender_id: auth.user.id,
                body: pending.body,
                created_at: pending.sent_at,
                read_at: null,
                is_mine: true,
            })),
    ];

    // ID pesan tertinggi yang sudah diketahui klien, dipakai sebagai
    // cursor incremental untuk fetch senyap.
    const latestMessageIdRef = useRef(0);

    useEffect(() => {
        for (const message of [...messages, ...extraMessages]) {
            if (
                typeof message.id === 'number' &&
                message.id > latestMessageIdRef.current
            ) {
                latestMessageIdRef.current = message.id;
            }
        }
    }, [messages, extraMessages]);

    const ingestExternal = (
        items: Array<{
            id: number;
            sender_id: number;
            body: string;
            created_at: string | null;
        }>,
    ) => {
        if (items.length === 0) {
            return;
        }

        setExtraMessages((current) => {
            const knownIds = new Set(current.map((message) => message.id));
            const additions = items
                .filter((item) => !knownIds.has(item.id))
                .map((item) => ({
                    id: item.id,
                    sender_id: item.sender_id,
                    body: item.body,
                    created_at: item.created_at,
                    read_at: null,
                    is_mine: item.sender_id === auth.user.id,
                }));

            return additions.length > 0 ? [...current, ...additions] : current;
        });
    };

    const fetchNewMessages = async (contactId: number) => {
        try {
            const response = await fetch(
                contacts.messages.index(contactId, {
                    query: { after_id: latestMessageIdRef.current },
                }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                return;
            }

            const data = (await response.json()) as {
                messages?: Array<{
                    id: number;
                    sender_id: number;
                    body: string;
                    created_at: string | null;
                }>;
            };

            ingestExternal(data.messages ?? []);
        } catch {
            // Offline: coba lagi pada siklus berikutnya.
        }
    };

    // Cadangan saat WebSocket terputus: tarik pesan baru lewat fetch JSON
    // murni — sengaja di luar router Inertia supaya tidak pernah memicu
    // reload/swap halaman yang bisa mengganggu ketikan, fokus, atau scroll.
    useEffect(() => {
        if (connectionStatus === 'connected' || selectedContact == null) {
            return;
        }

        const contactId = selectedContact.id;

        void fetchNewMessages(contactId);

        const interval = setInterval(() => {
            void fetchNewMessages(contactId);
        }, 4000);

        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [connectionStatus, selectedContact]);

    useEcho<{
        id: number;
        sender_id: number;
        body: string;
        created_at: string | null;
    }>(
        `users.${auth.user.id}`,
        '.message.sent',
        (message) => {
            ingestExternal([message]);
            scheduleContactsReload();
        },
        [selectedContact?.id],
    );

    useEffect(() => {
        const container = messageContainerRef.current;

        if (!container) {
            return;
        }

        const contactChanged =
            (selectedContact?.id ?? null) !== prevContactIdRef.current;

        prevContactIdRef.current = selectedContact?.id ?? null;

        // Ganti percakapan: langsung ke pesan terakhir tanpa animasi.
        if (contactChanged) {
            container.scrollTop = container.scrollHeight;

            return;
        }

        // Pesan baru hanya menggulung saat pengguna memang berada di dekat
        // dasar percakapan; membaca riwayat tidak boleh terganggu.
        const distanceFromBottom =
            container.scrollHeight - container.scrollTop - container.clientHeight;

        if (distanceFromBottom < 120) {
            messageEndRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'end',
            });
        }
    }, [selectedContact?.id, visibleMessages.length]);

    const sendMessage = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const body = messageForm.data.body.trim();

        if (!selectedContact || !body) {
            return;
        }

        // Optimistic: tampilkan pesan pengirim seketika, buang bila gagal.
        const uid = `${Date.now()}-${Math.random().toString(36).slice(2)}`;

        setPendingMessages((current) => [
            ...current,
            {
                uid,
                body,
                sent_at: new Date().toISOString(),
                contact_id: selectedContact.id,
            },
        ]);

        messageForm.post(contacts.messages.store(selectedContact.id).url, {
            preserveScroll: true,
            // Tanpa ini Inertia me-remount halaman setelah redirect POST:
            // state lokal (pencarian, fokus, scroll) hilang dan terasa
            // seperti reload.
            preserveState: true,
            onSuccess: () => {
                messageForm.reset('body');
                setPendingMessages((current) =>
                    current.filter((message) => message.uid !== uid),
                );
            },
            onError: () => {
                setPendingMessages((current) =>
                    current.filter((message) => message.uid !== uid),
                );
            },
        });
    };

    return (
        <>
            <Head title="Daftar Kontak" />

            <main className="h-[calc(100svh-4rem)] min-h-[36rem] p-3 md:p-6">
                <div className="mx-auto grid h-full max-w-7xl overflow-hidden rounded-xl border border-tb-outline-variant bg-tb-surface-bright shadow-sm md:grid-cols-[21rem_minmax(0,1fr)]">
                    <section
                        aria-label="Daftar kontak"
                        className={`${selectedContact ? 'hidden md:flex' : 'flex'} min-h-0 flex-col border-tb-outline-variant md:border-r`}
                    >
                        <header className="border-b border-tb-outline-variant px-4 py-4">
                            <div className="flex items-center gap-3">
                                <div className="grid size-10 place-items-center rounded-lg bg-tb-primary text-white">
                                    <MessageCircle className="size-5" />
                                </div>
                                <div>
                                    <h1 className="font-display text-xl font-bold text-tb-on-surface">
                                        Daftar Kontak
                                    </h1>
                                    <p className="text-xs text-tb-on-surface-variant">
                                        Keluarga dalam satu marga
                                    </p>
                                </div>
                            </div>

                            <div className="relative mt-4">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-outline" />
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari nama kontak..."
                                    aria-label="Cari kontak"
                                    className="bg-tb-surface-container-low border-tb-outline-variant pl-9"
                                />
                            </div>
                        </header>

                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {filteredContacts.length > 0 ? (
                                <ul className="divide-y divide-tb-outline-variant">
                                    {filteredContacts.map((contact) => (
                                        <li key={contact.id}>
                                            <Link
                                                href={contacts.show(contact.id)}
                                                aria-current={
                                                    selectedContact?.id ===
                                                    contact.id
                                                        ? 'page'
                                                        : undefined
                                                }
                                                className={`flex min-h-20 items-center gap-3 border-l-3 px-4 py-3 transition-colors focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:outline-none focus-visible:ring-inset ${
                                                    selectedContact?.id ===
                                                    contact.id
                                                        ? 'border-l-tb-primary bg-tb-primary/8'
                                                        : 'hover:bg-tb-surface-container-low border-l-transparent'
                                                }`}
                                            >
                                                <AppAvatar
                                                    name={contact.name}
                                                    color={contact.color}
                                                    className="size-11"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-baseline justify-between gap-2">
                                                        <p className="truncate text-sm font-semibold text-tb-on-surface">
                                                            {contact.name}
                                                        </p>
                                                        {contact.latest_message_at && (
                                                            <time className="shrink-0 text-[11px] text-tb-outline">
                                                                {formatListTime(
                                                                    contact.latest_message_at,
                                                                )}
                                                            </time>
                                                        )}
                                                    </div>
                                                    <div className="mt-1 flex items-center justify-between gap-2">
                                                        <p className="truncate text-xs text-tb-on-surface-variant">
                                                            {contact.latest_message ??
                                                                contact.role_label}
                                                        </p>
                                                        {contact.unread_count >
                                                            0 && (
                                                            <Badge className="min-w-5 justify-center rounded-full bg-tb-primary px-1.5 text-[10px] text-white">
                                                                {contact.unread_count >
                                                                99
                                                                    ? '99+'
                                                                    : contact.unread_count}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <ContactEmptyState
                                    hasSearch={Boolean(search)}
                                />
                            )}
                        </div>
                    </section>

                    <section
                        aria-label="Percakapan pribadi"
                        className={`${selectedContact ? 'flex' : 'hidden md:flex'} bg-tb-surface-container-low/40 min-h-0 min-w-0 flex-col`}
                    >
                        {selectedContact ? (
                            <>
                                <header className="flex h-17 shrink-0 items-center gap-3 border-b border-tb-outline-variant bg-tb-surface-bright px-3 md:px-5">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        asChild
                                        className="md:hidden"
                                    >
                                        <Link
                                            href={contacts.index()}
                                            aria-label="Kembali ke daftar kontak"
                                        >
                                            <ArrowLeft />
                                        </Link>
                                    </Button>
                                    <AppAvatar
                                        name={selectedContact.name}
                                        color={selectedContact.color}
                                        className="size-10"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate text-sm font-semibold text-tb-on-surface">
                                            {selectedContact.name}
                                        </h2>
                                        <div className="flex items-center gap-1.5 text-xs text-tb-on-surface-variant">
                                            {selectedContact.role_label ===
                                                'Pengurus Marga' && (
                                                <ShieldCheck className="size-3.5 text-tb-primary" />
                                            )}
                                            <span>
                                                {selectedContact.role_label}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        className="hidden items-center gap-2 text-xs text-tb-on-surface-variant sm:flex"
                                        role="status"
                                    >
                                        <span
                                            className={`size-2 rounded-full ${
                                                connectionStatus === 'connected'
                                                    ? 'bg-emerald-500'
                                                    : connectionStatus === 'disconnected'
                                                      ? 'bg-red-500'
                                                      : 'bg-amber-500'
                                            }`}
                                        />
                                        {connectionStatus === 'connected'
                                            ? 'Real-time aktif'
                                            : connectionStatus === 'disconnected'
                                              ? 'Terputus — mode hemat'
                                              : 'Menyambungkan'}
                                    </div>
                                </header>

                                <div
                                    ref={messageContainerRef}
                                    className="min-h-0 flex-1 overflow-y-auto px-3 py-5 md:px-7"
                                    aria-live="polite"
                                >
                                    {visibleMessages.length > 0 ? (
                                        <div className="mx-auto flex max-w-3xl flex-col gap-2.5">
                                            {visibleMessages.map((message) => (
                                                <article
                                                    key={message.id}
                                                    className={`flex ${message.is_mine ? 'justify-end' : 'justify-start'}`}
                                                >
                                                    <div
                                                        className={`max-w-[84%] px-3.5 py-2 text-sm shadow-xs md:max-w-[70%] ${
                                                            message.is_mine
                                                                ? 'rounded-[1rem_1rem_0.25rem_1rem] bg-tb-primary text-white'
                                                                : 'rounded-[1rem_1rem_1rem_0.25rem] border border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface'
                                                        }`}
                                                    >
                                                        <p className="leading-relaxed break-words whitespace-pre-wrap">
                                                            {message.body}
                                                        </p>
                                                        <time
                                                            className={`mt-1 block text-right text-[10px] ${message.is_mine ? 'text-white/70' : 'text-tb-outline'}`}
                                                        >
                                                            {formatMessageTime(
                                                                message.created_at,
                                                            )}
                                                        </time>
                                                    </div>
                                                </article>
                                            ))}
                                            <div ref={messageEndRef} />
                                        </div>
                                    ) : (
                                        <div className="grid h-full place-items-center">
                                            <div className="max-w-sm text-center">
                                                <div className="mx-auto grid size-14 place-items-center rounded-full bg-tb-primary/10 text-tb-primary">
                                                    <MessageCircle className="size-6" />
                                                </div>
                                                <h3 className="mt-4 font-display text-lg font-bold text-tb-on-surface">
                                                    Mulai percakapan
                                                </h3>
                                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                                    Kirim salam kepada{' '}
                                                    {selectedContact.name}.
                                                    Pesan ini hanya dapat dibaca
                                                    oleh kalian.
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <form
                                    onSubmit={sendMessage}
                                    className="shrink-0 border-t border-tb-outline-variant bg-tb-surface-bright p-3 md:px-5 md:py-4"
                                >
                                    <div className="mx-auto flex max-w-3xl items-end gap-2">
                                        <label
                                            htmlFor="message-body"
                                            className="sr-only"
                                        >
                                            Tulis pesan
                                        </label>
                                        <textarea
                                            id="message-body"
                                            value={messageForm.data.body}
                                            onChange={(event) =>
                                                messageForm.setData(
                                                    'body',
                                                    event.target.value,
                                                )
                                            }
                                            onKeyDown={(event) => {
                                                if (
                                                    event.key === 'Enter' &&
                                                    !event.shiftKey
                                                ) {
                                                    event.preventDefault();
                                                    event.currentTarget.form?.requestSubmit();
                                                }
                                            }}
                                            maxLength={2000}
                                            rows={1}
                                            placeholder="Tulis pesan..."
                                            className="bg-tb-surface-container-low max-h-32 min-h-10 flex-1 resize-none rounded-xl border border-tb-outline-variant px-3.5 py-2.5 text-sm text-tb-on-surface transition outline-none focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                        />
                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={
                                                messageForm.processing ||
                                                !messageForm.data.body.trim()
                                            }
                                            aria-label="Kirim pesan"
                                            className="size-10 rounded-full bg-tb-primary text-white hover:bg-tb-primary-light"
                                        >
                                            <Send className="size-4" />
                                        </Button>
                                    </div>
                                    <InputError
                                        message={messageForm.errors.body}
                                        className="mx-auto mt-1 max-w-3xl"
                                    />
                                </form>
                            </>
                        ) : (
                            <div className="grid h-full place-items-center px-6">
                                <div className="max-w-sm text-center">
                                    <div className="mx-auto grid size-18 place-items-center rounded-full border border-tb-outline-variant bg-tb-surface-bright text-tb-primary shadow-sm">
                                        <Users className="size-8" />
                                    </div>
                                    <h2 className="mt-5 font-display text-xl font-bold text-tb-on-surface">
                                        Pilih anggota keluarga
                                    </h2>
                                    <p className="mt-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                        Pilih kontak di sebelah kiri untuk
                                        memulai percakapan pribadi.
                                    </p>
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}

ContactsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Daftar Kontak', href: contacts.index() },
    ],
};

function ContactEmptyState({ hasSearch }: { hasSearch: boolean }) {
    return (
        <div className="grid min-h-64 place-items-center px-6 py-10 text-center">
            <div>
                <Users className="mx-auto size-9 text-tb-outline" />
                <h2 className="mt-3 text-sm font-semibold text-tb-on-surface">
                    {hasSearch ? 'Kontak tidak ditemukan' : 'Belum ada kontak'}
                </h2>
                <p className="mt-1 text-xs leading-relaxed text-tb-on-surface-variant">
                    {hasSearch
                        ? 'Coba gunakan nama yang berbeda.'
                        : 'Kontak akan muncul ketika ada akun lain dalam marga Anda.'}
                </p>
            </div>
        </div>
    );
}

function formatListTime(value: string): string {
    const date = new Date(value);
    const today = new Date();

    if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
    });
}

function formatMessageTime(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
