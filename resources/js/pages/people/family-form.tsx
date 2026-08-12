import { Link, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Eye, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NameCombobox } from '@/components/ui/name-combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import people from '@/routes/people';

export type ChildRow = {
    id?: number | null;
    name: string;
    gender: string;
    spouse: string;
    spouse_marga: string;
    marga_id?: number | null;
    new_marga?: string;
    alias?: string | null;
    nomor?: string | null;
    is_leader?: boolean;
    birth_year?: string | null;
    death_year?: string | null;
    image?: string | null;
    bio?: string | null;
};

type ParentEntry = {
    name: string;
    birth_year: string;
    death_year: string;
    marga_id?: number | null;
    new_marga?: string;
    nomor?: string | null;
};

export type LineageChild = {
    id: number;
    name: string;
    gender: string | null;
    marga: string | null;
    nomor: string | null;
    birth_order: number | null;
};

export type LineageEntry = {
    id: number;
    name: string;
    marga: string | null;
    nomor: string | null;
    is_leader: boolean;
    is_self: boolean;
    children: LineageChild[];
};

export type MargaLineageEntry = {
    id: number;
    name: string;
    marga_id: number | null;
    marga: string | null;
    nomor: string | null;
};

export type FamilyData = {
    id?: number | null;
    name: string;
    gender: string;
    alias: string;
    marga_id: number | null;
    birth_order: number | null;
    sibling_count: number | null;
    is_leader: boolean;
    nomor: string;
    nomor_manual?: boolean;
    birth_year: string;
    death_year: string;
    image: string;
    bio: string;
    new_marga?: string;
    father: ParentEntry | null;
    mother: ParentEntry | null;
    lineage: LineageEntry[];
    children: ChildRow[];
};

type MargaOption = { id: number; name: string };

type Props = {
    person: FamilyData | null;
    margas: MargaOption[];
    nameSuggestions: string[];
    nomorUsed: { nomor: string; name: string }[];
    lockedMarga?: { id: number; name: string } | null;
    margaLineage?: MargaLineageEntry[];
};

const VALUE_NONE = 'none';
const NEW_MARGA_VALUE = '__new__';

function isNameFilled(name: string): boolean {
    return name.trim() !== '' && name.trim().toUpperCase() !== 'N/A';
}

function isRowFilled(row: ChildRow): boolean {
    return (
        row.id != null ||
        isNameFilled(row.name ?? '') ||
        (row.nomor ?? '').trim() !== ''
    );
}

function parseNomor(nomor?: string | null): number[] | null {
    const trimmed = (nomor ?? '').trim();

    if (!trimmed) {
        return null;
    }

    const parts = trimmed.split('.').map((part) => parseInt(part, 10));

    return parts.every((part) => Number.isFinite(part)) ? parts : null;
}

function impliedNomorPosition(nomor?: string | null): number | null {
    const parts = parseNomor(nomor);

    if (parts === null) {
        return null;
    }

    const last = parts[parts.length - 1];

    return last > 0 ? last : null;
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
    return (
        row.id == null &&
        !isNameFilled(row.name ?? '') &&
        (row.nomor ?? '').trim() === ''
    );
}

function normalizeChildrenPositions(children: ChildRow[]): ChildRow[] {
    const rows = [...children];

    if (rows.length === 0) {
        return rows;
    }

    const placed: { row: ChildRow; position: number }[] = [];
    const fallback: ChildRow[] = [];

    rows.forEach((row, index) => {
        // Pemimpin have flat lineage numbers (1, 2, 3, ...) that are NOT
        // sibling positions, so they always stay at their array position.
        const fromNomor = row.is_leader ? null : impliedNomorPosition(row.nomor ?? null);
        const position = fromNomor ?? index + 1;

        if (position >= 1) {
            placed.push({ row, position });
        } else {
            fallback.push(row);
        }
    });

    const maxPosition = Math.max(rows.length, ...placed.map((p) => p.position));
    const slots: (ChildRow | undefined)[] = Array.from({ length: maxPosition }, () => undefined);

    placed.forEach(({ row, position }) => {
        const target = position - 1;

        if (target >= 0 && target < maxPosition && slots[target] === undefined) {
            slots[target] = row;
        } else {
            fallback.push(row);
        }
    });

    const result: ChildRow[] = [];
    let fallbackIndex = 0;

    for (let index = 0; index < maxPosition; index++) {
        if (slots[index] !== undefined) {
            result.push(slots[index]!);
        } else if (fallbackIndex < fallback.length) {
            result.push(fallback[fallbackIndex++]);
        } else if (index < rows.length) {
            result.push(isGapRow(rows[index]) ? rows[index] : emptyRow());
        } else {
            result.push(emptyRow());
        }
    }

    for (; fallbackIndex < fallback.length; fallbackIndex++) {
        result.push(fallback[fallbackIndex]);
    }

    return result;
}

