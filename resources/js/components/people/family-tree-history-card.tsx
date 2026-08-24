import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    Clock3,
    Copy,
    Crown,
    Maximize2,
    Pencil,
    Search,
    TreePine,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import familyTrees from '@/routes/family-trees';

const ITEMS_PER_PAGE = 5;

export type FamilyTreeHistoryEntry = {
    id: number;
    root_person_id: number;
    root_name: string;
    name: string | null;
    source_name: string | null;
    is_primary: boolean;
    updated_at: string;
};

export function FamilyTreeHistoryCard({
    entries,
}: {
    entries: FamilyTreeHistoryEntry[];
}) {
    const [expanded, setExpanded] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
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
    const rangeStart = filteredEntries.length === 0 ? 0 : startIndex + 1;
    const rangeEnd = Math.min(
        startIndex + ITEMS_PER_PAGE,
        filteredEntries.length,
    );
    const showPagination = filteredEntries.length > ITEMS_PER_PAGE;

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
                                <CardDescription>
                                    Pohon yang pernah Anda buat, diurutkan dari
                                    pembaruan terbaru.
                                </CardDescription>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setExpanded((current) => !current)}
                            aria-expanded={expanded}
                            aria-label={
                                expanded
                                    ? 'Kembali ke form Tambah Keluarga'
                                    : 'Perbesar Daftar Silsilah'
                            }
                            title={expanded ? 'Kembali ke Form' : 'Perbesar'}
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
                </CardHeader>
                <CardContent>
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
                                                                    {entry.source_name ??
                                                                        'Sumber belum dicatat'}
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
                                                        <Link
                                                            href={familyTrees.duplicate(
                                                                entry.id,
                                                            )}
                                                            method="post"
                                                            as="button"
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-tb-outline-variant px-3 py-2 text-xs font-semibold text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary"
                                                        >
                                                            <Copy className="size-3.5" />{' '}
                                                            Versi Alternatif
                                                        </Link>
                                                        <Link
                                                            href={familyTrees.edit(
                                                                entry.id,
                                                            )}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-tb-outline-variant px-3 py-2 text-xs font-semibold text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary"
                                                        >
                                                            <Pencil className="size-3.5" />{' '}
                                                            Ubah Struktur
                                                        </Link>
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
        </div>
    );
}
