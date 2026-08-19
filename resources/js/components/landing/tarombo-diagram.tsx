import { ReactFlow, ReactFlowProvider, useReactFlow } from '@xyflow/react';
import type { NodeMouseHandler } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
<<<<<<< HEAD
import {
    ArrowLeft,
    Focus,
    Minus,
    Plus,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
=======
import { ArrowLeft } from 'lucide-react';
>>>>>>> origin/main
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    edgeTypes,
    nodeTypes,
} from '@/components/landing/diagram/diagram-types';
import {
    buildRadialLayoutFromPerson,
    getConnectionIds,
    getGenerationLabel,
    generationColors,
    MOCK_MARGAS,
    MOCK_TAROMBO,
} from '@/data/tarombo-tree';
import type {
    MargaInfo,
    SectorNodeData,
    TaromboNodeData,
    TaromboPerson,
} from '@/data/tarombo-tree';

export function TaromboDiagram({
    onSelect,
    onPaneClick,
    onBack,
    canGoBack = false,
    selectedId,
    centerPersonId,
    context = 'extended',
    maxDepth,
    people = MOCK_TAROMBO,
    margas = MOCK_MARGAS,
}: {
    onSelect: (person: TaromboPerson) => void;
    onPaneClick?: () => void;
    onBack?: () => void;
    canGoBack?: boolean;
    selectedId?: string;
    centerPersonId: string;
    context?: 'extended' | 'descendants';
    maxDepth?: number;
    people?: TaromboPerson[];
    margas?: MargaInfo[];
}) {
    return (
        <ReactFlowProvider>
            <TaromboDiagramInner
                onSelect={onSelect}
                onPaneClick={onPaneClick}
                onBack={onBack}
                canGoBack={canGoBack}
                selectedId={selectedId}
                centerPersonId={centerPersonId}
                context={context}
                maxDepth={maxDepth}
                people={people}
                margas={margas}
            />
        </ReactFlowProvider>
    );
}

function TaromboDiagramInner({
    onSelect,
    onPaneClick,
    onBack,
    canGoBack,
    selectedId,
    centerPersonId,
    context,
    maxDepth,
    people,
    margas,
}: {
    onSelect: (person: TaromboPerson) => void;
    onPaneClick?: () => void;
    onBack?: () => void;
    canGoBack: boolean;
    selectedId?: string;
    centerPersonId: string;
    context: 'extended' | 'descendants';
    maxDepth?: number;
    people: TaromboPerson[];
    margas: MargaInfo[];
}) {
    const { fitView, zoomIn, zoomOut } = useReactFlow();
    const layout = useMemo(
        () =>
            buildRadialLayoutFromPerson(
                people,
                margas,
                centerPersonId,
                context,
                maxDepth,
            ),
        [people, margas, centerPersonId, context, maxDepth],
    );

    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            fitView({ padding: 0.02, duration: 300 });
        });

        return () => cancelAnimationFrame(frame);
    }, [fitView, centerPersonId, context]);
