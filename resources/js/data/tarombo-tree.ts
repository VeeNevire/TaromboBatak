import type { Edge, Node } from '@xyflow/react';
import taromboRows from '@/data/tarombo-tree.json';

export type TaromboPersonRow = {
    id: string;
    name: string;
    alias?: string;
    marga: string;
    hasMarga?: boolean;
    parentId: string | null;
    birthYear?: string;
    birthOrder?: number | null;
    gender?: string | null;
    spouse?: string | null;
    image?: string | null;
    bio?: string;
    createdBy?: string | null;
    relatedStories?: RelatedStory[];
    location?: RegionLocation;
    chain?: string | null;
    pending?: boolean;
    childrenNames?: string[];
};

export type TaromboPerson = {
    id: string;
    name: string;
    alias?: string;
    marga: string;
    hasMarga?: boolean;
    generation: number;
    parentId?: string | null;
    birthYear?: string;
    birthOrder?: number | null;
    gender?: string | null;
    spouse?: string | null;
    image?: string | null;
    bio?: string;
    createdBy?: string | null;
    relatedStories?: RelatedStory[];
    location?: RegionLocation;
    chain?: string | null;
    pending?: boolean;
    childrenNames?: string[];
};

export type RelatedStory = {
    title: string;
    url: string;
};

export type RegionLocation = {
    province: string | null;
    regency: string | null;
    district: string | null;
    village: string | null;
};

export type TaromboAlternativeTreeRow = {
    id: number;
    name: string;
    rootPersonId: string;
    people: TaromboPersonRow[];
};

export type MargaInfo = {
    name: string;
    color: string;
};

export type TaromboSector = {
    marga: string;
    color: string;
    start: number;
    end: number;
};

export type TaromboLabel = {
    text: string;
    color: string;
    angle: number;
    radius: number;
};

export type TaromboTag = {
    label: string;
    radius: number;
    angle: number;
};

export type TaromboNodeData = {
    person: TaromboPerson;
    ringColor: string;
    selected?: boolean;
    related?: boolean;
    bubble?: boolean;
};

export type SectorNodeData = {
    sectors: TaromboSector[];
    guides: number[];
    labels: TaromboLabel[];
    generationTags: TaromboTag[];
    extent: number;
};

export type RadialEdgeData = {
    centerX: number;
    centerY: number;
    stroke: string;
    sourceRadius: number;
    targetRadius: number;
    active?: boolean;
    dimmed?: boolean;
};

export type TaromboLayout = {
    nodes: Node[];
    edges: Edge<RadialEdgeData>[];
    sectors: TaromboSector[];
    guides: number[];
    labels: TaromboLabel[];
    generationTags: TaromboTag[];
    metrics: { extent: number };
};

export const generationColors = [
    '#b34b1e',
    '#2a527c',
    '#3e6b48',
    '#f59e0b',
    '#7c3aed',
    '#0e7490',
];

export const margaColors = [
    '#b34b1e',
    '#2a527c',
    '#3e6b48',
    '#f59e0b',
    '#7c3aed',
    '#0e7490',
    '#9a3412',
    '#4f46e5',
    '#0f766e',
    '#a16207',
    '#be185d',
    '#4338ca',
    '#15803d',
    '#c2410c',
    '#475569',
    '#6d28d9',
    '#0e7490',
    '#b45309',
    '#1e40af',
    '#047857',
    '#9d174d',
    '#334155',
    '#7c2d12',
    '#1d4ed8',
    '#a21caf',
];

export const generationLabels = ['Pusat', 'Anak', 'Cucu', 'Cicit'];

export const getGenerationLabel = (generation: number): string =>
    generationLabels[generation - 1] ?? `Generasi ${generation}`;

export const ROOT_NODE_SIZE = 112;
export const PERSON_NODE_WIDTH = 72;
export const PERSON_NODE_HEIGHT = 104;
export const ROOT_NODE_RADIUS = ROOT_NODE_SIZE / 2;
export const PERSON_AVATAR_RADIUS = 31;

