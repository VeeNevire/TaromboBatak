import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    CheckCheck,
    ChevronUp,
    Clock,
    Copy,
    FilePlus2,
    Loader2,
    MessageCircle,
    Megaphone,
    Plus,
    RefreshCw,
    Search,
    Send,
    ShieldCheck,
    Users,
    X,
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
import {
    categoryForFile,
    MessageAttachmentCard,
} from '@/components/chat/message-attachment-card';
import type { ChatAttachment } from '@/components/chat/message-attachment-card';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { dashboard, login } from '@/routes';
import announcements from '@/routes/announcements';
import contactRequests from '@/routes/contact-requests';
import contacts from '@/routes/contacts';

type Contact = {
    id: number;
    name: string;
    personName: string | null;
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
    attachments: ChatAttachment[];
    progress?: number;
    created_at: string | null;
    read_at: string | null;
    is_mine: boolean;
    status: MessageStatus;
};

type Store = Record<number, ChatItem[]>;

type RawMessage = {
    id: number;
    sender_id: number;
    body: string | null;
    attachments?: ChatAttachment[];
    created_at: string | null;
    read_at?: string | null;
    is_mine: boolean;
};

type Props = {
    contacts: Contact[];
    selectedContact: Contact | null;
    messages: RawMessage[];
    availableUsers: { id: number; name: string; marga: string | null }[];
    incomingContactRequests: {
        id: number;
        name: string;
        marga: string | null;
    }[];
    outgoingContactRequests: number[];
};

