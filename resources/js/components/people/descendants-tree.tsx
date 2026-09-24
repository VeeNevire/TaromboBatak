import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { NodeCard } from '@/components/people/node-card';
import type { TreeNode } from '@/components/people/node-card';
import { PersonSummaryDialog } from '@/components/people/person-summary-dialog';
import type { TaromboPerson } from '@/data/tarombo-tree';
import people from '@/routes/people';

type Props = {
    people: TaromboPerson[];
    centerId: string;
    rootId?: string;
    onSelect?: (id: string) => void;
    highlightId?: string | null;
    editNodes?: boolean;
    selectOnClick?: boolean;
    alternativeTrees?: DescendantsAlternativeTree[];
    hideRoot?: boolean;
    nodeIdPrefix?: string;
    lineagePath?: readonly string[];
    showProfileOnName?: boolean;
    readOnly?: boolean;
    markFemaleLineage?: boolean;
    collapseDepth?: number;
    compact?: boolean;
    detachedPeople?: TaromboPerson[];
    currentUserId?: number;
    versionTreeId?: number | null;
    showNodeAvatar?: boolean;
    showSpouseNames?: boolean;
    allowBranchEntry?: boolean;
    compactTerminalBranches?: boolean;
    packCollapsed?: boolean;
    scrollToLineageEnd?: boolean;
};

type LineageLine = {
    id: string;
    path: string;
};

const EMPTY_LINEAGE_PATH: readonly string[] = [];
const EMPTY_LEAF_SIBLINGS: TaromboPerson[] = [];

// Childless siblings sit beside the card rather than past the whole subtree.
// Their cards are fixed width, so the group's footprint and the length of the
// connector bar reaching them are exact constants — a phantom spacer of the
// same footprint balances the group on the other side, keeping the card centred
// on the li so every existing connector still lines up.
const CLUSTER_CARD_WIDTH = { compact: 64, normal: 80 } as const;
const CLUSTER_GAP = 6;

/**
 * Whether a person has descendants at all — not merely whether any are drawn.
 * Nodes at the render depth limit have no rendered children but do carry
 * childrenNames from the database, and must not be treated as childless.
 */
