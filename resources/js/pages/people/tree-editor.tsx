import { Head, Link, useForm } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { ArrowLeft, ChevronDown, ChevronUp, Save } from 'lucide-react';
import { dashboard } from '@/routes';
import familyTrees from '@/routes/family-trees';

type Entry = {
    id: number;
    personId: number;
    name: string;
    gender: string | null;
    fatherNodeId: number | null;
    birthOrder: number | null;
    chain: string | null;
};

type Props = {
    familyTree: {
        id: number;
        name: string;
        sourceName: string | null;
    };
    entries: Entry[];
};

export default function TreeEditor({ familyTree, entries }: Props) {
    const prefersReducedMotion = useReducedMotion();
    const { data, setData, put, processing, errors } = useForm({
        entries: entries.map((entry) => ({
            id: entry.id,
            father_node_id: entry.fatherNodeId,
            birth_order: entry.birthOrder,
        })),
    });
    const possibleFathers = entries.filter(
        (entry) => entry.gender === 'L' || entry.gender === null,
    );
    const entriesById = new Map(entries.map((entry) => [entry.id, entry]));
    const editorEntries = data.entries.flatMap((draft, index) => {
        const entry = entriesById.get(draft.id);

        return entry ? [{ entry, draft, index }] : [];
    });

    const updateEntry = (
        id: number,
        field: 'father_node_id' | 'birth_order',
        value: number | null,
    ) => {
        setData(
            'entries',
            data.entries.map((entry) =>
                entry.id === id ? { ...entry, [field]: value } : entry,
            ),
        );
    };

    const siblingIndices = (fatherNodeId: number | null) =>
        data.entries
            .map((entry, index) => ({ entry, index }))
            .filter(({ entry }) => entry.father_node_id === fatherNodeId)
            .sort(
                (left, right) =>
                    (left.entry.birth_order ?? Number.MAX_SAFE_INTEGER) -
                        (right.entry.birth_order ?? Number.MAX_SAFE_INTEGER) ||
                    left.index - right.index,
            )
            .map(({ index }) => index);

    const moveSibling = (index: number, direction: -1 | 1) => {
        const siblings = siblingIndices(data.entries[index].father_node_id);
        const position = siblings.indexOf(index);
        const target = position + direction;

        if (target < 0 || target >= siblings.length) {
            return;
        }

        const reorderedIds = siblings.map(
            (siblingIndex) => data.entries[siblingIndex].id,
        );
        [reorderedIds[position], reorderedIds[target]] = [
            reorderedIds[target],
            reorderedIds[position],
        ];
        const siblingOrders = new Map(
            reorderedIds.map((id, siblingIndex) => [id, siblingIndex + 1]),
        );
        const nextEntries = data.entries.map((entry) =>
            siblingOrders.has(entry.id)
                ? { ...entry, birth_order: siblingOrders.get(entry.id) ?? null }
                : entry,
        );
        const targetIndex = siblings[target];

        [nextEntries[index], nextEntries[targetIndex]] = [
            nextEntries[targetIndex],
            nextEntries[index],
        ];

        setData('entries', nextEntries);
    };

    return (
        <>
            <Head title={`Ubah ${familyTree.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Link
                            href={familyTrees.show(familyTree.id)}
                            className="inline-flex items-center gap-1.5 text-sm text-tb-on-surface-variant transition-colors hover:text-tb-primary"
                        >
                            <ArrowLeft className="size-4" /> Kembali ke silsilah
                        </Link>
                        <p className="mt-4 text-xs font-semibold tracking-[0.16em] text-tb-primary uppercase">
                            Versi Silsilah
                        </p>
                        <h1 className="font-display text-3xl text-tb-on-surface">
                            {familyTree.name}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            {familyTree.sourceName ??
                                'Ubah hubungan hanya pada versi ini. Biodata orang tetap bersama.'}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() =>
                            put(familyTrees.update.url(familyTree.id))
                        }
                        disabled={processing}
                        className="text-tb-on-primary inline-flex items-center justify-center gap-2 rounded-xl bg-tb-primary px-4 py-2.5 text-sm font-semibold transition-opacity disabled:opacity-60"
                    >
                        <Save className="size-4" />
                        {processing ? 'Menyimpan...' : 'Simpan Struktur'}
                    </button>
                </div>

                {errors.entries && (
                    <p className="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {errors.entries}
                    </p>
                )}

                <div className="overflow-x-auto rounded-2xl border border-tb-outline-variant bg-tb-surface-bright">
                    <table className="w-full min-w-[700px] text-left text-sm">
                        <thead className="border-b border-tb-outline-variant bg-tb-surface-container/50 text-xs tracking-wide text-tb-on-surface-variant uppercase">
                            <tr>
                                <th className="px-4 py-3 font-semibold">
                                    Tokoh
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Ayah pada Versi Ini
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Urutan Anak
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Chain Saat Ini
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-tb-outline-variant">
                            {editorEntries.map(({ entry, draft, index }) => (
                                <motion.tr
                                    key={entry.id}
                                    layout="position"
                                    transition={
                                        prefersReducedMotion
                                            ? { duration: 0 }
                                            : {
                                                  type: 'spring',
                                                  stiffness: 400,
                                                  damping: 35,
                                              }
                                    }
                                >
                                    <td className="px-4 py-3 font-medium text-tb-on-surface">
                                        {entry.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        <select
                                            value={draft.father_node_id ?? ''}
                                            onChange={(event) =>
                                                updateEntry(
                                                    entry.id,
                                                    'father_node_id',
                                                    event.target.value === ''
                                                        ? null
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                )
                                            }
                                            className="w-full rounded-lg border border-tb-outline-variant bg-tb-surface px-3 py-2 text-tb-on-surface outline-none focus:border-tb-primary"
                                        >
                                            <option value="">
                                                Tidak diketahui / akar
                                            </option>
                                            {possibleFathers
                                                .filter(
                                                    (father) =>
                                                        father.id !== entry.id,
                                                )
                                                .map((father) => (
                                                    <option
                                                        key={father.id}
                                                        value={father.id}
                                                    >
                                                        {father.name}
                                                    </option>
                                                ))}
                                        </select>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1.5">
                                            <input
                                                type="number"
                                                min="1"
                                                value={draft.birth_order ?? ''}
                                                onChange={(event) =>
                                                    updateEntry(
                                                        entry.id,
                                                        'birth_order',
                                                        event.target.value ===
                                                            ''
                                                            ? null
                                                            : Number(
                                                                  event.target
                                                                      .value,
                                                              ),
                                                    )
                                                }
                                                className="w-20 rounded-lg border border-tb-outline-variant bg-tb-surface px-3 py-2 text-tb-on-surface outline-none focus:border-tb-primary"
                                            />
                                            {(() => {
                                                const siblings = siblingIndices(
                                                    draft.father_node_id,
                                                );
                                                const position =
                                                    siblings.indexOf(index);

                                                return (
                                                    <span className="flex items-center gap-0.5">
                                                        <motion.button
                                                            type="button"
                                                            onClick={() =>
                                                                moveSibling(
                                                                    index,
                                                                    -1,
                                                                )
                                                            }
                                                            disabled={
                                                                position <= 0
                                                            }
                                                            aria-label="Pindah ke atas"
                                                            title="Pindah ke atas"
                                                            className="inline-flex size-8 items-center justify-center rounded-lg text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                                                            whileTap={
                                                                prefersReducedMotion
                                                                    ? undefined
                                                                    : {
                                                                          scale: 0.9,
                                                                      }
                                                            }
                                                        >
                                                            <ChevronUp className="size-4" />
                                                        </motion.button>
                                                        <motion.button
                                                            type="button"
                                                            onClick={() =>
                                                                moveSibling(
                                                                    index,
                                                                    1,
                                                                )
                                                            }
                                                            disabled={
                                                                position ===
                                                                    -1 ||
                                                                position ===
                                                                    siblings.length -
                                                                        1
                                                            }
                                                            aria-label="Pindah ke bawah"
                                                            title="Pindah ke bawah"
                                                            className="inline-flex size-8 items-center justify-center rounded-lg text-tb-outline transition-colors hover:bg-tb-surface-container hover:text-tb-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                                                            whileTap={
                                                                prefersReducedMotion
                                                                    ? undefined
                                                                    : {
                                                                          scale: 0.9,
                                                                      }
                                                            }
                                                        >
                                                            <ChevronDown className="size-4" />
                                                        </motion.button>
                                                    </span>
                                                );
                                            })()}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-tb-on-surface-variant">
                                        {entry.chain ?? 'Belum dihitung'}
                                    </td>
                                </motion.tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

TreeEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Silsilah', href: '#' },
        { title: 'Ubah Struktur', href: '#' },
    ],
};
