import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Info } from 'lucide-react';
import { useMemo, useState } from 'react';
import { TaromboDiagram } from '@/components/landing/tarombo-diagram';
import {
    buildTaromboPeople,
    findPerson,
    oldestOfMarga,
} from '@/data/tarombo-tree';
import type {
    MargaInfo,
    TaromboPerson,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import { home } from '@/routes';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    truncated: boolean;
    initialPersonId?: string;
};

const FULL_PAGE_DEPTH = 11;

export default function TaromboPublicFullscreen({
    people: rows,
    margas,
    truncated,
    initialPersonId,
}: Props) {
    const people = useMemo(() => buildTaromboPeople(rows), [rows]);
    const rootPerson = useMemo(
        () => people.find((person) => !person.parentId) ?? people[0],
        [people],
    );
    const [centerPersonId, setCenterPersonId] = useState<string>(
        findPerson(people, initialPersonId ?? '')?.id ?? rootPerson?.id ?? '',
    );
    const [history, setHistory] = useState<string[]>([]);

    const focusPerson = (person: TaromboPerson) => {
        if (person.id === centerPersonId) {
            return;
        }

        setHistory((previous) => [...previous, centerPersonId]);
        setCenterPersonId(person.id);
    };

    const handleBack = () => {
        if (history.length === 0) {
            return;
        }

        const previousId = history[history.length - 1];

        setHistory((previous) => previous.slice(0, -1));
        setCenterPersonId(previousId);
    };

    return (
        <div className="bg-tb-surface font-body text-tb-on-surface antialiased">
            <Head title="Pohon Tarombo" />
            <div className="flex min-h-screen flex-col">
                <header className="flex items-center justify-between gap-4 border-b border-tb-outline-variant bg-tb-surface-bright px-4 py-3 md:px-6">
                    <Link
                        href={home()}
                        className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-tb-outline-variant bg-tb-surface-bright px-3 py-1.5 text-xs font-medium text-tb-on-surface shadow-sm transition-colors hover:bg-tb-surface-container"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" /> Kembali ke Beranda
                    </Link>
                    <div className="min-w-0 text-center">
                        <p className="font-display text-base leading-tight font-bold">
                            Pohon Tarombo
                        </p>
                        <p className="truncate text-[10px] text-tb-on-surface-variant">
                            Arahkan kursor ke anggota untuk melihat detail •
                            klik untuk menjadikan pusat
                        </p>
                    </div>
                    {truncated ? (
                        <span
                            className="hidden items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-[10px] font-medium text-amber-800 sm:inline-flex"
                            title="Data yang ditampilkan dibatasi agar tetap cepat dimuat."
                        >
                            <Info className="h-3 w-3" /> Data dibatasi
                        </span>
                    ) : (
                        <span className="hidden w-[110px] sm:block" />
                    )}
                </header>

                <main className="min-h-0 flex-1 px-4 py-8 md:px-6">
                    {people.length === 0 ? (
                        <div className="mx-auto mt-16 max-w-md rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center">
                            <p className="text-sm text-tb-on-surface-variant">
                                Belum ada data tarombo.
                            </p>
                        </div>
                    ) : (
                        <div className="mx-auto max-w-5xl rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                            <TaromboDiagram
                                onSelect={focusPerson}
                                onBack={handleBack}
                                canGoBack={history.length > 0}
                                selectedId={centerPersonId}
                                centerPersonId={centerPersonId}
                                context="descendants"
                                maxDepth={FULL_PAGE_DEPTH}
                                people={people}
                                margas={margas}
                                allowPan
                                showScrollbars
                                initialScrollable
                                bubbleTrigger="hover"
                                enableSearch
                                onSearchSelect={focusPerson}
                                onMargaClick={(margaName) => {
                                    const person = oldestOfMarga(
                                        people,
                                        margaName,
                                    );

                                    if (person) {
                                        focusPerson(person);
                                    }
                                }}
                            />
                        </div>
                    )}
                </main>
            </div>
        </div>
    );
}
