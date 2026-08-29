import { Link, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Copy,
    Eye,
    ImageIcon,
    Layers3,
    Link2,
    Pencil,
    Plus,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { FamilyTreeHistoryCard } from '@/components/people/family-tree-history-card';
import type { FamilyTreeHistoryEntry } from '@/components/people/family-tree-history-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NameCombobox } from '@/components/ui/name-combobox';
import type { NameSuggestion } from '@/components/ui/name-combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getInitials } from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';
import familyTreeRoutes from '@/routes/family-trees';
import people from '@/routes/people';

export type ChildRow = {
    id?: number | null;
    uid?: string;
    linkedFromSuggestion?: boolean;
    name: string;
    gender: string;
    spouse: string;
    spouse_marga: string;
    marga_id?: number | null;
    new_marga?: string;
    marga?: string | null;
    birth_order?: number | null;
    alias?: string | null;
    chain?: string | null;
    pending?: boolean;
    birth_year?: string | null;
    death_year?: string | null;
    image?: string | null;
    bio?: string | null;
    descendant_count?: number;
    descendant_names?: string[];
    mother_id?: number | null;
    mother_index?: number | null;
};

export type RelatedStoryEntry = {
    title: string;
    url: string;
};

type ParentEntry = {
    id?: number | null;
    name: string;
    alias: string;
    birth_year: string;
    death_year: string;
    marga_id?: number | null;
    new_marga?: string;
    father_name?: string;
    father_marga_id?: number | null;
    father_marga?: string | null;
};

export type LineageChild = {
    id: number;
    name: string;
    gender: string | null;
    marga: string | null;
    chain: string | null;
    birth_order: number | null;
    editable?: boolean;
    isSelf?: boolean;
};

export type LineageEntry = {
    id: number;
    name: string;
    marga: string | null;
    chain: string | null;
    is_self: boolean;
    editable?: boolean;
    children: LineageChild[];
};

export type MargaLineageEntry = {
    id: number;
    name: string | null;
    marga_id: number | null;
    marga: string | null;
    chain: string | null;
    isAyah?: boolean;
    editable?: boolean;
    children?: LineageChild[];
};

export type FamilyData = {
    id?: number | null;
    name: string;
    gender: string;
    alias: string;
    marga_id: number | null;
    birth_order: number | null;
    sibling_count: number | null;
    chain: string | null;
    birth_year: string;
    death_year: string;
    image: string;
    bio: string;
    related_stories: RelatedStoryEntry[];
    new_marga?: string;
    father: ParentEntry | null;
    mother: ParentEntry | null;
    mothers?: ParentEntry[] | null;
    lineage: LineageEntry[];
    children: ChildRow[];
    ownChildren?: ChildRow[];
    is_public: boolean;
};

type MargaOption = { id: number; name: string };

type Props = {
    person: FamilyData | null;
    margas: MargaOption[];
    nameSuggestions: NameSuggestion[];
    fatherSuggestions: NameSuggestion[];
    lockedMarga?: { id: number; name: string } | null;
    lineage?: MargaLineageEntry[];
    familyTrees?: FamilyTreeHistoryEntry[];
    approvedMargaTrees?: FamilyTreeHistoryEntry[];
    versionTrees?: FamilyTreeHistoryEntry[];
    selectedVersionName?: string | null;
    selectedVersionId?: number | null;
    shareableAccounts?: ShareableAccount[];
    pendingTreeShares?: PendingTreeShare[];
    showFamilyTreeHistory?: boolean;
    initialFatherName?: string;
    canPublish?: boolean;
    readOnly?: boolean;
};

export type ShareableAccount = {
    id: number;
    name: string;
    email: string;
    marga: string | null;
};

export type PendingTreeShare = {
    id: number;
    tree_name: string;
    sender_name: string;
};

const VALUE_NONE = 'none';
const NEW_MARGA_VALUE = '__new__';
const SPOUSE_OTHER_VALUE = '__other__';

const normalizeNameForMatch = (value: string): string =>
    value.trim().replace(/\s+/g, ' ').toLocaleUpperCase();

type ParentKey = 'father' | number;

const parentErrorPrefix = (key: ParentKey): 'father' | `mothers.${number}` =>
    key === 'father' ? 'father' : `mothers.${key}`;

const orderFamilyTreeVersions = (entries: FamilyTreeHistoryEntry[]) =>
    [...entries].sort(
        (left, right) =>
            Number(right.is_primary) - Number(left.is_primary) ||
            left.id - right.id,
    );

function FamilyTreeVersionAction({
    entries,
    personId,
    iconOnly = false,
    mode = 'duplicate',
}: {
    entries: FamilyTreeHistoryEntry[];
    personId?: number;
    iconOnly?: boolean;
    mode?: 'duplicate' | 'open';
}) {
    if (entries.length === 0) {
        if (personId == null) {
            return null;
        }

        return (
            <Link
                href={people.familyVersion.duplicate(personId)}
                method="post"
                as="button"
                aria-label={
                    iconOnly
                        ? 'Salin keluarga menjadi versi alternatif'
                        : undefined
                }
                title={
                    iconOnly
                        ? 'Salin keluarga menjadi versi alternatif'
                        : undefined
                }
                className={cn(
                    'inline-flex shrink-0 items-center justify-center rounded-lg border border-tb-outline-variant text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary',
                    iconOnly
                        ? 'size-6 text-tb-outline opacity-70 hover:bg-tb-primary/10 hover:opacity-100 focus-visible:ring-2 focus-visible:ring-tb-primary/40 focus-visible:outline-none'
                        : 'gap-1.5 px-3 py-2 text-xs font-semibold',
                )}
            >
                <Copy className="size-3.5" />
                {!iconOnly && ' Salin Versi'}
            </Link>
        );
    }

    const isOpenMode = mode === 'open';
    const Icon = isOpenMode ? Layers3 : Copy;
    const actionLabel = isOpenMode ? 'Versi Silsilah' : 'Salin Versi';
    const orderedEntries = orderFamilyTreeVersions(entries);
    const versionNumberById = new Map(
        orderedEntries.map((entry, index) => [entry.id, index + 1]),
    );
    const actionHref = (entry: FamilyTreeHistoryEntry) =>
        isOpenMode
            ? entry.can_manage
                ? people.edit(entry.root_person_id, {
                      query: { version_tree: entry.id },
                  })
                : familyTreeRoutes.show(entry.id)
            : familyTreeRoutes.duplicate(entry.id);

    if (entries.length === 1) {
        return (
            <Link
                href={actionHref(entries[0])}
                method={isOpenMode ? 'get' : 'post'}
                as={isOpenMode ? 'a' : 'button'}
                aria-label={iconOnly ? actionLabel : undefined}
                title={iconOnly ? actionLabel : undefined}
                className={cn(
                    'inline-flex shrink-0 items-center justify-center rounded-lg border border-tb-outline-variant text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary',
                    iconOnly
                        ? 'size-6 text-tb-outline opacity-70 hover:bg-tb-primary/10 hover:opacity-100 focus-visible:ring-2 focus-visible:ring-tb-primary/40 focus-visible:outline-none'
                        : 'gap-1.5 px-3 py-2 text-xs font-semibold',
                )}
            >
                <Icon className="size-3.5" />
                {!iconOnly && ` ${actionLabel}`}
            </Link>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={iconOnly ? actionLabel : undefined}
                    title={iconOnly ? actionLabel : undefined}
                    className={cn(
                        'inline-flex shrink-0 items-center justify-center rounded-lg border border-tb-outline-variant text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary',
                        iconOnly
                            ? 'size-6 text-tb-outline opacity-70 hover:bg-tb-primary/10 hover:opacity-100 focus-visible:ring-2 focus-visible:ring-tb-primary/40 focus-visible:outline-none'
                            : 'gap-1.5 px-3 py-2 text-xs font-semibold',
                    )}
                >
                    <Icon className="size-3.5" />
                    {!iconOnly && (
                        <>
                            {' '}
                            {actionLabel}
                            <ChevronDown className="size-3.5" />
                        </>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-64">
                {orderedEntries.map((entry) => (
                    <DropdownMenuItem key={entry.id} asChild>
                        <Link
                            href={actionHref(entry)}
                            method={isOpenMode ? 'get' : 'post'}
                            as={isOpenMode ? 'a' : 'button'}
                            className="w-full text-left"
                        >
                            {entry.name ?? entry.root_name} (V
                            {versionNumberById.get(entry.id)})
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function FamilyTreePersonControls({
    entries,
    personId,
    personName,
    editable = false,
}: {
    entries: FamilyTreeHistoryEntry[];
    personId: number;
    personName: string | null | undefined;
    editable?: boolean;
}) {
    const manageableEntries = entries.filter((entry) => entry.can_manage);

    return (
        <div className="flex shrink-0 flex-col items-end gap-1">
            <div className="flex items-center gap-1.5">
                <FamilyTreeVersionAction
                    entries={entries}
                    personId={personId}
                    iconOnly
                    mode={manageableEntries.length > 0 ? 'duplicate' : 'open'}
                />
                {(manageableEntries.length > 0 || editable) && (
                    <Link
                        href={people.edit(personId)}
                        aria-label={`Ubah ${displayRowName(personName)}`}
                        className="inline-flex size-6 shrink-0 items-center justify-center rounded-md border border-tb-outline-variant text-tb-outline opacity-70 transition-opacity hover:border-tb-primary hover:text-tb-primary hover:opacity-100"
                    >
                        <Pencil className="size-3.5" />
                    </Link>
                )}
            </div>
            <span className="rounded-full bg-tb-surface-container px-1.5 py-0.5 text-[10px] leading-none font-semibold whitespace-nowrap text-tb-on-surface-variant">
                {entries.length +
                    (entries.some((entry) => entry.is_primary) ? 0 : 1)}{' '}
                versi
            </span>
        </div>
    );
}

function isNameFilled(name: string): boolean {
    return name.trim() !== '' && name.trim().toUpperCase() !== 'N/A';
}

function isRowFilled(row: ChildRow): boolean {
    return row.id != null || isNameFilled(row.name ?? '');
}

function isNaPlaceholder(name: string | null | undefined): boolean {
    return (name ?? '').trim().toUpperCase() === 'N/A';
}

function displayRowName(name: string | null | undefined): string {
    const trimmed = (name ?? '').trim();

    if (isNameFilled(trimmed)) {
        return trimmed;
    }

    return isNaPlaceholder(name) ? 'N/A' : 'Belum diisi';
}

function isGapRow(row: ChildRow): boolean {
    return row.id == null && !isNameFilled(row.name ?? '');
}

function treeBelongsToFamily(
    tree: FamilyTreeHistoryEntry,
    personId: number,
): boolean {
    return Number(tree.root_person_id) === Number(personId);
}

function SilsilahListCard({
    lineage,
    selfId,
    familyTrees,
}: {
    lineage: LineageEntry[];
    selfId?: number | null;
    familyTrees: FamilyTreeHistoryEntry[];
}) {
    const [expanded, setExpanded] = useState<Set<number>>(() => {
        const selfIndex = lineage.findIndex((entry) => entry.is_self);

        return selfIndex > 0
            ? new Set([lineage[selfIndex - 1].id])
            : new Set<number>();
    });

    const toggle = (id: number) => {
        setExpanded((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    return (
        <Card className="border-tb-outline-variant bg-tb-surface-bright">
            <CardHeader>
                <CardTitle className="font-display text-lg text-tb-on-surface">
                    List Silsilah
                </CardTitle>
                <CardDescription>
                    Garis keturunan Anda (buyut &rarr; kakek &rarr; ayah &rarr;
                    Anda). Klik baris untuk melihat saudaranya.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-2">
                {lineage.length === 0 ? (
                    <p className="text-sm text-tb-on-surface-variant italic">
                        Belum ada catatan garis keturunan.
                    </p>
                ) : (
                    <ul className="grid gap-1.5">
                        {lineage.map((entry) => {
                            const isOpen = expanded.has(entry.id);
                            const count = entry.children.length;
                            const entryTrees = familyTrees.filter((tree) =>
                                treeBelongsToFamily(tree, entry.id),
                            );

                            return (
                                <li
                                    key={entry.id}
                                    className={cn(
                                        'overflow-hidden rounded-lg border transition-colors',
                                        isOpen
                                            ? 'border-tb-primary/40 bg-tb-primary/5'
                                            : 'border-tb-outline-variant bg-tb-surface-bright',
                                    )}
                                >
                                    <div className="flex items-center gap-2 px-2 py-2">
                                        <button
                                            type="button"
                                            onClick={() => toggle(entry.id)}
                                            aria-label={
                                                isOpen
                                                    ? 'Tutup daftar saudara'
                                                    : 'Buka daftar saudara'
                                            }
                                            className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface"
                                        >
                                            {isOpen ? (
                                                <ChevronDown className="h-4 w-4" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4" />
                                            )}
                                        </button>
                                        <div className="min-w-0 flex-1">
                                            <span className="inline-flex min-h-6 max-w-full min-w-6 items-center justify-center overflow-x-auto rounded-full bg-tb-surface-container px-2 py-1 text-center text-xs leading-tight font-bold whitespace-nowrap text-tb-on-surface-variant">
                                                {entry.chain ?? '—'}
                                            </span>
                                            <div className="mt-1 flex items-start gap-1.5">
                                                <Link
                                                    href={people.show(entry.id)}
                                                    className="min-w-0 flex-1 text-sm leading-snug font-semibold break-words whitespace-normal text-tb-on-surface hover:text-tb-primary"
                                                >
                                                    {displayRowName(entry.name)}
                                                </Link>
                                                {entry.is_self && (
                                                    <span className="shrink-0 rounded-full bg-tb-primary px-2 py-0.5 text-[10px] font-bold text-white">
                                                        Anda
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-0.5 text-xs break-words text-tb-on-surface-variant">
                                                {entry.marga || 'Tanpa marga'}
                                                {count > 0 &&
                                                    ` · ${count} anak`}
                                            </p>
                                        </div>
                                        <FamilyTreePersonControls
                                            entries={entryTrees}
                                            personId={entry.id}
                                            personName={entry.name}
                                            editable={entry.editable}
                                        />
                                    </div>

                                    {isOpen && (
                                        <div className="ml-[3.25rem] border-l border-tb-outline-variant pb-1.5 pl-2">
                                            {count === 0 ? (
                                                <p className="px-1 py-1 text-xs text-tb-on-surface-variant italic">
                                                    Belum ada saudara tercatat.
                                                </p>
                                            ) : (
                                                <ul className="grid gap-0.5">
                                                    {entry.children.map(
                                                        (child) => {
                                                            const filled =
                                                                isNameFilled(
                                                                    child.name,
                                                                );
                                                            const isSelf =
                                                                child.id ===
                                                                selfId;
                                                            const childTrees =
                                                                familyTrees.filter(
                                                                    (tree) =>
                                                                        treeBelongsToFamily(
                                                                            tree,
                                                                            child.id,
                                                                        ),
                                                                );

                                                            return (
                                                                <li
                                                                    key={
                                                                        child.id
                                                                    }
                                                                >
                                                                    <div className="group flex items-center gap-1.5 rounded-md px-1.5 py-1 transition-colors hover:bg-tb-surface-container/70">
                                                                        <Link
                                                                            href={people.show(
                                                                                child.id,
                                                                            )}
                                                                            className="flex min-w-0 flex-1 flex-col items-start rounded-md"
                                                                        >
                                                                            <span className="inline-flex min-h-5 max-w-full min-w-5 items-center justify-center overflow-x-auto rounded-full bg-tb-surface-container px-1.5 py-0.5 text-center text-[10px] leading-tight font-semibold whitespace-nowrap text-tb-on-surface-variant">
                                                                                {child.chain ??
                                                                                    ''}
                                                                            </span>
                                                                            <span className="mt-1 flex w-full min-w-0 items-start gap-1.5">
                                                                                <span
                                                                                    className={cn(
                                                                                        'min-w-0 flex-1 text-sm leading-snug break-words whitespace-normal',
                                                                                        filled
                                                                                            ? 'text-tb-on-surface'
                                                                                            : 'text-tb-on-surface-variant italic',
                                                                                    )}
                                                                                >
                                                                                    {filled
                                                                                        ? child.name
                                                                                        : displayRowName(
                                                                                              child.name,
                                                                                          )}
                                                                                </span>
                                                                                {isSelf && (
                                                                                    <span className="shrink-0 text-[11px] font-medium text-tb-primary">
                                                                                        Anda
                                                                                    </span>
                                                                                )}
                                                                            </span>
                                                                        </Link>
                                                                        <FamilyTreePersonControls
                                                                            entries={
                                                                                childTrees
                                                                            }
                                                                            personId={
                                                                                child.id
                                                                            }
                                                                            personName={
                                                                                child.name
                                                                            }
                                                                            editable={
                                                                                child.editable
                                                                            }
                                                                        />
                                                                    </div>
                                                                </li>
                                                            );
                                                        },
                                                    )}
                                                </ul>
                                            )}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function MargaLineageCard({
    entries,
    fatherChain,
    focusChain,
    familyTrees,
}: {
    entries: MargaLineageEntry[];
    fatherChain?: string | null;
    focusChain?: string | null;
    familyTrees: FamilyTreeHistoryEntry[];
}) {
    const [expanded, setExpanded] = useState<Set<string>>(new Set());

    const toggle = (key: string) => {
        setExpanded((prev) => {
            const next = new Set(prev);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    return (
        <Card className="border-tb-outline-variant bg-tb-surface-bright">
            <CardHeader>
                <CardTitle className="font-display text-lg text-tb-on-surface">
                    List Silsilah
                </CardTitle>
                <CardDescription>
                    Garis keturunan marga saat ini. Klik baris untuk melihat
                    saudaranya.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <p className="text-sm text-tb-on-surface-variant italic">
                        Belum ada catatan silsilah marga.
                    </p>
                ) : (
                    <ul className="grid gap-1.5">
                        {entries.map((entry) => {
                            const hasChildren =
                                (entry.children ?? []).length > 0;
                            const expandKey = `${entry.id}-${entry.chain ?? 'na'}`;
                            const isOpen = expanded.has(expandKey);
                            const entryTrees = familyTrees.filter((tree) =>
                                treeBelongsToFamily(tree, entry.id),
                            );

                            return (
                                <li
                                    key={`${entry.id}-${entry.chain ?? 'na'}`}
                                    className={cn(
                                        'overflow-hidden rounded-lg border transition-colors',
                                        entry.isAyah
                                            ? 'border-tb-primary/50 bg-tb-primary/5'
                                            : isOpen
                                              ? 'border-tb-primary/40 bg-tb-primary/5'
                                              : 'border-tb-outline-variant bg-tb-surface-bright',
                                    )}
                                >
                                    <div className="flex items-center gap-2 px-2 py-1.5">
                                        {hasChildren ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggle(expandKey)
                                                }
                                                aria-label={
                                                    isOpen
                                                        ? 'Tutup daftar saudara'
                                                        : 'Buka daftar saudara'
                                                }
                                                className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface"
                                            >
                                                {isOpen ? (
                                                    <ChevronDown className="h-4 w-4" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4" />
                                                )}
                                            </button>
                                        ) : (
                                            <span className="size-6 shrink-0" />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <span className="inline-flex min-h-6 max-w-full min-w-6 items-center justify-center overflow-x-auto rounded-full bg-tb-surface-container px-2 py-1 text-center text-xs leading-tight font-bold whitespace-nowrap text-tb-on-surface-variant">
                                                {entry.chain ?? '—'}
                                            </span>
                                            <div className="mt-1 flex items-start gap-1.5">
                                                <Link
                                                    href={people.show(entry.id)}
                                                    className="min-w-0 flex-1 text-sm leading-snug font-medium break-words whitespace-normal text-tb-on-surface hover:text-tb-primary"
                                                >
                                                    {displayRowName(entry.name)}
                                                </Link>
                                                {entry.isAyah && (
                                                    <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                        Ayah
                                                    </span>
                                                )}
                                            </div>
                                            {entry.marga && (
                                                <p className="mt-0.5 text-xs break-words text-tb-on-surface-variant">
                                                    {entry.marga}
                                                </p>
                                            )}
                                        </div>
                                        <FamilyTreePersonControls
                                            entries={entryTrees}
                                            personId={entry.id}
                                            personName={entry.name}
                                            editable={entry.editable}
                                        />
                                    </div>

                                    {isOpen && (
                                        <div className="ml-[3.25rem] border-l border-tb-outline-variant pb-1.5 pl-2">
                                            {(entry.children ?? []).length ===
                                            0 ? (
                                                <p className="px-1 py-1 text-xs text-tb-on-surface-variant italic">
                                                    Belum ada saudara tercatat.
                                                </p>
                                            ) : (
                                                <ul className="grid gap-0.5">
                                                    {(entry.children ?? []).map(
                                                        (child) => {
                                                            const childTrees =
                                                                familyTrees.filter(
                                                                    (tree) =>
                                                                        treeBelongsToFamily(
                                                                            tree,
                                                                            child.id,
                                                                        ),
                                                                );

                                                            return (
                                                                <li
                                                                    key={`${child.id}-${child.chain ?? 'na'}`}
                                                                >
                                                                    <div className="group flex items-center gap-1.5 rounded-md px-1.5 py-1 transition-colors hover:bg-tb-surface-container/70">
                                                                        <Link
                                                                            href={people.show(
                                                                                child.id,
                                                                            )}
                                                                            className="flex min-w-0 flex-1 flex-col items-start rounded-md"
                                                                        >
                                                                            <span className="inline-flex min-h-5 max-w-full min-w-5 items-center justify-center overflow-x-auto rounded-full bg-tb-surface-container px-1.5 py-0.5 text-center text-[10px] leading-tight font-semibold whitespace-nowrap text-tb-on-surface-variant">
                                                                                {child.chain ??
                                                                                    ''}
                                                                            </span>
                                                                            <span className="mt-1 w-full min-w-0 text-sm leading-snug break-words whitespace-normal text-tb-on-surface">
                                                                                {displayRowName(
                                                                                    child.name,
                                                                                )}
                                                                            </span>
                                                                        </Link>
                                                                        <FamilyTreePersonControls
                                                                            entries={
                                                                                childTrees
                                                                            }
                                                                            personId={
                                                                                child.id
                                                                            }
                                                                            personName={
                                                                                child.name
                                                                            }
                                                                            editable={
                                                                                child.editable
                                                                            }
                                                                        />
                                                                    </div>
                                                                </li>
                                                            );
                                                        },
                                                    )}
                                                </ul>
                                            )}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}

                {(fatherChain || focusChain) && (
                    <div className="mt-4 rounded-lg border border-tb-primary/40 bg-tb-primary/5 p-3 text-sm">
                        {fatherChain && (
                            <p className="text-tb-on-surface-variant">
                                Ayah yang diketik:{' '}
                                <span className="font-semibold text-tb-primary">
                                    {fatherChain}
                                </span>
                            </p>
                        )}
                        {focusChain && (
                            <p className="text-tb-on-surface">
                                Prediksi chain untuk orang ini:{' '}
                                <span className="font-semibold text-tb-primary">
                                    {focusChain}
                                </span>
                            </p>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

let uidCounter = 0;

const createUid = (): string => {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `row-${Date.now().toString(36)}-${(uidCounter++).toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
};

const emptyRow = (): ChildRow => ({
    id: null,
    uid: createUid(),
    name: '',
    gender: '',
    spouse: '',
    spouse_marga: '',
    marga_id: null,
    new_marga: '',
});

const emptyOwnRow = (motherIndex: number | null = null): ChildRow => ({
    id: null,
    uid: createUid(),
    name: '',
    gender: '',
    spouse: '',
    spouse_marga: '',
    marga_id: null,
    marga: null,
    new_marga: '',
    birth_order: null,
    chain: null,
    pending: false,
    mother_index: motherIndex,
});

const emptyParent = (): ParentEntry => ({
    name: '',
    alias: '',
    birth_year: '',
    death_year: '',
    marga_id: null,
    new_marga: '',
    father_name: '',
});

function toMotherRows(person: FamilyData | null): ParentEntry[] {
    const fromList = (entries: ParentEntry[] | null | undefined) =>
        (entries ?? [])
            .map((entry) => ({
                id: entry.id ?? null,
                name: entry.name ?? '',
                alias: entry.alias ?? '',
                birth_year: entry.birth_year ?? '',
                death_year: entry.death_year ?? '',
                marga_id: entry.marga_id ?? null,
                new_marga: '',
                father_name: entry.father_name ?? '',
                father_marga_id: entry.father_marga_id ?? null,
                father_marga: entry.father_marga ?? null,
            }))
            .filter(
                (entry, index, all) =>
                    index ===
                    all.findIndex(
                        (other) =>
                            other.name.trim().toUpperCase() ===
                            entry.name.trim().toUpperCase(),
                    ),
            );

    if (person?.mothers && person.mothers.length > 0) {
        const rows = fromList(person.mothers);

        if (rows.length > 0) {
            return rows;
        }
    }

    if (person?.mother && isNameFilled(person.mother.name ?? '')) {
        return [
            {
                id: person.mother.id ?? null,
                name: person.mother.name ?? '',
                alias: person.mother.alias ?? '',
                birth_year: person.mother.birth_year ?? '',
                death_year: person.mother.death_year ?? '',
                marga_id: person.mother.marga_id ?? null,
                new_marga: '',
                father_name: person.mother.father_name ?? '',
                father_marga_id: person.mother.father_marga_id ?? null,
                father_marga: person.mother.father_marga ?? null,
            },
        ];
    }

    return [emptyParent()];
}

function soleMotherIndex(mothers: ParentEntry[]): number | null {
    const indexes = mothers
        .map((mother, index) => (isNameFilled(mother.name) ? index : -1))
        .filter((index) => index >= 0);

    return indexes.length === 1 ? indexes[0] : null;
}

function MargaField({
    value,
    newMarga,
    onValue,
    onNewMarga,
    margas,
    disabled = false,
    placeholder = 'Pilih marga',
}: {
    value: number | null;
    newMarga: string;
    onValue: (id: number | null) => void;
    onNewMarga: (name: string) => void;
    margas: MargaOption[];
    disabled?: boolean;
    placeholder?: string;
}) {
    const [creating, setCreating] = useState(false);

    if (disabled) {
        return (
            <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                {margas.find((marga) => marga.id === value)?.name ?? '—'}
            </div>
        );
    }

    if (creating) {
        return (
            <div className="flex gap-2">
                <Input
                    autoFocus
                    value={newMarga}
                    onChange={(e) => onNewMarga(e.target.value)}
                    placeholder="Nama marga baru"
                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        setCreating(false);
                        onNewMarga('');
                        onValue(null);
                    }}
                >
                    Batal
                </Button>
            </div>
        );
    }

    return (
        <Select
            value={value ? String(value) : VALUE_NONE}
            onValueChange={(selected) => {
                if (selected === NEW_MARGA_VALUE) {
                    setCreating(true);
                    onValue(null);
                } else {
                    onValue(selected === VALUE_NONE ? null : Number(selected));
                }
            }}
        >
            <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={VALUE_NONE}>— Pilih marga —</SelectItem>
                {margas.map((marga) => (
                    <SelectItem key={marga.id} value={String(marga.id)}>
                        {marga.name}
                    </SelectItem>
                ))}
                <SelectItem value={NEW_MARGA_VALUE}>
                    ＋ Buat marga baru…
                </SelectItem>
            </SelectContent>
        </Select>
    );
}

function SpouseMargaSelect({
    value,
    margas,
    onChange,
}: {
    value: string;
    margas: MargaOption[];
    onChange: (value: string) => void;
}) {
    const matched = margas.find((marga) => marga.name === value);
    const isOther = value.trim() !== '' && !matched;
    const selected = matched
        ? String(matched.id)
        : isOther
          ? SPOUSE_OTHER_VALUE
          : VALUE_NONE;

    return (
        <div className="grid gap-1.5">
            <Select
                value={selected}
                onValueChange={(next) => {
                    if (next === VALUE_NONE) {
                        onChange('');

                        return;
                    }

                    if (next === SPOUSE_OTHER_VALUE) {
                        onChange(value);

                        return;
                    }

                    onChange(
                        margas.find((marga) => String(marga.id) === next)
                            ?.name ?? '',
                    );
                }}
            >
                <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                    <SelectValue placeholder="Pilih marga pasangan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={VALUE_NONE}>— Tidak ada —</SelectItem>
                    {margas.map((marga) => (
                        <SelectItem key={marga.id} value={String(marga.id)}>
                            {marga.name}
                        </SelectItem>
                    ))}
                    <SelectItem value={SPOUSE_OTHER_VALUE}>Lainnya…</SelectItem>
                </SelectContent>
            </Select>
            {isOther && (
                <Input
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="Nama marga pasangan"
                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                />
            )}
        </div>
    );
}

export default function FamilyForm({
    person,
    margas,
    nameSuggestions,
    fatherSuggestions,
    lockedMarga = null,
    lineage,
    familyTrees = [],
    approvedMargaTrees = [],
    versionTrees = [],
    selectedVersionName = null,
    selectedVersionId = null,
    shareableAccounts = [],
    pendingTreeShares = [],
    showFamilyTreeHistory = true,
    canPublish = false,
    readOnly = false,
}: Props) {
    const isEdit = person !== null;
    const initialImageMode: 'url' | 'upload' =
        person?.image && !/^https?:\/\//i.test(person.image) ? 'upload' : 'url';
    const initialMothers = toMotherRows(person);
    const initialMotherIndex = (motherId: number | null | undefined) => {
        const index = initialMothers.findIndex(
            (mother) => mother.id === motherId,
        );

        return index >= 0 ? index : soleMotherIndex(initialMothers);
    };

    const {
        data,
        setData,
        transform,
        post,
        processing,
        errors,
        setError,
        clearErrors,
    } = useForm({
        name: person?.name ?? '',
        gender: person?.gender ?? '',
        alias: person?.alias ?? '',
        marga_id: person?.marga_id ?? lockedMarga?.id ?? null,
        new_marga: person?.new_marga ?? '',
        birth_order: person?.birth_order ?? 1,
        sibling_count:
            person?.sibling_count ?? Math.max(person?.children.length ?? 1, 1),
        birth_year: person?.birth_year ?? '',
        death_year: person?.death_year ?? '',
        image: person?.image ?? '',
        image_mode: initialImageMode,
        image_file: null as File | null,
        bio: person?.bio ?? '',
        related_stories: person?.related_stories ?? [],
        is_public: person?.is_public ?? false,
        father: person?.father
            ? {
                  name: person.father.name ?? '',
                  alias: person.father.alias ?? '',
                  birth_year: person.father.birth_year ?? '',
                  death_year: person.father.death_year ?? '',
                  marga_id: person.father.marga_id ?? lockedMarga?.id ?? null,
                  new_marga: '',
              }
            : emptyParent(),
        mothers: initialMothers,
        children:
            person?.children && person.children.length > 0
                ? person.children.map((child) => ({
                      id: child.id ?? null,
                      uid: createUid(),
                      name: child.name ?? '',
                      alias: child.alias ?? '',
                      gender: child.gender ?? '',
                      spouse: child.spouse ?? '',
                      spouse_marga: child.spouse_marga ?? '',
                      marga_id: child.marga_id ?? lockedMarga?.id ?? null,
                      new_marga: '',
                      pending: child.pending ?? false,
                      descendant_count: child.descendant_count ?? 0,
                      descendant_names: child.descendant_names ?? [],
                  }))
                : [emptyRow()],
        ownChildren:
            person?.ownChildren && person.ownChildren.length > 0
                ? person.ownChildren.map((child) => ({
                      id: child.id ?? null,
                      uid: createUid(),
                      name: child.name ?? '',
                      alias: child.alias ?? '',
                      gender: child.gender ?? '',
                      spouse: child.spouse ?? '',
                      spouse_marga: child.spouse_marga ?? '',
                      marga_id: child.marga_id ?? lockedMarga?.id ?? null,
                      new_marga: '',
                      pending: child.pending ?? false,
                      birth_order: child.birth_order ?? null,
                      chain: child.chain ?? null,
                      marga: child.marga ?? null,
                      descendant_count: child.descendant_count ?? 0,
                      descendant_names: child.descendant_names ?? [],
                      mother_id: child.mother_id ?? null,
                      mother_index: initialMotherIndex(child.mother_id),
                  }))
                : ([] as ChildRow[]),
        removed_child_ids: [] as number[],
        removed_own_child_ids: [] as number[],
        version_tree: selectedVersionId,
    });

    const birthOrder = Number(data.birth_order) || 1;
    const siblingCount = Number(data.sibling_count) || 1;
    const listFamilyTrees = [...familyTrees, ...approvedMargaTrees].filter(
        (tree, index, all) =>
            all.findIndex((candidate) => candidate.id === tree.id) === index,
    );
    const selectedVersionNumber =
        selectedVersionId === null
            ? null
            : orderFamilyTreeVersions(versionTrees).findIndex(
                  (entry) => entry.id === selectedVersionId,
              ) + 1;

    const prevSiblingCount = useRef(siblingCount);
    const savedExcessToastShown = useRef(false);
    const [reductionConfirm, setReductionConfirm] = useState<{
        from: number;
        to: number;
        filledNew: number;
    } | null>(null);
    const [removalConfirm, setRemovalConfirm] = useState<{
        kind: 'children' | 'ownChildren';
        index: number;
        row: ChildRow;
    } | null>(null);
    const [selectedIndex, setSelectedIndex] = useState<number | null>(null);
    const [imageStatus, setImageStatus] = useState<
        'idle' | 'loading' | 'valid' | 'invalid'
    >(person?.image ? 'loading' : 'idle');
    const [filePreview, setFilePreview] = useState<string | null>(null);

    useEffect(
        () => () => {
            if (filePreview) {
                URL.revokeObjectURL(filePreview);
            }
        },
        [filePreview],
    );

    const imagePreview =
        data.image_mode === 'upload'
            ? (filePreview ?? person?.image ?? '')
            : data.image.trim();

    const selectImageMode = (mode: 'url' | 'upload') => {
        setData('image_mode', mode);
        clearErrors('image', 'image_file');

        if (mode === 'url') {
            setData('image_file', null);
            setFilePreview(null);
        }

        if (mode === 'url' && data.image && !/^https?:\/\//i.test(data.image)) {
            setData('image', '');
            setImageStatus('idle');

            return;
        }

        const preview =
            mode === 'upload'
                ? (filePreview ?? person?.image ?? '')
                : data.image.trim();
        setImageStatus(preview ? 'loading' : 'idle');
    };

    const handleImageFile = (file: File | null) => {
        setData('image_file', file);
        setFilePreview(file ? URL.createObjectURL(file) : null);
        clearErrors('image_file');
        setImageStatus(file ? 'loading' : person?.image ? 'loading' : 'idle');
    };

    const addRelatedStory = () => {
        if (data.related_stories.length >= 10) {
            return;
        }

        setData('related_stories', [
            ...data.related_stories,
            { title: '', url: '' },
        ]);
    };

    const setRelatedStory = (
        index: number,
        field: keyof RelatedStoryEntry,
        value: string,
    ) => {
        setData(
            'related_stories',
            data.related_stories.map((story, storyIndex) =>
                storyIndex === index ? { ...story, [field]: value } : story,
            ),
        );
    };

    const removeRelatedStory = (index: number) => {
        setData(
            'related_stories',
            data.related_stories.filter(
                (_, storyIndex) => storyIndex !== index,
            ),
        );
    };

    const selectRow = (index: number) => {
        setSelectedIndex(selectedIndex === index ? null : index);
    };

    const clearSelection = () => {
        setSelectedIndex(null);
    };

    const selectedChild =
        selectedIndex != null
            ? (data.children[selectedIndex] as ChildRow | undefined)
            : undefined;

    const ownChildrenCount = data.ownChildren.length;
    const availableMothers = data.mothers
        .map((mother, index) => ({ mother, index }))
        .filter(({ mother }) => isNameFilled(mother.name));

    const isFocusRow =
        person?.id != null
            ? selectedChild?.id === person.id
            : selectedIndex === birthOrder - 1;

    const margaName = (
        margaId: number | null | undefined,
        newMarga?: string | null,
    ): string => {
        if (newMarga?.trim()) {
            return newMarga.trim();
        }

        return margas.find((marga) => marga.id === margaId)?.name ?? '—';
    };
    const activeMargaName =
        data.new_marga.trim() ||
        margas.find((marga) => marga.id === data.marga_id)?.name ||
        lockedMarga?.name ||
        null;

    useEffect(() => {
        if (data.children.length === 0) {
            return;
        }

        const focusIndex =
            person?.id != null
                ? data.children.findIndex((child) => child.id === person.id)
                : Math.min(birthOrder - 1, data.children.length - 1);

        if (focusIndex < 0) {
            return;
        }

        setData(
            'children',
            data.children.map((child, index) =>
                index === focusIndex
                    ? {
                          ...child,
                          id: person?.id ?? child.id,
                          name: data.name,
                          gender: data.gender,
                          alias: data.alias,
                          marga_id: data.marga_id,
                          new_marga: data.new_marga,
                          birth_year: data.birth_year || null,
                          death_year: data.death_year || null,
                          image: data.image || null,
                          bio: data.bio || null,
                      }
                    : child,
            ),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        data.name,
        data.gender,
        data.alias,
        data.marga_id,
        data.new_marga,
        data.birth_year,
        data.death_year,
        data.image,
        data.bio,
        birthOrder,
        person?.id,
    ]);

    useEffect(() => {
        const prev = prevSiblingCount.current;
        prevSiblingCount.current = siblingCount;
        const decreased = siblingCount < prev;

        const effectiveOrder = decreased
            ? Math.min(birthOrder, siblingCount)
            : birthOrder;
        const needed = Math.max(siblingCount, effectiveOrder);
        let next = [...data.children];

        if (!decreased && next.length < needed) {
            next = [
                ...next,
                ...Array.from({ length: needed - next.length }, emptyRow),
            ];
        }

        if (decreased) {
            const excessNewFilled = next
                .slice(siblingCount)
                .filter((row) => isRowFilled(row) && row.id == null).length;
            const excessSaved = next
                .slice(siblingCount)
                .filter((row) => row.id != null).length;

            if (excessNewFilled > 0 && reductionConfirm === null) {
                setReductionConfirm({
                    from: prev,
                    to: siblingCount,
                    filledNew: excessNewFilled,
                });

                return;
            }

            if (
                excessNewFilled === 0 &&
                excessSaved > 0 &&
                !savedExcessToastShown.current
            ) {
                savedExcessToastShown.current = true;

                toast.info(
                    `${excessSaved} baris tersimpan tetap dipertahankan karena sudah ada di database. Untuk menghapus, gunakan Data Anggota.`,
                );
            }

            while (next.length > siblingCount && next.length > needed) {
                const last = next[next.length - 1];

                if (isRowFilled(last)) {
                    break;
                }

                next = next.slice(0, -1);
            }
        }

        if (next.length !== data.children.length) {
            setData('children', next);
        }

        if (decreased && effectiveOrder !== birthOrder) {
            setData('birth_order', effectiveOrder);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [birthOrder, siblingCount]);

    useEffect(() => {
        if (person != null || lockedMarga != null) {
            return;
        }

        const margaId = data.marga_id ?? null;
        const newMarga = (data.new_marga ?? '').trim();

        if (margaId == null && !newMarga) {
            return;
        }

        const targetNewMarga = margaId != null ? '' : newMarga;

        setData(
            'father',
            data.father
                ? {
                      ...data.father,
                      marga_id: margaId,
                      new_marga: targetNewMarga,
                  }
                : data.father,
        );
        setData(
            'children',
            (data.children ?? []).map((child) => ({
                ...child,
                marga_id: margaId,
                new_marga: targetNewMarga,
            })),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.marga_id, data.new_marga, person, lockedMarga]);

    const setChild = (
        index: number,
        key: 'name' | 'alias' | 'gender' | 'spouse' | 'spouse_marga',
        value: string,
    ) => {
        const next = data.children.map((child, i) => {
            if (i !== index) {
                return child;
            }

            return {
                ...child,
                ...(key === 'name' &&
                'linkedFromSuggestion' in child &&
                child.linkedFromSuggestion
                    ? { id: null, linkedFromSuggestion: false }
                    : {}),
                [key]: value,
            };
        });

        setData('children', next);
    };

    const selectChild = (index: number, suggestion: NameSuggestion) => {
        const next = data.children.map((child, i) =>
            i === index
                ? {
                      ...child,
                      id: suggestion.id,
                      linkedFromSuggestion: child.id !== suggestion.id,
                      name: suggestion.name,
                      alias: suggestion.alias ?? '',
                      gender: suggestion.gender ?? '',
                      spouse: suggestion.spouse ?? '',
                      spouse_marga: suggestion.spouse_marga ?? '',
                      marga_id: suggestion.marga_id ?? child.marga_id,
                  }
                : child,
        );

        setData('children', next);
    };

    const setChildMarga = (index: number, margaId: number | null) => {
        const next = data.children.map((child, i) =>
            i === index ? { ...child, marga_id: margaId } : child,
        );
        setData('children', next);
    };

    const setChildNewMarga = (index: number, name: string) => {
        const next = data.children.map((child, i) =>
            i === index ? { ...child, new_marga: name } : child,
        );
        setData('children', next);
    };

    const addChild = () => {
        setData('children', [...data.children, emptyRow()]);
        setData('sibling_count', siblingCount + 1);
    };

    const removeChild = (index: number) => {
        setData(
            'children',
            data.children.filter((_, i) => i !== index),
        );
    };

    const moveChild = (index: number, direction: -1 | 1) => {
        const target = index + direction;

        if (target < 0 || target >= data.children.length) {
            return;
        }

        const next = [...data.children];
        [next[index], next[target]] = [next[target], next[index]];
        setData('children', next);

        if (person == null) {
            const focusIndex = birthOrder - 1;
            let focusNewIndex = focusIndex;

            if (index === focusIndex) {
                focusNewIndex = target;
            } else if (index > focusIndex && target <= focusIndex) {
                focusNewIndex = focusIndex + 1;
            } else if (index < focusIndex && target >= focusIndex) {
                focusNewIndex = focusIndex - 1;
            }

            if (focusNewIndex !== focusIndex) {
                setData('birth_order', focusNewIndex + 1);
            }
        }
    };

    const insertChildAbove = (index: number) => {
        const next = [...data.children];
        next.splice(index, 0, emptyRow());
        setData('children', next);
        setData('sibling_count', siblingCount + 1);

        if (person == null && index <= birthOrder - 1) {
            setData('birth_order', birthOrder + 1);
        }
    };

    const cancelReduction = () => {
        if (reductionConfirm === null) {
            return;
        }

        prevSiblingCount.current = reductionConfirm.from;
        setData('sibling_count', reductionConfirm.from);
        setReductionConfirm(null);
    };

    const confirmReduction = () => {
        if (reductionConfirm === null) {
            return;
        }

        const { to } = reductionConfirm;

        setData(
            'children',
            data.children.filter((row, index) => index < to || row.id != null),
        );

        setData('birth_order', Math.min(birthOrder, to));
        prevSiblingCount.current = to;
        setReductionConfirm(null);
    };

    const setOwnChild = (
        index: number,
        key: 'name' | 'alias' | 'gender' | 'spouse' | 'spouse_marga',
        value: string,
    ) => {
        const next = data.ownChildren.map((child, i) => {
            if (i !== index) {
                return child;
            }

            return {
                ...child,
                ...(key === 'name' &&
                'linkedFromSuggestion' in child &&
                child.linkedFromSuggestion
                    ? { id: null, linkedFromSuggestion: false }
                    : {}),
                [key]: value,
            };
        });
        setData('ownChildren', next);
    };

    const selectOwnChild = (index: number, suggestion: NameSuggestion) => {
        const next = data.ownChildren.map((child, i) =>
            i === index
                ? {
                      ...child,
                      id: suggestion.id,
                      linkedFromSuggestion: child.id !== suggestion.id,
                      name: suggestion.name,
                      alias: suggestion.alias ?? '',
                      gender: suggestion.gender ?? '',
                      spouse: suggestion.spouse ?? '',
                      spouse_marga: suggestion.spouse_marga ?? '',
                      marga_id: suggestion.marga_id ?? child.marga_id,
                  }
                : child,
        );

        setData('ownChildren', next);
    };

    const setOwnChildMarga = (index: number, margaId: number | null) => {
        const next = data.ownChildren.map((child, i) =>
            i === index ? { ...child, marga_id: margaId } : child,
        );
        setData('ownChildren', next);
    };

    const setOwnChildNewMarga = (index: number, name: string) => {
        const next = data.ownChildren.map((child, i) =>
            i === index ? { ...child, new_marga: name } : child,
        );
        setData('ownChildren', next);
    };

    const setOwnChildMother = (index: number, motherIndex: number | null) => {
        setData(
            'ownChildren',
            data.ownChildren.map((child, childIndex) =>
                childIndex === index
                    ? { ...child, mother_index: motherIndex }
                    : child,
            ),
        );
    };

    const addOwnChild = () => {
        setData('ownChildren', [
            ...data.ownChildren,
            emptyOwnRow(soleMotherIndex(data.mothers)),
        ]);
    };

    const removeOwnChild = (index: number) => {
        setData(
            'ownChildren',
            data.ownChildren.filter((_, i) => i !== index),
        );
    };

    const moveOwnChild = (index: number, direction: -1 | 1) => {
        const target = index + direction;

        if (target < 0 || target >= data.ownChildren.length) {
            return;
        }

        const next = [...data.ownChildren];
        [next[index], next[target]] = [next[target], next[index]];
        setData('ownChildren', next);
    };

    const insertOwnChildAbove = (index: number) => {
        const next = [...data.ownChildren];
        next.splice(index, 0, emptyOwnRow(soleMotherIndex(data.mothers)));
        setData('ownChildren', next);
    };

    const requestRemoveRow = (
        kind: 'children' | 'ownChildren',
        index: number,
    ) => {
        const rows = kind === 'children' ? data.children : data.ownChildren;
        const row = rows[index];

        if (!row) {
            return;
        }

        if (row.id == null) {
            if (kind === 'children') {
                removeChild(index);
            } else {
                removeOwnChild(index);
            }

            return;
        }

        setRemovalConfirm({ kind, index, row });
    };

    const cancelRemove = () => {
        setRemovalConfirm(null);
    };

    const confirmRemove = () => {
        if (removalConfirm === null) {
            return;
        }

        const { kind, index, row } = removalConfirm;

        if (kind === 'children') {
            setData('removed_child_ids', [...data.removed_child_ids, row.id!]);
            setData(
                'children',
                data.children.filter((_, i) => i !== index),
            );
            setData('sibling_count', Math.max(siblingCount - 1, 1));
        } else {
            setData('removed_own_child_ids', [
                ...data.removed_own_child_ids,
                row.id!,
            ]);
            setData(
                'ownChildren',
                data.ownChildren.filter((_, i) => i !== index),
            );
        }

        setRemovalConfirm(null);
    };

    const parentAt = (key: ParentKey): ParentEntry =>
        key === 'father' ? (data.father ?? emptyParent()) : data.mothers[key];

    const updateParent = (key: ParentKey, patch: Partial<ParentEntry>) => {
        if (key === 'father') {
            setData('father', { ...data.father, ...patch } as ParentEntry);

            return;
        }

        setData(
            'mothers',
            data.mothers.map((entry, index) =>
                index === key ? { ...entry, ...patch } : entry,
            ),
        );
    };

    const addMother = () => {
        setData('mothers', [...data.mothers, emptyParent()]);
    };

    const removeMother = (index: number) => {
        if (data.mothers.length <= 1) {
            return;
        }

        const mothers = data.mothers.filter((_, i) => i !== index);

        setData('mothers', mothers);
        setData(
            'ownChildren',
            data.ownChildren.map((child) => {
                const remainingMotherIndex = soleMotherIndex(mothers);

                if (remainingMotherIndex !== null) {
                    return {
                        ...child,
                        mother_index: remainingMotherIndex,
                    };
                }

                if (child.mother_index === index) {
                    return { ...child, mother_index: null };
                }

                if (
                    child.mother_index !== null &&
                    child.mother_index !== undefined &&
                    child.mother_index > index
                ) {
                    return {
                        ...child,
                        mother_index: child.mother_index - 1,
                    };
                }

                return child;
            }),
        );
    };

    const setParentEntry = (
        key: ParentKey,
        field: 'name' | 'alias' | 'birth_year' | 'death_year' | 'father_name',
        value: string,
    ) => {
        updateParent(key, {
            [field]: value,
            ...(key === 'father' && field === 'name' ? { id: null } : {}),
        });
    };

    const setParentMarga = (key: ParentKey, margaId: number | null) => {
        updateParent(key, { marga_id: margaId });
    };

    const setParentNewMarga = (key: ParentKey, name: string) => {
        updateParent(key, { new_marga: name });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (
            data.image_mode === 'url' &&
            data.image.trim() &&
            imageStatus !== 'valid'
        ) {
            setError(
                'image',
                imageStatus === 'invalid'
                    ? 'URL tidak terhubung ke gambar yang dapat ditampilkan.'
                    : 'Tunggu sampai pemeriksaan URL foto selesai.',
            );

            return;
        }

        if (
            data.image_mode === 'upload' &&
            data.image_file &&
            imageStatus !== 'valid'
        ) {
            setError(
                'image_file',
                'File belum dapat ditampilkan sebagai gambar.',
            );

            return;
        }

        if (isEdit && person?.id) {
            const updateAction = people.update.form(person.id, {
                query:
                    selectedVersionId !== null
                        ? { version_tree: selectedVersionId }
                        : {},
            }).action;

            post(updateAction, {
                forceFormData: true,
            });
        } else {
            post(people.store.form().action, { forceFormData: true });
        }
    };

    const fatherName = data.father?.name?.trim() ?? '';
    const lineageEntries = (lineage ?? []).flatMap((entry) => [
        entry,
        ...(entry.children ?? []),
    ]);
    const fatherCandidates = [...lineageEntries, ...fatherSuggestions];
    const fatherMatch = fatherName
        ? fatherCandidates.find(
              (entry) =>
                  normalizeNameForMatch(entry.name ?? '') ===
                  normalizeNameForMatch(fatherName),
          )
        : undefined;
    const predictedFatherChain = fatherMatch?.chain ?? null;
    const predictedFocusChain =
        predictedFatherChain && data.name.trim()
            ? `${predictedFatherChain}-${birthOrder}`
            : null;

    const renderParentBlock = (
        key: ParentKey,
        label: string,
        birthPlace: string,
        deathPlace: string,
        showMarga = true,
        lockMarga = lockedMarga !== null,
    ) => {
        const entry = parentAt(key);
        const errorPrefix = parentErrorPrefix(key);

        return (
            <div className="space-y-4 rounded-lg border border-tb-outline-variant p-4">
                <p className="text-sm font-medium text-tb-on-surface">
                    {label}
                </p>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${errorPrefix}-name`}
                        className="text-tb-on-surface"
                    >
                        Nama {label}
                    </Label>
                    <NameCombobox
                        value={entry.name}
                        onChange={(value) => setParentEntry(key, 'name', value)}
                        suggestions={
                            key === 'father'
                                ? fatherSuggestions
                                : nameSuggestions
                        }
                        placeholder={`Nama ${label.toLowerCase()}`}
                        allowNa
                        onSelect={
                            key === 'father'
                                ? (suggestion) =>
                                      updateParent(key, {
                                          id: suggestion.id,
                                          name: suggestion.name,
                                          marga_id: suggestion.marga_id ?? null,
                                      })
                                : undefined
                        }
                    />
                    <InputError message={errors[`${errorPrefix}.name`]} />
                    <div className="grid gap-1.5 pt-2">
                        <Label
                            htmlFor={`${errorPrefix}-alias`}
                            className="text-tb-on-surface"
                        >
                            Alias / Gelar
                        </Label>
                        <Input
                            id={`${errorPrefix}-alias`}
                            value={entry.alias}
                            onChange={(e) =>
                                setParentEntry(key, 'alias', e.target.value)
                            }
                            placeholder="Tuan Sorba Dibanua"
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                        <InputError message={errors[`${errorPrefix}.alias`]} />
                    </div>
                    {key === 'father' && (
                        <div className="text-xs">
                            {fatherMatch ? (
                                <p className="font-medium text-emerald-700">
                                    Akan tersambung ke {fatherMatch.name}
                                    {fatherMatch.marga
                                        ? ` (${fatherMatch.marga})`
                                        : ''}{' '}
                                    — chain {predictedFatherChain}
                                    {predictedFocusChain
                                        ? ` → ${predictedFocusChain}`
                                        : ''}
                                    .
                                </p>
                            ) : fatherName && !isNaPlaceholder(fatherName) ? (
                                <p className="font-medium text-amber-700">
                                    Nama "{fatherName}" tidak ditemukan di
                                    silsilah — akan dibuat sebagai rumpun baru
                                    (nomor baru), bukan menyambung.
                                </p>
                            ) : (
                                <p className="font-medium text-tb-on-surface-variant">
                                    Ayah belum diisi — keluarga ini dicatat
                                    sebagai rumpun sendiri berstatus "Belum
                                    tersambung". Isi nama ayah yang sudah ada
                                    untuk menyambung ke silsilahnya.
                                </p>
                            )}
                        </div>
                    )}
                </div>
                {showMarga && (
                    <div className="grid gap-1.5">
                        <Label className="text-tb-on-surface">
                            Marga {label}
                        </Label>
                        <MargaField
                            value={entry.marga_id ?? null}
                            newMarga={entry.new_marga ?? ''}
                            onValue={(value) => setParentMarga(key, value)}
                            onNewMarga={(value) =>
                                setParentNewMarga(key, value)
                            }
                            margas={margas}
                            placeholder={`Marga ${label.toLowerCase()}`}
                            disabled={lockMarga}
                        />
                        <InputError
                            message={errors[`${errorPrefix}.marga_id`]}
                        />
                    </div>
                )}
                {key !== 'father' && (
                    <div className="grid gap-1.5">
                        <Label className="text-tb-on-surface">
                            Nama Ayah dari Ibu
                        </Label>
                        <NameCombobox
                            value={entry.father_name ?? ''}
                            onChange={(value) =>
                                setParentEntry(key, 'father_name', value)
                            }
                            suggestions={fatherSuggestions}
                            placeholder="Nama ayah dari ibu"
                            allowNa
                        />
                        <p className="text-xs text-tb-on-surface-variant">
                            Marga Ayah dari Ibu mengikuti marga Ibu:{' '}
                            <span className="font-medium text-tb-on-surface">
                                {margaName(entry.marga_id, entry.new_marga)}
                            </span>
                        </p>
                        <InputError
                            message={errors[`${errorPrefix}.father_name`]}
                        />
                    </div>
                )}
                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-1.5">
                        <Label className="text-tb-on-surface">
                            Tahun Lahir
                        </Label>
                        <Input
                            value={entry.birth_year}
                            onChange={(e) =>
                                setParentEntry(
                                    key,
                                    'birth_year',
                                    e.target.value,
                                )
                            }
                            placeholder={birthPlace}
                            maxLength={4}
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                        <InputError
                            message={errors[`${errorPrefix}.birth_year`]}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label className="text-tb-on-surface">
                            Tahun Wafat
                        </Label>
                        <Input
                            value={entry.death_year}
                            onChange={(e) =>
                                setParentEntry(
                                    key,
                                    'death_year',
                                    e.target.value,
                                )
                            }
                            placeholder={deathPlace}
                            maxLength={4}
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                        <InputError
                            message={errors[`${errorPrefix}.death_year`]}
                        />
                    </div>
                </div>
            </div>
        );
    };

    const highlightedLineage: MargaLineageEntry[] = (lineage ?? []).map(
        (entry) => ({
            ...entry,
            isAyah:
                entry.id === fatherMatch?.id ||
                (entry.children ?? []).some(
                    (child) => child.id === fatherMatch?.id,
                ),
        }),
    );

    transform((currentData) => {
        const withMarga = lockedMarga
            ? {
                  ...currentData,
                  marga_id: lockedMarga.id,
                  new_marga: '',
                  father: currentData.father
                      ? {
                            ...currentData.father,
                            marga_id: lockedMarga.id,
                            new_marga: '',
                        }
                      : null,
                  children: (currentData.children ?? []).map((child) => ({
                      ...child,
                      marga_id: lockedMarga.id,
                      new_marga: '',
                  })),
                  ownChildren: (currentData.ownChildren ?? []).map((child) => ({
                      ...child,
                      marga_id: lockedMarga.id,
                      new_marga: '',
                  })),
              }
            : currentData;
        const withSelectedMother = {
            ...withMarga,
            ownChildren: withMarga.ownChildren.map((child) => ({
                ...child,
                mother_index:
                    soleMotherIndex(withMarga.mothers) ?? child.mother_index,
            })),
        };

        const sorted = withSelectedMother.children ?? [];
        const focusIndex =
            person?.id != null
                ? sorted.findIndex((child) => child.id === person.id)
                : (Number(withMarga.birth_order) || 1) - 1;

        const submitData = {
            ...withSelectedMother,
            related_stories: withSelectedMother.related_stories
                .filter((story) => story.title.trim() || story.url.trim())
                .map((story) => ({
                    title: story.title.trim(),
                    url: story.url.trim(),
                })),
            image:
                withSelectedMother.image_mode === 'url'
                    ? withSelectedMother.image.trim() || null
                    : null,
        };

        if (!canPublish) {
            delete (submitData as { is_public?: boolean }).is_public;
        }

        return {
            ...submitData,
            children: sorted,
            birth_order: Math.max(1, (focusIndex >= 0 ? focusIndex : 0) + 1),
        };
    });

    return (
        <>
            <div className="w-full">
                <form
                    onSubmit={
                        readOnly ? (event) => event.preventDefault() : submit
                    }
                    className="w-full"
                >
                    <fieldset
                        disabled={readOnly}
                        className="grid w-full max-w-7xl gap-6"
                    >
                        <div
                            className={cn(
                                showFamilyTreeHistory
                                    ? 'max-w-full overflow-x-auto'
                                    : 'w-full',
                            )}
                        >
                            <div
                                className={cn(
                                    'grid gap-6',
                                    showFamilyTreeHistory
                                        ? 'min-w-[1920px] grid-cols-[minmax(640px,1fr)_360px_minmax(640px,1fr)]'
                                        : 'grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px]',
                                )}
                            >
                                <div className="grid gap-6">
                                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                        <CardHeader>
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <CardTitle className="font-display text-lg text-tb-on-surface">
                                                        Informasi Pribadi
                                                    </CardTitle>
                                                    <CardDescription>
                                                        Data dasar anggota yang
                                                        sedang dicatat dalam
                                                        jejak keluarga.
                                                    </CardDescription>
                                                </div>
                                                {selectedVersionName && (
                                                    <span className="max-w-56 shrink-0 rounded-lg border border-tb-primary/30 bg-tb-primary/5 px-2.5 py-1.5 text-right text-xs font-semibold text-tb-primary">
                                                        {selectedVersionName}
                                                        {selectedVersionNumber &&
                                                            ` (V${selectedVersionNumber})`}
                                                    </span>
                                                )}
                                                {person && !readOnly && (
                                                    <FamilyTreeVersionAction
                                                        entries={versionTrees}
                                                        mode="open"
                                                    />
                                                )}
                                            </div>
                                        </CardHeader>
                                        <CardContent className="grid gap-5">
                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor="name"
                                                    className="text-tb-on-surface"
                                                >
                                                    Nama Lengkap{' '}
                                                    <span className="text-red-600">
                                                        *
                                                    </span>
                                                </Label>
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Mis. Ompu Sitorus"
                                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <div className="grid gap-5 sm:grid-cols-3">
                                                <div className="grid gap-1.5">
                                                    <Label
                                                        htmlFor="alias"
                                                        className="text-tb-on-surface"
                                                    >
                                                        Alias / Gelar
                                                    </Label>
                                                    <Input
                                                        id="alias"
                                                        value={data.alias}
                                                        onChange={(e) =>
                                                            setData(
                                                                'alias',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Tuan Sorba Dibanua"
                                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                    />
                                                    <InputError
                                                        message={errors.alias}
                                                    />
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <Label className="text-tb-on-surface">
                                                        Jenis Kelamin
                                                    </Label>
                                                    <Select
                                                        value={
                                                            data.gender || ''
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setData(
                                                                'gender',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                                            <SelectValue placeholder="Pilih" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="L">
                                                                Laki-laki (L)
                                                            </SelectItem>
                                                            <SelectItem value="P">
                                                                Perempuan (P)
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        message={errors.gender}
                                                    />
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <Label className="text-tb-on-surface">
                                                        Marga
                                                    </Label>
                                                    <MargaField
                                                        value={data.marga_id}
                                                        newMarga={
                                                            data.new_marga
                                                        }
                                                        onValue={(value) =>
                                                            setData(
                                                                'marga_id',
                                                                value,
                                                            )
                                                        }
                                                        onNewMarga={(value) =>
                                                            setData(
                                                                'new_marga',
                                                                value,
                                                            )
                                                        }
                                                        margas={margas}
                                                        disabled={
                                                            lockedMarga !== null
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.marga_id
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="grid gap-1.5">
                                                    <Label
                                                        htmlFor="birth_year"
                                                        className="text-tb-on-surface"
                                                    >
                                                        Tahun Lahir
                                                    </Label>
                                                    <Input
                                                        id="birth_year"
                                                        value={data.birth_year}
                                                        onChange={(e) =>
                                                            setData(
                                                                'birth_year',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="1920"
                                                        maxLength={4}
                                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.birth_year
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <Label
                                                        htmlFor="death_year"
                                                        className="text-tb-on-surface"
                                                    >
                                                        Tahun Wafat
                                                    </Label>
                                                    <Input
                                                        id="death_year"
                                                        value={data.death_year}
                                                        onChange={(e) =>
                                                            setData(
                                                                'death_year',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="2001"
                                                        maxLength={4}
                                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.death_year
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label className="text-tb-on-surface">
                                                    Foto
                                                </Label>
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            data.image_mode ===
                                                            'url'
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        onClick={() =>
                                                            selectImageMode(
                                                                'url',
                                                            )
                                                        }
                                                    >
                                                        <Link2 className="size-4" />
                                                        Gunakan URL
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            data.image_mode ===
                                                            'upload'
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        onClick={() =>
                                                            selectImageMode(
                                                                'upload',
                                                            )
                                                        }
                                                    >
                                                        <Upload className="size-4" />
                                                        Unggah File
                                                    </Button>
                                                </div>

                                                <div className="grid items-start gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-container/30 p-3 sm:grid-cols-[80px_1fr]">
                                                    <div className="flex size-20 items-center justify-center overflow-hidden rounded-xl border border-tb-outline-variant bg-tb-surface-bright text-sm font-bold text-tb-on-surface-variant">
                                                        {imagePreview &&
                                                        imageStatus !==
                                                            'invalid' ? (
                                                            <img
                                                                key={
                                                                    imagePreview
                                                                }
                                                                src={
                                                                    imagePreview
                                                                }
                                                                alt={`Pratinjau ${data.name || 'anggota'}`}
                                                                className="size-full object-cover"
                                                                onLoad={() => {
                                                                    setImageStatus(
                                                                        'valid',
                                                                    );
                                                                    clearErrors(
                                                                        'image',
                                                                        'image_file',
                                                                    );
                                                                }}
                                                                onError={() =>
                                                                    setImageStatus(
                                                                        'invalid',
                                                                    )
                                                                }
                                                            />
                                                        ) : data.name.trim() ? (
                                                            getInitials(
                                                                data.name,
                                                            )
                                                        ) : (
                                                            <ImageIcon className="size-5" />
                                                        )}
                                                    </div>

                                                    <div className="grid min-w-0 gap-2">
                                                        {data.image_mode ===
                                                        'url' ? (
                                                            <Input
                                                                id="image"
                                                                type="url"
                                                                value={
                                                                    data.image
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    setData(
                                                                        'image',
                                                                        e.target
                                                                            .value,
                                                                    );
                                                                    clearErrors(
                                                                        'image',
                                                                    );
                                                                    setImageStatus(
                                                                        e.target.value.trim()
                                                                            ? 'loading'
                                                                            : 'idle',
                                                                    );
                                                                }}
                                                                placeholder="https://contoh.com/foto.jpg"
                                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                            />
                                                        ) : (
                                                            <Input
                                                                id="image_file"
                                                                type="file"
                                                                accept="image/jpeg,image/png,image/webp"
                                                                onChange={(e) =>
                                                                    handleImageFile(
                                                                        e.target
                                                                            .files?.[0] ??
                                                                            null,
                                                                    )
                                                                }
                                                                className="border-tb-outline-variant bg-tb-surface-bright file:mr-3 file:font-medium"
                                                            />
                                                        )}

                                                        <p
                                                            className={cn(
                                                                'text-xs',
                                                                imageStatus ===
                                                                    'valid' &&
                                                                    'text-emerald-600 dark:text-emerald-400',
                                                                imageStatus ===
                                                                    'invalid' &&
                                                                    'text-red-600 dark:text-red-400',
                                                                (imageStatus ===
                                                                    'idle' ||
                                                                    imageStatus ===
                                                                        'loading') &&
                                                                    'text-tb-on-surface-variant',
                                                            )}
                                                        >
                                                            {imageStatus ===
                                                            'loading'
                                                                ? 'Memeriksa apakah foto dapat ditampilkan...'
                                                                : imageStatus ===
                                                                    'valid'
                                                                  ? 'Foto berhasil ditampilkan.'
                                                                  : imageStatus ===
                                                                      'invalid'
                                                                    ? 'Foto tidak dapat dimuat. Inisial akan digunakan sebagai pengganti.'
                                                                    : data.image_mode ===
                                                                        'upload'
                                                                      ? 'JPG, PNG, atau WebP. Maksimal 2 MB.'
                                                                      : 'Masukkan URL HTTP/HTTPS yang langsung menampilkan gambar.'}
                                                        </p>
                                                        <InputError
                                                            message={
                                                                data.image_mode ===
                                                                'upload'
                                                                    ? errors.image_file
                                                                    : errors.image
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor="bio"
                                                    className="text-tb-on-surface"
                                                >
                                                    Biografi
                                                </Label>
                                                <textarea
                                                    id="bio"
                                                    value={data.bio}
                                                    onChange={(e) =>
                                                        setData(
                                                            'bio',
                                                            e.target.value,
                                                        )
                                                    }
                                                    rows={4}
                                                    placeholder="Cerita singkat tentang anggota ini..."
                                                    className="w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm shadow-xs outline-none focus:border-tb-primary focus:ring-tb-primary/20 focus-visible:ring-[3px]"
                                                />
                                                <InputError
                                                    message={errors.bio}
                                                />
                                            </div>

                                            <div className="grid gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-container/30 p-4">
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <h3 className="flex items-center gap-2 text-sm font-semibold text-tb-on-surface">
                                                            <Link2 className="size-4 text-tb-primary" />
                                                            Daftar
                                                            Sejarah/Cerita
                                                            Terkait
                                                        </h3>
                                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                            Tambahkan judul dan
                                                            link referensi yang
                                                            berkaitan dengan
                                                            orang ini.
                                                        </p>
                                                    </div>
                                                    {!readOnly &&
                                                        data.related_stories
                                                            .length < 10 && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={
                                                                    addRelatedStory
                                                                }
                                                            >
                                                                <Plus className="size-4" />
                                                                Tambah Cerita
                                                            </Button>
                                                        )}
                                                </div>

                                                {data.related_stories.length ===
                                                0 ? (
                                                    <p className="rounded-lg border border-dashed border-tb-outline-variant px-3 py-4 text-center text-xs text-tb-on-surface-variant">
                                                        Belum ada sejarah atau
                                                        cerita terkait.
                                                    </p>
                                                ) : (
                                                    <div className="grid gap-3">
                                                        {data.related_stories.map(
                                                            (story, index) => (
                                                                <div
                                                                    key={index}
                                                                    className="grid gap-3 rounded-lg border border-tb-outline-variant bg-tb-surface-bright p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto]"
                                                                >
                                                                    <div className="grid gap-1.5">
                                                                        <Label
                                                                            htmlFor={`related-story-title-${index}`}
                                                                        >
                                                                            Judul
                                                                        </Label>
                                                                        <Input
                                                                            id={`related-story-title-${index}`}
                                                                            value={
                                                                                story.title
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setRelatedStory(
                                                                                    index,
                                                                                    'title',
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            placeholder="Contoh: Sejarah Sangkar Toba"
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                errors[
                                                                                    `related_stories.${index}.title`
                                                                                ]
                                                                            }
                                                                        />
                                                                    </div>
                                                                    <div className="grid gap-1.5">
                                                                        <Label
                                                                            htmlFor={`related-story-url-${index}`}
                                                                        >
                                                                            Link
                                                                        </Label>
                                                                        <Input
                                                                            id={`related-story-url-${index}`}
                                                                            type="url"
                                                                            value={
                                                                                story.url
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setRelatedStory(
                                                                                    index,
                                                                                    'url',
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            placeholder="https://contoh.com/cerita"
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                errors[
                                                                                    `related_stories.${index}.url`
                                                                                ]
                                                                            }
                                                                        />
                                                                    </div>
                                                                    {!readOnly && (
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            onClick={() =>
                                                                                removeRelatedStory(
                                                                                    index,
                                                                                )
                                                                            }
                                                                            aria-label={`Hapus cerita ${index + 1}`}
                                                                            className="self-end text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                                                        >
                                                                            <Trash2 className="size-4" />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                                <InputError
                                                    message={
                                                        errors.related_stories
                                                    }
                                                />
                                            </div>

                                            {canPublish && (
                                                <div className="flex items-start gap-3 rounded-lg border border-tb-outline-variant bg-tb-surface-container/40 p-4">
                                                    <Checkbox
                                                        id="is_public"
                                                        checked={data.is_public}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setData(
                                                                'is_public',
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor="is_public"
                                                            className="text-tb-on-surface"
                                                        >
                                                            Tampilkan di tarombo
                                                            publik
                                                        </Label>
                                                        <p className="text-xs leading-relaxed text-tb-on-surface-variant">
                                                            Hanya identitas
                                                            genealogis ringkas
                                                            yang ditampilkan.
                                                            Ayah harus sudah
                                                            publik agar jalur
                                                            silsilah tetap
                                                            lengkap.
                                                        </p>
                                                        <InputError
                                                            message={
                                                                errors.is_public
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                                {person ? (
                                    <SilsilahListCard
                                        lineage={person.lineage}
                                        selfId={person.id}
                                        familyTrees={listFamilyTrees}
                                    />
                                ) : (
                                    <MargaLineageCard
                                        entries={highlightedLineage}
                                        fatherChain={predictedFatherChain}
                                        focusChain={predictedFocusChain}
                                        familyTrees={listFamilyTrees}
                                    />
                                )}
                                {showFamilyTreeHistory && (
                                    <FamilyTreeHistoryCard
                                        entries={familyTrees}
                                        approvedEntries={approvedMargaTrees}
                                        margaName={activeMargaName}
                                        shareableAccounts={shareableAccounts}
                                        pendingTreeShares={pendingTreeShares}
                                    />
                                )}
                                {selectedChild && selectedIndex != null && (
                                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                                            <div>
                                                <CardTitle className="font-display text-lg text-tb-on-surface">
                                                    Detail Silsilah
                                                </CardTitle>
                                                <CardDescription>
                                                    Keluarga dari baris ke{' '}
                                                    {selectedIndex + 1}.
                                                </CardDescription>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={clearSelection}
                                                aria-label="Tutup preview"
                                                className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-tb-on-surface-variant transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface"
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        </CardHeader>
                                        <CardContent className="grid gap-4">
                                            <div className="grid gap-1.5">
                                                <p className="text-[11px] font-semibold tracking-[0.14em] text-tb-on-surface-variant uppercase">
                                                    Orang Tua
                                                </p>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="rounded-lg border border-tb-outline-variant bg-tb-surface-container/60 px-3 py-2">
                                                        <p className="text-[11px] font-medium text-tb-on-surface-variant">
                                                            Ayah
                                                        </p>
                                                        <p className="text-sm font-semibold text-tb-on-surface">
                                                            {displayRowName(
                                                                data.father
                                                                    ?.name,
                                                            )}
                                                        </p>
                                                        <p className="text-xs text-tb-on-surface-variant">
                                                            {margaName(
                                                                data.father
                                                                    ?.marga_id ??
                                                                    null,
                                                                data.father
                                                                    ?.new_marga,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <div className="rounded-lg border border-tb-outline-variant bg-tb-surface-container/60 px-3 py-2">
                                                        <p className="text-[11px] font-medium text-tb-on-surface-variant">
                                                            {data.mothers
                                                                .length > 1
                                                                ? 'Ibu / Istri'
                                                                : 'Ibu'}
                                                        </p>
                                                        {data.mothers.map(
                                                            (wife, index) => (
                                                                <div
                                                                    key={
                                                                        wife.name ||
                                                                        `istri-${index}`
                                                                    }
                                                                    className={
                                                                        index ===
                                                                        0
                                                                            ? ''
                                                                            : 'mt-1.5 border-t border-tb-outline-variant pt-1.5'
                                                                    }
                                                                >
                                                                    <p className="text-sm font-semibold text-tb-on-surface">
                                                                        {displayRowName(
                                                                            wife.name,
                                                                        )}
                                                                    </p>
                                                                    <p className="text-xs text-tb-on-surface-variant">
                                                                        {margaName(
                                                                            wife.marga_id ??
                                                                                null,
                                                                            wife.new_marga,
                                                                        )}
                                                                    </p>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="grid gap-1.5">
                                                <p className="text-[11px] font-semibold tracking-[0.14em] text-tb-on-surface-variant uppercase">
                                                    Orang Ini
                                                </p>
                                                <div className="grid gap-3 rounded-lg border border-tb-primary/50 bg-tb-primary/5 p-3">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="min-w-0 flex-1 truncate font-display text-base font-semibold text-tb-on-surface">
                                                            {displayRowName(
                                                                selectedChild.name,
                                                            )}
                                                        </p>
                                                        <span className="shrink-0 rounded-full bg-tb-primary px-2 py-0.5 text-[10px] font-bold text-white">
                                                            Anak ke{' '}
                                                            {selectedIndex + 1}
                                                        </span>
                                                    </div>

                                                    {isFocusRow && (
                                                        <p className="text-[11px] font-medium text-tb-on-surface-variant italic">
                                                            Ini Anda — nama,
                                                            jenis kelamin, marga
                                                            diisi lewat
                                                            Informasi Pribadi.
                                                        </p>
                                                    )}

                                                    <div className="grid gap-2.5">
                                                        <div className="grid gap-1">
                                                            <Label className="text-tb-on-surface">
                                                                Nama
                                                            </Label>
                                                            {isFocusRow ? (
                                                                <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm font-medium text-tb-on-surface">
                                                                    {data.name ||
                                                                        '—'}
                                                                </div>
                                                            ) : (
                                                                <NameCombobox
                                                                    value={
                                                                        selectedChild.name
                                                                    }
                                                                    onChange={(
                                                                        value,
                                                                    ) =>
                                                                        setChild(
                                                                            selectedIndex,
                                                                            'name',
                                                                            value,
                                                                        )
                                                                    }
                                                                    onSelect={(
                                                                        suggestion,
                                                                    ) =>
                                                                        selectChild(
                                                                            selectedIndex,
                                                                            suggestion,
                                                                        )
                                                                    }
                                                                    suggestions={
                                                                        nameSuggestions
                                                                    }
                                                                    placeholder="Nama"
                                                                    allowNa
                                                                />
                                                            )}
                                                        </div>

                                                        <div className="grid grid-cols-2 gap-2.5">
                                                            <div className="grid gap-1">
                                                                <Label className="text-tb-on-surface">
                                                                    Jenis
                                                                    Kelamin
                                                                </Label>
                                                                {isFocusRow ? (
                                                                    <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                                        {data.gender ||
                                                                            '—'}
                                                                    </div>
                                                                ) : (
                                                                    <Select
                                                                        value={
                                                                            selectedChild.gender ||
                                                                            ''
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) =>
                                                                            setChild(
                                                                                selectedIndex,
                                                                                'gender',
                                                                                value,
                                                                            )
                                                                        }
                                                                    >
                                                                        <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                                                            <SelectValue placeholder="L/P" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            <SelectItem value="L">
                                                                                L
                                                                            </SelectItem>
                                                                            <SelectItem value="P">
                                                                                P
                                                                            </SelectItem>
                                                                        </SelectContent>
                                                                    </Select>
                                                                )}
                                                            </div>
                                                            <div className="grid gap-1">
                                                                <Label className="text-tb-on-surface">
                                                                    Marga
                                                                </Label>
                                                                {isFocusRow ? (
                                                                    <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                                        {margaName(
                                                                            data.marga_id,
                                                                            data.new_marga,
                                                                        )}
                                                                    </div>
                                                                ) : (
                                                                    <MargaField
                                                                        value={
                                                                            selectedChild.marga_id ??
                                                                            null
                                                                        }
                                                                        newMarga={
                                                                            selectedChild.new_marga ??
                                                                            ''
                                                                        }
                                                                        onValue={(
                                                                            value,
                                                                        ) =>
                                                                            setChildMarga(
                                                                                selectedIndex,
                                                                                value,
                                                                            )
                                                                        }
                                                                        onNewMarga={(
                                                                            value,
                                                                        ) =>
                                                                            setChildNewMarga(
                                                                                selectedIndex,
                                                                                value,
                                                                            )
                                                                        }
                                                                        margas={
                                                                            margas
                                                                        }
                                                                        placeholder="Marga"
                                                                        disabled={
                                                                            lockedMarga !==
                                                                            null
                                                                        }
                                                                    />
                                                                )}
                                                            </div>
                                                        </div>

                                                        <div className="grid grid-cols-2 gap-2.5">
                                                            <div className="grid gap-1">
                                                                <Label className="text-tb-on-surface">
                                                                    Pasangan
                                                                </Label>
                                                                <Input
                                                                    value={
                                                                        selectedChild.spouse
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setChild(
                                                                            selectedIndex,
                                                                            'spouse',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Nama pasangan"
                                                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                                />
                                                            </div>
                                                            <div className="grid gap-1">
                                                                <Label className="text-tb-on-surface">
                                                                    Marga
                                                                    Pasangan
                                                                </Label>
                                                                <SpouseMargaSelect
                                                                    value={
                                                                        selectedChild.spouse_marga
                                                                    }
                                                                    onChange={(
                                                                        next,
                                                                    ) =>
                                                                        setChild(
                                                                            selectedIndex,
                                                                            'spouse_marga',
                                                                            next,
                                                                        )
                                                                    }
                                                                    margas={
                                                                        margas
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="grid gap-1.5">
                                                <p className="text-[11px] font-semibold tracking-[0.14em] text-tb-on-surface-variant uppercase">
                                                    Saudara
                                                </p>
                                                {data.children.length === 0 ? (
                                                    <p className="text-sm text-tb-on-surface-variant italic">
                                                        Belum ada saudara.
                                                    </p>
                                                ) : (
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {data.children.map(
                                                            (
                                                                sibling,
                                                                index,
                                                            ) => (
                                                                <button
                                                                    key={
                                                                        sibling.id ??
                                                                        `sibling-${index}`
                                                                    }
                                                                    type="button"
                                                                    onClick={() =>
                                                                        selectRow(
                                                                            index,
                                                                        )
                                                                    }
                                                                    className={cn(
                                                                        'max-w-full rounded-full border px-2.5 py-1 text-xs font-medium transition-colors',
                                                                        index ===
                                                                            selectedIndex
                                                                            ? 'border-tb-primary bg-tb-primary text-white'
                                                                            : 'border-tb-outline-variant bg-tb-surface-container/60 text-tb-on-surface hover:border-tb-primary',
                                                                    )}
                                                                >
                                                                    <span className="truncate">
                                                                        {isNameFilled(
                                                                            sibling.name ??
                                                                                '',
                                                                        )
                                                                            ? sibling.name
                                                                            : `Anak ke ${index + 1}`}
                                                                    </span>
                                                                </button>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        </div>

                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardHeader className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="font-display text-lg text-tb-on-surface">
                                        Daftar Anak
                                    </CardTitle>
                                    <CardDescription>
                                        Anak kandung dari{' '}
                                        {data.name || 'anggota ini'}. Urutan
                                        anak mengikuti urutan baris.
                                    </CardDescription>
                                </div>
                                <div className="text-xs text-tb-on-surface-variant">
                                    Urut 01, 02, …
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {data.ownChildren.length === 0 && (
                                    <div className="rounded-lg border border-dashed border-tb-outline-variant bg-tb-surface-container/30 px-4 py-8 text-center">
                                        <p className="text-sm font-medium text-tb-on-surface">
                                            Belum ada anak yang dicatat
                                        </p>
                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                            Gunakan tombol di bawah untuk
                                            menambahkan anak pertama.
                                        </p>
                                    </div>
                                )}
                                <AnimatePresence initial={false}>
                                    {data.ownChildren.map((child, index) => {
                                        const effectiveMotherIndex =
                                            child.mother_index ??
                                            soleMotherIndex(data.mothers);
                                        const selectedMother =
                                            effectiveMotherIndex !== null
                                                ? data.mothers[
                                                      effectiveMotherIndex
                                                  ]
                                                : undefined;

                                        return (
                                            <motion.div
                                                key={
                                                    child.uid ??
                                                    child.id ??
                                                    `own-row-${index}`
                                                }
                                                layout
                                                initial={{
                                                    opacity: 0,
                                                    scale: 0.97,
                                                }}
                                                animate={{
                                                    opacity: 1,
                                                    scale: 1,
                                                }}
                                                exit={{
                                                    opacity: 0,
                                                    scale: 0.97,
                                                }}
                                                transition={{
                                                    type: 'spring',
                                                    stiffness: 400,
                                                    damping: 35,
                                                }}
                                                className="grid overflow-hidden rounded-xl border border-tb-outline-variant bg-tb-surface-bright"
                                            >
                                                <div className="flex items-center justify-between gap-3 border-b border-tb-outline-variant bg-tb-surface-container/40 px-4 py-3">
                                                    <span className="flex min-w-0 items-center gap-1.5">
                                                        <span className="inline-flex w-fit shrink-0 items-center rounded-full bg-tb-surface-container px-2 py-0.5 text-xs font-semibold text-tb-on-surface-variant">
                                                            Urut{' '}
                                                            {String(
                                                                index + 1,
                                                            ).padStart(2, '0')}
                                                        </span>
                                                        <span className="max-w-64 truncate text-sm font-semibold text-tb-on-surface">
                                                            {displayRowName(
                                                                child.name,
                                                            )}
                                                        </span>
                                                        {isGapRow(child) && (
                                                            <span className="inline-flex w-fit items-center rounded-md bg-tb-surface-container px-1.5 py-0.5 text-[10px] font-semibold text-tb-on-surface-variant">
                                                                N/A
                                                            </span>
                                                        )}
                                                        {child.pending && (
                                                            <span className="inline-flex w-fit items-center rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                                                Belum tersambung
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="flex shrink-0 items-center gap-1">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                insertOwnChildAbove(
                                                                    index,
                                                                )
                                                            }
                                                            aria-label="Sisipkan anak di atas"
                                                            title="Sisipkan anak di atas"
                                                            className="inline-flex h-7 w-7 items-center justify-center rounded-full border border-tb-outline-variant text-tb-outline transition-colors hover:border-tb-primary hover:text-tb-primary"
                                                        >
                                                            <Plus className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                moveOwnChild(
                                                                    index,
                                                                    -1,
                                                                )
                                                            }
                                                            disabled={
                                                                index === 0
                                                            }
                                                            aria-label="Pindah ke atas"
                                                            title="Pindah ke atas"
                                                            className="inline-flex h-6 w-6 items-center justify-center rounded-full text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                                                        >
                                                            <ChevronUp className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                moveOwnChild(
                                                                    index,
                                                                    1,
                                                                )
                                                            }
                                                            disabled={
                                                                index ===
                                                                ownChildrenCount -
                                                                    1
                                                            }
                                                            aria-label="Pindah ke bawah"
                                                            title="Pindah ke bawah"
                                                            className="inline-flex h-6 w-6 items-center justify-center rounded-full text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                                                        >
                                                            <ChevronDown className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                requestRemoveRow(
                                                                    'ownChildren',
                                                                    index,
                                                                )
                                                            }
                                                            aria-label={
                                                                child.id == null
                                                                    ? 'Hapus anak'
                                                                    : 'Pisahkan anak dari silsilah'
                                                            }
                                                            title={
                                                                child.id == null
                                                                    ? 'Hapus anak'
                                                                    : 'Pisahkan anak dari silsilah'
                                                            }
                                                            className="inline-flex h-6 w-6 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-950"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </button>
                                                    </span>
                                                </div>
                                                <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-6">
                                                    <div className="flex items-center gap-3 sm:col-span-2 lg:col-span-6">
                                                        <p className="text-[11px] font-semibold tracking-[0.14em] text-tb-on-surface-variant uppercase">
                                                            Data Anak
                                                        </p>
                                                        <span className="h-px flex-1 bg-tb-outline-variant" />
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-3">
                                                        <Label>Nama</Label>
                                                        <NameCombobox
                                                            value={child.name}
                                                            onChange={(value) =>
                                                                setOwnChild(
                                                                    index,
                                                                    'name',
                                                                    value,
                                                                )
                                                            }
                                                            onSelect={(
                                                                suggestion,
                                                            ) =>
                                                                selectOwnChild(
                                                                    index,
                                                                    suggestion,
                                                                )
                                                            }
                                                            suggestions={
                                                                nameSuggestions
                                                            }
                                                            placeholder="Nama anak"
                                                        />
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-3">
                                                        <Label>
                                                            Alias / Gelar
                                                        </Label>
                                                        <Input
                                                            value={
                                                                child.alias ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                setOwnChild(
                                                                    index,
                                                                    'alias',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Tuan Sorba Dibanua"
                                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                        />
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-2">
                                                        <Label>
                                                            Jenis Kelamin
                                                        </Label>
                                                        <Select
                                                            value={
                                                                child.gender ||
                                                                VALUE_NONE
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                setOwnChild(
                                                                    index,
                                                                    'gender',
                                                                    value ===
                                                                        VALUE_NONE
                                                                        ? ''
                                                                        : value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="border-tb-outline-variant bg-tb-surface-bright">
                                                                <SelectValue placeholder="Pilih" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    value={
                                                                        VALUE_NONE
                                                                    }
                                                                >
                                                                    — Pilih —
                                                                </SelectItem>
                                                                <SelectItem value="L">
                                                                    Laki-laki
                                                                </SelectItem>
                                                                <SelectItem value="P">
                                                                    Perempuan
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-4">
                                                        <Label>Marga</Label>
                                                        <MargaField
                                                            value={
                                                                child.marga_id ??
                                                                null
                                                            }
                                                            newMarga={
                                                                child.new_marga ??
                                                                ''
                                                            }
                                                            onValue={(value) =>
                                                                setOwnChildMarga(
                                                                    index,
                                                                    value,
                                                                )
                                                            }
                                                            onNewMarga={(
                                                                value,
                                                            ) =>
                                                                setOwnChildNewMarga(
                                                                    index,
                                                                    value,
                                                                )
                                                            }
                                                            margas={margas}
                                                            disabled={
                                                                lockedMarga !==
                                                                null
                                                            }
                                                        />
                                                    </div>
                                                    <div className="flex items-center gap-3 pt-1 sm:col-span-2 lg:col-span-6">
                                                        <p className="text-[11px] font-semibold tracking-[0.14em] text-tb-on-surface-variant uppercase">
                                                            Relasi Ibu
                                                        </p>
                                                        <span className="h-px flex-1 bg-tb-outline-variant" />
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-3">
                                                        <Label>Ibu</Label>
                                                        <Select
                                                            value={
                                                                effectiveMotherIndex ===
                                                                null
                                                                    ? VALUE_NONE
                                                                    : String(
                                                                          effectiveMotherIndex,
                                                                      )
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                setOwnChildMother(
                                                                    index,
                                                                    value ===
                                                                        VALUE_NONE
                                                                        ? null
                                                                        : Number(
                                                                              value,
                                                                          ),
                                                                )
                                                            }
                                                            disabled={
                                                                availableMothers.length <=
                                                                1
                                                            }
                                                        >
                                                            <SelectTrigger className="border-tb-outline-variant bg-tb-surface-bright">
                                                                <SelectValue placeholder="Pilih Ibu" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {availableMothers.length >
                                                                    1 && (
                                                                    <SelectItem
                                                                        value={
                                                                            VALUE_NONE
                                                                        }
                                                                    >
                                                                        — Pilih
                                                                        Ibu —
                                                                    </SelectItem>
                                                                )}
                                                                {availableMothers.map(
                                                                    ({
                                                                        mother,
                                                                        index: motherIndex,
                                                                    }) => (
                                                                        <SelectItem
                                                                            key={
                                                                                mother.id ??
                                                                                `mother-${motherIndex}`
                                                                            }
                                                                            value={String(
                                                                                motherIndex,
                                                                            )}
                                                                        >
                                                                            {isNameFilled(
                                                                                mother.name,
                                                                            )
                                                                                ? mother.name
                                                                                : `Istri ${motherIndex + 1}`}
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `ownChildren.${index}.mother_index`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-3">
                                                        <Label>
                                                            Ayah dari Ibu
                                                        </Label>
                                                        <Input
                                                            value={
                                                                selectedMother?.father_name ??
                                                                ''
                                                            }
                                                            readOnly
                                                            placeholder="Isi pada data Ibu"
                                                            className="border-tb-outline-variant bg-tb-surface-container/60"
                                                        />
                                                        {selectedMother && (
                                                            <p className="text-xs text-tb-on-surface-variant">
                                                                Marga:{' '}
                                                                {margaName(
                                                                    selectedMother.marga_id,
                                                                    selectedMother.new_marga,
                                                                )}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-3 pt-1 sm:col-span-2 lg:col-span-6">
                                                        <p className="text-[11px] font-semibold tracking-[0.14em] text-tb-on-surface-variant uppercase">
                                                            Pasangan
                                                        </p>
                                                        <span className="h-px flex-1 bg-tb-outline-variant" />
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-3">
                                                        <Label>
                                                            Nama Pasangan
                                                        </Label>
                                                        <Input
                                                            value={child.spouse}
                                                            onChange={(event) =>
                                                                setOwnChild(
                                                                    index,
                                                                    'spouse',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Nama pasangan"
                                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                        />
                                                    </div>
                                                    <div className="grid gap-1.5 lg:col-span-3">
                                                        <Label>
                                                            Marga Pasangan
                                                        </Label>
                                                        <SpouseMargaSelect
                                                            value={
                                                                child.spouse_marga
                                                            }
                                                            onChange={(next) =>
                                                                setOwnChild(
                                                                    index,
                                                                    'spouse_marga',
                                                                    next,
                                                                )
                                                            }
                                                            margas={margas}
                                                        />
                                                    </div>
                                                </div>
                                            </motion.div>
                                        );
                                    })}
                                </AnimatePresence>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addOwnChild}
                                    className="mt-1 w-full border-dashed border-tb-outline-variant text-tb-primary hover:bg-tb-primary/5"
                                >
                                    <Plus className="size-4" /> Tambah Anak
                                </Button>
                            </CardContent>
                        </Card>

                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardHeader>
                                <CardTitle className="font-display text-lg text-tb-on-surface">
                                    Orang Tua
                                </CardTitle>
                                <CardDescription>
                                    Ayah dan istri-istrinya dari anak-anak yang
                                    dicatat di bawah. Setiap anak dapat
                                    ditautkan ke Ibu yang sesuai.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5 lg:grid-cols-2">
                                {renderParentBlock(
                                    'father',
                                    'Ayah',
                                    '1950',
                                    '2020',
                                )}
                                <div className="grid content-start gap-5">
                                    {data.mothers.map((wife, index) => (
                                        <div
                                            key={
                                                wife.id ??
                                                `istri-block-${index}`
                                            }
                                            className="relative"
                                        >
                                            {data.mothers.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeMother(index)
                                                    }
                                                    aria-label={`Hapus Istri ${index + 1}`}
                                                    title={`Hapus Istri ${index + 1}`}
                                                    className="absolute top-2 right-2 z-10 inline-flex h-6 w-6 items-center justify-center rounded-full text-tb-on-surface-variant transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                                                >
                                                    <X className="size-3.5" />
                                                </button>
                                            )}
                                            {renderParentBlock(
                                                index,
                                                `Istri ${index + 1}`,
                                                '1955',
                                                '2025',
                                                true,
                                                false,
                                            )}
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addMother}
                                        className="w-full border-dashed border-tb-outline-variant text-tb-primary hover:bg-tb-primary/5"
                                    >
                                        <Plus className="size-4" /> Tambah Istri
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardHeader className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="font-display text-lg text-tb-on-surface">
                                        Daftar Saudara
                                    </CardTitle>
                                    <CardDescription>
                                        Total bersaudara: {siblingCount} —
                                        menambah total otomatis menambah baris
                                        di bawah. Urutan saudara mengikuti
                                        urutan baris ("Urut").
                                    </CardDescription>
                                </div>
                                <div className="text-xs text-tb-on-surface-variant">
                                    Urut 01, 02, …
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <AnimatePresence initial={false}>
                                    {data.children.map((child, index) => {
                                        const focused =
                                            person?.id != null
                                                ? child.id === person.id
                                                : index === birthOrder - 1;

                                        return (
                                            <motion.div
                                                key={
                                                    child.uid ??
                                                    child.id ??
                                                    `row-${index}`
                                                }
                                                layout
                                                initial={{
                                                    opacity: 0,
                                                    scale: 0.97,
                                                }}
                                                animate={{
                                                    opacity: 1,
                                                    scale: 1,
                                                }}
                                                exit={{
                                                    opacity: 0,
                                                    scale: 0.97,
                                                }}
                                                transition={{
                                                    type: 'spring',
                                                    stiffness: 400,
                                                    damping: 35,
                                                }}
                                                className={cn(
                                                    'grid gap-3 rounded-lg border p-3',
                                                    focused
                                                        ? 'border-tb-primary/50 bg-tb-primary/5'
                                                        : selectedIndex ===
                                                            index
                                                          ? 'border-tb-primary bg-tb-primary/5'
                                                          : 'border-tb-outline-variant bg-tb-surface-bright',
                                                )}
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="flex items-center gap-1.5">
                                                        <span
                                                            className={cn(
                                                                'inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                                                focused
                                                                    ? 'bg-tb-primary text-white'
                                                                    : 'bg-tb-surface-container text-tb-on-surface-variant',
                                                            )}
                                                        >
                                                            Urut{' '}
                                                            {String(
                                                                index + 1,
                                                            ).padStart(2, '0')}
                                                        </span>
                                                        {isGapRow(child) && (
                                                            <span className="inline-flex w-fit items-center rounded-md bg-tb-surface-container px-1.5 py-0.5 text-[10px] font-semibold text-tb-on-surface-variant">
                                                                N/A
                                                            </span>
                                                        )}
                                                        {child.pending && (
                                                            <span className="inline-flex w-fit items-center rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                                                Belum tersambung
                                                            </span>
                                                        )}
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                insertChildAbove(
                                                                    index,
                                                                )
                                                            }
                                                            aria-label="Sisipkan saudara di atas"
                                                            title="Sisipkan saudara di atas"
                                                            className="inline-flex h-6 w-6 items-center justify-center rounded-full border border-tb-outline-variant text-tb-outline transition-colors hover:border-tb-primary hover:text-tb-primary"
                                                        >
                                                            <Plus className="h-3.5 w-3.5" />
                                                        </button>
                                                    </span>
                                                    <span className="flex items-center gap-2">
                                                        {focused && (
                                                            <span className="text-[11px] font-medium text-tb-primary">
                                                                (Anda sedang
                                                                diedit)
                                                            </span>
                                                        )}
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                moveChild(
                                                                    index,
                                                                    -1,
                                                                )
                                                            }
                                                            disabled={
                                                                index === 0
                                                            }
                                                            aria-label="Pindah ke atas"
                                                            title="Pindah ke atas"
                                                            className="inline-flex h-6 w-6 items-center justify-center rounded-full text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                                                        >
                                                            <ChevronUp className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                moveChild(
                                                                    index,
                                                                    1,
                                                                )
                                                            }
                                                            disabled={
                                                                index ===
                                                                data.children
                                                                    .length -
                                                                    1
                                                            }
                                                            aria-label="Pindah ke bawah"
                                                            title="Pindah ke bawah"
                                                            className="inline-flex h-6 w-6 items-center justify-center rounded-full text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                                                        >
                                                            <ChevronDown className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                selectRow(index)
                                                            }
                                                            aria-label={`Lihat detail ${child.name || `baris ${index + 1}`}`}
                                                            title="Lihat detail"
                                                            className={cn(
                                                                'inline-flex h-6 w-6 items-center justify-center rounded-full transition-colors',
                                                                selectedIndex ===
                                                                    index
                                                                    ? 'bg-tb-primary text-white'
                                                                    : 'text-tb-outline hover:bg-tb-surface-container hover:text-tb-on-surface',
                                                            )}
                                                        >
                                                            <Eye className="h-3.5 w-3.5" />
                                                        </button>
                                                        {(!focused ||
                                                            child.id ==
                                                                null) && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    requestRemoveRow(
                                                                        'children',
                                                                        index,
                                                                    )
                                                                }
                                                                aria-label={
                                                                    child.id ==
                                                                    null
                                                                        ? 'Hapus baris'
                                                                        : 'Pisahkan dari silsilah'
                                                                }
                                                                title={
                                                                    child.id ==
                                                                    null
                                                                        ? 'Hapus baris'
                                                                        : 'Pisahkan dari silsilah'
                                                                }
                                                                className="inline-flex h-6 w-6 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-950"
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </button>
                                                        )}
                                                    </span>
                                                </div>

                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <div className="grid gap-1.5">
                                                        <Label>
                                                            {focused
                                                                ? 'Nama (ini Anda)'
                                                                : 'Nama'}
                                                        </Label>
                                                        {focused ? (
                                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm font-medium text-tb-on-surface">
                                                                {data.name ||
                                                                    '—'}
                                                            </div>
                                                        ) : (
                                                            <NameCombobox
                                                                value={
                                                                    child.name
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) =>
                                                                    setChild(
                                                                        index,
                                                                        'name',
                                                                        value,
                                                                    )
                                                                }
                                                                onSelect={(
                                                                    suggestion,
                                                                ) =>
                                                                    selectChild(
                                                                        index,
                                                                        suggestion,
                                                                    )
                                                                }
                                                                suggestions={
                                                                    nameSuggestions
                                                                }
                                                                placeholder={
                                                                    isGapRow(
                                                                        child,
                                                                    )
                                                                        ? 'N/A'
                                                                        : 'Nama saudara'
                                                                }
                                                                allowNa
                                                            />
                                                        )}
                                                    </div>
                                                    <div className="grid gap-1.5">
                                                        <Label>
                                                            Jenis Kelamin
                                                        </Label>
                                                        {focused ? (
                                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                                {data.gender ||
                                                                    '—'}
                                                            </div>
                                                        ) : (
                                                            <Select
                                                                value={
                                                                    child.gender ||
                                                                    ''
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    setChild(
                                                                        index,
                                                                        'gender',
                                                                        value,
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                                                    <SelectValue placeholder="L/P" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="L">
                                                                        Laki-Laki
                                                                    </SelectItem>
                                                                    <SelectItem value="P">
                                                                        Perempuan
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <div className="grid gap-1.5">
                                                        <Label>Marga</Label>
                                                        {focused ? (
                                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                                {margas.find(
                                                                    (marga) =>
                                                                        marga.id ===
                                                                        data.marga_id,
                                                                )?.name ??
                                                                    (data.new_marga ||
                                                                        '—')}
                                                            </div>
                                                        ) : (
                                                            <MargaField
                                                                value={
                                                                    child.marga_id ??
                                                                    null
                                                                }
                                                                newMarga={
                                                                    child.new_marga ??
                                                                    ''
                                                                }
                                                                onValue={(
                                                                    value,
                                                                ) =>
                                                                    setChildMarga(
                                                                        index,
                                                                        value,
                                                                    )
                                                                }
                                                                onNewMarga={(
                                                                    value,
                                                                ) =>
                                                                    setChildNewMarga(
                                                                        index,
                                                                        value,
                                                                    )
                                                                }
                                                                margas={margas}
                                                                placeholder="Marga saudara"
                                                                disabled={
                                                                    lockedMarga !==
                                                                    null
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                    <div className="grid gap-1.5">
                                                        <Label>Pasangan</Label>
                                                        <Input
                                                            value={child.spouse}
                                                            onChange={(e) =>
                                                                setChild(
                                                                    index,
                                                                    'spouse',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Nama pasangan"
                                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                        />
                                                    </div>
                                                    <div className="grid gap-1.5">
                                                        <Label>
                                                            Marga/Suku Lain
                                                        </Label>
                                                        <SpouseMargaSelect
                                                            value={
                                                                child.spouse_marga
                                                            }
                                                            onChange={(next) =>
                                                                setChild(
                                                                    index,
                                                                    'spouse_marga',
                                                                    next,
                                                                )
                                                            }
                                                            margas={margas}
                                                        />
                                                    </div>
                                                    {!focused && (
                                                        <div className="grid gap-1.5">
                                                            <Label>
                                                                Alias / Gelar
                                                            </Label>
                                                            <Input
                                                                value={
                                                                    child.alias ??
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    setChild(
                                                                        index,
                                                                        'alias',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="Tuan Sorba Dibanua"
                                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                            />
                                                        </div>
                                                    )}
                                                </div>
                                            </motion.div>
                                        );
                                    })}
                                </AnimatePresence>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addChild}
                                    className="mt-1 w-full border-dashed border-tb-outline-variant text-tb-primary hover:bg-tb-primary/5"
                                >
                                    <Plus className="size-4" /> Tambah Saudara
                                </Button>
                            </CardContent>
                        </Card>

                        {!readOnly && (
                            <div className="flex items-center gap-3 pb-6">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-full bg-tb-primary px-6 hover:bg-tb-primary-light"
                                >
                                    {processing
                                        ? 'Menyimpan...'
                                        : person
                                          ? 'Simpan Perubahan'
                                          : 'Tambah Keluarga'}
                                </Button>
                            </div>
                        )}
                    </fieldset>
                </form>
            </div>

            <Dialog
                open={reductionConfirm !== null}
                onOpenChange={(open) => !open && cancelReduction()}
            >
                <DialogContent className="border-tb-outline-variant bg-tb-surface-bright sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-tb-on-surface">
                            Hapus baris saudara?
                        </DialogTitle>
                        <DialogDescription>
                            Mengurangi total bersaudara ke{' '}
                            <strong>{reductionConfirm?.to}</strong> akan
                            menghapus{' '}
                            <strong>{reductionConfirm?.filledNew}</strong> baris
                            yang baru diisi. Baris tersimpan tetap
                            dipertahankan. Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={cancelReduction}>
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmReduction}
                        >
                            Ya, hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={removalConfirm !== null}
                onOpenChange={(open) => !open && cancelRemove()}
            >
                <DialogContent className="border-tb-outline-variant bg-tb-surface-bright sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-tb-on-surface">
                            Pisahkan {removalConfirm?.row.name || 'anggota ini'}{' '}
                            dari silsilah?
                        </DialogTitle>
                        <DialogDescription className="space-y-3">
                            <p>
                                Nama ini dan seluruh keturunannya tetap
                                tersimpan di database. Hanya hubungan dengan
                                orang tuanya yang diputus, sehingga menjadi
                                pohon silsilah yang berdiri sendiri.
                            </p>
                            {(removalConfirm?.row.descendant_count ?? 0) >
                                0 && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-950 dark:bg-amber-950/40">
                                    <p className="text-sm font-semibold text-amber-700 dark:text-amber-300">
                                        Keturunan yang ikut ke pohon baru (
                                        {removalConfirm?.row.descendant_count}
                                        ):
                                    </p>
                                    <ul className="mt-1.5 max-h-40 space-y-1 overflow-y-auto text-sm text-amber-700/90 dark:text-amber-300/90">
                                        {(
                                            removalConfirm?.row
                                                .descendant_names ?? []
                                        ).map((name) => (
                                            <li key={name}>• {name}</li>
                                        ))}
                                        {(removalConfirm?.row
                                            .descendant_count ?? 0) >
                                            (
                                                removalConfirm?.row
                                                    .descendant_names ?? []
                                            ).length && (
                                            <li className="text-xs italic">
                                                …dan{' '}
                                                {(removalConfirm?.row
                                                    .descendant_count ?? 0) -
                                                    (
                                                        removalConfirm?.row
                                                            .descendant_names ??
                                                        []
                                                    ).length}{' '}
                                                lainnya
                                            </li>
                                        )}
                                    </ul>
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={cancelRemove}>
                            Batal
                        </Button>
                        <Button onClick={confirmRemove}>Ya, pisahkan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