<<<<<<< HEAD
    const squareRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const square = squareRef.current;

        if (!square) {
            return;
        }

        let frame = 0;

        const observer = new ResizeObserver(() => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                fitView({ padding: 0.02, duration: 0 });
            });
        });

        observer.observe(square);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [fitView]);
    const layoutPeople = useMemo(() => {
        const layoutNodeIds = new Set(
            layout.nodes
                .filter((n) => (n.data as TaromboNodeData).person)
                .map((n) => n.id),
        );

=======
    const layoutPeople = useMemo(() => {
        const layoutNodeIds = new Set(
            layout.nodes
                .filter((n) => (n.data as TaromboNodeData).person)
                .map((n) => n.id),
        );

>>>>>>> origin/main
        return people.filter((p) => layoutNodeIds.has(p.id));
    }, [layout.nodes, people]);

    const maxGeneration = useMemo(
        () => Math.max(1, ...layoutPeople.map((person) => person.generation)),
        [layoutPeople],
    );
    const [hoveredId, setHoveredId] = useState<string | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const handleWheel = (event: WheelEvent) => {
            if (container.contains(event.target as Node)) {
                event.stopPropagation();
            }
        };

        document.addEventListener('wheel', handleWheel, {
            capture: true,
            passive: true,
        });

        return () =>
            document.removeEventListener('wheel', handleWheel, {
                capture: true,
            });
    }, []);

    const focusId = hoveredId ?? selectedId;
    const connection = useMemo(
        () =>
            focusId
                ? getConnectionIds(layoutPeople, focusId)
                : new Set<string>(),
        [focusId, layoutPeople],
    );

    const nodes = useMemo(
        () =>
            layout.nodes.map((node) => {
                const base = node.data as TaromboNodeData | SectorNodeData;
                const person = (base as TaromboNodeData).person;

                return {
                    ...node,
                    zIndex:
                        Boolean(person) && person.id === selectedId
                            ? 60
                            : (node.zIndex ?? 1),
                    data: {
                        ...base,
                        selected: Boolean(person) && person.id === selectedId,
                        related: Boolean(person) && connection.has(person.id),
                    },
                };
            }),
        [layout, selectedId, connection],
    );

    const edges = useMemo(
        () =>
            layout.edges.map((edge) => {
                const hasFocus = connection.size > 0;
                const active =
                    hasFocus &&
                    connection.has(edge.source) &&
                    connection.has(edge.target);

                return {
                    ...edge,
                    data: { ...edge.data, active, dimmed: hasFocus && !active },
                };
            }),
        [layout, connection],
    );

    const handleNodeClick: NodeMouseHandler = (_event, node) => {
        const data = node.data as TaromboNodeData;

        if (!data?.person) {
            return;
        }

        if (data.person.id === selectedId) {
            onPaneClick?.();
        } else {
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
<<<<<<< HEAD
        <div className="relative mx-auto w-full max-w-2xl" ref={containerRef}>
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
            <div className="relative aspect-square w-full" ref={squareRef}>
=======
        <div className="relative w-full max-w-2xl" ref={containerRef}>
            <div className="relative aspect-square w-full">
>>>>>>> origin/main
                {canGoBack && onBack && (
                    <button
                        type="button"
                        onClick={onBack}
                        className="absolute top-3 left-3 z-10 inline-flex items-center gap-1.5 rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 px-3 py-1.5 text-xs font-medium text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" /> Kembali
<<<<<<< HEAD
                    </button>
                )}
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    nodeTypes={nodeTypes}
                    edgeTypes={edgeTypes}
                    onNodeClick={handleNodeClick}
                    onNodeMouseEnter={handleNodeMouseEnter}
                    onNodeMouseLeave={() => setHoveredId(null)}
                    onPaneClick={() => onPaneClick?.()}
                    nodesDraggable={false}
                    nodesConnectable={false}
                    elementsSelectable={false}
                    panOnDrag={false}
                    panOnScroll={false}
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
                <div className="absolute right-3 bottom-3 z-10 flex flex-col overflow-hidden rounded-lg border border-tb-outline-variant bg-tb-surface-bright/95 shadow-md backdrop-blur">
                    <button
                        type="button"
                        onClick={() => zoomIn({ duration: 200 })}
                        aria-label="Perbesar"
                        title="Perbesar"
                        className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container"
                    >
                        <Plus className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => zoomOut({ duration: 200 })}
                        aria-label="Perkecil"
                        title="Perkecil"
                        className="inline-flex h-9 w-9 items-center justify-center border-y border-tb-outline-variant text-tb-on-surface transition-colors hover:bg-tb-surface-container"
                    >
                        <Minus className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            fitView({ padding: 0.02, duration: 300 })
                        }
                        aria-label="Sesuaikan tampilan"
                        title="Sesuaikan tampilan"
                        className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container"
                    >
                        <Focus className="h-4 w-4" />
                    </button>
                </div>
=======
                    </button>
                )}
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    nodeTypes={nodeTypes}
                    edgeTypes={edgeTypes}
                    onNodeClick={handleNodeClick}
                    onNodeMouseEnter={handleNodeMouseEnter}
                    onNodeMouseLeave={() => setHoveredId(null)}
                    onPaneClick={() => onPaneClick?.()}
                    nodesDraggable={false}
                    nodesConnectable={false}
                    elementsSelectable={false}
                    panOnDrag={false}
                    panOnScroll={false}
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
>>>>>>> origin/main
            </div>
            <div className="mt-8">
                <p className="text-center text-[10px] font-semibold tracking-widest text-tb-outline uppercase">
                    Legenda
                </p>
                <div className="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-2.5">
                    {Array.from({ length: maxGeneration }, (_, index) => (
                        <span
                            key={index}
                            className="flex items-center gap-1.5 rounded-full bg-tb-surface-bright px-3 py-1.5 shadow-sm"
                        >
                            <span
                                className="h-2.5 w-2.5 rounded-full"
                                style={{
                                    backgroundColor:
                                        generationColors[
                                            index % generationColors.length
                                        ],
                                }}
                            />
                            <span className="text-[11px] font-medium text-tb-on-surface">
                                Gen {index + 1} ({getGenerationLabel(index + 1)}
                                )
                            </span>
                        </span>
                    ))}
                </div>
                <div className="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-2.5">
                    {margas.map((marga) => (
                        <span
                            key={marga.name}
                            className="flex items-center gap-1.5 rounded-full bg-tb-surface-bright px-3 py-1.5 shadow-sm"
                        >
                            <span
                                className="h-2.5 w-2.5 rounded-sm"
                                style={{ backgroundColor: marga.color }}
                            />
                            <span className="text-[11px] font-medium text-tb-on-surface">
                                {marga.name}
                            </span>
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}