const RING_GAP = 165;
const INNER_RADIUS = RING_GAP * 0.3;
const LABEL_OFFSET = 16;
const PAD = 36;

const TAROMBO_ROWS = taromboRows as TaromboPersonRow[];

export const MOCK_MARGAS: MargaInfo[] = [
    ...new Set(TAROMBO_ROWS.map((row) => row.marga)),
].map((name, index) => ({
    name,
    color: margaColors[index % margaColors.length],
}));

export const MOCK_TAROMBO: TaromboPerson[] = buildTaromboPeople(TAROMBO_ROWS);

export function findPerson(
    people: TaromboPerson[],
    id: string,
): TaromboPerson | undefined {
    return people.find((person) => person.id === id);
}

export function findPersonChildren(
    people: TaromboPerson[],
    id: string,
): TaromboPerson[] {
    return people.filter((person) => person.parentId === id);
}

export function oldestOfMarga(
    people: TaromboPerson[],
    margaName: string,
): TaromboPerson | undefined {
    const members = people.filter((person) => person.marga === margaName);

    if (members.length === 0) {
        return undefined;
    }

    return [...members].sort((a, b) => {
        if (a.generation !== b.generation) {
            return a.generation - b.generation;
        }

        return (
            (a.birthOrder ?? Number.POSITIVE_INFINITY) -
            (b.birthOrder ?? Number.POSITIVE_INFINITY)
        );
    })[0];
}

export function buildTaromboPeople(rows: TaromboPersonRow[]): TaromboPerson[] {
    if (rows.length === 0) {
        return [];
    }

    const byId = new Map(rows.map((row) => [row.id, row]));
    const parentById = new Map(
        rows.map((row) => [
            row.id,
            row.parentId && row.parentId !== row.id && byId.has(row.parentId)
                ? row.parentId
                : null,
        ]),
    );
    const childrenOf = new Map<string, string[]>();

    for (const row of rows) {
        const parentId = parentById.get(row.id);

        if (parentId) {
            const siblings = childrenOf.get(parentId) ?? [];
            siblings.push(row.id);
            childrenOf.set(parentId, siblings);
        }
    }

    const roots = rows.filter((row) => parentById.get(row.id) === null);
    const generation = new Map<string, number>();
    const visited = new Set<string>(roots.map((root) => root.id));
    const queue: string[] = roots.map((root) => root.id);

    for (const root of roots) {
        generation.set(root.id, 1);
    }

    while (queue.length > 0) {
        const id = queue.shift() as string;
        const current = generation.get(id) ?? 1;

        for (const child of childrenOf.get(id) ?? []) {
            if (visited.has(child)) {
                continue;
            }

            visited.add(child);
            generation.set(child, current + 1);
            queue.push(child);
        }
    }

    // Legacy cycles have no valid root. Keep those records disconnected rather
    // than inventing a genealogical relationship in the visualization.
    for (const row of rows) {
        if (!visited.has(row.id)) {
            parentById.set(row.id, null);
            generation.set(row.id, 1);
        }
    }

    return rows.map((row) => {
        return {
            ...row,
            parentId: parentById.get(row.id) ?? null,
            generation: generation.get(row.id) ?? 1,
        };
    });
}

export function getConnectionIds(
    people: TaromboPerson[],
    id: string,
): Set<string> {
    const person = findPerson(people, id);
    const ids = new Set<string>([id]);

    if (person?.parentId) {
        ids.add(person.parentId);
    }

    for (const child of findPersonChildren(people, id)) {
        ids.add(child.id);
    }

    return ids;
}

export function getInitials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
}

export function shortestAngle(angle: number): number {
    let diff = angle % (2 * Math.PI);

    if (diff > Math.PI) {
        diff -= 2 * Math.PI;
    }

    if (diff < -Math.PI) {
        diff += 2 * Math.PI;
    }

    return diff;
}

