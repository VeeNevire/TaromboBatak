import { Link } from '@inertiajs/react';
import { ArrowUpRight, Clock3, TreePine } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import familyTrees from '@/routes/family-trees';

export type FamilyTreeHistoryEntry = {
    id: number;
    root_person_id: number;
    root_name: string;
    updated_at: string;
};

export function FamilyTreeHistoryCard({
    entries,
}: {
    entries: FamilyTreeHistoryEntry[];
}) {
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <Card className="border-tb-outline-variant bg-tb-surface-bright">
            <CardHeader>
                <div className="flex items-start gap-3">
                    <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-tb-primary/10 text-tb-primary">
                        <TreePine className="size-4.5" />
                    </span>
                    <div className="min-w-0">
                        <CardTitle className="font-display text-lg text-tb-on-surface">
                            Daftar Silsilah
                        </CardTitle>
                        <CardDescription>
                            Pohon yang pernah Anda buat, diurutkan dari
                            pembaruan terbaru.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-tb-outline-variant bg-tb-surface-container/40 px-4 py-6 text-center">
                        <TreePine className="mx-auto size-6 text-tb-outline" />
                        <p className="mt-2 text-sm font-medium text-tb-on-surface">
                            Belum ada silsilah
                        </p>
                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                            Pohon pertama akan muncul setelah keluarga disimpan.
                        </p>
                    </div>
                ) : (
                    <ol className="grid gap-2">
                        {entries.map((entry, index) => (
                            <li key={entry.id}>
                                <Link
                                    href={familyTrees.show(entry.id)}
                                    className="group flex items-center gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-container/35 p-3 transition-colors hover:border-tb-primary/50 hover:bg-tb-primary/5"
                                >
                                    <span className="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-tb-surface-bright text-xs font-bold text-tb-on-surface-variant ring-1 ring-tb-outline-variant">
                                        {index + 1}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-semibold text-tb-on-surface group-hover:text-tb-primary">
                                            {entry.root_name}
                                        </span>
                                        <span className="mt-1 flex items-center gap-1 text-[11px] text-tb-on-surface-variant">
                                            <Clock3 className="size-3" />
                                            Waktu Update:{' '}
                                            {dateFormatter.format(
                                                new Date(entry.updated_at),
                                            )}
                                        </span>
                                    </span>
                                    <ArrowUpRight className="size-4 shrink-0 text-tb-outline transition-colors group-hover:text-tb-primary" />
                                </Link>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
