import { ChevronDown, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { NodeCard } from '@/components/people/node-card';
import type { TreeNode } from '@/components/people/node-card';
import type { TaromboPerson } from '@/data/tarombo-tree';

type Props = {
    people: TaromboPerson[];
    centerId: string;
    onSelect?: (id: string) => void;
    highlightId?: string | null;
};

function toNode(person: TaromboPerson): TreeNode {
    return {
        id: person.id,
        name: person.name,
        alias: person.alias,
        marga: person.marga,
        birthYear: person.birthYear,
        birthOrder: person.birthOrder,
        chain: person.chain,
        image: person.image,
        pending: person.pending,
    };
}

function TreeBranch({
    person,
    childrenOf,
    centerId,
    highlightId,
    collapsed,
    onToggle,
    onSelect,
}: {
    person: TaromboPerson;
    childrenOf: Map<string, TaromboPerson[]>;
    centerId: string;
    highlightId?: string | null;
    collapsed: Set<string>;
    onToggle: (id: string) => void;
    onSelect?: (id: string) => void;
}) {
    const children = childrenOf.get(person.id) ?? [];
    const isCenter = person.id === centerId;
    const isHighlighted = person.id === highlightId;
    const isCollapsed = collapsed.has(person.id);

    return (
        <li>
            <button
                id={`tree-node-${person.id}`}
                type="button"
                onClick={() => onSelect?.(person.id)}
                className="cursor-pointer rounded-lg transition-transform duration-200 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8934A]"
            >
                <NodeCard
                    node={toNode(person)}
                    highlighted={isCenter || isHighlighted}
                    badge={
                        isCenter
                            ? undefined
                            : `Anak ke ${person.birthOrder ?? '?'}`
                    }
                />
            </button>
            {children.length > 0 && (
                <button
                    type="button"
                    onClick={() => onToggle(person.id)}
                    aria-label={
                        isCollapsed ? 'Bentangkan cabang' : 'Ciutkan cabang'
                    }
                    className="mt-1 flex h-5 w-5 items-center justify-center rounded-full border border-[#a79e8c]/60 bg-white text-[#5B6A61] transition-colors hover:bg-[#EFE2C9]"
                >
                    {isCollapsed ? (
                        <ChevronRight className="size-3.5" />
                    ) : (
                        <ChevronDown className="size-3.5" />
                    )}
                </button>
            )}
            {!isCollapsed && children.length > 0 && (
                <ul>
                    {children.map((child) => (
                        <TreeBranch
                            key={child.id}
                            person={child}
                            childrenOf={childrenOf}
                            centerId={centerId}
                            highlightId={highlightId}
                            collapsed={collapsed}
                            onToggle={onToggle}
                            onSelect={onSelect}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

export function DescendantsTree({
    people,
    centerId,
    onSelect,
    highlightId,
}: Props) {
<<<<<<< HEAD
=======
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

>>>>>>> origin/main
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
            <ul className="tb-tree">
                <TreeBranch
                    person={center}
                    childrenOf={childrenOf}
                    centerId={centerId}
                    highlightId={highlightId}
                    collapsed={collapsed}
                    onToggle={handleToggle}
                    onSelect={onSelect}
                />
            </ul>
        </div>
    );
}
