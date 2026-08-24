import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ExternalLink,
    Maximize2,
    Minimize2,
    Minus,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { TaromboDiagram } from '@/components/landing/tarombo-diagram';
import { DescendantsTree } from '@/components/people/descendants-tree';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { buildTaromboPeople } from '@/data/tarombo-tree';
import type {
    MargaInfo,
    TaromboPerson,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';
import peopleRoutes from '@/routes/people';
import tarombo from '@/routes/tarombo';

type FullscreenView = 'diagram' | 'tree';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    fullscreen?: boolean;
    fullscreenView?: FullscreenView;
};

const ANCESTOR_DEPTH = 4;

function ancestorPath(
    people: TaromboPerson[],
    personId: string,
): TaromboPerson[] {
    const byId = new Map(people.map((person) => [person.id, person]));
    const selected = byId.get(personId);

    if (!selected) {
        return [];
    }

    const path = [selected];
    const visited = new Set<string>([selected.id]);
    let current = selected;

    for (let depth = 0; depth < ANCESTOR_DEPTH; depth += 1) {
        const parent = current.parentId
            ? byId.get(current.parentId)
            : undefined;

        if (!parent || visited.has(parent.id)) {
            break;
        }

        path.unshift(parent);
        visited.add(parent.id);
        current = parent;
    }

    return path;
}

