import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    CheckCheck,
    ChevronUp,
    Clock,
    Loader2,
    MessageCircle,
    RefreshCw,
    Search,
    Send,
    ShieldCheck,
    Users,
} from 'lucide-react';
import {
    useDeferredValue,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import type { CSSProperties } from 'react';
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

type MessageStatus = 'sending' | 'sent' | 'read' | 'failed';

/**
 * Satu-satunya representasi pesan di klien. Semua jalur masuk (snapshot
 * Inertia, broadcast Echo, fetch fallback, konfirmasi kirim) melewati
 * mergeMessages() yang dinormalisasi: dedupe by id, sort by id, dan
 * penggantian pesan pending menjadi persisten secara FIFO.
 */
type ChatItem = {
    /** `m{id}` untuk persisten, `p{uid}` untuk milik klien. */
    key: string;
    /** null selama belum tersimpan di server (status sending/failed). */
    id: number | null;
    uid: string;
    contact_id: number;
    sender_id: number;
    body: string;
    created_at: string | null;
    read_at: string | null;
    is_mine: boolean;
    status: MessageStatus;
};

type Store = Record<number, ChatItem[]>;

type RawMessage = {
    id: number;
    sender_id: number;
    body: string;
    created_at: string | null;
    read_at?: string | null;
    is_mine: boolean;
};

type Props = {
    contacts: Contact[];
    selectedContact: Contact | null;
    messages: RawMessage[];
};

const createUid = () =>
    `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;

/** Urutan tampil: pesan persisten naik by id; milik klien menempel di akhir. */
const compareItems = (a: ChatItem, b: ChatItem): number => {
    if (a.id !== null && b.id !== null) {
        return a.id - b.id;
    }

    if (a.id !== null) {
        return -1;
    }

    if (b.id !== null) {
        return 1;
    }

    return a.uid < b.uid ? -1 : a.uid > b.uid ? 1 : 0;
};

export default function ContactsIndex({
    contacts: contactItems,
    selectedContact,
    messages,
}: Props) {
    const { auth } = usePage().props;

    // ------------------------------------------------------------------
    // Store tunggal pesan per-percakapan + metadata pendukung
    // ------------------------------------------------------------------
    const [store, setStore] = useState<Store>({});
    const [unreadMap, setUnreadMap] = useState<Record<number, number>>({});
    const [history, setHistory] = useState<
        Record<number, { loading: boolean; hasMore: boolean }>
    >({});
    const [search, setSearch] = useState('');

    const storeRef = useRef<Store>({});
    const contactItemsRef = useRef<Contact[]>(contactItems);
    const selectedContactIdRef = useRef<number | null>(null);
    const messageContainerRef = useRef<HTMLDivElement>(null);
    const messageEndRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const prevContactIdRef = useRef<number | null | undefined>(undefined);
    const fetchingRef = useRef<Set<number>>(new Set());

    const deferredSearch = useDeferredValue(search.trim().toLocaleLowerCase());
    const connectionStatus = useConnectionStatus();
    const messageForm = useForm({ body: '' });

    const conversation =
        selectedContact != null ? (store[selectedContact.id] ?? []) : [];

    useEffect(() => {
        storeRef.current = store;
    }, [store]);

    useEffect(() => {
        contactItemsRef.current = contactItems;
    }, [contactItems]);

    useEffect(() => {
        selectedContactIdRef.current = selectedContact?.id ?? null;
    }, [selectedContact?.id]);

    /**
     * Satu pintu masuk untuk SEMUA pesan baru. Idempoten: aman dipanggil
     * berulang dengan data yang sama dari jalur berbeda.
     */
    const mergeMessages = useCallback(
        (items: RawMessage[], forcedContactId?: number) => {
            if (items.length === 0) {
                return;
            }

            setStore((current) => {
                let next = current;

                for (const raw of items) {
                    const contactId =
                        forcedContactId ?? selectedContactIdRef.current;

                    if (contactId == null) {
                        continue;
                    }

                    next = upsertMessage(next, contactId, {
                        key: `m${raw.id}`,
                        id: raw.id,
                        uid: `s${raw.id}`,
                        contact_id: contactId,
                        sender_id: raw.sender_id,
                        body: raw.body,
                        created_at: raw.created_at,
                        read_at: raw.read_at ?? null,
                        is_mine: raw.is_mine,
                        status: raw.is_mine
                            ? raw.read_at
                                ? 'read'
                                : 'sent'
                            : 'sent',
                    });
                }

                return next;
            });
        },
        [],
    );

    /**
     * Seed snapshot dari props Inertia (buka halaman / kunjungan penuh).
     * Merge bersifat idempoten sehingga tidak pernah menduplikasi ataupun
     * menghapus apa pun yang sudah ada di store.
     */
    useEffect(() => {
        if (selectedContact == null || messages.length === 0) {
            return;
        }

        mergeMessages(messages, selectedContact.id);
    }, [messages, selectedContact, mergeMessages]);

    /**
     * Badge unread murni lokal: bertambah dari payload Echo dan nol saat
     * percakapan dibuka. Tidak perlu di-reset manual — setiap kunjungan
     * halaman me-remount komponen sehingga nilai segar kembali dari server,
     * dan alur chat sendiri tidak pernah menyentuh router Inertia lagi.
     */
    const bumpUnread = useCallback((contactId: number) => {
        setUnreadMap((current) => {
            if (contactId === selectedContactIdRef.current) {
                return current;
            }

            const base =
                current[contactId] ??
                contactItemsRef.current.find((item) => item.id === contactId)
                    ?.unread_count ??
                0;

            return { ...current, [contactId]: base + 1 };
        });
    }, []);

    // ------------------------------------------------------------------
    // Jalur realtime utama: broadcast privat penerima
    // ------------------------------------------------------------------
    useEcho<{
        id: number;
        sender_id: number;
        body: string;
        created_at: string | null;
    }>(
        `users.${auth.user.id}`,
        '.message.sent',
        (message) => {
            mergeMessages([
                {
                    id: message.id,
                    sender_id: message.sender_id,
                    body: message.body,
                    created_at: message.created_at,
                    read_at: null,
                    is_mine: false,
                },
            ]);
            bumpUnread(message.sender_id);
        },
        [mergeMessages, bumpUnread],
    );

    /**
     * Read receipt: lawan bicara membaca pesan kita.
     */
    useEcho<{
        reader_id: number;
        message_ids: number[];
        read_at: string;
    }>(
        `users.${auth.user.id}`,
        '.message.read',
        ({ reader_id, message_ids, read_at }) => {
            const ids = new Set(message_ids);

            setStore((current) => {
                const items = current[reader_id];

                if (!items) {
                    return current;
                }

                let changed = false;
                const nextItems: ChatItem[] = items.map((item) => {
                    if (
                        item.id !== null &&
                        ids.has(item.id) &&
                        item.is_mine &&
                        !item.read_at
                    ) {
                        changed = true;

                        return {
                            ...item,
                            read_at: read_at,
                            status: 'read' as MessageStatus,
                        };
                    }

                    return item;
                });

                return changed
                    ? { ...current, [reader_id]: nextItems }
                    : current;
            });
        },
        [],
    );

    // ------------------------------------------------------------------
    // Fallback senyap: fetch JSON murni, di luar router Inertia.
    // Dipanggil sekali saat percakapan dibuka (gap-fill) lalu berkala
    // hanya ketika WebSocket terputus.
    // ------------------------------------------------------------------
    const fetchNewMessages = useCallback(
        async (contactId: number) => {
            if (fetchingRef.current.has(contactId)) {
                return;
            }

            const cursor = Math.max(
                0,
                ...(storeRef.current[contactId] ?? [])
                    .filter(
                        (item) =>
                            item.id !== null && item.is_mine !== undefined,
                    )
                    .map((item) => item.id as number),
                0,
            );
            const knownIds = new Set(
                (storeRef.current[contactId] ?? []).map((item) => item.key),
            );

            fetchingRef.current.add(contactId);

            try {
                const response = await fetch(
                    contacts.messages.index(contactId, {
                        query: { after_id: cursor },
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
                        read_at: string | null;
                        is_mine: boolean;
                    }>;
                };

                const fresh = (data.messages ?? []).filter(
                    (raw) => !knownIds.has(`m${raw.id}`),
                );

                if (fresh.length > 0) {
                    mergeMessages(fresh, contactId);
                }
            } catch {
                // Offline: dicoba lagi pada siklus berikutnya.
            } finally {
                fetchingRef.current.delete(contactId);
            }
        },
        [mergeMessages],
    );

    useEffect(() => {
        if (selectedContact == null) {
            return;
        }

        const contactId = selectedContact.id;

        void fetchNewMessages(contactId);

        if (connectionStatus === 'connected') {
            return;
        }

        const interval = setInterval(() => {
            void fetchNewMessages(contactId);
        }, 4000);

        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedContact?.id, connectionStatus, fetchNewMessages]);

    // ------------------------------------------------------------------
    // Muat riwayat ke belakang (pagination)
    // ------------------------------------------------------------------
    const loadOlderMessages = useCallback(
        async (contactId: number) => {
            const items = storeRef.current[contactId] ?? [];
            const oldestId = items.reduce<number | null>((acc, item) => {
                if (item.id === null) {
                    return acc;
                }

                return acc === null ? item.id : Math.min(acc, item.id);
            }, null);

            if (oldestId === null) {
                return;
            }

            setHistory((current) => ({
                ...current,
                [contactId]: { loading: true, hasMore: true },
            }));

            try {
                const response = await fetch(
                    contacts.messages.index(contactId, {
                        query: { before_id: oldestId, limit: 30 },
                    }).url,
                    { headers: { Accept: 'application/json' } },
                );

                if (!response.ok) {
                    return;
                }

                const data = (await response.json()) as {
                    messages?: RawMessage[];
                    has_more?: boolean;
                };

                mergeMessages(data.messages ?? [], contactId);
                setHistory((current) => ({
                    ...current,
                    [contactId]: {
                        loading: false,
                        hasMore: data.has_more ?? false,
                    },
                }));
            } catch {
                setHistory((current) => ({
                    ...current,
                    [contactId]: { loading: false, hasMore: true },
                }));
            }
        },
        [mergeMessages],
    );

    // ------------------------------------------------------------------
    // Pengiriman: optimistic + konfirmasi lewat fetch senyap + retry
    // ------------------------------------------------------------------
    const submitBody = (
        contactId: number,
        body: string,
        replacingUid?: string,
    ) => {
        const uid = createUid();

        setStore((current) => {
            const items = current[contactId] ?? [];
            const cleaned = replacingUid
                ? items.filter((item) => item.uid !== replacingUid)
                : items;

            return {
                ...current,
                [contactId]: [
                    ...cleaned,
                    {
                        key: `p${uid}`,
                        id: null,
                        uid,
                        contact_id: contactId,
                        sender_id: auth.user.id,
                        body,
                        created_at: new Date().toISOString(),
                        read_at: null,
                        is_mine: true,
                        status: 'sending',
                    },
                ],
            };
        });

        messageForm.post(contacts.messages.store(contactId).url, {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                setStore((current) => ({
                    ...current,
                    [contactId]: (current[contactId] ?? []).map((item) =>
                        item.uid === uid && item.id === null
                            ? { ...item, status: 'failed' }
                            : item,
                    ),
                }));
            },
        });

        // Konfirmasi: ambil pesan persisten (berisi id) dari server lalu
        // biarkan merge menggantikan gelembung pending secara FIFO.
        window.setTimeout(() => void fetchNewMessages(contactId), 350);
    };

    const autoGrow = (element: HTMLTextAreaElement) => {
        element.style.height = 'auto';
        element.style.height = `${Math.min(element.scrollHeight, 128)}px`;
    };

    const sendMessage = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const body = messageForm.data.body.trim();
        const contactId = selectedContact?.id;

        if (contactId == null || !body) {
            return;
        }

        submitBody(contactId, body);
        messageForm.reset('body');

        if (textareaRef.current) {
            textareaRef.current.style.height = '';
        }
    };

    const retryMessage = (item: ChatItem) => {
        if (selectedContact == null) {
            return;
        }

        submitBody(selectedContact.id, item.body, item.uid);
    };

    const openContact = (contactId: number) => {
        setUnreadMap((current) => ({ ...current, [contactId]: 0 }));
    };

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
            container.scrollHeight -
            container.scrollTop -
            container.clientHeight;

        if (distanceFromBottom < 120) {
            messageEndRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'end',
            });
        }
    }, [selectedContact?.id, conversation.length]);

    const filteredContacts = contactItems.filter((contact) =>
        contact.name.toLocaleLowerCase().includes(deferredSearch),
    );

    const statusIndicator = (
        <div
            className="flex items-center gap-2 text-xs text-tb-on-surface-variant"
            role="status"
        >
            <span
                className={`size-2 shrink-0 rounded-full ${
                    connectionStatus === 'connected'
                        ? 'bg-emerald-500'
                        : connectionStatus === 'disconnected'
                          ? 'bg-red-500'
                          : 'bg-amber-500'
                }`}
            />
            <span className="hidden sm:inline">
                {connectionStatus === 'connected'
                    ? 'Real-time aktif'
                    : connectionStatus === 'disconnected'
                      ? 'Terputus — mode hemat'
                      : 'Menyambungkan'}
            </span>
        </div>
    );

    const historyMeta =
        selectedContact != null ? history[selectedContact.id] : undefined;
    const showHistoryButton =
        selectedContact != null &&
        (conversation.length >= 50 || historyMeta?.hasMore === true) &&
        historyMeta?.hasMore !== false;

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
                                    {filteredContacts.map((contact) => {
                                        const unread =
                                            unreadMap[contact.id] ??
                                            contact.unread_count;

                                        return (
                                            <li key={contact.id}>
                                                <Link
                                                    href={contacts.show(
                                                        contact.id,
                                                    )}
                                                    onClick={() =>
                                                        openContact(contact.id)
                                                    }
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
                                                            {unread > 0 && (
                                                                <Badge className="min-w-5 justify-center rounded-full bg-tb-primary px-1.5 text-[10px] text-white">
                                                                    {unread > 99
                                                                        ? '99+'
                                                                        : unread}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>
                                                </Link>
                                            </li>
                                        );
                                    })}
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
                                    {statusIndicator}
                                </header>

                                <div
                                    ref={messageContainerRef}
                                    className="min-h-0 flex-1 overflow-y-auto px-3 py-5 md:px-7"
                                    aria-live="polite"
                                >
                                    <div className="mx-auto flex max-w-3xl flex-col gap-2.5">
                                        {showHistoryButton && (
                                            <div className="flex justify-center pb-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={
                                                        historyMeta?.loading
                                                    }
                                                    onClick={() =>
                                                        void loadOlderMessages(
                                                            selectedContact.id,
                                                        )
                                                    }
                                                    className="gap-1.5 rounded-full border-tb-outline-variant text-xs text-tb-on-surface-variant"
                                                >
                                                    {historyMeta?.loading ? (
                                                        <Loader2 className="size-3.5 animate-spin" />
                                                    ) : (
                                                        <ChevronUp className="size-3.5" />
                                                    )}
                                                    Muat pesan sebelumnya
                                                </Button>
                                            </div>
                                        )}

                                        {conversation.length > 0 ? (
                                            conversation.map((item) => (
                                                <article
                                                    key={item.key}
                                                    className={`flex ${item.is_mine ? 'justify-end' : 'justify-start'}`}
                                                >
                                                    <div
                                                        className={`max-w-[84%] px-3.5 py-2 text-sm shadow-xs md:max-w-[70%] ${
                                                            item.is_mine
                                                                ? item.status ===
                                                                  'failed'
                                                                    ? 'rounded-[1rem_1rem_0.25rem_1rem] border border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200'
                                                                    : 'rounded-[1rem_1rem_0.25rem_1rem] bg-tb-primary text-white'
                                                                : 'rounded-[1rem_1rem_1rem_0.25rem] border border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface'
                                                        }`}
                                                    >
                                                        <p className="leading-relaxed break-words whitespace-pre-wrap">
                                                            {item.body}
                                                        </p>
                                                        <div
                                                            className={`mt-1 flex items-center justify-end gap-1 text-[10px] ${item.is_mine && item.status !== 'failed' ? 'text-white/70' : 'text-tb-outline'}`}
                                                        >
                                                            <time>
                                                                {formatMessageTime(
                                                                    item.created_at,
                                                                )}
                                                            </time>
                                                            {item.is_mine && (
                                                                <DeliveryStatus
                                                                    status={
                                                                        item.status
                                                                    }
                                                                />
                                                            )}
                                                        </div>
                                                        {item.status ===
                                                            'failed' && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    retryMessage(
                                                                        item,
                                                                    )
                                                                }
                                                                className="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 underline-offset-2 hover:underline dark:text-red-300"
                                                            >
                                                                <RefreshCw className="size-3" />
                                                                Kirim ulang
                                                            </button>
                                                        )}
                                                    </div>
                                                </article>
                                            ))
                                        ) : (
                                            <div className="grid h-full place-items-center py-16">
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
                                                        Pesan ini hanya dapat
                                                        dibaca oleh kalian.
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                        <div ref={messageEndRef} />
                                    </div>
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
                                            ref={textareaRef}
                                            value={messageForm.data.body}
                                            onChange={(event) => {
                                                messageForm.setData(
                                                    'body',
                                                    event.target.value,
                                                );
                                                autoGrow(event.target);
                                            }}
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
                                            style={
                                                {
                                                    height: '42px',
                                                } as CSSProperties
                                            }
                                            className="bg-tb-surface-container-low max-h-32 min-h-10 flex-1 resize-none overflow-hidden rounded-xl border border-tb-outline-variant px-3.5 py-2.5 text-sm text-tb-on-surface transition outline-none focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
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

/**
 * Masukkan satu pesan ke store satu percakapan:
 * - dedupe by id (persisten) / uid (milik klien),
 * - pesan persisten milik sendiri menggantikan gelembung pending
 *   ber-body sama secara FIFO (konfirmasi kirim),
 * - hasil selalu terurut naik by id dengan pesan lokal di ekor.
 */
function upsertMessage(
    current: Store,
    contactId: number,
    item: ChatItem,
): Store {
    const existing = current[contactId] ?? [];
    const merged: ChatItem[] = [];
    const seenPersisted = new Set<string>();

    for (const candidate of [...existing, item]) {
        if (candidate.id !== null) {
            const key = `m${candidate.id}`;

            if (seenPersisted.has(key)) {
                continue;
            }

            seenPersisted.add(key);
            merged.push(candidate);

            continue;
        }

        merged.push(candidate);
    }

    // Konfirmasi FIFO: setiap pesan persisten milik sendiri mengonsumsi
    // satu gelembung "sending" ber-body sama (tertua lebih dulu).
    const pendingQueue = merged.filter(
        (candidate) => candidate.id === null && candidate.status === 'sending',
    );

    for (const persisted of merged) {
        if (persisted.id === null || !persisted.is_mine) {
            continue;
        }

        const index = pendingQueue.findIndex(
            (pending) => pending.body === persisted.body,
        );

        if (index === -1) {
            continue;
        }

        const [matched] = pendingQueue.splice(index, 1);
        const position = merged.indexOf(matched);

        if (position !== -1) {
            merged.splice(position, 1);
        }
    }

    const ordered = [...merged].sort(compareItems);

    return { ...current, [contactId]: ordered };
}

function DeliveryStatus({ status }: { status: MessageStatus }) {
    if (status === 'sending') {
        return <Clock className="size-3" aria-label="Mengirim" />;
    }

    if (status === 'failed') {
        return <AlertCircle className="size-3" aria-label="Gagal terkirim" />;
    }

    if (status === 'read') {
        return (
            <CheckCheck
                className="size-3.5 text-sky-300"
                aria-label="Sudah dibaca"
            />
        );
    }

    return <Check className="size-3" aria-label="Terkirim" />;
}

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
