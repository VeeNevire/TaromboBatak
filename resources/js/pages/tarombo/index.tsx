import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { TaromboDiagram } from '@/components/landing/tarombo-diagram';
import { Input } from '@/components/ui/input';
import { buildTaromboPeople } from '@/data/tarombo-tree';
import type { MargaInfo, TaromboPersonRow } from '@/data/tarombo-tree';
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
};

export default function TaromboIndex({ people: rows, margas }: Props) {
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [search, setSearch] = useState('');

    const people = buildTaromboPeople(rows);
    const rootPerson = people.find((p) => !p.parentId) ?? people[0];
    const [centerPersonId, setCenterPersonId] = useState<string>(rootPerson?.id ?? '');

    const searchSelect = (value: string) => {
        const person = people.find((p) => p.name.toLowerCase().includes(value.toLowerCase()));

        if (person) {
            setSelectedId(person.id);
            setCenterPersonId(person.id);
        }
    };

    const handlePersonSelect = (person: any) => {
        setSelectedId(person.id);
        setCenterPersonId(person.id);
    };

    return (
        <>
            <Head title="Pohon Tarombo" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        Pohon Tarombo
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Visualisasi silsilah keluarga langsung dari database. Klik anggota untuk melihat detail.
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
                    <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                        <TaromboDiagram
                            onSelect={handlePersonSelect}
                            onPaneClick={() => setSelectedId(null)}
                            selectedId={selectedId ?? undefined}
                            centerPersonId={centerPersonId}
                            people={people}
                            margas={margas}
                        />
                    </div>
                </div>

                {people.length === 0 && (
                    <div className="mx-auto max-w-md rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center">
                        <p className="text-sm text-tb-on-surface-variant">
                            Belum ada data tarombo. Tambahkan anggota terlebih dahulu.
                        </p>
                    </div>
                )}
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