export function TaromboExplorer({
    people: rows,
    margas,
    fullscreen = false,
    fullscreenView = 'diagram',
}: Props) {
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const [history, setHistory] = useState<string[]>([]);
    const [expanded, setExpanded] = useState<'diagram' | 'tree' | null>(null);
    const [treeZoom, setTreeZoom] = useState(1);
    const [ancestorFocusId, setAncestorFocusId] = useState<string | null>(null);

    const clampZoom = (value: number) =>
        Math.round(Math.min(2, Math.max(0.5, value)) * 100) / 100;

    const treeZoomControls = (
        <div className="flex flex-col overflow-hidden rounded-lg border border-tb-outline-variant bg-tb-surface-bright/95 shadow-md backdrop-blur">
            <button
                type="button"
                onClick={() => setTreeZoom((z) => clampZoom(z + 0.25))}
                aria-label="Perbesar pohon"
                title="Perbesar pohon"
                className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container"
            >
                <Plus className="size-4" />
            </button>
            <span className="flex h-6 w-9 items-center justify-center border-y border-tb-outline-variant text-[11px] font-medium text-tb-on-surface-variant">
                {Math.round(treeZoom * 100)}%
            </span>
            <button
                type="button"
                onClick={() => setTreeZoom((z) => clampZoom(z - 0.25))}
                aria-label="Perkecil pohon"
                title="Perkecil pohon"
                className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container"
            >
                <Minus className="size-4" />
            </button>
        </div>
    );

    const people = buildTaromboPeople(rows);
    const rootPerson = people.find((p) => !p.parentId) ?? people[0];
    const [centerPersonId, setCenterPersonId] = useState<string>(
        rootPerson?.id ?? '',
    );
    const centerPerson = people.find((p) => p.id === centerPersonId);
    const normalizedSearch = search.trim().toLowerCase();
    const searchResults = normalizedSearch
        ? people
              .filter((person) =>
                  person.name.toLowerCase().includes(normalizedSearch),
              )
              .sort((a, b) => {
                  const aStarts = a.name
                      .toLowerCase()
                      .startsWith(normalizedSearch);
                  const bStarts = b.name
                      .toLowerCase()
                      .startsWith(normalizedSearch);

                  if (aStarts !== bStarts) {
                      return aStarts ? -1 : 1;
                  }

                  return a.name.localeCompare(b.name, 'id');
              })
              .slice(0, 10)
        : [];
    const ancestorPeople = ancestorFocusId
        ? ancestorPath(people, ancestorFocusId)
        : [];
    const treePeople = ancestorPeople.length > 0 ? ancestorPeople : people;
    const treeCenterId = ancestorPeople[0]?.id ?? centerPersonId;
    const ancestorFocusPerson = ancestorFocusId
        ? people.find((person) => person.id === ancestorFocusId)
        : undefined;
    const treeHasChildren = treePeople.some(
        (person) => person.parentId === treeCenterId,
    );

    const searchSelect = (person: TaromboPerson) => {
        if (person.id !== centerPersonId) {
            setHistory((prev) => [...prev, centerPersonId]);
        }

        setSelectedId(person.id);
        setCenterPersonId(person.id);
        setAncestorFocusId(person.id);
        setSearch(person.name);
        setSearchOpen(false);
    };

    const handleBack = () => {
        if (history.length === 0) {
            return;
        }

        const prev = history[history.length - 1];
        setHistory((prevHistory) => prevHistory.slice(0, -1));
        setSelectedId(prev === rootPerson?.id ? null : prev);
        setCenterPersonId(prev);
        setAncestorFocusId(null);
        setSearch('');
    };

    const handlePersonSelect = (id: string) => {
        setAncestorFocusId(null);

        if (id === centerPersonId) {
            handleBack();

            return;
        }

        setHistory((prev) => [...prev, centerPersonId]);
        setSelectedId(id);
        setCenterPersonId(id);
    };

    const handleDiagramSelect = (person: TaromboPerson) =>
        handlePersonSelect(person.id);

    const backButton = (
        <Link
            href={tarombo.index()}
            aria-label="Kembali ke Pohon Tarombo"
            title="Kembali"
            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
        >
            <ArrowLeft className="size-4" />
        </Link>
    );

    const expandButton = (card: 'diagram' | 'tree', isExpanded: boolean) => (
        <button
            type="button"
            onClick={() => setExpanded(isExpanded ? null : card)}
            aria-label={isExpanded ? 'Kecilkan tampilan' : 'Perbesar tampilan'}
            title={isExpanded ? 'Kecilkan' : 'Perbesar'}
            className="absolute top-3 right-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
        >
            {isExpanded ? (
                <Minimize2 className="size-4" />
            ) : (
                <Maximize2 className="size-4" />
            )}
        </button>
    );

    const newTabButton = (view: FullscreenView) => (
        <button
            type="button"
            onClick={() =>
                window.open(
                    tarombo.fullscreen({ view }).url,
                    '_blank',
                    'noopener',
                )
            }
            aria-label="Buka di tab baru"
            title="Buka di tab baru"
            className="absolute top-3 right-14 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
        >
            <ExternalLink className="size-4" />
        </button>
    );

    const renderDiagramCard = (isExpanded: boolean) => (
        <div
            className={cn(
                'relative overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright',
                fullscreen && 'flex min-h-0 flex-col',
            )}
        >
            {!fullscreen && expandButton('diagram', isExpanded)}
            {!fullscreen && newTabButton('diagram')}
            <div
                className={cn(
                    '[scrollbar-gutter:stable] overflow-y-scroll p-4',
                    fullscreen ? 'min-h-0 flex-1' : 'max-h-[600px]',
                    !fullscreen && isExpanded && 'max-h-[70vh]',
                )}
            >
                <TaromboDiagram
                    onSelect={handleDiagramSelect}
                    onPaneClick={() => setSelectedId(null)}
                    onBack={handleBack}
                    canGoBack={history.length > 0}
                    selectedId={selectedId ?? undefined}
                    centerPersonId={centerPersonId}
                    people={people}
                    margas={margas}
                    context="descendants"
                    allowPan={fullscreen}
                    showScrollbars={fullscreen}
                    initialScrollable={fullscreen}
                />
            </div>
        </div>
    );

    const noChildrenNotice = (
        <div className="flex flex-col items-center gap-3 pb-4">
            <p className="text-center text-sm text-tb-on-surface-variant">
                Belum memiliki keturunan tercatat. Klik node lagi untuk kembali.
            </p>
            {centerPerson && (
                <Button
                    asChild
                    size="sm"
                    variant="outline"
                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface hover:bg-tb-surface-container hover:text-tb-on-surface"
                >
                    <Link
                        href={peopleRoutes.show({
                            person: Number(centerPerson.id),
                        })}
                    >
                        Update Silsilah
                    </Link>
                </Button>
            )}
        </div>
    );

    const renderTreeCard = (isExpanded: boolean) => (
        <div
            className={cn(
                'relative overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright',
                fullscreen && 'flex min-h-0 flex-col',
            )}
        >
            {!fullscreen && expandButton('tree', isExpanded)}
            {!fullscreen && newTabButton('tree')}
            <div
                className={cn(
                    '[scrollbar-gutter:stable] overflow-auto p-4',
                    fullscreen ? 'min-h-0 flex-1' : 'max-h-[600px]',
                    !fullscreen && isExpanded && 'max-h-[70vh]',
                )}
            >
                <div className="mb-4 grid grid-cols-[1fr_auto_1fr] items-center border-b border-tb-outline-variant pb-3">
                    {fullscreen ? (
                        <Link
                            href={tarombo.index()}
                            className="inline-flex w-fit items-center gap-1.5 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-xs font-semibold text-tb-on-surface transition-colors hover:bg-tb-surface-container"
                        >
                            <ArrowLeft className="size-4" /> Kembali
                        </Link>
                    ) : (
                        <span />
                    )}
                    <div className="min-w-0 text-center">
                        <h3 className="font-display text-lg font-bold text-tb-on-surface">
                            Silsilah Keturunan
                        </h3>
                        <p className="mt-1 max-w-64 truncate text-xs text-tb-on-surface-variant">
                            {ancestorFocusPerson
                                ? `Jalur ${ANCESTOR_DEPTH} tingkat leluhur dari ${ancestorFocusPerson.name}`
                                : `Pohon vertikal dari ${centerPerson?.name ?? 'Leluhur Utama'}`}
                        </p>
                    </div>
                    <span />
                </div>
                <div style={{ zoom: treeZoom }}>
                    <DescendantsTree
                        key={`${treeCenterId}-${ancestorFocusId ?? 'branch'}`}
                        people={treePeople}
                        centerId={treeCenterId}
                        onSelect={handlePersonSelect}
                        highlightId={selectedId}
                    />
                </div>
                {!treeHasChildren && !ancestorFocusId && noChildrenNotice}
            </div>
            {treeHasChildren && (
                <div className="absolute bottom-3 left-3 z-10">
                    {treeZoomControls}
                </div>
            )}
        </div>
    );

    if (people.length === 0) {
        return (
            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <div className="mx-auto w-full max-w-md rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center">
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface">
                        Pohon Tarombo
                    </h1>
                    <p className="mt-2 text-sm text-tb-on-surface-variant">
                        Belum ada data tarombo. Tambahkan anggota terlebih
                        dahulu.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'flex flex-col gap-6 p-4 md:p-6',
                fullscreen ? 'h-dvh gap-4 md:p-4' : 'h-full flex-1',
            )}
        >
            <div className={cn('flex', fullscreen && 'items-center gap-3')}>
                {fullscreen && fullscreenView === 'diagram' && backButton}
                <div>
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        Pohon Tarombo
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Visualisasi silsilah keluarga langsung dari database.
                        Klik anggota untuk melihat detail.
                    </p>
                </div>
            </div>

            <div
                className={cn(
                    'w-full',
                    fullscreen && 'flex min-h-0 flex-1 flex-col',
                )}
            >
                <div
                    className="relative mx-auto mb-4 w-full max-w-md"
                    onBlur={(event) => {
                        if (
                            !event.currentTarget.contains(event.relatedTarget)
                        ) {
                            setSearchOpen(false);
                        }
                    }}
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-on-surface-variant" />
                    <Input
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setSearchOpen(true);
                        }}
                        onFocus={() => setSearchOpen(true)}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') {
                                setSearchOpen(false);
                            }
                        }}
                        placeholder="Cari nama anggota..."
                        aria-label="Cari nama anggota"
                        aria-expanded={searchOpen && normalizedSearch !== ''}
                        aria-controls="tarombo-search-results"
                        className="border-tb-outline-variant bg-tb-surface-bright pl-9 focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    {searchOpen && normalizedSearch !== '' && (
                        <div
                            id="tarombo-search-results"
                            role="listbox"
                            className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-tb-outline-variant bg-tb-surface-bright p-1 shadow-lg"
                        >
                            {searchResults.length > 0 ? (
                                searchResults.map((person) => (
                                    <button
                                        key={person.id}
                                        type="button"
                                        role="option"
                                        aria-selected={
                                            person.id === ancestorFocusId
                                        }
                                        onClick={() => searchSelect(person)}
                                        className="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left transition-colors hover:bg-tb-surface-container focus-visible:bg-tb-surface-container focus-visible:outline-none"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-tb-on-surface">
                                                {person.name}
                                            </span>
                                            <span className="block truncate text-xs text-tb-on-surface-variant">
                                                {person.marga ||
                                                    'Marga belum dicatat'}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-[10px] font-semibold tracking-wide text-tb-primary uppercase">
                                            Lihat leluhur
                                        </span>
                                    </button>
                                ))
                            ) : (
                                <p className="px-3 py-4 text-center text-sm text-tb-on-surface-variant">
                                    Nama tidak ditemukan.
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {fullscreen ? (
                    <div className="min-h-0 flex-1">
                        {fullscreenView === 'diagram'
                            ? renderDiagramCard(false)
                            : renderTreeCard(false)}
                    </div>
                ) : (
                    <>
                        {/* Desktop: Side by side */}
                        {expanded === null ? (
                            <div className="hidden gap-6 lg:grid lg:grid-cols-2">
                                {renderDiagramCard(false)}
                                {renderTreeCard(false)}
                            </div>
                        ) : (
                            <div className="hidden lg:block">
                                {expanded === 'diagram'
                                    ? renderDiagramCard(true)
                                    : renderTreeCard(true)}
                            </div>
                        )}

                        {/* Mobile: Tabs */}
                        <div className="lg:hidden">
                            <Tabs defaultValue="tree" className="w-full">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger value="diagram">
                                        Diagram Radial
                                    </TabsTrigger>
                                    <TabsTrigger value="tree">
                                        Silsilah Pohon
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="diagram">
                                    <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                                        <TaromboDiagram
                                            onSelect={handleDiagramSelect}
                                            onPaneClick={() =>
                                                setSelectedId(null)
                                            }
                                            onBack={handleBack}
                                            canGoBack={history.length > 0}
                                            selectedId={selectedId ?? undefined}
                                            centerPersonId={centerPersonId}
                                            people={people}
                                            margas={margas}
                                            context="descendants"
                                        />
                                    </div>
                                </TabsContent>
                                <TabsContent value="tree">
                                    <div className="relative overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                                        <div className="mb-4 border-b border-tb-outline-variant pb-3 text-center">
                                            <h3 className="font-display text-lg font-bold text-tb-on-surface">
                                                Silsilah Keturunan
                                            </h3>
                                            <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                {ancestorFocusPerson
                                                    ? `Jalur ${ANCESTOR_DEPTH} tingkat leluhur dari ${ancestorFocusPerson.name}`
                                                    : `Pohon vertikal dari ${centerPerson?.name ?? 'Leluhur Utama'}`}
                                            </p>
                                        </div>
                                        <div style={{ zoom: treeZoom }}>
                                            <DescendantsTree
                                                key={`${treeCenterId}-${ancestorFocusId ?? 'branch'}`}
                                                people={treePeople}
                                                centerId={treeCenterId}
                                                onSelect={handlePersonSelect}
                                                highlightId={selectedId}
                                            />
                                        </div>
                                        {!treeHasChildren &&
                                            !ancestorFocusId &&
                                            noChildrenNotice}
                                        {treeHasChildren && (
                                            <div className="absolute bottom-3 left-3 z-10">
                                                {treeZoomControls}
                                            </div>
                                        )}
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
