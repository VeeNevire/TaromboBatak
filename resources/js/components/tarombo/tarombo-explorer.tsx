import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ExternalLink,
    Maximize2,
    Minimize2,
    Minus,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import { TaromboDiagram } from '@/components/landing/tarombo-diagram';
import { DescendantsTree } from '@/components/people/descendants-tree';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { buildTaromboPeople } from '@/data/tarombo-tree';
import type {
    MargaInfo,
    TaromboPerson,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';
import tarombo from '@/routes/tarombo';

type FullscreenView = 'diagram' | 'tree';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    fullscreen?: boolean;
    fullscreenView?: FullscreenView;
};

export function TaromboExplorer({
    people: rows,
    margas,
    fullscreen = false,
    fullscreenView = 'diagram',
}: Props) {
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [history, setHistory] = useState<string[]>([]);
    const [expanded, setExpanded] = useState<'diagram' | 'tree' | null>(null);
    const [treeZoom, setTreeZoom] = useState(1);

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
    const hasChildren = people.some((p) => p.parentId === centerPersonId);

    const searchSelect = (value: string) => {
        const person = people.find((p) =>
            p.name.toLowerCase().includes(value.toLowerCase()),
        );

        if (person && person.id !== centerPersonId) {
            setHistory((prev) => [...prev, centerPersonId]);
            setSelectedId(person.id);
            setCenterPersonId(person.id);
        }
    };

    const handleBack = () => {
        if (history.length === 0) {
            return;
        }

        const prev = history[history.length - 1];
        setHistory((prevHistory) => prevHistory.slice(0, -1));
        setSelectedId(prev === rootPerson?.id ? null : prev);
        setCenterPersonId(prev);
        setSearch('');
    };

    const handlePersonSelect = (id: string) => {
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
                />
            </div>
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
                <div className="mb-4 border-b border-tb-outline-variant pb-3 text-center">
                    <h3 className="font-display text-lg font-bold text-tb-on-surface">
                        Silsilah Keturunan
                    </h3>
                    <p className="mt-1 text-xs text-tb-on-surface-variant">
                        Pohon vertikal dari{' '}
                        {centerPerson?.name ?? 'Leluhur Utama'}
                    </p>
                </div>
                <div style={{ zoom: treeZoom }}>
                    <DescendantsTree
                        key={centerPersonId}
                        people={people}
                        centerId={centerPersonId}
                        onSelect={handlePersonSelect}
                        highlightId={selectedId}
                    />
                </div>
                {!hasChildren && (
                    <p className="pb-4 text-center text-sm text-tb-on-surface-variant">
                        Belum memiliki keturunan tercatat. Klik node lagi untuk
                        kembali.
                    </p>
                )}
            </div>
            {hasChildren && (
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
                {fullscreen && backButton}
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
                <div className="mx-auto mb-4 flex max-w-md items-center gap-2">
                    <Input
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            searchSelect(e.target.value);
                        }}
                        placeholder="Cari anggota..."
                        className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                    />
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
                                                Pohon vertikal dari{' '}
                                                {centerPerson?.name ??
                                                    'Leluhur Utama'}
                                            </p>
                                        </div>
                                        <div style={{ zoom: treeZoom }}>
                                            <DescendantsTree
                                                key={centerPersonId}
                                                people={people}
                                                centerId={centerPersonId}
                                                onSelect={handlePersonSelect}
                                                highlightId={selectedId}
                                            />
                                        </div>
                                        {!hasChildren && (
                                            <p className="pb-4 text-center text-sm text-tb-on-surface-variant">
                                                Belum memiliki keturunan
                                                tercatat. Klik node lagi untuk
                                                kembali.
                                            </p>
                                        )}
                                        {hasChildren && (
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
