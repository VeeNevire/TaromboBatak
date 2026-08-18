import { Head } from '@inertiajs/react';
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
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
};

export default function TaromboIndex({ people: rows, margas }: Props) {
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [history, setHistory] = useState<string[]>([]);

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

    const handlePersonSelect = (id: string) => {
        if (id === centerPersonId) {
            return;
        }

        setHistory((prev) => [...prev, centerPersonId]);
        setSelectedId(id);
        setCenterPersonId(id);
    };

    const handleDiagramSelect = (person: TaromboPerson) =>
        handlePersonSelect(person.id);

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

    if (people.length === 0) {
        return (
            <>
                <Head title="Pohon Tarombo" />
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
            </>
        );
    }

    return (
        <>
            <Head title="Pohon Tarombo" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        Pohon Tarombo
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Visualisasi silsilah keluarga langsung dari database.
                        Klik anggota untuk melihat detail.
                    </p>
                </div>

                <div className="w-full">
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

                    {/* Desktop: Side by side */}
                    <div className="hidden gap-6 lg:grid lg:grid-cols-2">
                        {/* Left: Diagram Radial */}
                        <div className="overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright">
                            <div className="max-h-[600px] overflow-y-auto p-4">
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

                        {/* Right: Silsilah Tree */}
                        <div className="overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright">
                            <div className="max-h-[600px] overflow-y-auto p-4">
                                <div className="mb-4 border-b border-tb-outline-variant pb-3 text-center">
                                    <h3 className="font-display text-lg font-bold text-tb-on-surface">
                                        Silsilah Keturunan
                                    </h3>
                                    <p className="mt-1 text-xs text-tb-on-surface-variant">
                                        Pohon vertikal dari{' '}
                                        {centerPerson?.name ?? 'Leluhur Utama'}
                                    </p>
                                </div>
                                {hasChildren ? (
                                    <DescendantsTree
                                        people={people}
                                        centerId={centerPersonId}
                                        onSelect={handlePersonSelect}
                                        highlightId={selectedId}
                                    />
                                ) : (
                                    <div className="py-8 text-center text-sm text-tb-on-surface-variant">
                                        <p className="mb-2 text-base font-medium text-tb-on-surface">
                                            {centerPerson?.name ??
                                                'Anggota ini'}
                                        </p>
                                        <p>
                                            belum memiliki keturunan tercatat.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

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
                            </TabsContent>
                            <TabsContent value="tree">
                                <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
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
                                    {hasChildren ? (
                                        <DescendantsTree
                                            people={people}
                                            centerId={centerPersonId}
                                            onSelect={handlePersonSelect}
                                            highlightId={selectedId}
                                        />
                                    ) : (
                                        <div className="py-8 text-center text-sm text-tb-on-surface-variant">
                                            <p className="mb-2 text-base font-medium text-tb-on-surface">
                                                {centerPerson?.name ??
                                                    'Anggota ini'}
                                            </p>
                                            <p>
                                                belum memiliki keturunan
                                                tercatat.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </>
    );
}

TaromboIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pohon Tarombo', href: tarombo.index() },
    ],
};