function SilsilahListCard({
    lineage,
    selfId,
}: {
    lineage: LineageEntry[];
    selfId?: number | null;
}) {
    const [expanded, setExpanded] = useState<Set<number>>(() => {
        const selfIndex = lineage.findIndex((entry) => entry.is_self);

        return selfIndex > 0 ? new Set([lineage[selfIndex - 1].id]) : new Set<number>();
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

    const childNomor = (entry: LineageEntry, child: LineageChild, index: number): string => {
        if (child.nomor?.trim()) {
            return child.nomor.trim();
        }

        const order = child.birth_order ?? index + 1;

        if (entry.nomor) {
            return `${entry.nomor}.${order}`;
        }

        return String(order);
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
                    <p className="text-sm italic text-tb-on-surface-variant">
                        Belum ada catatan garis keturunan.
                    </p>
                ) : (
                    <ul className="grid gap-1.5">
                        {lineage.map((entry) => {
                            const isOpen = expanded.has(entry.id);
                            const count = entry.children.length;

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
                                            aria-label={isOpen ? 'Tutup daftar saudara' : 'Buka daftar saudara'}
                                            className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface"
                                        >
                                            {isOpen ? (
                                                <ChevronDown className="h-4 w-4" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4" />
                                            )}
                                        </button>
                                        <span className="flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-tb-surface-container px-1 text-xs font-bold text-tb-on-surface-variant">
                                            {entry.nomor ?? '—'}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-1.5">
                                                <p className="min-w-0 flex-1 truncate text-sm font-semibold text-tb-on-surface">
                                                    {displayRowName(entry.name)}
                                                </p>
                                                {entry.is_self && (
                                                    <span className="shrink-0 rounded-full bg-tb-primary px-2 py-0.5 text-[10px] font-bold text-white">
                                                        Anda
                                                    </span>
                                                )}
                                                {!entry.is_self && entry.is_leader && (
                                                    <span className="shrink-0 rounded-full bg-tb-surface-container px-2 py-0.5 text-[10px] font-semibold text-tb-on-surface-variant">
                                                        Pemimpin
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-0.5 truncate text-xs text-tb-on-surface-variant">
                                                {entry.marga || 'Tanpa marga'}
                                                {count > 0 && ` · ${count} anak`}
                                            </p>
                                        </div>
                                    </div>

                                    {isOpen && (
                                        <div className="ml-[3.25rem] border-l border-tb-outline-variant pb-1.5 pl-2">
                                            {count === 0 ? (
                                                <p className="px-1 py-1 text-xs italic text-tb-on-surface-variant">
                                                    Belum ada saudara tercatat.
                                                </p>
                                            ) : (
                                                <ul className="grid gap-0.5">
                                                    {entry.children.map((child, index) => {
                                                        const filled = isNameFilled(child.name);
                                                        const isSelf = child.id === selfId;

                                                        return (
                                                            <li key={child.id}>
                                                                <div className="group flex items-center gap-1.5 rounded-md px-1.5 py-1 transition-colors hover:bg-tb-surface-container/70">
                                                                    <Link
                                                                        href={people.show(child.id)}
                                                                        className="flex min-w-0 flex-1 items-center gap-2 rounded-md"
                                                                    >
                                                                        <span className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-tb-surface-container px-1 text-[10px] font-semibold text-tb-on-surface-variant">
                                                                            {childNomor(entry, child, index)}
                                                                        </span>
                                                                        <span
                                                                            className={cn(
                                                                                'min-w-0 flex-1 truncate text-sm',
                                                                                filled
                                                                                    ? 'text-tb-on-surface'
                                                                                    : 'italic text-tb-on-surface-variant',
                                                                            )}
                                                                        >
                                                                            {filled ? child.name : displayRowName(child.name)}
                                                                        </span>
                                                                        {isSelf && (
                                                                            <span className="shrink-0 text-[11px] font-medium text-tb-primary">
                                                                                Anda
                                                                            </span>
                                                                        )}
                                                                    </Link>
                                                                    <Link
                                                                        href={people.edit(child.id)}
                                                                        aria-label={`Ubah ${child.name}`}
                                                                        className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-tb-outline-variant text-tb-outline opacity-70 transition-opacity hover:border-tb-primary hover:text-tb-primary group-hover:opacity-100"
                                                                    >
                                                                        <Pencil className="h-3.5 w-3.5" />
                                                                    </Link>
                                                                </div>
                                                            </li>
                                                        );
                                                    })}
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

function MargaLineageCard({ entries }: { entries: MargaLineageEntry[] }) {
    return (
        <Card className="border-tb-outline-variant bg-tb-surface-bright">
            <CardHeader>
                <CardTitle className="font-display text-lg text-tb-on-surface">
                    List Silsilah
                </CardTitle>
                <CardDescription>
                    Garis keturunan marga saat ini (read-only).
                </CardDescription>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <p className="text-sm italic text-tb-on-surface-variant">
                        Belum ada pemimpin yang ditandai.
                    </p>
                ) : (
                    <ul className="grid gap-1.5">
                        {entries.map((entry) => (
                            <li
                                key={entry.id}
                                className="flex items-center gap-2 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-2 py-1.5"
                            >
                                <span className="flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-tb-surface-container px-1 text-xs font-bold text-tb-on-surface-variant">
                                    {entry.nomor ?? '—'}
                                </span>
                                <span className="min-w-0 flex-1 truncate text-sm font-medium text-tb-on-surface">
                                    {displayRowName(entry.name)}
                                </span>
                                {entry.marga && (
                                    <span className="shrink-0 text-xs text-tb-on-surface-variant">
                                        {entry.marga}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

const emptyRow = (): ChildRow => ({
    id: null,
    name: '',
    gender: '',
    spouse: '',
    spouse_marga: '',
    marga_id: null,
    new_marga: '',
    nomor: '',
    is_leader: false,
});

const emptyParent = (): ParentEntry => ({
    name: '',
    birth_year: '',
    death_year: '',
    marga_id: null,
    new_marga: '',
    nomor: null,
});

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

export default function FamilyForm({ person, margas, nameSuggestions, nomorUsed, lockedMarga, margaLineage }: Props) {
    const isEdit = person !== null;

    const { data, setData, transform, post, put, processing, errors } = useForm({
        name: person?.name ?? '',
        gender: person?.gender ?? '',
        alias: person?.alias ?? '',
        marga_id: person?.marga_id ?? lockedMarga?.id ?? null,
        new_marga: person?.new_marga ?? '',
        birth_order: person?.birth_order ?? 1,
        sibling_count:
            person?.sibling_count ?? Math.max(person?.children.length ?? 1, 1),
        is_leader: person?.is_leader ?? false,
        nomor: person?.nomor_manual ? (person?.nomor ?? '') : '',
        birth_year: person?.birth_year ?? '',
        death_year: person?.death_year ?? '',
        image: person?.image ?? '',
        bio: person?.bio ?? '',
        father: person?.father
            ? {
                  name: person.father.name ?? '',
                  birth_year: person.father.birth_year ?? '',
                  death_year: person.father.death_year ?? '',
                  marga_id: person.father.marga_id ?? lockedMarga?.id ?? null,
                  new_marga: '',
                  nomor: person.father.nomor ?? null,
              }
            : emptyParent(),
        mother: person?.mother
            ? {
                  name: person.mother.name ?? '',
                  birth_year: person.mother.birth_year ?? '',
                  death_year: person.mother.death_year ?? '',
                  marga_id: person.mother.marga_id ?? null,
                  new_marga: '',
              }
            : emptyParent(),
        children:
            person?.children && person.children.length > 0
                ? person.children.map((child) => ({
                      id: child.id ?? null,
                      name: child.name ?? '',
                      gender: child.gender ?? '',
                      spouse: child.spouse ?? '',
                      spouse_marga: child.spouse_marga ?? '',
                      marga_id: child.marga_id ?? lockedMarga?.id ?? null,
                      new_marga: '',
                      nomor: child.nomor ?? '',
                      is_leader: child.is_leader ?? false,
                  }))
                : [emptyRow()],
    });

    transform((currentData) => {
        const withMarga = lockedMarga
            ? {
                  ...currentData,
                  marga_id: lockedMarga.id,
                  new_marga: '',
                  father: currentData.father
                      ? { ...currentData.father, marga_id: lockedMarga.id, new_marga: '' }
                      : null,
                  children: (currentData.children ?? []).map((child) => ({
                      ...child,
                      marga_id: lockedMarga.id,
                      new_marga: '',
                  })),
              }
            : currentData;

        const sorted = normalizeChildrenPositions(withMarga.children ?? []);
        const focusIndex =
            person?.id != null
                ? sorted.findIndex((child) => child.id === person.id)
                : (Number(withMarga.birth_order) || 1) - 1;

        return {
            ...withMarga,
            children: sorted,
            birth_order: Math.max(1, (focusIndex >= 0 ? focusIndex : 0) + 1),
        };
    });

    const birthOrder = Number(data.birth_order) || 1;
    const siblingCount = Number(data.sibling_count) || 1;

    const trimmedNomor = data.nomor.trim();
    const nomorConflict = trimmedNomor
        ? nomorUsed.find((entry) => entry.nomor === trimmedNomor)
        : undefined;

    const prevSiblingCount = useRef(siblingCount);
    const savedExcessToastShown = useRef(false);
    const [reductionConfirm, setReductionConfirm] = useState<{
        from: number;
        to: number;
        filledNew: number;
    } | null>(null);
    const [selectedIndex, setSelectedIndex] = useState<number | null>(null);

    const selectRow = (index: number) => {
        setSelectedIndex(selectedIndex === index ? null : index);
    };

    const clearSelection = () => {
        setSelectedIndex(null);
    };

    const selectedChild = selectedIndex != null
        ? (data.children[selectedIndex] as ChildRow | undefined)
        : undefined;

    const isFocusRow = person?.id != null
        ? selectedChild?.id === person.id
        : selectedIndex === birthOrder - 1;

    const margaName = (margaId: number | null | undefined, newMarga?: string | null): string => {
        if (newMarga?.trim()) {
            return newMarga.trim();
        }

        return margas.find((marga) => marga.id === margaId)?.name ?? '—';
    };

    useEffect(() => {
        if (data.children.length === 0) {
            return;
        }

        const focusIndex = person?.id != null
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
                          nomor: data.nomor || null,
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
        data.nomor,
        data.birth_year,
        data.death_year,
        data.image,
        data.bio,
        birthOrder,
        person?.id,
    ]);

    useEffect(() => {
        const sorted = normalizeChildrenPositions(data.children);
        const changed = sorted.some((child, index) => child !== data.children[index]);

        if (changed) {
            setData('children', sorted);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.children]);

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

            if (excessNewFilled === 0 && excessSaved > 0 && !savedExcessToastShown.current) {
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

    const setChild = (
        index: number,
        key: 'name' | 'gender' | 'spouse' | 'spouse_marga' | 'nomor',
        value: string,
    ) => {
        const next = data.children.map((child, i) =>
            i === index ? { ...child, [key]: value } : child,
        );

        if (key === 'nomor') {
            const normalized = normalizeChildrenPositions(next);

            setData('children', normalized);

            if (normalized.length > data.children.length) {
                setData('sibling_count', Math.max(normalized.length, siblingCount));
            }

            return;
        }

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

    const setParentEntry = (
        key: 'father' | 'mother',
        field: 'name' | 'birth_year' | 'death_year',
        value: string,
    ) => {
        setData(key, { ...data[key], [field]: value });
    };

    const setParentMarga = (
        key: 'father' | 'mother',
        margaId: number | null,
    ) => {
        setData(key, { ...data[key], marga_id: margaId });
    };

    const setParentNewMarga = (key: 'father' | 'mother', name: string) => {
        setData(key, { ...data[key], new_marga: name });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && person?.id) {
            put(people.update(person.id).url);
        } else {
            post(people.store().url);
        }
    };

    const renderParentBlock = (
        key: 'father' | 'mother',
        label: string,
        birthPlace: string,
        deathPlace: string,
        showMarga = true,
    ) => (
        <div className="space-y-4 rounded-lg border border-tb-outline-variant p-4">
            <p className="text-sm font-medium text-tb-on-surface">{label}</p>
            <div className="grid gap-1.5">
                <Label htmlFor={`${key}-name`} className="text-tb-on-surface">
                    Nama {label}
                </Label>
                <NameCombobox
                    value={data[key].name}
                    onChange={(value) => setParentEntry(key, 'name', value)}
                    suggestions={nameSuggestions}
                    placeholder={`Nama ${label.toLowerCase()}`}
                    allowNa
                />
                <InputError message={errors[`${key}.name`]} />
            </div>
            {showMarga && (
                <div className="grid gap-1.5">
                    <Label className="text-tb-on-surface">Marga {label}</Label>
                    <MargaField
                        value={data[key].marga_id ?? null}
                        newMarga={data[key].new_marga ?? ''}
                        onValue={(value) => setParentMarga(key, value)}
                        onNewMarga={(value) => setParentNewMarga(key, value)}
                        margas={margas}
                        placeholder={`Marga ${label.toLowerCase()}`}
                        disabled={lockedMarga !== null}
                    />
                    <InputError message={errors[`${key}.marga_id`]} />
                </div>
            )}
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-1.5">
                    <Label className="text-tb-on-surface">Tahun Lahir</Label>
                    <Input
                        value={data[key].birth_year}
                        onChange={(e) =>
                            setParentEntry(key, 'birth_year', e.target.value)
                        }
                        placeholder={birthPlace}
                        maxLength={4}
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    <InputError message={errors[`${key}.birth_year`]} />
                </div>
                <div className="grid gap-1.5">
                    <Label className="text-tb-on-surface">Tahun Wafat</Label>
                    <Input
                        value={data[key].death_year}
                        onChange={(e) =>
                            setParentEntry(key, 'death_year', e.target.value)
                        }
                        placeholder={deathPlace}
                        maxLength={4}
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    <InputError message={errors[`${key}.death_year`]} />
                </div>
            </div>
        </div>
    );

    return (
        <>
            <div className="max-w-full min-w-0 overflow-x-auto">
            <form
                onSubmit={submit}
                className={cn(
                    'grid gap-6',
                    selectedChild
                        ? 'min-w-[1240px]'
                        : 'max-w-4xl',
                )}
            >
            <div
                className={cn(
                    'grid gap-6',
                    selectedChild
                        ? 'lg:grid-cols-[1fr_320px_320px]'
                        : 'lg:grid-cols-[1fr_320px]',
                )}
            >
                <div className="grid gap-6">
                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="font-display text-lg text-tb-on-surface">
                            Informasi Pribadi
                        </CardTitle>
                        <CardDescription>
                            Data dasar anggota yang sedang dicatat dalam jejak
                            keluarga.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="name"
                                className="text-tb-on-surface"
                            >
                                Nama Lengkap{' '}
                                <span className="text-red-600">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Mis. Ompu Sitorus"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.name} />
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
                                        setData('alias', e.target.value)
                                    }
                                    placeholder="Tuan Sorba Dibanua"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.alias} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    Jenis Kelamin
                                </Label>
                                <Select
                                    value={data.gender || ''}
                                    onValueChange={(value) =>
                                        setData('gender', value)
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
                                <InputError message={errors.gender} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    Marga
                                </Label>
                                <MargaField
                                    value={data.marga_id}
                                    newMarga={data.new_marga}
                                    onValue={(value) =>
                                        setData('marga_id', value)
                                    }
                                    onNewMarga={(value) =>
                                        setData('new_marga', value)
                                    }
                                    margas={margas}
                                    disabled={lockedMarga !== null}
                                />
                                <InputError message={errors.marga_id} />
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    Anak ke
                                </Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={data.birth_order ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'birth_order',
                                            Number(e.target.value),
                                        )
                                    }
                                    placeholder="2"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.birth_order} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label className="text-tb-on-surface">
                                    dari total bersaudara
                                </Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={data.sibling_count ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'sibling_count',
                                            Number(e.target.value),
                                        )
                                    }
                                    placeholder="5"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.sibling_count} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="nomor"
                                    className="text-tb-on-surface"
                                >
                                    Nomor Silsilah
                                </Label>
                                <Input
                                    id="nomor"
                                    value={data.nomor}
                                    onChange={(e) =>
                                        setData('nomor', e.target.value)
                                    }
                                    placeholder="otomatis (mis. 1.2.1)"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <p className="text-xs text-tb-on-surface-variant">
                                    Kosongkan untuk nomor otomatis berjenjang;
                                    isi untuk koreksi manual.
                                </p>
                                {nomorConflict && (
                                    <p className="text-xs font-medium text-amber-600 dark:text-amber-400">
                                        Nomor silsilah &ldquo;{nomorConflict.nomor}&rdquo; sudah dipakai oleh{' '}
                                        {nomorConflict.name}.
                                    </p>
                                )}
                                <InputError message={errors.nomor} />
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
                                        setData('birth_year', e.target.value)
                                    }
                                    placeholder="1920"
                                    maxLength={4}
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.birth_year} />
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
                                        setData('death_year', e.target.value)
                                    }
                                    placeholder="2001"
                                    maxLength={4}
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.death_year} />
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-lg border border-tb-outline-variant bg-tb-surface-container/50 px-3 py-2.5">
                            <Checkbox
                                id="is_leader"
                                checked={data.is_leader}
                                onCheckedChange={(checked) => setData('is_leader', checked === true)}
                                className="rounded border-tb-outline-variant text-tb-primary focus:ring-tb-primary/20"
                            />
                            <Label htmlFor="is_leader" className="cursor-pointer text-sm font-medium text-tb-on-surface">
                                Pemimpin Marga
                            </Label>
                            <p className="ml-auto text-xs text-tb-on-surface-variant">
                                Orang ini memimpin/melanjutkan marga dan masuk daftar silsilah.
                            </p>
                        </div>
                        <InputError message={errors.is_leader} />

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="image"
                                className="text-tb-on-surface"
                            >
                                URL Foto
                            </Label>
                            <Input
                                id="image"
                                type="url"
                                value={data.image}
                                onChange={(e) =>
                                    setData('image', e.target.value)
                                }
                                placeholder="https://..."
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.image} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="bio" className="text-tb-on-surface">
                                Biografi
                            </Label>
                            <textarea
                                id="bio"
                                value={data.bio}
                                onChange={(e) => setData('bio', e.target.value)}
                                rows={4}
                                placeholder="Cerita singkat tentang anggota ini..."
                                className="w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm shadow-xs outline-none focus:border-tb-primary focus:ring-tb-primary/20 focus-visible:ring-[3px]"
                            />
                            <InputError message={errors.bio} />
                        </div>
                    </CardContent>
                </Card>
                </div>
                {person ? (
                    <SilsilahListCard
                        lineage={person.lineage}
                        selfId={person.id}
                    />
                ) : (
                    <MargaLineageCard entries={margaLineage ?? []} />
                )}
                {selectedChild && selectedIndex != null && (
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                            <div>
                                <CardTitle className="font-display text-lg text-tb-on-surface">
                                    Detail Silsilah
                                </CardTitle>
                                <CardDescription>
                                    Keluarga dari baris nomor {selectedIndex + 1}.
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
                                <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-tb-on-surface-variant">
                                    Orang Tua
                                </p>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="rounded-lg border border-tb-outline-variant bg-tb-surface-container/60 px-3 py-2">
                                        <p className="text-[11px] font-medium text-tb-on-surface-variant">Ayah</p>
                                        <p className="text-sm font-semibold text-tb-on-surface">
                                            {displayRowName(data.father?.name)}
                                        </p>
                                        <p className="text-xs text-tb-on-surface-variant">
                                            {margaName(data.father?.marga_id ?? null, data.father?.new_marga)}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-tb-outline-variant bg-tb-surface-container/60 px-3 py-2">
                                        <p className="text-[11px] font-medium text-tb-on-surface-variant">Ibu</p>
                                        <p className="text-sm font-semibold text-tb-on-surface">
                                            {displayRowName(data.mother?.name)}
                                        </p>
                                        <p className="text-xs text-tb-on-surface-variant">
                                            {margaName(data.mother?.marga_id ?? null, data.mother?.new_marga)}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-tb-on-surface-variant">
                                    Orang Ini
                                </p>
                                <div className="grid gap-3 rounded-lg border border-tb-primary/50 bg-tb-primary/5 p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="min-w-0 flex-1 truncate font-display text-base font-semibold text-tb-on-surface">
                                            {displayRowName(selectedChild.name)}
                                        </p>
                                        <span className="shrink-0 rounded-full bg-tb-primary px-2 py-0.5 text-[10px] font-bold text-white">
                                            Anak ke {selectedIndex + 1}
                                        </span>
                                    </div>

                                    {isFocusRow && (
                                        <p className="text-[11px] font-medium italic text-tb-on-surface-variant">
                                            Ini Anda — nama, jenis kelamin, marga, dan nomor silsilah diisi lewat Informasi Pribadi.
                                        </p>
                                    )}

                                    <div className="grid gap-2.5">
                                        <div className="grid gap-1">
                                            <Label className="text-tb-on-surface">Nama</Label>
                                            {isFocusRow ? (
                                                <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm font-medium text-tb-on-surface">
                                                    {data.name || '—'}
                                                </div>
                                            ) : (
                                                <NameCombobox
                                                    value={selectedChild.name}
                                                    onChange={(value) => setChild(selectedIndex, 'name', value)}
                                                    suggestions={nameSuggestions}
                                                    placeholder="Nama"
                                                    allowNa
                                                />
                                            )}
                                        </div>

                                        <div className="grid grid-cols-2 gap-2.5">
                                            <div className="grid gap-1">
                                                <Label className="text-tb-on-surface">Jenis Kelamin</Label>
                                                {isFocusRow ? (
                                                    <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                        {data.gender || '—'}
                                                    </div>
                                                ) : (
                                                    <Select
                                                        value={selectedChild.gender || ''}
                                                        onValueChange={(value) => setChild(selectedIndex, 'gender', value)}
                                                    >
                                                        <SelectTrigger className="w-full border-tb-outline-variant bg-tb-surface-bright">
                                                            <SelectValue placeholder="L/P" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="L">L</SelectItem>
                                                            <SelectItem value="P">P</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                )}
                                            </div>
                                            <div className="grid gap-1">
                                                <Label className="text-tb-on-surface">Nomor Silsilah</Label>
                                                {isFocusRow ? (
                                                    <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                        {data.nomor || '—'}
                                                    </div>
                                                ) : (
                                                    <Input
                                                        value={selectedChild.nomor ?? ''}
                                                        onChange={(e) => setChild(selectedIndex, 'nomor', e.target.value)}
                                                        placeholder="otomatis"
                                                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                    />
                                                )}
                                            </div>
                                        </div>

                                        <div className="grid gap-1">
                                            <Label className="text-tb-on-surface">Marga</Label>
                                            {isFocusRow ? (
                                                <div className="flex min-h-9 items-center rounded-md border border-tb-outline-variant bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                    {margaName(data.marga_id, data.new_marga)}
                                                </div>
                                            ) : (
                                                <MargaField
                                                    value={selectedChild.marga_id ?? null}
                                                    newMarga={selectedChild.new_marga ?? ''}
                                                    onValue={(value) => setChildMarga(selectedIndex, value)}
                                                    onNewMarga={(value) => setChildNewMarga(selectedIndex, value)}
                                                    margas={margas}
                                                    placeholder="Marga"
                                                    disabled={lockedMarga !== null}
                                                />
                                            )}
                                        </div>

                                        <div className="grid grid-cols-2 gap-2.5">
                                            <div className="grid gap-1">
                                                <Label className="text-tb-on-surface">Pasangan</Label>
                                                <Input
                                                    value={selectedChild.spouse}
                                                    onChange={(e) => setChild(selectedIndex, 'spouse', e.target.value)}
                                                    placeholder="Nama pasangan"
                                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label className="text-tb-on-surface">Marga Pasangan</Label>
                                                <Input
                                                    value={selectedChild.spouse_marga}
                                                    onChange={(e) => setChild(selectedIndex, 'spouse_marga', e.target.value)}
                                                    placeholder="Marga pasangan"
                                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-tb-on-surface-variant">
                                    Saudara
                                </p>
                                {data.children.length === 0 ? (
                                    <p className="text-sm italic text-tb-on-surface-variant">Belum ada saudara.</p>
                                ) : (
                                    <div className="flex flex-wrap gap-1.5">
                                        {data.children.map((sibling, index) => (
                                            <button
                                                key={sibling.id ?? `sibling-${index}`}
                                                type="button"
                                                onClick={() => selectRow(index)}
                                                className={cn(
                                                    'max-w-full rounded-full border px-2.5 py-1 text-xs font-medium transition-colors',
                                                    index === selectedIndex
                                                        ? 'border-tb-primary bg-tb-primary text-white'
                                                        : 'border-tb-outline-variant bg-tb-surface-container/60 text-tb-on-surface hover:border-tb-primary',
                                                )}
                                            >
                                                <span className="truncate">
                                                    {isNameFilled(sibling.name ?? '')
                                                        ? sibling.name
                                                        : `Anak ke ${index + 1}`}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader>
                    <CardTitle className="font-display text-lg text-tb-on-surface">
                        Orang Tua
                    </CardTitle>
                    <CardDescription>
                        Ayah dan ibu dari anak-anak yang dicatat di bawah.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 lg:grid-cols-2">
                    {renderParentBlock('father', 'Ayah', '1950', '2020')}
                    {renderParentBlock('mother', 'Ibu', '1955', '2025', false)}
                </CardContent>
            </Card>

            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="font-display text-lg text-tb-on-surface">
                            Daftar Saudara
                        </CardTitle>
                        <CardDescription>
                            Total bersaudara: {siblingCount} — menambah total otomatis menambah baris di bawah.
                            Baris tanpa nomor silsilah tidak tampil di "List Silsilah" sampai disimpan (nomor kosong diisi otomatis).
                        </CardDescription>
                    </div>
                    <div className="text-xs text-tb-on-surface-variant">
                        Urut 01, 02, …
                    </div>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {data.children.map((child, index) => {
                        const focused =
                            person?.id != null
                                ? child.id === person.id
                                : index === birthOrder - 1;

                        return (
                            <div
                                key={child.id ?? `new-${index}`}
                                className={cn(
                                    'grid gap-3 rounded-lg border p-3',
                                    focused
                                        ? 'border-tb-primary/50 bg-tb-primary/5'
                                        : selectedIndex === index
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
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        {isGapRow(child) && (
                                            <span className="inline-flex w-fit items-center rounded-md bg-tb-surface-container px-1.5 py-0.5 text-[10px] font-semibold text-tb-on-surface-variant">
                                                N/A
                                            </span>
                                        )}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        {focused && (
                                            <span className="text-[11px] font-medium text-tb-primary">
                                                (Anda sedang diedit)
                                            </span>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => selectRow(index)}
                                            aria-label={`Lihat detail ${child.name || `baris ${index + 1}`}`}
                                            title="Lihat detail"
                                            className={cn(
                                                'inline-flex h-6 w-6 items-center justify-center rounded-full transition-colors',
                                                selectedIndex === index
                                                    ? 'bg-tb-primary text-white'
                                                    : 'text-tb-outline hover:bg-tb-surface-container hover:text-tb-on-surface',
                                            )}
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                        </button>
                                        {!child.id && (
                                            <button
                                                type="button"
                                                onClick={() => removeChild(index)}
                                                aria-label="Hapus baris"
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
                                                {data.name || '—'}
                                            </div>
                                        ) : (
                                            <NameCombobox
                                                value={child.name}
                                                onChange={(value) =>
                                                    setChild(
                                                        index,
                                                        'name',
                                                        value,
                                                    )
                                                }
                                                suggestions={nameSuggestions}
                                                placeholder={isGapRow(child) ? 'N/A' : 'Nama saudara'}
                                                allowNa
                                            />
                                        )}
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Jenis Kelamin</Label>
                                        {focused ? (
                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                {data.gender || '—'}
                                            </div>
                                        ) : (
                                            <Select
                                                value={child.gender || ''}
                                                onValueChange={(value) =>
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
                                                        L
                                                    </SelectItem>
                                                    <SelectItem value="P">
                                                        P
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label>Nomor Silsilah</Label>
                                        {focused ? (
                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                {data.nomor || '—'}
                                            </div>
                                        ) : (
                                            <Input
                                                value={child.nomor ?? ''}
                                                onChange={(e) =>
                                                    setChild(
                                                        index,
                                                        'nomor',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="otomatis (mis. 1.2.1)"
                                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                            />
                                        )}
                                        <p className="text-xs text-tb-on-surface-variant">
                                            Kosongkan untuk nomor otomatis.
                                        </p>
                                        <InputError message={errors[`children.${index}.nomor`]} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Marga</Label>
                                        {focused ? (
                                            <div className="flex min-h-9 items-center rounded-md border border-tb-primary/40 bg-tb-surface-container px-3 text-sm text-tb-on-surface">
                                                {margas.find(
                                                    (marga) =>
                                                        marga.id ===
                                                        data.marga_id,
                                                )?.name ??
                                                    (data.new_marga || '—')}
                                            </div>
                                        ) : (
                                            <MargaField
                                                value={child.marga_id ?? null}
                                                newMarga={child.new_marga ?? ''}
                                                onValue={(value) =>
                                                    setChildMarga(index, value)
                                                }
                                                onNewMarga={(value) =>
                                                    setChildNewMarga(
                                                        index,
                                                        value,
                                                    )
                                                }
                                                margas={margas}
                                                placeholder="Marga saudara"
                                                disabled={lockedMarga !== null}
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
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Nama pasangan"
                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Marga/Suku Lain</Label>
                                        <Input
                                            value={child.spouse_marga}
                                            onChange={(e) =>
                                                setChild(
                                                    index,
                                                    'spouse_marga',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Marga pasangan"
                                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                        />
                                    </div>
                                </div>
                            </div>
                        );
                    })}

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
            </form>
            </div>

            <Dialog open={reductionConfirm !== null} onOpenChange={(open) => !open && cancelReduction()}>
                <DialogContent className="border-tb-outline-variant bg-tb-surface-bright sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-tb-on-surface">
                            Hapus baris saudara?
                        </DialogTitle>
                        <DialogDescription>
                            Mengurangi total bersaudara ke{' '}
                            <strong>{reductionConfirm?.to}</strong> akan menghapus{' '}
                            <strong>{reductionConfirm?.filledNew}</strong> baris yang baru
                            diisi. Baris tersimpan tetap dipertahankan. Tindakan ini tidak
                            dapat dibatalkan.
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
        </>
    );
}