function hasDescendants(
    person: TaromboPerson,
    childrenOf: Map<string, TaromboPerson[]>,
    alternativeTrees: DescendantsAlternativeTree[],
): boolean {
    return (
        (childrenOf.get(person.id)?.length ?? 0) > 0 ||
        (person.childrenNames?.length ?? 0) > 0 ||
        alternativeTrees.some((tree) => tree.rootId === person.id)
    );
}

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
        birthOrder: person.parentId ? person.birthOrder : undefined,
        displayNumber,
        image: person.image,
        pending: person.pending,
        claimed: (person.claimedAccounts?.length ?? 0) > 0,
        spouses: person.spouses?.map((spouse) => spouse.name),
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
    selectOnClick,
    showProfileOnName,
    readOnly,
    onOpenProfile,
    lineageIds,
    femaleLineage,
    markFemaleLineage,
    collapseDepth,
    compact,
    showNodeAvatar,
    showSpouseNames,
    compactTerminalBranches,
    alternativeTrees,
    nodeIdPrefix,
    leafSiblings = EMPTY_LEAF_SIBLINGS,
    leafSiblingsBefore = EMPTY_LEAF_SIBLINGS,
    leafSiblingsFemaleLineage = false,
    packCollapsed = false,
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
    selectOnClick?: boolean;
    showProfileOnName?: boolean;
    readOnly?: boolean;
    onOpenProfile: (person: TaromboPerson) => void;
    alternativeTrees: DescendantsAlternativeTree[];
    nodeIdPrefix: string;
    lineageIds: ReadonlySet<string>;
    femaleLineage: boolean;
    markFemaleLineage: boolean;
    collapseDepth?: number;
    compact?: boolean;
    showNodeAvatar?: boolean;
    showSpouseNames?: boolean;
    compactTerminalBranches?: boolean;
    /** Packed siblings born after this one, drawn to the right of its card. */
    leafSiblings?: TaromboPerson[];
    /** Packed siblings born before this one, drawn to the left of its card. */
    leafSiblingsBefore?: TaromboPerson[];
    leafSiblingsFemaleLineage?: boolean;
    packCollapsed?: boolean;
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
    // A searched lineage must remain visible even when this branch was
    // previously collapsed. The target itself is included so every ancestor
    // required to reach it is rendered for the red path overlay.
    const isCollapsed = collapsed.has(person.id) && !lineageIds.has(person.id);
    const isTerminalBranch = !hasDescendants(
        person,
        childrenOf,
        alternativeTrees,
    );
    const useCompactCard =
        compact || (compactTerminalBranches && isTerminalBranch);
    // Childless siblings sit beside this card instead of each taking their
    // own column past the whole subtree.
    // A branch (has descendants) always keeps its own column and width,
    // collapsed or not — packing it beside the parent while collapsed made
    // its card jump from the narrow leaf width to the full branch width the
    // instant it was expanded, which read as the whole row widening even
    // though only one child row was added underneath.
    const isPackable = (child: TaromboPerson) =>
        !hasDescendants(child, childrenOf, alternativeTrees);
    const branchChildren = children.filter((child) => !isPackable(child));
    const hasPackedChildren =
        branchChildren.length > 0 && branchChildren.length < children.length;
    const renderedChildren = hasPackedChildren ? branchChildren : children;
    // Packed children keep their birth order: those born before the first
    // branch sit on its left, every other one on the right of the branch it
    // follows. Reading the row left to right is then still 1-2-3.
    const leavesBefore = new Map<string, TaromboPerson[]>();
    const leavesAfter = new Map<string, TaromboPerson[]>();

    if (hasPackedChildren) {
        let currentBranch: TaromboPerson | null = null;
        const beforeFirstBranch: TaromboPerson[] = [];

        for (const child of children) {
            if (!isPackable(child)) {
                if (currentBranch === null) {
                    leavesBefore.set(child.id, beforeFirstBranch);
                }

                currentBranch = child;
            } else if (currentBranch === null) {
                beforeFirstBranch.push(child);
            } else {
                leavesAfter.set(currentBranch.id, [
                    ...(leavesAfter.get(currentBranch.id) ?? []),
                    child,
                ]);
            }
        }
    }

    // Each leaf's card width, mirroring the mode its own TreeBranch renders:
    // terminal leaves go compact when this tree compacts terminal branches,
    // folded ones keep the regular narrow card.
    const leafCardWidth = (leaf: TaromboPerson) =>
        compact ||
        (compactTerminalBranches &&
            !hasDescendants(leaf, childrenOf, alternativeTrees))
            ? CLUSTER_CARD_WIDTH.compact
            : CLUSTER_CARD_WIDTH.normal;
    const groupWidth = (leaves: TaromboPerson[]) =>
        leaves.length === 0
            ? 0
            : leaves.reduce((sum, leaf) => sum + leafCardWidth(leaf), 0) +
              (leaves.length - 1) * CLUSTER_GAP;
    // Card centre → centre of the farthest leaf on one side: the span that
    // side's sibling bar has to cover.
    const barLength = (leaves: TaromboPerson[], farthest: TaromboPerson) =>
        CLUSTER_GAP +
        groupWidth(leaves) -
        leafCardWidth(farthest) +
        leafCardWidth(farthest) / 2;
    const hasLeafCluster =
        leafSiblings.length > 0 || leafSiblingsBefore.length > 0;
    // Both sides are the same width, so the card stays centred on the li and
    // every existing connector still lines up.
    const leafSideWidth = Math.max(
        groupWidth(leafSiblings),
        groupWidth(leafSiblingsBefore),
    );
    const renderLeaf = (leaf: TaromboPerson) => (
        <TreeBranch
            key={leaf.id}
            person={leaf}
            childrenOf={childrenOf}
            centerId={centerId}
            highlightId={highlightId}
            numberById={numberById}
            collapsed={collapsed}
            onToggle={onToggle}
            onSelect={onSelect}
            editNodes={editNodes}
            selectOnClick={selectOnClick}
            showProfileOnName={showProfileOnName}
            readOnly={readOnly}
            onOpenProfile={onOpenProfile}
            alternativeTrees={alternativeTrees}
            nodeIdPrefix={nodeIdPrefix}
            lineageIds={lineageIds}
            femaleLineage={
                leafSiblingsFemaleLineage || leaf.gender?.toUpperCase() === 'P'
            }
            markFemaleLineage={markFemaleLineage}
            collapseDepth={collapseDepth}
            compact={compact}
            showNodeAvatar={showNodeAvatar}
            showSpouseNames={showSpouseNames}
            compactTerminalBranches={compactTerminalBranches}
            packCollapsed={packCollapsed}
        />
    );
    const card = (
        <NodeCard
            node={toNode(person, numberById.get(person.id))}
            compact={useCompactCard}
            narrow={isTerminalBranch}
            highlighted={isCenter || isHighlighted}
            onAvatarClick={
                showProfileOnName ? () => onSelect?.(person.id) : undefined
            }
            onNameClick={
                showProfileOnName ? () => onOpenProfile(person) : undefined
            }
            dashed={markFemaleLineage && femaleLineage}
            showAvatar={showNodeAvatar}
            showSpouseNames={showSpouseNames}
        />
    );

    return (
        <li
            data-female-lineage={markFemaleLineage && femaleLineage}
            data-terminal-branch={isTerminalBranch}
            data-leaf-cluster={hasLeafCluster}
            data-leaf-after={leafSiblings.length > 0}
            data-leaf-before={leafSiblingsBefore.length > 0}
            style={
                hasLeafCluster
                    ? ({
                          '--tb-leaf-width': `${leafSideWidth}px`,
                          '--tb-leaf-bar': `${
                              leafSiblings.length > 0
                                  ? barLength(leafSiblings, leafSiblings[leafSiblings.length - 1])
                                  : 0
                          }px`,
                          '--tb-leaf-bar-before': `${
                              leafSiblingsBefore.length > 0
                                  ? barLength(leafSiblingsBefore, leafSiblingsBefore[0])
                                  : 0
                          }px`,
                      } as React.CSSProperties)
                    : undefined
            }
        >
            <div className="tb-node-row">
            {hasLeafCluster && (
                <ul className="tb-leaf-cluster tb-leaf-cluster--before">
                    {leafSiblingsBefore.map(renderLeaf)}
                </ul>
            )}
            <div className="tb-node-main">
            {readOnly ? (
                <div
                    id={`${nodeIdPrefix}-${person.id}`}
                    className="relative z-20 inline-block rounded-lg"
                >
                    {card}
                </div>
            ) : showProfileOnName ? (
                <div
                    id={`${nodeIdPrefix}-${person.id}`}
                    className="relative z-20 inline-block rounded-lg"
                >
                    {card}
                </div>
            ) : editNodes && !selectOnClick ? (
                <Link
                    id={`${nodeIdPrefix}-${person.id}`}
                    href={people.edit(Number(person.id))}
                    aria-label={`Ubah ${person.name}`}
                    className="relative z-20 inline-block cursor-pointer rounded-lg transition-transform duration-200 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8934A]"
                >
                    {card}
                </Link>
            ) : (
                <button
                    id={`${nodeIdPrefix}-${person.id}`}
                    type="button"
                    onClick={() => onSelect?.(person.id)}
                    className="relative z-20 cursor-pointer rounded-lg transition-transform duration-200 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8934A]"
                >
                    {card}
                </button>
            )}
            {(renderedChildren.length > 0 || personAlternatives.length > 0) && (
                <div
                    className={
                        compact
                            ? 'mt-0.5 flex items-center gap-1'
                            : 'mt-1 flex items-center gap-1.5'
                    }
                >
                    {renderedChildren.length > 0 && !activeAlternative && (
                        <button
                            type="button"
                            onClick={() => onToggle(person.id)}
                            aria-label={
                                isCollapsed
                                    ? 'Bentangkan cabang'
                                    : 'Ciutkan cabang'
                            }
                            className={
                                compact
                                    ? 'flex size-4 items-center justify-center rounded-full border border-[#a79e8c]/60 bg-white text-[#5B6A61] transition-colors hover:bg-[#EFE2C9]'
                                    : 'flex size-5 items-center justify-center rounded-full border border-[#a79e8c]/60 bg-white text-[#5B6A61] transition-colors hover:bg-[#EFE2C9]'
                            }
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
                            className={
                                compact
                                    ? 'hover:text-tb-on-primary inline-flex h-5 min-w-5 items-center justify-center gap-0.5 rounded-full border border-dashed border-tb-primary bg-tb-primary/10 px-1 text-[9px] font-bold text-tb-primary transition-colors hover:bg-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary/40 focus-visible:outline-none'
                                    : 'hover:text-tb-on-primary inline-flex h-6 min-w-6 items-center justify-center gap-0.5 rounded-full border border-dashed border-tb-primary bg-tb-primary/10 px-1.5 text-[10px] font-bold text-tb-primary transition-colors hover:bg-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary/40 focus-visible:outline-none'
                            }
                        >
                            <ChevronDown className="size-3.5" />
                            {personAlternatives.length > 1 && (
                                <span>{personAlternatives.length}</span>
                            )}
                        </button>
                    )}
                </div>
            )}
            </div>
            {hasLeafCluster && (
                <ul className="tb-leaf-cluster">
                    {leafSiblings.map(renderLeaf)}
                </ul>
            )}
            </div>
            {!activeAlternative && !isCollapsed && renderedChildren.length > 0 && (
                <ul>
                    {renderedChildren.map((child) => (
                        <TreeBranch
                            key={child.id}
                            person={child}
                            leafSiblings={
                                leavesAfter.get(child.id) ?? EMPTY_LEAF_SIBLINGS
                            }
                            leafSiblingsBefore={
                                leavesBefore.get(child.id) ?? EMPTY_LEAF_SIBLINGS
                            }
                            leafSiblingsFemaleLineage={femaleLineage}
                            childrenOf={childrenOf}
                            centerId={centerId}
                            highlightId={highlightId}
                            numberById={numberById}
                            collapsed={collapsed}
                            onToggle={onToggle}
                            onSelect={onSelect}
                            editNodes={editNodes}
                            selectOnClick={selectOnClick}
                            showProfileOnName={showProfileOnName}
                            readOnly={readOnly}
                            onOpenProfile={onOpenProfile}
                            alternativeTrees={alternativeTrees}
                            nodeIdPrefix={nodeIdPrefix}
                            lineageIds={lineageIds}
                            femaleLineage={
                                femaleLineage ||
                                child.gender?.toUpperCase() === 'P'
                            }
                            markFemaleLineage={markFemaleLineage}
                            collapseDepth={collapseDepth}
                            compact={compact}
                            showNodeAvatar={showNodeAvatar}
                            showSpouseNames={showSpouseNames}
                            compactTerminalBranches={compactTerminalBranches}
                            packCollapsed={packCollapsed}
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
                            selectOnClick={selectOnClick}
                            showProfileOnName={showProfileOnName}
                            hideRoot
                            nodeIdPrefix={`${nodeIdPrefix}-alternative-${activeAlternative.id}`}
                            lineagePath={[]}
                            markFemaleLineage={markFemaleLineage}
                            showNodeAvatar={showNodeAvatar}
                            showSpouseNames={showSpouseNames}
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
    rootId,
    onSelect,
    highlightId,
    editNodes = false,
    selectOnClick = false,
    showProfileOnName = false,
    readOnly = false,
    alternativeTrees = [],
    hideRoot = false,
    nodeIdPrefix = 'tree-node',
    lineagePath = EMPTY_LINEAGE_PATH,
    markFemaleLineage = false,
    collapseDepth,
    compact = false,
    detachedPeople = [],
    currentUserId,
    versionTreeId,
    showNodeAvatar = true,
    showSpouseNames = false,
    allowBranchEntry = false,
    compactTerminalBranches = false,
    packCollapsed = false,
    scrollToLineageEnd = false,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [profilePerson, setProfilePerson] = useState<TaromboPerson | null>(
        null,
    );
    const [lineageLines, setLineageLines] = useState<LineageLine[]>([]);
    const [overlaySize, setOverlaySize] = useState({ width: 0, height: 0 });
    const lineageIds = useMemo(() => new Set(lineagePath), [lineagePath]);
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
    const root = people.find((person) => person.id === rootId) ?? center;
    const numberById = useMemo(() => {
        const numbers = new Map<string, number>();

        if (!root) {
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

        walk(root.id, 1);

        return numbers;
    }, [childrenOf, root]);

    const [collapsed, setCollapsed] = useState<Set<string>>(() => {
        const initial = new Set<string>();
        const visited = new Set<string>();

        const walk = (id: string, depth: number) => {
            if (visited.has(id)) {
                return;
            }

            visited.add(id);
            const children = childrenOf.get(id) ?? [];

            if (
                depth >= (collapseDepth ?? 3) &&
                children.length > 0 &&
                !lineageIds.has(id)
            ) {
                initial.add(id);
            }

            for (const child of children) {
                walk(child.id, depth + 1);
            }
        };

        if (root) {
            walk(root.id, 1);
        }

        return initial;
    });

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

    useLayoutEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const updateLines = () => {
            const containerRect = container.getBoundingClientRect();
            const scaleX = container.offsetWidth
                ? containerRect.width / container.offsetWidth
                : 1;
            const scaleY = container.offsetHeight
                ? containerRect.height / container.offsetHeight
                : 1;
            const lines: LineageLine[] = [];

            for (let index = 0; index < lineagePath.length - 1; index += 1) {
                const parentId = lineagePath[index];
                const childId = lineagePath[index + 1];
                const parent = document.getElementById(
                    `${nodeIdPrefix}-${parentId}`,
                );
                const child = document.getElementById(
                    `${nodeIdPrefix}-${childId}`,
                );

                if (
                    !parent ||
                    !child ||
                    !container.contains(parent) ||
                    !container.contains(child)
                ) {
                    continue;
                }

                const parentRect = parent.getBoundingClientRect();
                const childRect = child.getBoundingClientRect();
                const startX =
                    (parentRect.left +
                        parentRect.width / 2 -
                        containerRect.left) /
                    scaleX;
                const startY = (parentRect.bottom - containerRect.top) / scaleY;
                const endX =
                    (childRect.left +
                        childRect.width / 2 -
                        containerRect.left) /
                    scaleX;
                const endY = (childRect.top - containerRect.top) / scaleY;
                const middleY = startY + (endY - startY) / 2;

                lines.push({
                    id: `${parentId}-${childId}`,
                    path: `M ${startX} ${startY} V ${middleY} H ${endX} V ${endY}`,
                });
            }

            setOverlaySize({
                width: container.scrollWidth,
                height: container.scrollHeight,
            });
            setLineageLines(lines);
        };

        const frame = window.requestAnimationFrame(updateLines);
        const observer = new ResizeObserver(updateLines);
        observer.observe(container);
        window.addEventListener('resize', updateLines);

        return () => {
            window.cancelAnimationFrame(frame);
            observer.disconnect();
            window.removeEventListener('resize', updateLines);
        };
    }, [collapsed, lineagePath, nodeIdPrefix, packCollapsed, people]);

    const focusTargetId =
        scrollToLineageEnd && lineagePath.length > 1
            ? lineagePath[lineagePath.length - 1]
            : null;

    useEffect(() => {
        if (!focusTargetId) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            document
                .getElementById(`${nodeIdPrefix}-${focusTargetId}`)
                ?.scrollIntoView({
                    block: 'center',
                    inline: 'center',
                    behavior: 'smooth',
                });
        });

        return () => window.cancelAnimationFrame(frame);
    }, [focusTargetId, nodeIdPrefix]);

    if (!center) {
        return null;
    }

    const visibleRoots = hideRoot ? (childrenOf.get(root.id) ?? []) : [root];
    const detachedRoots = detachedPeople.filter(
        (person) => person.id !== center.id,
    );
    const detachedLeafRoots = detachedRoots.filter(
        (person) => (childrenOf.get(person.id) ?? []).length === 0,
    );
    const detachedBranchRoots = detachedRoots.filter(
        (person) => (childrenOf.get(person.id) ?? []).length > 0,
    );

    return (
        <div ref={containerRef} className="relative w-max min-w-full pb-4">
            {lineageLines.length > 0 && (
                <svg
                    aria-hidden="true"
                    className="pointer-events-none absolute top-0 left-0 z-10 overflow-visible"
                    width={overlaySize.width}
                    height={overlaySize.height}
                >
                    {lineageLines.map((line) => (
                        <path
                            key={line.id}
                            d={line.path}
                            fill="none"
                            stroke="#dc2626"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth="2"
                        />
                    ))}
                </svg>
            )}
            {visibleRoots.length > 0 ? (
                <ul
                    className={compact ? 'tb-tree tb-tree--compact' : 'tb-tree'}
                >
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
                            selectOnClick={selectOnClick}
                            showProfileOnName={showProfileOnName}
                            readOnly={readOnly}
                            onOpenProfile={setProfilePerson}
                            alternativeTrees={alternativeTrees}
                            nodeIdPrefix={nodeIdPrefix}
                            lineageIds={lineageIds}
                            femaleLineage={root.gender?.toUpperCase() === 'P'}
                            markFemaleLineage={markFemaleLineage}
                            collapseDepth={collapseDepth}
                            compact={compact}
                            showNodeAvatar={showNodeAvatar}
                            showSpouseNames={showSpouseNames}
                            compactTerminalBranches={compactTerminalBranches}
                            packCollapsed={packCollapsed}
                        />
                    ))}
                </ul>
            ) : (
                <p className="px-3 py-2 text-xs text-tb-on-surface-variant italic">
                    Versi ini belum memiliki keturunan berbeda.
                </p>
            )}
            {detachedRoots.length > 0 && (
                <div className="mt-6 border-t border-dashed border-tb-outline-variant pt-4">
                    <p className="mb-3 text-center text-xs font-semibold text-tb-on-surface-variant">
                        Anggota marga tanpa jalur ayah tersambung
                    </p>
                    {detachedBranchRoots.length > 0 && (
                        <div className="flex w-max min-w-full flex-wrap items-start justify-center gap-x-10 gap-y-6">
                            {detachedBranchRoots.map((root) => (
                                <div
                                    key={root.id}
                                    className="flex flex-col items-center gap-2"
                                >
                                    <p className="rounded-full border border-tb-outline-variant bg-tb-surface-container px-3 py-1 text-xs font-semibold text-tb-on-surface">
                                        Nama Keluarga: {root.name}
                                    </p>
                                    <ul
                                        className={
                                            compact
                                                ? 'tb-tree tb-tree--compact'
                                                : 'tb-tree'
                                        }
                                    >
                                        <TreeBranch
                                            person={root}
                                            childrenOf={childrenOf}
                                            centerId={centerId}
                                            highlightId={highlightId}
                                            numberById={numberById}
                                            collapsed={collapsed}
                                            onToggle={handleToggle}
                                            onSelect={onSelect}
                                            editNodes={editNodes}
                                            selectOnClick={selectOnClick}
                                            showProfileOnName={
                                                showProfileOnName
                                            }
                                            readOnly={readOnly}
                                            onOpenProfile={setProfilePerson}
                                            alternativeTrees={alternativeTrees}
                                            nodeIdPrefix={nodeIdPrefix}
                                            lineageIds={lineageIds}
                                            femaleLineage={
                                                root.gender?.toUpperCase() ===
                                                'P'
                                            }
                                            markFemaleLineage={
                                                markFemaleLineage
                                            }
                                            collapseDepth={collapseDepth}
                                            compact={compact}
                                            showNodeAvatar={showNodeAvatar}
                                            showSpouseNames={showSpouseNames}
                                            compactTerminalBranches={
                                                compactTerminalBranches
                                            }
                                            packCollapsed={packCollapsed}
                                        />
                                    </ul>
                                </div>
                            ))}
                        </div>
                    )}
                    {detachedLeafRoots.length > 0 && (
                        <div className="mx-auto flex w-full max-w-[72rem] flex-wrap items-start justify-center gap-x-5 gap-y-6">
                            {detachedLeafRoots.map((root) => (
                                <div
                                    key={root.id}
                                    className="flex flex-col items-center gap-2"
                                >
                                    <p className="rounded-full border border-tb-outline-variant bg-tb-surface-container px-3 py-1 text-xs font-semibold text-tb-on-surface">
                                        Nama Keluarga: {root.name}
                                    </p>
                                    <ul
                                        className={
                                            compact
                                                ? 'tb-tree tb-tree--compact'
                                                : 'tb-tree'
                                        }
                                    >
                                        <TreeBranch
                                            person={root}
                                            childrenOf={childrenOf}
                                            centerId={centerId}
                                            highlightId={highlightId}
                                            numberById={numberById}
                                            collapsed={collapsed}
                                            onToggle={handleToggle}
                                            onSelect={onSelect}
                                            editNodes={editNodes}
                                            selectOnClick={selectOnClick}
                                            showProfileOnName={
                                                showProfileOnName
                                            }
                                            readOnly={readOnly}
                                            onOpenProfile={setProfilePerson}
                                            alternativeTrees={alternativeTrees}
                                            nodeIdPrefix={nodeIdPrefix}
                                            lineageIds={lineageIds}
                                            femaleLineage={
                                                root.gender?.toUpperCase() ===
                                                'P'
                                            }
                                            markFemaleLineage={
                                                markFemaleLineage
                                            }
                                            collapseDepth={collapseDepth}
                                            compact={compact}
                                            showNodeAvatar={showNodeAvatar}
                                            showSpouseNames={showSpouseNames}
                                            packCollapsed={packCollapsed}
                                        />
                                    </ul>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
            {showProfileOnName && (
                <PersonSummaryDialog
                    person={profilePerson}
                    people={people}
                    onClose={() => setProfilePerson(null)}
                    currentUserId={currentUserId}
                    versionTreeId={versionTreeId}
                    allowBranchEntry={allowBranchEntry}
                />
            )}
        </div>
    );
}
