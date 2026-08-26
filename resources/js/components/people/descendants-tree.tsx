import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { NodeCard } from '@/components/people/node-card';
import type { TreeNode } from '@/components/people/node-card';
import type { TaromboPerson } from '@/data/tarombo-tree';
import people from '@/routes/people';

type Props = {
    people: TaromboPerson[];
    centerId: string;
    onSelect?: (id: string) => void;
    highlightId?: string | null;
    editNodes?: boolean;
    alternativeTrees?: DescendantsAlternativeTree[];
    hideRoot?: boolean;
    nodeIdPrefix?: string;
};

export type DescendantsAlternativeTree = {
    id: number;
    name: string;
    rootId: string;
    people: TaromboPerson[];
};

function toNode(person: TaromboPerson, displayNumber?: number): TreeNode {
    return {
        id: person.id,
        name: person.name,
        alias: person.alias,
        marga: person.marga,
        birthYear: person.birthYear,
        birthOrder: person.birthOrder,
        displayNumber,
        image: person.image,
        pending: person.pending,
    };
}

function TreeBranch({
    person,
    childrenOf,
    centerId,
    highlightId,
    numberById,
    collapsed,
    onToggle,
    onSelect,
    editNodes,
    alternativeTrees,
    nodeIdPrefix,
}: {
    person: TaromboPerson;
    childrenOf: Map<string, TaromboPerson[]>;
    centerId: string;
    highlightId?: string | null;
    numberById: Map<string, number>;
    collapsed: Set<string>;
    onToggle: (id: string) => void;
    onSelect?: (id: string) => void;
    editNodes?: boolean;
    alternativeTrees: DescendantsAlternativeTree[];
    nodeIdPrefix: string;
}) {
    const [activeAlternativeId, setActiveAlternativeId] = useState<
        number | null
    >(null);
    const children = childrenOf.get(person.id) ?? [];
    const personAlternatives = alternativeTrees.filter(
        (tree) => tree.rootId === person.id,
    );
    const activeAlternative = personAlternatives.find(
        (tree) => tree.id === activeAlternativeId,
    );
    const alternativePanelId = `${nodeIdPrefix}-${person.id}-alternatives`;
    const isCenter = person.id === centerId;
    const isHighlighted = person.id === highlightId;
    const isCollapsed = collapsed.has(person.id);
    const card = (
        <NodeCard
            node={toNode(person, numberById.get(person.id))}
            highlighted={isCenter || isHighlighted}
            badge={isCenter ? undefined : `Anak ke ${person.birthOrder ?? '?'}`}
        />
    );

    return (
        <li>
            {editNodes ? (
                <Link
                    id={`${nodeIdPrefix}-${person.id}`}
                    href={people.edit(Number(person.id))}
                    aria-label={`Ubah ${person.name}`}
                    className="inline-block cursor-pointer rounded-lg transition-transform duration-200 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8934A]"
                >
                    {card}
                </Link>
            ) : (
                <button
                    id={`${nodeIdPrefix}-${person.id}`}
                    type="button"
                    onClick={() => onSelect?.(person.id)}
                    className="cursor-pointer rounded-lg transition-transform duration-200 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8934A]"
                >
                    {card}
                </button>
            )}
            {(children.length > 0 || personAlternatives.length > 0) && (
                <div className="mt-1 flex items-center gap-1.5">
                    {children.length > 0 && !activeAlternative && (
                        <button
                            type="button"
                            onClick={() => onToggle(person.id)}
                            aria-label={
                                isCollapsed
                                    ? 'Bentangkan cabang'
                                    : 'Ciutkan cabang'
                            }
                            className="flex size-5 items-center justify-center rounded-full border border-[#a79e8c]/60 bg-white text-[#5B6A61] transition-colors hover:bg-[#EFE2C9]"
                        >
                            {isCollapsed ? (
                                <ChevronRight className="size-3.5" />
                            ) : (
                                <ChevronDown className="size-3.5" />
                            )}
                        </button>
                    )}
                    {personAlternatives.length > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                setActiveAlternativeId((current) =>
                                    current === null
                                        ? personAlternatives[0].id
                                        : null,
                                )
                            }
                            aria-expanded={activeAlternative !== undefined}
                            aria-controls={alternativePanelId}
                            aria-label={
                                activeAlternative
                                    ? 'Kembali ke keturunan utama'
                                    : 'Buka keturunan alternatif'
                            }
                            title={
                                activeAlternative
                                    ? 'Kembali ke versi utama'
                                    : 'Buka versi alternatif'
                            }
                            className="hover:text-tb-on-primary inline-flex h-6 min-w-6 items-center justify-center gap-0.5 rounded-full border border-dashed border-tb-primary bg-tb-primary/10 px-1.5 text-[10px] font-black text-tb-primary transition-colors hover:bg-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary/40 focus-visible:outline-none"
                        >
                            V
                            {personAlternatives.length > 1 && (
                                <span>{personAlternatives.length}</span>
                            )}
                        </button>
                    )}
                </div>
            )}
            {!activeAlternative && !isCollapsed && children.length > 0 && (
                <ul>
                    {children.map((child) => (
                        <TreeBranch
                            key={child.id}
                            person={child}
                            childrenOf={childrenOf}
                            centerId={centerId}
                            highlightId={highlightId}
                            numberById={numberById}
                            collapsed={collapsed}
                            onToggle={onToggle}
                            onSelect={onSelect}
                            editNodes={editNodes}
                            alternativeTrees={alternativeTrees}
                            nodeIdPrefix={nodeIdPrefix}
                        />
                    ))}
                </ul>
            )}
            {activeAlternative && (
                <div className="relative mt-3 min-w-max pt-5 before:absolute before:top-0 before:left-1/2 before:h-5 before:border-l before:border-dashed before:border-tb-primary">
                    <div
                        id={alternativePanelId}
                        className="rounded-xl border border-dashed border-tb-primary/60 bg-tb-primary/5 px-3 pt-3 shadow-sm"
                    >
                        <p className="text-[10px] font-black tracking-[0.14em] text-tb-primary uppercase">
                            Versi Alternatif
                        </p>
                        <p className="mt-1 max-w-sm truncate text-xs font-semibold text-tb-on-surface">
                            {activeAlternative.name}
                        </p>
                        <div className="mt-2 mb-3 flex max-w-sm flex-wrap items-center justify-center gap-1.5">
                            <button
                                type="button"
                                onClick={() => setActiveAlternativeId(null)}
                                className="rounded-full border border-tb-outline-variant bg-tb-surface-bright px-2.5 py-1 text-[10px] font-semibold text-tb-on-surface-variant transition-colors hover:border-tb-primary hover:text-tb-primary"
                            >
                                V Utama
                            </button>
                            {personAlternatives.map((tree, index) => (
                                <button
                                    key={tree.id}
                                    type="button"
                                    onClick={() =>
                                        setActiveAlternativeId(tree.id)
                                    }
                                    aria-pressed={
                                        tree.id === activeAlternative.id
                                    }
                                    className={
                                        tree.id === activeAlternative.id
                                            ? 'text-tb-on-primary rounded-full bg-tb-primary px-2.5 py-1 text-[10px] font-bold'
                                            : 'rounded-full border border-tb-outline-variant bg-tb-surface-bright px-2.5 py-1 text-[10px] font-semibold text-tb-on-surface-variant transition-colors hover:border-tb-primary hover:text-tb-primary'
                                    }
                                >
                                    V{index + 2}
                                </button>
                            ))}
                        </div>
                        <DescendantsTree
                            key={activeAlternative.id}
                            people={activeAlternative.people}
                            centerId={activeAlternative.rootId}
                            onSelect={onSelect}
                            highlightId={highlightId}
                            editNodes={editNodes}
                            hideRoot
                            nodeIdPrefix={`${nodeIdPrefix}-alternative-${activeAlternative.id}`}
                        />
                    </div>
                </div>
            )}
        </li>
    );
}