function subtreeLeafCount(
    id: string,
    childrenOf: Map<string, string[]>,
): number {
    const children = childrenOf.get(id) ?? [];

    if (children.length === 0) {
        return 1;
    }

    return children.reduce(
        (sum, child) => sum + subtreeLeafCount(child, childrenOf),
        0,
    );
}

function assignSpans(
    id: string,
    start: number,
    end: number,
    childrenOf: Map<string, string[]>,
    spans: Map<string, { start: number; end: number; angle: number }>,
): void {
    const children = childrenOf.get(id) ?? [];
    spans.set(id, { start, end, angle: (start + end) / 2 });

    if (children.length === 0) {
        return;
    }

    const totalWeight = children.reduce(
        (sum, child) => sum + subtreeLeafCount(child, childrenOf),
        0,
    );
    let cursor = start;

    for (const child of children) {
        const weight = subtreeLeafCount(child, childrenOf);
        const childEnd = cursor + (end - start) * (weight / totalWeight);
        assignSpans(child, cursor, childEnd, childrenOf, spans);
        cursor = childEnd;
    }
}

function normalizeAngleWithin(
    angle: number,
    start: number,
    end: number,
): number {
    const width = end - start;
    const relative = angle - start;
    const wrapped = ((relative % width) + width) % width;

    return start + wrapped;
}

function enforceMinGap(
    people: TaromboPerson[],
    spans: Map<string, { start: number; end: number; angle: number }>,
): void {
    const byGeneration = new Map<number, string[]>();

    for (const person of people) {
        if (person.generation < 2) {
            continue;
        }

        const ids = byGeneration.get(person.generation) ?? [];
        ids.push(person.id);
        byGeneration.set(person.generation, ids);
    }

    for (const [generation, ids] of byGeneration) {
        const ringRadius = (generation - 1) * RING_GAP;
        // Increased padding from 6px to 24px for better breathing room
        const minGap = (PERSON_NODE_WIDTH + 24) / ringRadius;

        // Increased passes from 2 to 8 for better overlap resolution
        for (let pass = 0; pass < 8; pass++) {
            const ordered = [...ids].sort(
                (a, b) =>
                    (spans.get(a)?.angle ?? 0) - (spans.get(b)?.angle ?? 0),
            );

            // Forward pass: push nodes to the right
            for (let i = 0; i < ordered.length - 1; i++) {
                const left = spans.get(ordered[i]);
                const right = spans.get(ordered[i + 1]);

                if (!left || !right) {
                    continue;
                }

                const gap = shortestAngle(right.angle - left.angle);

                if (gap < minGap) {
                    right.angle = normalizeAngleWithin(
                        left.angle + minGap,
                        right.start,
                        right.end,
                    );
                }
            }

            // Wrap-around check: last node vs first node
            if (ordered.length > 1) {
                const first = spans.get(ordered[0]);
                const last = spans.get(ordered[ordered.length - 1]);

                if (first && last) {
                    // Check gap from last to first (crossing 2π boundary)
                    const wrapGap = shortestAngle(first.angle - last.angle);

                    if (wrapGap < minGap && wrapGap > 0) {
                        // Push first node forward
                        first.angle = normalizeAngleWithin(
                            last.angle + minGap,
                            first.start,
                            first.end,
                        );
                    }
                }
            }

            // Backward pass: pull nodes to the left to balance
            for (let i = ordered.length - 1; i > 0; i--) {
                const left = spans.get(ordered[i - 1]);
                const right = spans.get(ordered[i]);

                if (!left || !right) {
                    continue;
                }

                const gap = shortestAngle(right.angle - left.angle);

                if (gap < minGap) {
                    left.angle = normalizeAngleWithin(
                        right.angle - minGap,
                        left.start,
                        left.end,
                    );
                }
            }
        }
    }
}

