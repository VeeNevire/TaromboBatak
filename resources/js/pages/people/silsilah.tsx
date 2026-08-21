import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Copy, Pencil } from 'lucide-react';
import { useState } from 'react';
import { DescendantsTree } from '@/components/people/descendants-tree';
import { buildTaromboPeople } from '@/data/tarombo-tree';
import type { TaromboPersonRow } from '@/data/tarombo-tree';
import { dashboard } from '@/routes';
import familyTrees from '@/routes/family-trees';
import people from '@/routes/people';

type Props = {
    people: TaromboPersonRow[];
    centerPersonId: string;
    person: {
        id: string;
        name: string;
        alias: string | null;
        marga: string;
        birthOrder: number | null;
    };
    familyTree?: {
        id: number;
        name: string;
    };
    canEditFamilyTree?: boolean;
};

const FOREST = '#2F4538';
const INK_SOFT = '#5B6A61';
const LINE = '#A79E8C';

export default function PersonSilsilah(props: Props) {
    const tree = buildTaromboPeople(props.people);
    const [centerPersonId, setCenterPersonId] = useState<string>(
        props.centerPersonId,
    );
    const [history, setHistory] = useState<string[]>([]);

    const center =
        tree.find((person) => person.id === centerPersonId) ?? tree[0];

    const handleSelect = (id: string) => {
        if (id === centerPersonId) {
            return;
        }

        setHistory((prev) => [...prev, centerPersonId]);
        setCenterPersonId(id);
    };

    const handleBack = () => {
        if (history.length === 0) {
            return;
        }

        const prev = history[history.length - 1];
        setHistory((prevHistory) => prevHistory.slice(0, -1));
        setCenterPersonId(prev);
    };

    return (
        <>
            <Head title={`Silsilah ${center?.name ?? props.person.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <div className="flex items-center justify-between gap-3">
                        <Link
                            href={people.index()}
                            className="flex w-fit items-center gap-1.5 text-sm text-tb-on-surface-variant transition-colors hover:text-tb-primary"
                        >
                            <ArrowLeft className="size-4" /> Kembali ke Data
                            Anggota
                        </Link>
                        {props.familyTree && props.canEditFamilyTree !== false && (
                            <div className="flex items-center gap-2">
                                <Link
                                    href={familyTrees.duplicate(
                                        props.familyTree.id,
                                    )}
                                    method="post"
                                    as="button"
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-tb-outline-variant px-3 py-1.5 text-sm font-medium text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary"
                                >
                                    <Copy className="size-3.5" /> Buat Versi
                                    Alternatif
                                </Link>
                                <Link
                                    href={familyTrees.edit(props.familyTree.id)}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-tb-outline-variant px-3 py-1.5 text-sm font-medium text-tb-on-surface transition-colors hover:border-tb-primary hover:text-tb-primary"
                                >
                                    <Pencil className="size-3.5" /> Ubah
                                    Struktur
                                </Link>
                            </div>
                        )}
                    </div>
                    <div style={{ color: FOREST }} className="text-center">
                        <p
                            className="mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase"
                            style={{ color: INK_SOFT }}
                        >
                            Jejak Silsilah Keluarga
                        </p>
                        <h1 className="font-display text-3xl font-semibold text-tb-on-surface sm:text-4xl">
                            {center?.name ?? props.person.name}
                        </h1>
                        <div className="mt-3 flex items-center justify-center gap-3">
                            <span
                                className="h-px w-7"
                                style={{ backgroundColor: LINE }}
                            />
                            <p className="font-display text-base text-tb-on-surface-variant italic sm:text-lg">
                                Anak ke {center?.birthOrder ?? '?'} · Marga{' '}
                                {center?.marga ?? props.person.marga}
                            </p>
                            <span
                                className="h-px w-7"
                                style={{ backgroundColor: LINE }}
                            />
                        </div>
                    </div>
                </div>

                <div className="relative overflow-x-auto rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4 sm:p-6">
                    {history.length > 0 && (
                        <button
                            type="button"
                            onClick={handleBack}
                            className="absolute top-3 left-3 z-10 inline-flex items-center gap-1.5 rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 px-3 py-1.5 text-xs font-medium text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
                        >
                            <ArrowLeft className="size-3.5" /> Kembali
                        </button>
                    )}
                    <DescendantsTree
                        key={centerPersonId}
                        people={tree}
                        centerId={centerPersonId}
                        onSelect={handleSelect}
                    />
                </div>
            </div>
        </>
    );
}

PersonSilsilah.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Silsilah Keluarga', href: people.index() },
    ],
};
