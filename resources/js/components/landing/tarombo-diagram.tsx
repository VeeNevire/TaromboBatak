import type { NodeMouseHandler } from '@xyflow/react';
import {
    PanOnScrollMode,
    ReactFlow,
    ReactFlowProvider,
    useReactFlow,
    useStore,
    useViewport,
} from '@xyflow/react';
import { getNodesBounds } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import {
    ArrowLeft,
    Focus,
    Minus,
    Plus,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type {
    ChangeEvent,
    KeyboardEvent,
    PointerEvent as ReactPointerEvent,
    ReactNode,
} from 'react';
import {
    edgeTypes,
    nodeTypes,
} from '@/components/landing/diagram/diagram-types';
import {
    buildRadialLayoutFromPerson,
    getConnectionIds,
    getGenerationLabel,
    generationColors,
    getInitials,
    MOCK_MARGAS,
    MOCK_TAROMBO,
} from '@/data/tarombo-tree';
import type {
    MargaInfo,
    SectorNodeData,
    TaromboNodeData,
    TaromboPerson,
} from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';

function SearchResultItem({
    person,
    color,
    onPick,
}: {
    person: TaromboPerson;
    color: string;
    onPick: (person: TaromboPerson) => void;
}) {
    return (
        <button
            type="button"
            onMouseDown={(event) => {
                event.preventDefault();
                event.stopPropagation();
                onPick(person);
            }}
            onClick={() => onPick(person)}
            className="flex w-full cursor-pointer items-center gap-2.5 px-3 py-2 text-left transition-colors hover:bg-tb-surface-container"
        >
            <span className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-tb-surface-container text-[10px] font-bold text-tb-on-surface-variant">
                {person.image ? (
                    <img
                        src={person.image}
                        alt={person.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    getInitials(person.name)
                )}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold text-tb-on-surface">
                    {person.name}
                </span>
                {person.alias && (
                    <span className="block truncate text-[10px] text-tb-on-surface-variant italic">
                        {person.alias}
                    </span>
                )}
            </span>
            <span
                className="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold tracking-wide uppercase"
                style={{ backgroundColor: `${color}1f`, color }}
            >
                {person.marga}
            </span>
        </button>
    );
}

const SCROLLBAR_H_RESERVED = 76;
const SCROLLBAR_V_RESERVED = 108;
const THUMB_MIN = 28;

function DiagramScrollbar({
    orientation,
    trackLength,
    thumbLength,
    positionPx,
    onPositionChange,
}: {
    orientation: 'horizontal' | 'vertical';
    trackLength: number;
    thumbLength: number;
    positionPx: number;
    onPositionChange: (nextPositionPx: number) => void;
}) {
    const [dragging, setDragging] = useState(false);
    const dragState = useRef({ startPointer: 0, startPosition: 0 });
    const isHorizontal = orientation === 'horizontal';

    const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.setPointerCapture(event.pointerId);
        dragState.current = {
            startPointer: isHorizontal ? event.clientX : event.clientY,
            startPosition: positionPx,
        };
        setDragging(true);
    };

    const handlePointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (!dragging) {
            return;
        }

        const pointer = isHorizontal ? event.clientX : event.clientY;
        const raw =
            dragState.current.startPosition +
            (pointer - dragState.current.startPointer);

        onPositionChange(Math.max(0, Math.min(trackLength, raw)));
    };

    const stopDragging = () => setDragging(false);

    return (
        <div
            data-tb-scrollbar={orientation}
            className={cn(
                'absolute z-20 rounded-full border border-tb-outline-variant bg-tb-surface-container p-0.5 shadow-md',
                isHorizontal
                    ? 'right-16 bottom-1 left-3 h-2.5'
                    : 'top-11 right-1 bottom-16 w-2.5',
            )}
        >
            <div
                role="presentation"
                title={isHorizontal ? 'Scroll horizontal' : 'Scroll vertikal'}
                onPointerDown={handlePointerDown}
                onPointerMove={handlePointerMove}
                onPointerUp={stopDragging}
                onPointerCancel={stopDragging}
                className={cn(
                    'absolute rounded-full bg-tb-outline transition-colors select-none',
                    isHorizontal
                        ? 'top-0.5 left-0 h-1.5'
                        : 'top-0 left-0.5 w-1.5',
                    dragging
                        ? 'cursor-grabbing bg-tb-primary'
                        : 'cursor-grab hover:bg-tb-primary',
                )}
                style={{
                    width: isHorizontal ? thumbLength : undefined,
                    height: isHorizontal ? undefined : thumbLength,
                    transform: isHorizontal
                        ? `translateX(${positionPx}px)`
                        : `translateY(${positionPx}px)`,
                    touchAction: 'none',
                }}
            />
        </div>
    );
}

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
    bubbleTrigger = 'select',
    enableSearch = false,
    onSearchSelect,
    onMargaClick,
    actionSlot,
    allowPan = false,
    showScrollbars = false,
    initialScrollable = false,
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
    bubbleTrigger?: 'select' | 'hover';
    enableSearch?: boolean;
    onSearchSelect?: (person: TaromboPerson) => void;
    onMargaClick?: (margaName: string) => void;
    actionSlot?: ReactNode;
    allowPan?: boolean;
    showScrollbars?: boolean;
    initialScrollable?: boolean;
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
                bubbleTrigger={bubbleTrigger}
                enableSearch={enableSearch}
                onSearchSelect={onSearchSelect}
                onMargaClick={onMargaClick}
                actionSlot={actionSlot}
                allowPan={allowPan}
                showScrollbars={showScrollbars}
                initialScrollable={initialScrollable}
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
    bubbleTrigger,
    enableSearch,
    onSearchSelect,
    onMargaClick,
    actionSlot,
    allowPan,
    showScrollbars,
    initialScrollable,
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
    bubbleTrigger: 'select' | 'hover';
    enableSearch: boolean;
    onSearchSelect?: (person: TaromboPerson) => void;
    onMargaClick?: (margaName: string) => void;
    actionSlot?: ReactNode;
    allowPan: boolean;
    showScrollbars: boolean;
    initialScrollable: boolean;
}) {
    const { fitView, zoomIn, zoomOut, setViewport } = useReactFlow();
    const viewport = useViewport();
    const flowWidth = useStore((state) => state.width);
    const flowHeight = useStore((state) => state.height);
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
    const bounds = useMemo(() => getNodesBounds(layout.nodes), [layout.nodes]);

    const dimsRef = useRef({ width: flowWidth, height: flowHeight });
    const viewportRef = useRef(viewport);
    const didInitialCenterRef = useRef(false);

    useEffect(() => {
        dimsRef.current = { width: flowWidth, height: flowHeight };
        viewportRef.current = viewport;
    }, [flowWidth, flowHeight, viewport]);

    const centerScrollable = useCallback(
        (duration: number) => {
            const { width, height } = dimsRef.current;

            if (width === 0 || height === 0 || bounds.width === 0) {
                return false;
            }

            const zoom = didInitialCenterRef.current
                ? viewportRef.current.zoom || 1
                : Math.max(1, (width * 1.15) / bounds.width);

            didInitialCenterRef.current = true;
            void setViewport(
                { x: width / 2, y: height / 2, zoom },
                { duration },
            );

            return true;
        },
        [bounds.width, setViewport],
    );

    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            if (initialScrollable) {
                centerScrollable(300);

                return;
            }

            fitView({ padding: 0.02, duration: 300 });
        });

        return () => cancelAnimationFrame(frame);
    }, [fitView, centerPersonId, context, initialScrollable, centerScrollable]);
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
                if (initialScrollable) {
                    if (!didInitialCenterRef.current) {
                        centerScrollable(0);
                    }

                    return;
                }

                fitView({ padding: 0.02, duration: 0 });
            });
        });

        observer.observe(square);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [fitView, initialScrollable, centerScrollable]);
    const layoutPeople = useMemo(() => {
        const layoutNodeIds = new Set(
            layout.nodes
                .filter((n) => (n.data as TaromboNodeData).person)
                .map((n) => n.id),
        );

        return people.filter((p) => layoutNodeIds.has(p.id));
    }, [layout.nodes, people]);

    const maxGeneration = useMemo(
        () => Math.max(1, ...layoutPeople.map((person) => person.generation)),
        [layoutPeople],
    );
    const [hoveredId, setHoveredId] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (allowPan) {
            return;
        }

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
    }, [allowPan]);

    const focusId = hoveredId ?? selectedId;
    const connection = useMemo(
        () =>
            focusId
                ? getConnectionIds(layoutPeople, focusId)
                : new Set<string>(),
        [focusId, layoutPeople],
    );

    const searchResults = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();

        if (!enableSearch || query.length === 0) {
            return [];
        }

        return people
            .filter(
                (person) =>
                    person.name.toLowerCase().includes(query) ||
                    (person.alias ?? '').toLowerCase().includes(query),
            )
            .slice(0, 6);
    }, [enableSearch, people, searchQuery]);

    const handleSearchPick = (person: TaromboPerson) => {
        setSearchQuery('');
        onSearchSelect?.(person);
    };

    const nodes = useMemo(
        () =>
            layout.nodes.map((node) => {
                const base = node.data as TaromboNodeData | SectorNodeData;
                const person = (base as TaromboNodeData).person;
                const isSelected = Boolean(person) && person.id === selectedId;
                const isHovered = Boolean(person) && person.id === hoveredId;

                return {
                    ...node,
                    zIndex: isSelected || isHovered ? 60 : (node.zIndex ?? 1),
                    data: {
                        ...base,
                        selected: isSelected,
                        related: Boolean(person) && connection.has(person.id),
                        bubble:
                            bubbleTrigger === 'hover' ? isHovered : isSelected,
                    },
                };
            }),
        [layout, selectedId, hoveredId, connection, bubbleTrigger],
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

        if (data.person.id === centerPersonId) {
            onSelect(data.person);

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

    const diagramZoom = viewport.zoom || 1;
    const contentW = bounds.width * diagramZoom;
    const contentH = bounds.height * diagramZoom;
    const overflowX = Math.max(0, contentW - flowWidth);
    const overflowY = Math.max(0, contentH - flowHeight);

    const fracX =
        overflowX > 0
            ? Math.min(
                  1,
                  Math.max(
                      0,
                      (-bounds.x * diagramZoom - viewport.x) / overflowX,
                  ),
              )
            : 0;
    const fracY =
        overflowY > 0
            ? Math.min(
                  1,
                  Math.max(
                      0,
                      (-bounds.y * diagramZoom - viewport.y) / overflowY,
                  ),
              )
            : 0;

    const trackX = Math.max(0, flowWidth - SCROLLBAR_H_RESERVED);
    const thumbX =
        contentW > 0
            ? Math.max(
                  THUMB_MIN,
                  Math.min(trackX, trackX * (flowWidth / contentW)),
              )
            : THUMB_MIN;
    const travelX = Math.max(0, trackX - thumbX);

    const trackY = Math.max(0, flowHeight - SCROLLBAR_V_RESERVED);
    const thumbY =
        contentH > 0
            ? Math.max(
                  THUMB_MIN,
                  Math.min(trackY, trackY * (flowHeight / contentH)),
              )
            : THUMB_MIN;
    const travelY = Math.max(0, trackY - thumbY);

    const handleScrollX = (nextPositionPx: number) => {
        if (overflowX <= 0 || travelX <= 0) {
            return;
        }

        setViewport({
            x: -(nextPositionPx / travelX) * overflowX - bounds.x * diagramZoom,
            y: viewport.y,
            zoom: diagramZoom,
        });
    };

    const handleScrollY = (nextPositionPx: number) => {
        if (overflowY <= 0 || travelY <= 0) {
            return;
        }

        setViewport({
            x: viewport.x,
            y: -(nextPositionPx / travelY) * overflowY - bounds.y * diagramZoom,
            zoom: diagramZoom,
        });
    };

    return (
        <div className="relative mx-auto w-full max-w-2xl" ref={containerRef}>
            <div className="relative mx-auto mb-6 flex max-w-sm items-center rounded-full border border-tb-outline-variant bg-tb-surface-bright px-4 py-2.5 shadow-lg">
                <Search className="mr-2 h-4 w-4 shrink-0 text-tb-outline" />
                <input
                    type="text"
                    placeholder="Cari anggota atau marga..."
                    className="w-full border-none bg-transparent text-sm text-tb-on-surface focus:ring-0"
                    {...(enableSearch
                        ? {
                              value: searchQuery,
                              onChange: (
                                  event: ChangeEvent<HTMLInputElement>,
                              ) => setSearchQuery(event.target.value),
                              onKeyDown: (
                                  event: KeyboardEvent<HTMLInputElement>,
                              ) => {
                                  if (event.key === 'Enter') {
                                      const first = searchResults[0];

                                      if (first) {
                                          handleSearchPick(first);
                                      }
                                  } else if (event.key === 'Escape') {
                                      setSearchQuery('');
                                  }
                              },
                          }
                        : {})}
                />
                {enableSearch && searchQuery.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setSearchQuery('')}
                        className="ml-1 text-tb-outline hover:text-tb-on-surface"
                        aria-label="Bersihkan pencarian"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
                <button
                    type="button"
                    className="ml-2 text-tb-outline hover:text-tb-primary"
                    aria-label="Filter"
                >
                    <SlidersHorizontal className="h-4 w-4" />
                </button>
                {enableSearch && searchQuery.trim().length > 0 && (
                    <div className="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-xl border border-tb-outline-variant bg-tb-surface-bright shadow-xl">
                        {searchResults.length === 0 ? (
                            <p className="px-3 py-3 text-center text-xs text-tb-on-surface-variant">
                                Anggota tidak ditemukan.
                            </p>
                        ) : (
                            searchResults.map((person) => (
                                <SearchResultItem
                                    key={person.id}
                                    person={person}
                                    color={
                                        margas.find(
                                            (marga) =>
                                                marga.name === person.marga,
                                        )?.color ?? '#8f836f'
                                    }
                                    onPick={handleSearchPick}
                                />
                            ))
                        )}
                    </div>
                )}
            </div>
            <div className="relative aspect-square w-full" ref={squareRef}>
                {canGoBack && onBack && (
                    <button
                        type="button"
                        onClick={onBack}
                        className="absolute top-3 left-3 z-10 inline-flex items-center gap-1.5 rounded-full border border-tb-outline-variant bg-tb-surface-bright px-3 py-1.5 text-xs font-medium text-tb-on-surface shadow-md transition-colors hover:bg-tb-surface-container"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" /> Kembali
                    </button>
                )}
                {actionSlot && (
                    <div className="absolute top-3 right-3 z-10">
                        {actionSlot}
                    </div>
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
                    panOnDrag={allowPan}
                    panOnScroll={allowPan}
                    panOnScrollMode={PanOnScrollMode.Free}
                    selectionOnDrag={false}
                    zoomOnScroll={false}
                    zoomOnPinch={allowPan}
                    zoomOnDoubleClick={false}
                    fitView={!initialScrollable}
                    fitViewOptions={{ padding: 0.02 }}
                    minZoom={0.2}
                    maxZoom={2}
                    proOptions={{ hideAttribution: true }}
                    className="tarombo-flow"
                />
                <div className="absolute right-3 bottom-3 z-10 flex flex-col overflow-hidden rounded-lg border border-tb-outline-variant bg-tb-surface-bright shadow-md">
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
                {showScrollbars && overflowX > 0 && (
                    <DiagramScrollbar
                        orientation="horizontal"
                        trackLength={travelX}
                        thumbLength={thumbX}
                        positionPx={fracX * travelX}
                        onPositionChange={handleScrollX}
                    />
                )}
                {showScrollbars && overflowY > 0 && (
                    <DiagramScrollbar
                        orientation="vertical"
                        trackLength={travelY}
                        thumbLength={thumbY}
                        positionPx={fracY * travelY}
                        onPositionChange={handleScrollY}
                    />
                )}
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
                    {margas.map((marga) => {
                        const chip = (
                            <>
                                <span
                                    className="h-2.5 w-2.5 rounded-sm"
                                    style={{ backgroundColor: marga.color }}
                                />
                                <span className="text-[11px] font-medium text-tb-on-surface">
                                    {marga.name}
                                </span>
                            </>
                        );

                        return onMargaClick ? (
                            <button
                                key={marga.name}
                                type="button"
                                onClick={() => onMargaClick(marga.name)}
                                title={`Tampilkan kepala tertua marga ${marga.name} sebagai pusat`}
                                className="flex cursor-pointer items-center gap-1.5 rounded-full bg-tb-surface-bright px-3 py-1.5 shadow-sm transition-colors hover:bg-tb-surface-container"
                            >
                                {chip}
                            </button>
                        ) : (
                            <span
                                key={marga.name}
                                className="flex items-center gap-1.5 rounded-full bg-tb-surface-bright px-3 py-1.5 shadow-sm"
                            >
                                {chip}
                            </span>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
