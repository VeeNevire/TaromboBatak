import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    ArrowUpRight,
    Clock3,
    Check,
    Copy,
    Crown,
    ChevronLeft,
    ChevronRight,
    Maximize2,
    Pencil,
    Search,
    Share2,
    Trash2,
    TreePine,
    UserPlus,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import contributions from '@/routes/contributions';
import familyTreeShares from '@/routes/family-tree-shares';
import familyTrees from '@/routes/family-trees';
import margaAccessRequests from '@/routes/marga-access-requests';
import people from '@/routes/people';
import tarombo from '@/routes/tarombo';

const ITEMS_PER_PAGE = 5;

export type FamilyTreeHistoryEntry = {
    id: number;
    root_person_id: number;
    member_person_ids: number[];
    root_name: string;
    name: string | null;
    source_name: string | null;
    is_primary: boolean;
    access: 'owner' | 'shared';
    owner_name: string;
    can_manage: boolean;
    can_share: boolean;
    can_append: boolean;
    can_request_marga_tree: boolean;
    marga_request_status: 'pending' | 'approved' | null;
    can_delete: boolean;
    shares: {
        id: number;
        recipient_id: number;
        recipient_name: string;
        recipient_email: string;
        status: 'pending' | 'accepted' | 'rejected';
    }[];
    deletion_pending: boolean;
    updated_at: string;
};

export type ApprovedMargaTreeEntry = {
    id: number;
    name: string;
    identity_person_id: number;
    identity_person_name: string | null;
    people_count: number;
};