function recenterParents(
    rootId: string,
    childrenOf: Map<string, string[]>,
    spans: Map<string, { start: number; end: number; angle: number }>,
): void {
    for (const id of spans.keys()) {
        if (id === rootId) {
            continue;
        }

        const children = childrenOf.get(id) ?? [];

        if (children.length === 0) {
            continue;
        }

        let sinSum = 0;
        let cosSum = 0;
        let total = 0;

        for (const child of children) {
            const childSpan = spans.get(child);

            if (!childSpan) {
                continue;
            }

            const weight = subtreeLeafCount(child, childrenOf);
            sinSum += weight * Math.sin(childSpan.angle);
            cosSum += weight * Math.cos(childSpan.angle);
            total += weight;
        }

        if (total === 0) {
            continue;
        }

        const span = spans.get(id);

        if (!span) {
            continue;
        }

        span.angle = normalizeAngleWithin(
            Math.atan2(sinSum, cosSum),
            span.start,
            span.end,
        );
    }
}

export function getDescendantsUpToDepth(
    people: TaromboPerson[],
    rootId: string,
    maxDepth: number = 3,
): TaromboPerson[] {
    const byId = new Map(people.map((person) => [person.id, person]));
    const childrenOf = new Map<string, string[]>();

    for (const person of people) {
        if (person.parentId) {
            const siblings = childrenOf.get(person.parentId) ?? [];
            siblings.push(person.id);
            childrenOf.set(person.parentId, siblings);
        }
    }

    const root = byId.get(rootId);

    if (!root) {
        return [];
    }

    const result: TaromboPerson[] = [root];
    const queue: Array<{ id: string; depth: number }> = [
        { id: rootId, depth: 0 },
    ];
    const visited = new Set<string>([rootId]);

    while (queue.length > 0) {
        const current = queue.shift();

        if (!current || current.depth >= maxDepth) {
            continue;
        }

        const children = childrenOf.get(current.id) ?? [];

        for (const childId of children) {
            if (visited.has(childId)) {
                continue;
            }

            visited.add(childId);
            const child = byId.get(childId);

            if (child) {
                result.push(child);
                queue.push({ id: childId, depth: current.depth + 1 });
            }
        }
    }

    return result;
}

export function buildRadialLayoutFromPerson(
    allPeople: TaromboPerson[],
    margas: MargaInfo[],
    centerPersonId: string,
    context: 'extended' | 'descendants' = 'extended',
    maxDepth: number = Number.POSITIVE_INFINITY,
): TaromboLayout {
    const byId = new Map(allPeople.map((person) => [person.id, person]));
    const childrenOf = new Map<string, string[]>();

    for (const person of allPeople) {
        if (person.parentId) {
            const siblings = childrenOf.get(person.parentId) ?? [];
            siblings.push(person.id);
            childrenOf.set(person.parentId, siblings);
        }
    }

    const centerPerson =
        byId.get(centerPersonId) ??
        allPeople.find((p) => p.id === centerPersonId);

    if (!centerPerson) {
        return buildRadialLayout(allPeople, margas);
    }

    const selected = new Map<string, TaromboPerson>();
    const add = (person?: TaromboPerson | null) => {
        if (person) {
            selected.set(person.id, person);
        }
    };

    if (context === 'descendants') {
        // Center + keturunan dengan depth limit
        for (const person of getDescendantsUpToDepth(
            allPeople,
            centerPersonId,
            maxDepth,
        )) {
            add(person);
        }
    } else {
        // Descendants: children → grandchildren (3 generations deep).
        for (const person of getDescendantsUpToDepth(
            allPeople,
            centerPersonId,
            3,
        )) {
            add(person);
        }

        // Ancestors: father line up to 2 generations above the center.
        let current: TaromboPerson | undefined = centerPerson;

        for (let depth = 0; depth < 2; depth++) {
            const parentId: string | null | undefined = current?.parentId;
            const parent: TaromboPerson | undefined = parentId
                ? byId.get(parentId)
                : undefined;
            add(parent);
            current = parent;
        }

        // Siblings: other children of the center person's father.
        const fatherId = centerPerson.parentId;

        if (fatherId) {
            for (const siblingId of childrenOf.get(fatherId) ?? []) {
                if (siblingId !== centerPersonId) {
                    add(byId.get(siblingId));
                }
            }
        }
    }

    if (!selected.has(centerPersonId)) {
        add(centerPerson);
    }

    const family = [...selected.values()];

    // Assign generations by BFS distance from the center along father links.
    const generation = new Map<string, number>();
    generation.set(centerPersonId, 1);
    const queue = [centerPersonId];

    while (queue.length > 0) {
        const id = queue.shift() as string;
        const currentGeneration = generation.get(id) ?? 1;
        const ids = [
            ...(childrenOf.get(id) ?? []),
            ...(byId.get(id)?.parentId
                ? [byId.get(id)?.parentId as string]
                : []),
        ];

        for (const neighborId of ids) {
            if (selected.has(neighborId) && !generation.has(neighborId)) {
                generation.set(neighborId, currentGeneration + 1);
                queue.push(neighborId);
            }
        }
    }

    const renumberedPeople: TaromboPerson[] = family.map((person) => {
        if (person.id === centerPersonId) {
            return { ...person, generation: 1, parentId: null };
        }

        const parentId =
            person.parentId && selected.has(person.parentId)
                ? person.parentId
                : centerPersonId;

        return {
            ...person,
            generation: generation.get(person.id) ?? 2,
            parentId,
        };
    });

    return buildRadialLayout(renumberedPeople, margas);
}

