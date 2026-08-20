import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight, Clock3, TreePine } from 'lucide-react';
import { dashboard } from '@/routes';
import familyTrees from '@/routes/family-trees';
import people from '@/routes/people';

type Props = {
    person: {
        id: number;
        name: string;
    };
    familyTrees: {
        id: number;
        name: string;
        rootName: string | null;
        updatedAt: string;
    }[];
};

export default function TreeSelector({ person, familyTrees: trees }: Props) {
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <>
            <Head title={`Pilih Silsilah ${person.name}`} />

            <div className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={people.index()}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-tb-on-surface-variant transition-colors hover:text-tb-primary"
                >
                    <ArrowLeft className="size-4" /> Kembali ke Data Anggota
                </Link>

                <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-5 sm:p-7">
                    <div className="flex items-start gap-3">
                        <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-tb-primary/10 text-tb-primary">
                            <TreePine className="size-5" />
                        </span>
                        <div>
                            <p className="text-xs font-semibold tracking-[0.16em] text-tb-primary uppercase">
                                Pilih Versi Silsilah
                            </p>
                            <h1 className="mt-1 font-display text-3xl text-tb-on-surface">
                                {person.name}
                            </h1>
                            <p className="mt-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                Tokoh ini tercatat pada beberapa versi. Pilih
                                versi yang ingin ditampilkan agar hubungan
                                keluarga tidak tercampur.
                            </p>
                        </div>
                    </div>

                    <ol className="mt-6 grid gap-3">
                        {trees.map((tree, index) => (
                            <li key={tree.id}>
                                <Link
                                    href={familyTrees.show(tree.id)}
                                    className="group flex items-center gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-container/35 p-4 transition-colors hover:border-tb-primary/50 hover:bg-tb-primary/5"
                                >
                                    <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-tb-surface-bright text-sm font-bold text-tb-on-surface-variant ring-1 ring-tb-outline-variant">
                                        {index + 1}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-base font-semibold text-tb-on-surface group-hover:text-tb-primary">
                                            {tree.name}
                                        </span>
                                        <span className="mt-1 flex items-center gap-1 text-xs text-tb-on-surface-variant">
                                            <Clock3 className="size-3" />
                                            Akar:{' '}
                                            {tree.rootName ??
                                                'Belum ditentukan'}{' '}
                                            ·{' '}
                                            {dateFormatter.format(
                                                new Date(tree.updatedAt),
                                            )}
                                        </span>
                                    </span>
                                    <ArrowUpRight className="size-4 shrink-0 text-tb-outline transition-colors group-hover:text-tb-primary" />
                                </Link>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
        </>
    );
}

TreeSelector.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Pilih Silsilah', href: '#' },
    ],
};