export function ApprovedMargaTreeList({
    entries,
    title = 'Daftar Silsilah Marga',
    description = 'Silsilah yang telah disetujui Kontributor Utama atau Kontributor Anggota.',
    headerAction,
    asCard = false,
}: {
    entries: ApprovedMargaTreeEntry[];
    title?: string;
    description?: string;
    headerAction?: ReactNode;
    asCard?: boolean;
}) {
    const [currentPage, setCurrentPage] = useState(1);
    const totalPages = Math.max(1, Math.ceil(entries.length / ITEMS_PER_PAGE));
    const safePage = Math.min(currentPage, totalPages);
    const startIndex = (safePage - 1) * ITEMS_PER_PAGE;
    const visibleEntries = entries.slice(
        startIndex,
        startIndex + ITEMS_PER_PAGE,
    );

    const content = (
        <section className="grid gap-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <h3 className="font-display text-base font-semibold text-tb-on-surface">
                    {title}
                </h3>
                {headerAction}
            </div>
            <div>
                <p className="mt-1 text-xs text-tb-on-surface-variant">
                    {description}
                </p>
            </div>
            {entries.length === 0 ? (
                <div className="rounded-xl border border-dashed border-tb-outline-variant bg-tb-surface-container/40 px-4 py-5 text-center">
                    <p className="text-sm font-medium text-tb-on-surface">
                        Belum ada silsilah yang disetujui
                    </p>
                </div>
            ) : (
                <>
                    <ol className="grid gap-3">
                        {visibleEntries.map((entry, index) => (
                            <li
                                key={entry.id}
                                className="flex flex-col gap-4 rounded-xl border border-tb-outline-variant bg-tb-surface-container/35 p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex min-w-0 items-start gap-3">
                                    <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-tb-surface-bright text-sm font-bold text-tb-on-surface-variant ring-1 ring-tb-outline-variant">
                                        {startIndex + index + 1}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-tb-on-surface">
                                            Keluarga{' '}
                                            {entry.identity_person_name ??
                                                entry.name}
                                        </p>
                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                            Marga: {entry.name} ·{' '}
                                            {entry.people_count} anggota
                                        </p>
                                    </div>
                                </div>
                                <div className="grid shrink-0 grid-cols-1 gap-2 sm:grid-cols-2">
                                    <Link
                                        href={tarombo.fullscreen('tree', {
                                            query: {
                                                marga_id: entry.id,
                                                marga_direction: 'upper',
                                            },
                                        })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={`Pohon Silsilah Atas ${entry.name}`}
                                        className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-xs font-semibold text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary"
                                    >
                                        <ArrowUp className="size-3.5" /> Pohon
                                        Atas
                                    </Link>
                                    <Link
                                        href={tarombo.fullscreen('tree', {
                                            query: {
                                                marga_id: entry.id,
                                                marga_direction: 'lower',
                                            },
                                        })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={`Pohon Silsilah Bawah ${entry.name}`}
                                        className="text-tb-on-primary inline-flex items-center justify-center gap-1.5 rounded-lg bg-tb-primary px-3 py-2 text-xs font-semibold transition-opacity hover:opacity-90"
                                    >
                                        <ArrowDown className="size-3.5" /> Pohon
                                        Bawah
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ol>
                    {totalPages > 1 && (
                        <div className="flex items-center justify-between gap-3 pt-1">
                            <p className="text-xs text-tb-on-surface-variant">
                                Halaman {safePage} dari {totalPages}
                            </p>
                            <div className="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Halaman silsilah marga sebelumnya"
                                    disabled={safePage === 1}
                                    onClick={() =>
                                        setCurrentPage(
                                            Math.max(1, safePage - 1),
                                        )
                                    }
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Halaman silsilah marga berikutnya"
                                    disabled={safePage === totalPages}
                                    onClick={() =>
                                        setCurrentPage(
                                            Math.min(totalPages, safePage + 1),
                                        )
                                    }
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </>
            )}
        </section>
    );

    return asCard ? (
        <Card className="border-tb-outline-variant bg-tb-surface-bright">
            <CardContent className="py-5">{content}</CardContent>
        </Card>
    ) : (
        content
    );
}

export function FamilyTreeHistoryCard({
    entries,
    approvedEntries = [],
    margaName,
    margaId,
    margaAccessStatus,
    shareableAccounts = [],
    pendingTreeShares = [],
}: {
    entries: FamilyTreeHistoryEntry[];
    approvedEntries?: FamilyTreeHistoryEntry[];
    margaName?: string | null;
    margaId?: number | null;
    margaAccessStatus?: 'pending' | 'approved' | 'rejected' | null;
    shareableAccounts?: {
        id: number;
        name: string;
        email: string;
        marga: string | null;
    }[];
    pendingTreeShares?: {
        id: number;
        tree_name: string;
        sender_name: string;
    }[];
}) {
    const [expanded, setExpanded] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [shareEntry, setShareEntry] = useState<FamilyTreeHistoryEntry | null>(
        null,
    );
    const [renameEntry, setRenameEntry] =
        useState<FamilyTreeHistoryEntry | null>(null);
    const [renameName, setRenameName] = useState('');
    const [recipientId, setRecipientId] = useState('');
    const [recipientSearch, setRecipientSearch] = useState('');
    const listTopRef = useRef<HTMLDivElement>(null);
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const showSearch = entries.length > ITEMS_PER_PAGE;

    const filteredEntries = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();

        if (!query) {
            return entries;
        }

        return entries.filter((entry) =>
            [entry.name ?? '', entry.root_name, entry.source_name ?? '']
                .join(' ')
                .toLowerCase()
                .includes(query),
        );
    }, [entries, searchQuery]);

    const totalPages = Math.max(
        1,
        Math.ceil(filteredEntries.length / ITEMS_PER_PAGE),
    );
    const safePage = Math.min(currentPage, totalPages);
    const startIndex = (safePage - 1) * ITEMS_PER_PAGE;
    const visibleEntries = filteredEntries.slice(
        startIndex,
        startIndex + ITEMS_PER_PAGE,
    );
    const margaRequestEntry =
        entries.find((entry) => entry.is_primary) ?? entries[0];
    const canRequestMargaAccess =
        margaId != null && margaAccessStatus !== 'approved';
    const rangeStart = filteredEntries.length === 0 ? 0 : startIndex + 1;
    const rangeEnd = Math.min(
        startIndex + ITEMS_PER_PAGE,
        filteredEntries.length,
    );
    const showPagination = filteredEntries.length > ITEMS_PER_PAGE;

    const availableRecipients = useMemo(() => {
        const query = recipientSearch.trim().toLowerCase();

        return shareableAccounts
            .filter(
                (account) =>
                    !shareEntry?.shares.some(
                        (share) =>
                            share.recipient_id === account.id &&
                            share.status !== 'rejected',
                    ),
            )
            .filter((account) =>
                [account.name, account.email, account.marga ?? '']
                    .join(' ')
                    .toLowerCase()
                    .includes(query),
            );
    }, [recipientSearch, shareEntry, shareableAccounts]);

    const goToPage = (page: number) => {
        setCurrentPage(page);
        listTopRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    };

    useEffect(() => {
        if (!expanded) {
            return;
        }

        const previousOverflow = document.body.style.overflow;
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setExpanded(false);
            }
        };

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [expanded]);

    return (
        <div
            id="daftar-silsilah"
            role={expanded ? 'dialog' : undefined}
            aria-modal={expanded ? true : undefined}
            aria-label={expanded ? 'Daftar Silsilah' : undefined}
            className={cn(
                expanded &&
                    'fixed inset-0 z-50 overflow-y-auto bg-tb-surface p-4 md:p-6',
            )}
        >
            <Card
                className={cn(
                    'border-tb-outline-variant bg-tb-surface-bright',
                    expanded &&
                        'min-h-[calc(100dvh-2rem)] md:min-h-[calc(100dvh-3rem)]',
                )}
            >
                <CardHeader>
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex min-w-0 items-start gap-3">
                            <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-tb-primary/10 text-tb-primary">
                                <TreePine className="size-4.5" />
                            </span>
                            <div className="min-w-0">
                                <CardTitle className="font-display text-lg text-tb-on-surface">
                                    Daftar Silsilah
                                </CardTitle>
                                {margaName && (
                                    <p className="mt-1 font-display text-base font-semibold text-tb-primary">
                                        Keluarga Marga {margaName}
                                    </p>
                                )}
                                <CardDescription>
                                    Pohon yang pernah Anda buat, diurutkan dari
                                    pembaruan terbaru.
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {canRequestMargaAccess && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={margaAccessStatus === 'pending'}
                                    onClick={() =>
                                        router.post(
                                            margaAccessRequests.store().url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <TreePine className="size-3.5" />
                                    {margaAccessStatus === 'pending'
                                        ? 'Menunggu Persetujuan'
                                        : margaAccessStatus === 'rejected'
                                          ? 'Ajukan Lagi'
                                          : 'Ajukan Buka Marga'}
                                </Button>
                            )}
                            <button
                                type="button"
                                onClick={() =>
                                    setExpanded((current) => !current)
                                }
                                aria-expanded={expanded}
                                aria-label={
                                    expanded
                                        ? 'Kembali ke form Tambah Keluarga'
                                        : 'Perbesar Daftar Silsilah'
                                }
                                title={
                                    expanded ? 'Kembali ke Form' : 'Perbesar'
                                }
                                className={cn(
                                    'inline-flex shrink-0 items-center justify-center rounded-lg border border-tb-outline-variant text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary/40 focus-visible:outline-none',
                                    expanded
                                        ? 'h-9 gap-2 px-3 text-sm font-medium'
                                        : 'size-9',
                                )}
                            >
                                {expanded ? (
                                    <>
                                        <ArrowLeft className="size-4" />
                                        Kembali ke Form
                                    </>
                                ) : (
                                    <Maximize2 className="size-4" />
                                )}
                            </button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-6">
                    {pendingTreeShares.length > 0 && (
                        <section className="grid gap-3 rounded-xl border border-tb-primary/25 bg-tb-primary/5 p-4">
                            <div>
                                <h3 className="font-display text-base font-semibold text-tb-on-surface">
                                    Undangan Berbagi Silsilah
                                </h3>
                                <p className="mt-1 text-xs text-tb-on-surface-variant">
                                    Setelah diterima, Anda hanya dapat menambah
                                    anggota baru.
                                </p>
                            </div>
                            {pendingTreeShares.map((share) => (
                                <div
                                    key={share.id}
                                    className="flex flex-col gap-3 rounded-lg border border-tb-outline-variant bg-tb-surface-bright p-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-tb-on-surface">
                                            {share.tree_name}
                                        </p>
                                        <p className="text-xs text-tb-on-surface-variant">
                                            Dibagikan oleh {share.sender_name}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    familyTreeShares.update(
                                                        share.id,
                                                    ).url,
                                                    { status: 'accepted' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Check className="size-3.5" />{' '}
                                            Terima
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    familyTreeShares.update(
                                                        share.id,
                                                    ).url,
                                                    { status: 'rejected' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <X className="size-3.5" /> Tolak
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </section>
                    )}
                    <ApprovedMargaTreeList
                        entries={approvedEntries}
                        title={`Daftar Silsilah Marga${margaName ? ` ${margaName}` : ''}`}
                        headerAction={
                            margaRequestEntry?.can_request_marga_tree && (
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={
                                        margaRequestEntry.marga_request_status !==
                                        null
                                    }
                                    className="text-tb-on-primary shrink-0 self-start bg-tb-primary text-xs hover:bg-tb-primary-light"
                                    onClick={() => {
                                        if (
                                            margaRequestEntry.marga_request_status ===
                                            null
                                        ) {
                                            router.post(
                                                contributions.margaTree.store(
                                                    margaRequestEntry.id,
                                                ).url,
                                                {},
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    <TreePine className="size-3.5" />{' '}
                                    {margaRequestEntry.marga_request_status ===
                                    'pending'
                                        ? 'Menunggu Persetujuan'
                                        : margaRequestEntry.marga_request_status ===
                                            'approved'
                                          ? 'Sudah Disetujui'
                                          : 'Ajukan Silsilah Marga'}
                                </Button>
                            )
                        }
                    />
                    <div className="border-t border-tb-outline-variant pt-6">
                        <h3 className="font-display text-base font-semibold text-tb-on-surface">
                            Silsilah Milik Akun
                        </h3>
                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                            Silsilah yang Anda buat dan dapat Anda kelola.
                        </p>
                    </div>
                    <div ref={listTopRef} />
                    {entries.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-tb-outline-variant bg-tb-surface-container/40 px-4 py-6 text-center">
                            <TreePine className="mx-auto size-6 text-tb-outline" />
                            <p className="mt-2 text-sm font-medium text-tb-on-surface">
                                Belum ada silsilah
                            </p>
                            <p className="mt-1 text-xs text-tb-on-surface-variant">
                                Pohon pertama akan muncul setelah keluarga
                                disimpan.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            {showSearch && (
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-on-surface-variant" />
                                    <Input
                                        type="search"
                                        value={searchQuery}
                                        onChange={(event) => {
                                            setSearchQuery(event.target.value);
                                            setCurrentPage(1);
                                        }}
                                        placeholder="Cari silsilah…"
                                        aria-label="Cari silsilah"
                                        className="border-tb-outline-variant bg-tb-surface-container/40 pl-9 text-tb-on-surface placeholder:text-tb-outline"
                                    />
                                </div>
                            )}
                            {filteredEntries.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-tb-outline-variant bg-tb-surface-container/40 px-4 py-6 text-center">
                                    <Search className="mx-auto size-6 text-tb-outline" />
                                    <p className="mt-2 text-sm font-medium text-tb-on-surface">
                                        Tidak ada hasil
                                    </p>
                                    <p className="mt-1 text-xs text-tb-on-surface-variant">
                                        Tidak ada silsilah yang cocok dengan
                                        pencarian "{searchQuery.trim()}
                                        ". Coba kata kunci lain.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <ol className="grid gap-3">
                                        {visibleEntries.map((entry, index) => (
                                            <li
                                                key={entry.id}
                                                className="rounded-xl border border-tb-outline-variant bg-tb-surface-container/35 p-4"
                                            >
                                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <Link
                                                        href={familyTrees.show(
                                                            entry.id,
                                                        )}
                                                        className="group flex min-w-0 items-start gap-3"
                                                    >
                                                        <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-tb-surface-bright text-sm font-bold text-tb-on-surface-variant ring-1 ring-tb-outline-variant">
                                                            {startIndex +
                                                                index +
                                                                1}
                                                        </span>
                                                        <span className="min-w-0 flex-1">
                                                            <span className="flex flex-wrap items-center gap-2">
                                                                <span className="truncate text-sm font-semibold text-tb-on-surface group-hover:text-tb-primary">
                                                                    {entry.name ??
                                                                        `Silsilah ${entry.root_name}`}
                                                                </span>
                                                                {entry.is_primary && (
                                                                    <span className="inline-flex items-center gap-1 rounded-full bg-tb-primary/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-tb-primary uppercase">
                                                                        <Crown className="size-3" />
                                                                        Versi
                                                                        Utama
                                                                    </span>
                                                                )}
                                                                {entry.access ===
                                                                    'shared' && (
                                                                    <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-blue-700 uppercase dark:text-blue-300">
                                                                        <Share2 className="size-3" />
                                                                        Dari{' '}
                                                                        {
                                                                            entry.owner_name
                                                                        }
                                                                    </span>
                                                                )}
                                                            </span>
                                                            <span className="mt-1 block text-xs text-tb-on-surface-variant">
                                                                Akar:{' '}
                                                                {
                                                                    entry.root_name
                                                                }
                                                            </span>
                                                            <span className="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] text-tb-on-surface-variant">
                                                                <Clock3 className="size-3" />
                                                                <span>
                                                                    {entry.source_name ||
                                                                        `Diinput oleh: ${entry.owner_name}`}
                                                                </span>
                                                                <span className="text-tb-outline">
                                                                    ·
                                                                </span>
                                                                <span>
                                                                    Diperbarui{' '}
                                                                    {dateFormatter.format(
                                                                        new Date(
                                                                            entry.updated_at,
                                                                        ),
                                                                    )}
                                                                </span>
                                                            </span>
                                                        </span>
                                                        <ArrowUpRight className="mt-0.5 size-4 shrink-0 text-tb-outline transition-colors group-hover:text-tb-primary" />
                                                    </Link>
                                                    <div className="flex flex-wrap gap-2 sm:justify-end">
                                                        <Link
                                                            href={familyTrees.show(
                                                                entry.id,
                                                            )}
                                                            className="text-tb-on-primary inline-flex items-center gap-1.5 rounded-lg bg-tb-primary px-3 py-2 text-xs font-semibold transition-opacity hover:opacity-90"
                                                        >
                                                            <TreePine className="size-3.5" />{' '}
                                                            Buka
                                                        </Link>
                                                        {entry.access ===
                                                            'owner' && (
                                                            <Link
                                                                href={tarombo.fullscreen(
                                                                    'tree',
                                                                    {
                                                                        query: {
                                                                            family_tree:
                                                                                entry.id,
                                                                            person: entry.root_person_id,
                                                                        },
                                                                    },
                                                                )}
                                                                className="inline-flex items-center gap-1.5 rounded-lg border border-tb-primary px-3 py-2 text-xs font-semibold text-tb-primary transition-colors hover:bg-tb-primary/10"
                                                            >
                                                                <Maximize2 className="size-3.5" />{' '}
                                                                Buka Full
                                                            </Link>
                                                        )}
                                                        {entry.can_manage && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                className="text-xs"
                                                                onClick={() => {
                                                                    setRenameEntry(
                                                                        entry,
                                                                    );
                                                                    setRenameName(
                                                                        entry.name ??
                                                                            `Silsilah ${entry.root_name}`,
                                                                    );
                                                                }}
                                                            >
                                                                <Pencil className="size-3.5" />{' '}
                                                                Ubah Nama
                                                            </Button>
                                                        )}
                                                        {entry.can_manage && (
                                                            <Link
                                                                href={people.familyVersion.duplicate(
                                                                    entry.root_person_id,
                                                                )}
                                                                method="post"
                                                                as="button"
                                                                className="inline-flex items-center gap-1.5 rounded-lg border border-tb-outline-variant px-3 py-2 text-xs font-semibold text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary"
                                                            >
                                                                <Copy className="size-3.5" />{' '}
                                                                Versi Alternatif
                                                                Keluarga
                                                            </Link>
                                                        )}
                                                        {entry.can_manage && (
                                                            <Link
                                                                href={people.edit(
                                                                    entry.root_person_id,
                                                                    {
                                                                        query: {
                                                                            version_tree:
                                                                                entry.id,
                                                                        },
                                                                    },
                                                                )}
                                                                className="inline-flex items-center gap-1.5 rounded-lg border border-tb-outline-variant px-3 py-2 text-xs font-semibold text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary"
                                                            >
                                                                <Pencil className="size-3.5" />{' '}
                                                                Ubah Struktur
                                                            </Link>
                                                        )}
                                                        {entry.can_share && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                className="text-xs"
                                                                onClick={() => {
                                                                    setShareEntry(
                                                                        entry,
                                                                    );
                                                                    setRecipientId(
                                                                        '',
                                                                    );
                                                                }}
                                                            >
                                                                <Share2 className="size-3.5" />{' '}
                                                                Share
                                                            </Button>
                                                        )}
                                                        {entry.access ===
                                                            'shared' &&
                                                            entry.can_append && (
                                                                <Link
                                                                    href={familyTrees.people.create(
                                                                        entry.id,
                                                                    )}
                                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-tb-primary px-3 py-2 text-xs font-semibold text-tb-primary transition-colors hover:bg-tb-primary/10"
                                                                >
                                                                    <UserPlus className="size-3.5" />{' '}
                                                                    Tambah
                                                                    Anggota
                                                                </Link>
                                                            )}
                                                        {entry.can_manage &&
                                                        entry.deletion_pending ? (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                disabled
                                                                className="border-amber-300 bg-amber-50 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                                                            >
                                                                Menunggu
                                                                Persetujuan
                                                            </Button>
                                                        ) : entry.can_manage ? (
                                                            entry.can_delete && (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="border-red-300 text-xs text-red-700 hover:bg-red-50 hover:text-red-800 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950"
                                                                    onClick={() => {
                                                                        const confirmed =
                                                                            window.confirm(
                                                                                `Hapus ${entry.name ?? `Silsilah ${entry.root_name}`}? Data anggota tetap tersimpan. Jika terhubung dengan akun lain, penghapusan akan menunggu persetujuan Kontributor.`,
                                                                            );

                                                                        if (
                                                                            confirmed
                                                                        ) {
                                                                            router.delete(
                                                                                familyTrees.destroy(
                                                                                    entry.id,
                                                                                )
                                                                                    .url,
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            );
                                                                        }
                                                                    }}
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                    Hapus
                                                                </Button>
                                                            )
                                                        ) : null}
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ol>
                                    {showPagination && (
                                        <div className="flex flex-col items-center justify-between gap-3 text-sm text-tb-on-surface-variant sm:flex-row">
                                            <p>
                                                Menampilkan {rangeStart}–
                                                {rangeEnd} dari{' '}
                                                {filteredEntries.length}{' '}
                                                silsilah
                                            </p>
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                                                    disabled={safePage <= 1}
                                                    onClick={() =>
                                                        goToPage(safePage - 1)
                                                    }
                                                >
                                                    Sebelumnya
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface"
                                                    disabled={
                                                        safePage >= totalPages
                                                    }
                                                    onClick={() =>
                                                        goToPage(safePage + 1)
                                                    }
                                                >
                                                    Berikutnya
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>
            <Dialog
                open={shareEntry !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setShareEntry(null);
                        setRecipientId('');
                        setRecipientSearch('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Bagikan Silsilah</DialogTitle>
                        <DialogDescription>
                            Penerima harus menyetujui undangan. Aksesnya hanya
                            untuk menambah anggota baru, tanpa mengubah data
                            lama.
                        </DialogDescription>
                    </DialogHeader>
                    {shareEntry && (
                        <div className="grid gap-5">
                            <form
                                className="grid gap-3"
                                onSubmit={(event) => {
                                    event.preventDefault();

                                    if (!recipientId) {
                                        return;
                                    }

                                    router.post(
                                        familyTrees.shares.store(shareEntry.id)
                                            .url,
                                        { recipient_id: Number(recipientId) },
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRecipientId('');
                                                setRecipientSearch('');
                                                setShareEntry(null);
                                            },
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <label className="text-sm font-medium text-tb-on-surface">
                                            Pilih akun penerima
                                        </label>
                                        <span className="text-xs text-tb-on-surface-variant">
                                            {availableRecipients.length} akun
                                            tersedia
                                        </span>
                                    </div>
                                    <Input
                                        value={recipientSearch}
                                        onChange={(event) =>
                                            setRecipientSearch(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Cari nama, email, atau marga..."
                                        aria-label="Cari akun penerima"
                                    />
                                    <div className="grid max-h-56 gap-1 overflow-y-auto rounded-lg border border-tb-outline-variant bg-tb-surface-container/40 p-1">
                                        {availableRecipients.length > 0 ? (
                                            availableRecipients.map(
                                                (account) => {
                                                    const selected =
                                                        recipientId ===
                                                        String(account.id);

                                                    return (
                                                        <button
                                                            key={account.id}
                                                            type="button"
                                                            onClick={() =>
                                                                setRecipientId(
                                                                    String(
                                                                        account.id,
                                                                    ),
                                                                )
                                                            }
                                                            className={cn(
                                                                'flex items-center justify-between gap-3 rounded-md px-3 py-2 text-left transition-colors hover:bg-tb-primary/10',
                                                                selected &&
                                                                    'bg-tb-primary/10 ring-1 ring-tb-primary/40',
                                                            )}
                                                        >
                                                            <span className="min-w-0">
                                                                <span className="block truncate text-sm font-medium text-tb-on-surface">
                                                                    {
                                                                        account.name
                                                                    }
                                                                </span>
                                                                <span className="block truncate text-xs text-tb-on-surface-variant">
                                                                    {
                                                                        account.email
                                                                    }
                                                                    {account.marga
                                                                        ? ` · ${account.marga}`
                                                                        : ''}
                                                                </span>
                                                            </span>
                                                            {selected && (
                                                                <Check className="size-4 shrink-0 text-tb-primary" />
                                                            )}
                                                        </button>
                                                    );
                                                },
                                            )
                                        ) : (
                                            <p className="px-3 py-4 text-center text-xs text-tb-on-surface-variant">
                                                Tidak ada akun yang cocok.
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={!recipientId}
                                    className="w-full sm:w-auto sm:justify-self-end"
                                >
                                    <Share2 className="size-4" /> Kirim Undangan
                                </Button>
                            </form>

                            <div className="grid gap-2">
                                <p className="text-sm font-semibold text-tb-on-surface">
                                    Akun yang telah diundang
                                </p>
                                {shareEntry.shares.length === 0 ? (
                                    <p className="rounded-lg border border-dashed border-tb-outline-variant p-3 text-xs text-tb-on-surface-variant">
                                        Belum ada akun yang diundang.
                                    </p>
                                ) : (
                                    shareEntry.shares.map((share) => (
                                        <div
                                            key={share.id}
                                            className="flex items-center justify-between gap-3 rounded-lg border border-tb-outline-variant p-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-tb-on-surface">
                                                    {share.recipient_name}
                                                </p>
                                                <p className="truncate text-xs text-tb-on-surface-variant">
                                                    {share.recipient_email} ·{' '}
                                                    {share.status === 'accepted'
                                                        ? 'Diterima'
                                                        : share.status ===
                                                            'pending'
                                                          ? 'Menunggu'
                                                          : 'Ditolak'}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600"
                                                onClick={() =>
                                                    router.delete(
                                                        familyTreeShares.destroy(
                                                            share.id,
                                                        ).url,
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () =>
                                                                setShareEntry(
                                                                    null,
                                                                ),
                                                        },
                                                    )
                                                }
                                            >
                                                Cabut
                                            </Button>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
            <Dialog
                open={renameEntry !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRenameEntry(null);
                        setRenameName('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Ubah Nama Silsilah</DialogTitle>
                        <DialogDescription>
                            Nama ini hanya mengubah label silsilah. Struktur,
                            anggota, dan chain tetap sama.
                        </DialogDescription>
                    </DialogHeader>
                    {renameEntry && (
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();

                                const name = renameName.trim();

                                if (!name) {
                                    return;
                                }

                                router.patch(
                                    familyTrees.name.update(renameEntry.id).url,
                                    { name },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setRenameEntry(null);
                                            setRenameName('');
                                        },
                                    },
                                );
                            }}
                        >
                            <div className="grid gap-2">
                                <label
                                    htmlFor="family-tree-name"
                                    className="text-sm font-medium text-tb-on-surface"
                                >
                                    Nama silsilah
                                </label>
                                <Input
                                    id="family-tree-name"
                                    value={renameName}
                                    onChange={(event) =>
                                        setRenameName(event.target.value)
                                    }
                                    maxLength={120}
                                    autoFocus
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setRenameEntry(null)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={!renameName.trim()}
                                >
                                    Simpan
                                </Button>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
