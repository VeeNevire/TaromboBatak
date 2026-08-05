import { ReactFlow } from '@xyflow/react';
import type { NodeMouseHandler } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { Search, SlidersHorizontal } from 'lucide-react';
import { useMemo, useState } from 'react';
import { edgeTypes, nodeTypes } from '@/components/landing/diagram/diagram-types';
import {
    buildRadialLayout,
    getConnectionIds,
    getGenerationLabel,
    generationColors,
    MOCK_MARGAS,
    MOCK_TAROMBO,
} from '@/data/tarombo-tree';
import type { MargaInfo, SectorNodeData, TaromboNodeData, TaromboPerson } from '@/data/tarombo-tree';

export function TaromboDiagram({
    onSelect,
    selectedId,
    people = MOCK_TAROMBO,
    margas = MOCK_MARGAS,
}: {
    onSelect: (person: TaromboPerson) => void;
    selectedId?: string;
    people?: TaromboPerson[];
    margas?: MargaInfo[];
}) {
    const layout = useMemo(() => buildRadialLayout(people, margas), [people, margas]);
    const maxGeneration = useMemo(
        () => Math.max(1, ...people.map((person) => person.generation)),
        [people],
    );
    const [hoveredId, setHoveredId] = useState<string | null>(null);

    const focusId = hoveredId ?? selectedId;
    const connection = useMemo(
        () => (focusId ? getConnectionIds(people, focusId) : new Set<string>()),
        [focusId, people],
    );

    const nodes = useMemo(
        () =>
            layout.nodes.map((node) => {
                const base = node.data as TaromboNodeData | SectorNodeData;
                const person = (base as TaromboNodeData).person;

                return {
                    ...node,
                    data: {
                        ...base,
                        selected: Boolean(person) && person.id === focusId,
                        related: Boolean(person) && connection.has(person.id),
                    },
                };
            }),
        [layout, focusId, connection],
    );

    const edges = useMemo(
        () =>
            layout.edges.map((edge) => {
                const hasFocus = connection.size > 0;
                const active = hasFocus && connection.has(edge.source) && connection.has(edge.target);

                return {
                    ...edge,
                    data: { ...edge.data, active, dimmed: hasFocus && !active },
                };
            }),
        [layout, connection],
    );

    const handleNodeClick: NodeMouseHandler = (_event, node) => {
        const data = node.data as TaromboNodeData;

        if (data?.person) {
            onSelect(data.person);
        }
    };

    const handleNodeMouseEnter: NodeMouseHandler = (_event, node) => {
        const data = node.data as TaromboNodeData;

        if (data?.person) {
            setHoveredId(data.person.id);
        }
    };

    return (
        <div className="relative w-full max-w-2xl">
            <div className="mx-auto mb-6 flex max-w-sm items-center rounded-full border border-tb-outline-variant bg-tb-surface-bright/90 px-4 py-2.5 shadow-lg backdrop-blur-md">
                    <Search className="mr-2 h-4 w-4 shrink-0 text-tb-outline" />
                    <input
                        type="text"
                        placeholder="Cari anggota atau marga..."
                        className="w-full border-none bg-transparent text-sm text-tb-on-surface focus:ring-0"
                    />
                    <button
                        type="button"
                        className="ml-2 text-tb-outline hover:text-tb-primary"
                        aria-label="Filter"
                    >
                        <SlidersHorizontal className="h-4 w-4" />
                    </button>
                </div>
                <div className="relative aspect-square w-full">
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={nodeTypes}
                        edgeTypes={edgeTypes}
                        onNodeClick={handleNodeClick}
                        onNodeMouseEnter={handleNodeMouseEnter}
                        onNodeMouseLeave={() => setHoveredId(null)}
                        nodesDraggable={false}
                        nodesConnectable={false}
                        elementsSelectable={false}
                        panOnDrag={false}
                        selectionOnDrag={false}
                        zoomOnScroll={false}
                        zoomOnPinch={false}
                        zoomOnDoubleClick={false}
                        fitView
                        fitViewOptions={{ padding: 0.02 }}
                        minZoom={0.2}
                        maxZoom={2}
                        proOptions={{ hideAttribution: true }}
                        className="tarombo-flow"
                    />
                </div>
                <div className="mt-8">
                    <p className="text-center text-[10px] font-semibold uppercase tracking-widest text-tb-outline">
                        Legenda
                    </p>
                    <div className="mt-3 flex flex-wrap justify-center gap-x-3 gap-y-2">
                        {Array.from({ length: maxGeneration }, (_, index) => (
                            <span
                                key={index}
                                className="flex items-center gap-1.5 rounded-full bg-tb-surface-bright px-2.5 py-1 shadow-sm"
                            >
                                <span
                                    className="h-2.5 w-2.5 rounded-full"
                                    style={{ backgroundColor: generationColors[index % generationColors.length] }}
                                />
                                <span className="text-[10px] font-medium text-tb-on-surface">
                                    Gen {index + 1} ({getGenerationLabel(index + 1)})
                                </span>
                            </span>
                        ))}
                    </div>
                    <div className="mt-2 flex flex-wrap justify-center gap-x-3 gap-y-2">
                        {margas.map((marga) => (
                            <span
                                key={marga.name}
                                className="flex items-center gap-1.5 rounded-full bg-tb-surface-bright px-2.5 py-1 shadow-sm"
                            >
                                <span
                                    className="h-2.5 w-2.5 rounded-sm"
                                    style={{ backgroundColor: marga.color }}
                                />
                                <span className="text-[10px] font-medium text-tb-on-surface">{marga.name}</span>
                            </span>
                        ))}
                    </div>
                </div>
        </div>
    );
}