export function buildRadialLayout(
    people: TaromboPerson[],
    margas: MargaInfo[],
): TaromboLayout {
    if (people.length === 0) {
        return {
            nodes: [],
            edges: [],
            sectors: [],
            guides: [],
            labels: [],
            generationTags: [],
            metrics: { extent: 0 },
        };
    }

    const byId = new Map(people.map((person) => [person.id, person]));
    const childrenOf = new Map<string, string[]>();

    for (const person of people) {
        if (person.parentId) {
            const siblings = childrenOf.get(person.parentId) ?? [];
            siblings.push(person.id);
            childrenOf.set(person.parentId, siblings);
        }
    }

    for (const ids of childrenOf.values()) {
        ids.sort((a, b) => {
            const orderA = byId.get(a)?.birthOrder ?? Number.POSITIVE_INFINITY;
            const orderB = byId.get(b)?.birthOrder ?? Number.POSITIVE_INFINITY;

            return orderA - orderB;
        });
    }

    const root = people.find((person) => !person.parentId) ?? people[0];
    const maxGeneration = Math.max(
        ...people.map((person) => person.generation),
    );

    const spans = new Map<
        string,
        { start: number; end: number; angle: number }
    >();
    assignSpans(
        root.id,
        -Math.PI / 2,
        -Math.PI / 2 + 2 * Math.PI,
        childrenOf,
        spans,
    );
    enforceMinGap(people, spans);
    recenterParents(root.id, childrenOf, spans);
    enforceMinGap(people, spans);

    const nodes: Node[] = [];
    const edges: Edge<RadialEdgeData>[] = [];

    for (const person of people) {
        const span = spans.get(person.id);

        if (!span) {
            continue;
        }

        const ringRadius = (person.generation - 1) * RING_GAP;
        const x = ringRadius * Math.cos(span.angle);
        const y = ringRadius * Math.sin(span.angle);
        const ringColor =
            generationColors[(person.generation - 1) % generationColors.length];

        if (person.id === root.id) {
            nodes.push({
                id: person.id,
                type: 'center',
                position: {
                    x: x - ROOT_NODE_SIZE / 2,
                    y: y - ROOT_NODE_SIZE / 2,
                },
                width: ROOT_NODE_SIZE,
                height: ROOT_NODE_SIZE,
                data: { person, ringColor },
                zIndex: 1,
            });
        } else {
            nodes.push({
                id: person.id,
                type: 'person',
                position: {
                    x: x - PERSON_NODE_WIDTH / 2,
                    y: y - PERSON_NODE_HEIGHT / 2,
                },
                width: PERSON_NODE_WIDTH,
                height: PERSON_NODE_HEIGHT,
                data: { person, ringColor },
                zIndex: 1,
            });
        }
    }

    for (const person of people) {
        if (!person.parentId) {
            continue;
        }

        const parent = byId.get(person.parentId);
        const stroke =
            generationColors[(person.generation - 1) % generationColors.length];
        const sourceRadius =
            parent?.generation === 1 ? ROOT_NODE_RADIUS : PERSON_AVATAR_RADIUS;
        edges.push({
            id: `edge-${person.parentId}-${person.id}`,
            source: person.parentId,
            target: person.id,
            type: 'radial',
            data: {
                centerX: 0,
                centerY: 0,
                stroke,
                sourceRadius,
                targetRadius: PERSON_AVATAR_RADIUS,
            },
            style: {
                stroke: parent
                    ? generationColors[
                          (parent.generation - 1) % generationColors.length
                      ]
                    : stroke,
            },
        });
    }

    const maxRingRadius = (maxGeneration - 1) * RING_GAP;
    const outerLabelRadius =
        maxRingRadius + PERSON_NODE_HEIGHT / 2 + LABEL_OFFSET;
    const extent = 2 * (outerLabelRadius + PAD);

    const sectorRootIds = childrenOf.get(root.id) ?? [];
    const sectors: TaromboSector[] = sectorRootIds.map((childId) => {
        const child = byId.get(childId);
        const span = spans.get(childId);
        const marga = margas.find(
            (margaInfo) => margaInfo.name === child?.marga,
        );

        return {
            marga: child?.marga ?? 'Marga',
            color: marga?.color ?? '#8f836f',
            start: span?.start ?? 0,
            end: span?.end ?? 0,
        };
    });

    const guides: number[] = Array.from(
        { length: maxGeneration - 1 },
        (_, index) => (index + 1) * RING_GAP,
    );

    const labels: TaromboLabel[] = sectors.map((sector) => ({
        text: sector.marga,
        color: sector.color,
        angle: (sector.start + sector.end) / 2,
        radius: outerLabelRadius,
    }));

    const generationTags: TaromboTag[] = Array.from(
        { length: maxGeneration - 1 },
        (_, index) => ({
            label: `Generasi ${index + 2}`,
            radius: guides[index],
            angle: -Math.PI / 2,
        }),
    );

    nodes.push({
        id: 'sectors',
        type: 'sectors',
        position: { x: -extent / 2, y: -extent / 2 },
        width: extent,
        height: extent,
        data: { sectors, guides, labels, generationTags, extent },
        zIndex: -1,
        draggable: false,
        selectable: false,
        style: { pointerEvents: 'none' },
    });

    return {
        nodes,
        edges,
        sectors,
        guides,
        labels,
        generationTags,
        metrics: { extent },
    };
}

export { INNER_RADIUS };

export function sectorPath(
    centerX: number,
    centerY: number,
    innerRadius: number,
    outerRadius: number,
    start: number,
    end: number,
): string {
    const innerStartX = centerX + innerRadius * Math.cos(start);
    const innerStartY = centerY + innerRadius * Math.sin(start);
    const outerStartX = centerX + outerRadius * Math.cos(start);
    const outerStartY = centerY + outerRadius * Math.sin(start);
    const outerEndX = centerX + outerRadius * Math.cos(end);
    const outerEndY = centerY + outerRadius * Math.sin(end);
    const innerEndX = centerX + innerRadius * Math.cos(end);
    const innerEndY = centerY + innerRadius * Math.sin(end);
    const largeArc = end - start > Math.PI ? 1 : 0;

    return [
        `M ${innerStartX} ${innerStartY}`,
        `L ${outerStartX} ${outerStartY}`,
        `A ${outerRadius} ${outerRadius} 0 ${largeArc} 1 ${outerEndX} ${outerEndY}`,
        `L ${innerEndX} ${innerEndY}`,
        `A ${innerRadius} ${innerRadius} 0 ${largeArc} 0 ${innerStartX} ${innerStartY}`,
        'Z',
    ].join(' ');
}