export function DescendantsTree({
    people,
    centerId,
    onSelect,
    highlightId,
    editNodes = false,
    alternativeTrees = [],
    hideRoot = false,
    nodeIdPrefix = 'tree-node',
}: Props) {
    const childrenOf = useMemo(() => {
        const map = new Map<string, TaromboPerson[]>();

        for (const person of people) {
            if (person.parentId) {
                const siblings = map.get(person.parentId) ?? [];
                siblings.push(person);
                map.set(person.parentId, siblings);
            }
        }

        for (const siblings of map.values()) {
            siblings.sort((a, b) => (a.birthOrder ?? 0) - (b.birthOrder ?? 0));
        }

        return map;
    }, [people]);

    const center = people.find((person) => person.id === centerId) ?? people[0];
    const numberById = useMemo(() => {
        const numbers = new Map<string, number>();

        if (!center) {
            return numbers;
        }

        const visited = new Set<string>();

        const walk = (id: string, depth: number) => {
            if (visited.has(id)) {
                return;
            }

            visited.add(id);
            numbers.set(id, depth);

            for (const child of childrenOf.get(id) ?? []) {
                walk(child.id, depth + 1);
            }
        };

        walk(center.id, 1);

        return numbers;
    }, [center, childrenOf]);

    const [collapsed, setCollapsed] = useState<Set<string>>(() => {
        const initial = new Set<string>();
        const visited = new Set<string>();

        const walk = (id: string, depth: number) => {
            if (visited.has(id)) {
                return;
            }

            visited.add(id);
            const children = childrenOf.get(id) ?? [];

            if (depth >= 3 && children.length > 0) {
                initial.add(id);
            }

            for (const child of children) {
                walk(child.id, depth + 1);
            }
        };

        if (center) {
            walk(center.id, 1);
        }

        return initial;
    });

    if (!center) {
        return null;
    }

    const visibleRoots = hideRoot
        ? (childrenOf.get(center.id) ?? [])
        : [center];

    const handleToggle = (id: string) => {
        setCollapsed((prev) => {
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
        <div className="pb-4">
            {visibleRoots.length > 0 ? (
                <ul className="tb-tree">
                    {visibleRoots.map((root) => (
                        <TreeBranch
                            key={root.id}
                            person={root}
                            childrenOf={childrenOf}
                            centerId={centerId}
                            highlightId={highlightId}
                            numberById={numberById}
                            collapsed={collapsed}
                            onToggle={handleToggle}
                            onSelect={onSelect}
                            editNodes={editNodes}
                            alternativeTrees={alternativeTrees}
                            nodeIdPrefix={nodeIdPrefix}
                        />
                    ))}
                </ul>
            ) : (
                <p className="px-3 py-2 text-xs text-tb-on-surface-variant italic">
                    Versi ini belum memiliki keturunan berbeda.
                </p>
            )}
        </div>
    );
}