const createUid = () =>
    `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;

const ACCEPTED_ATTACHMENTS =
    '.jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mp3,.m4a,.wav,.ogg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar';

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
    availableUsers,
    incomingContactRequests,
    outgoingContactRequests,
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
    const [addContactOpen, setAddContactOpen] = useState(false);
    const [telegramInviteOpen, setTelegramInviteOpen] = useState(false);
    const [telegramInviteCopied, setTelegramInviteCopied] = useState(false);
    const [contactSearch, setContactSearch] = useState('');
    const [requestingContactId, setRequestingContactId] = useState<
        number | null
    >(null);
    const [selectedAttachments, setSelectedAttachments] = useState<
        ChatAttachment[]
    >([]);
    const [attachmentError, setAttachmentError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const storeRef = useRef<Store>({});
    const contactItemsRef = useRef<Contact[]>(contactItems);
    const selectedContactIdRef = useRef<number | null>(null);
    const messageContainerRef = useRef<HTMLDivElement>(null);
    const messageEndRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const attachmentInputRef = useRef<HTMLInputElement>(null);
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
                        body: raw.body ?? '',
                        attachments: raw.attachments ?? [],
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
        body: string | null;
        created_at: string | null;
        attachments: ChatAttachment[];
    }>(
        `users.${auth.user.id}`,
        '.message.sent',
        (message) => {
            mergeMessages([
                {
                    id: message.id,
                    sender_id: message.sender_id,
                    body: message.body,
                    attachments: message.attachments,
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
                    messages?: RawMessage[];
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
        attachments: ChatAttachment[],
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
                        attachments,
                        progress: attachments.length > 0 ? 0 : undefined,
                        created_at: new Date().toISOString(),
                        read_at: null,
                        is_mine: true,
                        status: 'sending',
                    },
                ],
            };
        });

        setUploading(true);
        messageForm.clearErrors();
        setAttachmentError(null);

        router.post(
            contacts.messages.store(contactId).url,
            {
                body: body || null,
                attachments: attachments
                    .map((attachment) => attachment.file)
                    .filter((file): file is File => file instanceof File),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onProgress: (progress) => {
                    setStore((current) => ({
                        ...current,
                        [contactId]: (current[contactId] ?? []).map((item) =>
                            item.uid === uid && item.id === null
                                ? {
                                      ...item,
                                      progress: progress?.percentage ?? 0,
                                  }
                                : item,
                        ),
                    }));
                },
                onError: (errors) => {
                    if (errors.body) {
                        messageForm.setError('body', String(errors.body));
                    }

                    const attachmentValidationError = Object.entries(
                        errors,
                    ).find(
                        ([field]) =>
                            field === 'attachments' ||
                            field.startsWith('attachments.'),
                    );

                    if (attachmentValidationError) {
                        setAttachmentError(
                            String(attachmentValidationError[1]),
                        );
                    }

                    setStore((current) => ({
                        ...current,
                        [contactId]: (current[contactId] ?? []).map((item) =>
                            item.uid === uid && item.id === null
                                ? { ...item, status: 'failed' }
                                : item,
                        ),
                    }));
                },
                onSuccess: () => {
                    // Konfirmasi: ambil pesan persisten (berisi id) dari server lalu
                    // biarkan merge menggantikan gelembung pending secara FIFO.
                    window.setTimeout(
                        () => void fetchNewMessages(contactId),
                        350,
                    );
                },
                onFinish: () => setUploading(false),
            },
        );
    };

    const addAttachments = (files: FileList | null) => {
        if (!files) {
            return;
        }

        const incoming = Array.from(files);
        const tooLarge = incoming.find((file) => file.size > 25 * 1024 * 1024);

        if (tooLarge) {
            setAttachmentError(`${tooLarge.name} melebihi batas 25 MB.`);

            return;
        }

        if (selectedAttachments.length + incoming.length > 5) {
            setAttachmentError('Maksimal 5 lampiran dalam satu pesan.');

            return;
        }

        setAttachmentError(null);
        setSelectedAttachments((current) => [
            ...current,
            ...incoming.map((file) => {
                const url = URL.createObjectURL(file);

                return {
                    id: null,
                    name: file.name,
                    mime_type: file.type || 'application/octet-stream',
                    size: file.size,
                    category: categoryForFile(file),
                    url,
                    download_url: url,
                    file,
                } satisfies ChatAttachment;
            }),
        ]);

        if (attachmentInputRef.current) {
            attachmentInputRef.current.value = '';
        }
    };

    const removeAttachment = (index: number) => {
        setSelectedAttachments((current) => {
            const attachment = current[index];

            if (attachment?.url.startsWith('blob:')) {
                URL.revokeObjectURL(attachment.url);
            }

            return current.filter((_, itemIndex) => itemIndex !== index);
        });
    };

    const autoGrow = (element: HTMLTextAreaElement) => {
        element.style.height = 'auto';
        element.style.height = `${Math.min(element.scrollHeight, 128)}px`;
    };

    const sendMessage = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const body = messageForm.data.body.trim();
        const contactId = selectedContact?.id;

        if (contactId == null || (!body && selectedAttachments.length === 0)) {
            return;
        }

        submitBody(contactId, body, selectedAttachments);
        messageForm.reset('body');
        setSelectedAttachments([]);

        if (textareaRef.current) {
            textareaRef.current.style.height = '';
        }
    };

    const retryMessage = (item: ChatItem) => {
        if (selectedContact == null) {
            return;
        }

        submitBody(selectedContact.id, item.body, item.attachments, item.uid);
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
    const filteredAvailableUsers = availableUsers.filter((user) =>
        `${user.name} ${user.marga ?? ''}`
            .toLocaleLowerCase()
            .includes(contactSearch.toLocaleLowerCase()),
    );

    const sendContactRequest = (recipientId: number) => {
        setRequestingContactId(recipientId);
        router.post(
            contactRequests.store().url,
            { recipient_id: recipientId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAddContactOpen(false);
                    setContactSearch('');
                },
                onFinish: () => setRequestingContactId(null),
            },
        );
    };

    const telegramInviteUrl = () =>
        new URL(
            login({ query: { mode: 'register' } }).url,
            window.location.origin,
        ).toString();

    const telegramInviteText = `Horas! ${auth.user.name} mengundang Anda bergabung di Tarombo Batak agar kita dapat terhubung dan berkomunikasi.`;

    const shareTelegramInvite = () => {
        const shareUrl = new URL('https://t.me/share/url');
        shareUrl.searchParams.set('url', telegramInviteUrl());
        shareUrl.searchParams.set('text', telegramInviteText);

        window.open(shareUrl.toString(), '_blank', 'noopener,noreferrer');
    };

    const copyTelegramInvite = async () => {
        const invitation = `${telegramInviteText}\n${telegramInviteUrl()}`;
        let copied = false;

        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(invitation);
                copied = true;
            } catch {
                copied = false;
            }
        }

        if (!copied) {
            const textArea = document.createElement('textarea');
            textArea.value = invitation;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            textArea.remove();
        }

        setTelegramInviteCopied(true);

        window.setTimeout(() => setTelegramInviteCopied(false), 2000);
    };

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
                            <Button
                                type="button"
                                variant="outline"
                                className="mt-3 w-full"
                                onClick={() => setAddContactOpen(true)}
                            >
                                <Plus className="size-4" /> Tambah Kontak
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="mt-3 w-full"
                            >
                                <Link href={announcements.index()}>
                                    <Megaphone className="size-4" />
                                    Kirim Pengumuman Telegram
                                </Link>
                            </Button>
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
                                                                <span>
                                                                    {
                                                                        contact.name
                                                                    }
                                                                </span>{' '}
                                                                <span className="font-normal text-tb-on-surface-variant">
                                                                    (
                                                                    {contact.personName ??
                                                                        'Not Identified'}
                                                                    )
                                                                </span>
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

                            <div className="border-t border-tb-outline-variant p-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full justify-start border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 hover:text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-950/70"
                                    onClick={() => setTelegramInviteOpen(true)}
                                >
                                    <Send className="size-4" />
                                    Tambah Kontak Telegram
                                </Button>
                                <p className="mt-2 px-1 text-xs leading-relaxed text-tb-on-surface-variant">
                                    Undang keluarga lewat Telegram untuk
                                    bergabung dan mulai berkomunikasi.
                                </p>
                            </div>
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
                                            <span>{selectedContact.name}</span>{' '}
                                            <span className="font-normal text-tb-on-surface-variant">
                                                (
                                                {selectedContact.personName ??
                                                    'Not Identified'}
                                                )
                                            </span>
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
                                                        {item.attachments
                                                            .length > 0 && (
                                                            <div className="mb-1.5 flex flex-col gap-1.5">
                                                                {item.attachments.map(
                                                                    (
                                                                        attachment,
                                                                        index,
                                                                    ) => (
                                                                        <MessageAttachmentCard
                                                                            key={`${attachment.id ?? 'local'}-${index}`}
                                                                            attachment={
                                                                                attachment
                                                                            }
                                                                            mine={
                                                                                item.is_mine
                                                                            }
                                                                        />
                                                                    ),
                                                                )}
                                                            </div>
                                                        )}
                                                        {item.body && (
                                                            <p className="leading-relaxed break-words whitespace-pre-wrap">
                                                                {item.body}
                                                            </p>
                                                        )}
                                                        {item.status ===
                                                            'sending' &&
                                                            item.progress !=
                                                                null && (
                                                                <div className="mt-1.5 h-1 overflow-hidden rounded-full bg-white/20">
                                                                    <div
                                                                        className="h-full rounded-full bg-white/80 transition-[width]"
                                                                        style={{
                                                                            width: `${item.progress}%`,
                                                                        }}
                                                                    />
                                                                </div>
                                                            )}
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
                                    {selectedAttachments.length > 0 && (
                                        <div className="mx-auto mb-2 flex max-w-3xl gap-2 overflow-x-auto pb-1">
                                            {selectedAttachments.map(
                                                (attachment, index) => (
                                                    <div
                                                        key={`${attachment.name}-${index}`}
                                                        className="bg-tb-surface-container-low relative w-44 shrink-0 rounded-xl border border-tb-outline-variant p-1.5"
                                                    >
                                                        <MessageAttachmentCard
                                                            attachment={
                                                                attachment
                                                            }
                                                            mine={false}
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeAttachment(
                                                                    index,
                                                                )
                                                            }
                                                            aria-label={`Hapus ${attachment.name}`}
                                                            className="absolute -top-1.5 -right-1.5 grid size-6 place-items-center rounded-full bg-red-600 text-white shadow-sm hover:bg-red-700"
                                                        >
                                                            <X className="size-3.5" />
                                                        </button>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                                    <div className="mx-auto flex max-w-3xl items-end gap-2">
                                        <input
                                            ref={attachmentInputRef}
                                            type="file"
                                            multiple
                                            accept={ACCEPTED_ATTACHMENTS}
                                            className="sr-only"
                                            onChange={(event) =>
                                                addAttachments(
                                                    event.target.files,
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="outline"
                                            disabled={uploading}
                                            onClick={() =>
                                                attachmentInputRef.current?.click()
                                            }
                                            aria-label="Tambahkan foto atau file"
                                            title="Tambahkan foto atau file"
                                            className="size-10 shrink-0 rounded-full border-tb-outline-variant"
                                        >
                                            <FilePlus2 className="size-4" />
                                        </Button>
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
                                                uploading ||
                                                (!messageForm.data.body.trim() &&
                                                    selectedAttachments.length ===
                                                        0)
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
                                    {attachmentError && (
                                        <p className="mx-auto mt-1 max-w-3xl text-sm text-red-600 dark:text-red-400">
                                            {attachmentError}
                                        </p>
                                    )}
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

            <Dialog open={addContactOpen} onOpenChange={setAddContactOpen}>
                <DialogContent className="border-tb-outline-variant bg-tb-surface-bright sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-tb-on-surface">
                            Tambah Kontak
                        </DialogTitle>
                        <DialogDescription>
                            Cari akun dari marga lain. Pemilik akun harus
                            menyetujui permintaan sebelum Anda dapat mengirim
                            pesan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        {incomingContactRequests.length > 0 && (
                            <div className="grid gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/30">
                                <p className="text-sm font-semibold text-tb-on-surface">
                                    Permintaan masuk
                                </p>
                                {incomingContactRequests.map((request) => (
                                    <div
                                        key={request.id}
                                        className="flex items-center justify-between gap-3 rounded-md bg-tb-surface-bright px-3 py-2"
                                    >
                                        <p className="min-w-0 truncate text-sm text-tb-on-surface">
                                            {request.name}
                                            <span className="ml-2 text-xs text-tb-on-surface-variant">
                                                {request.marga ??
                                                    'Marga belum dicatat'}
                                            </span>
                                        </p>
                                        <div className="flex shrink-0 gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() =>
                                                    router.patch(
                                                        contactRequests.update(
                                                            request.id,
                                                        ).url,
                                                        { status: 'approved' },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Terima
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.patch(
                                                        contactRequests.update(
                                                            request.id,
                                                        ).url,
                                                        { status: 'rejected' },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Tolak
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-outline" />
                            <Input
                                value={contactSearch}
                                onChange={(event) =>
                                    setContactSearch(event.target.value)
                                }
                                placeholder="Cari nama atau marga..."
                                className="border-tb-outline-variant pl-9"
                            />
                        </div>

                        <div className="max-h-64 overflow-y-auto rounded-lg border border-tb-outline-variant">
                            {filteredAvailableUsers.length > 0 ? (
                                filteredAvailableUsers.map((user) => {
                                    const isPending =
                                        outgoingContactRequests.includes(
                                            user.id,
                                        );
                                    const isContact = contactItems.some(
                                        (contact) => contact.id === user.id,
                                    );

                                    return (
                                        <div
                                            key={user.id}
                                            className="flex items-center justify-between gap-3 border-b border-tb-outline-variant px-3 py-2.5 last:border-b-0"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-tb-on-surface">
                                                    {user.name}
                                                </p>
                                                <p className="text-xs text-tb-on-surface-variant">
                                                    {user.marga ??
                                                        'Marga belum dicatat'}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    isPending ||
                                                    isContact ||
                                                    requestingContactId !== null
                                                }
                                                onClick={() =>
                                                    sendContactRequest(user.id)
                                                }
                                            >
                                                {isContact
                                                    ? 'Sudah kontak'
                                                    : isPending
                                                      ? 'Menunggu'
                                                      : requestingContactId ===
                                                          user.id
                                                        ? 'Mengirim...'
                                                        : 'Tambah'}
                                            </Button>
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="px-3 py-6 text-center text-sm text-tb-on-surface-variant">
                                    Akun tidak ditemukan.
                                </p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAddContactOpen(false)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={telegramInviteOpen}
                onOpenChange={(open) => {
                    setTelegramInviteOpen(open);

                    if (!open) {
                        setTelegramInviteCopied(false);
                    }
                }}
            >
                <DialogContent className="border-tb-outline-variant bg-tb-surface-bright sm:max-w-md">
                    <DialogHeader>
                        <div className="mb-2 grid size-11 place-items-center rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-300">
                            <Send className="size-5" />
                        </div>
                        <DialogTitle className="font-display text-tb-on-surface">
                            Tambah Kontak Telegram
                        </DialogTitle>
                        <DialogDescription className="leading-relaxed">
                            Bagikan undangan kepada keluarga melalui Telegram.
                            Tautan akan membuka halaman pendaftaran Tarombo
                            Batak. Setelah mereka bergabung, Anda dapat saling
                            menambahkan sebagai kontak dan berkomunikasi.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="bg-tb-surface-container-low rounded-lg border border-tb-outline-variant p-3 text-sm leading-relaxed text-tb-on-surface-variant">
                        {telegramInviteText}
                    </div>

                    <DialogFooter className="grid gap-2 sm:grid-cols-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void copyTelegramInvite()}
                        >
                            {telegramInviteCopied ? (
                                <Check className="size-4" />
                            ) : (
                                <Copy className="size-4" />
                            )}
                            {telegramInviteCopied
                                ? 'Tautan disalin'
                                : 'Salin tautan'}
                        </Button>
                        <Button
                            type="button"
                            className="bg-sky-600 text-white hover:bg-sky-700"
                            onClick={shareTelegramInvite}
                        >
                            <Send className="size-4" />
                            Bagikan ke Telegram
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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
 *   berisi teks dan nama lampiran sama secara FIFO (konfirmasi kirim),
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

        const persistedNames = persisted.attachments
            .map((attachment) => attachment.name)
            .join('|');
        const index = pendingQueue.findIndex((pending) => {
            const pendingNames = pending.attachments
                .map((attachment) => attachment.name)
                .join('|');

            return (
                pending.body === persisted.body &&
                pendingNames === persistedNames
            );
        });

        if (index === -1) {
            continue;
        }

        const [matched] = pendingQueue.splice(index, 1);
        matched.attachments.forEach((attachment) => {
            if (attachment.url.startsWith('blob:')) {
                URL.revokeObjectURL(attachment.url);
            }
        });
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
