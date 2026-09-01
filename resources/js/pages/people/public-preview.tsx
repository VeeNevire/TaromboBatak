import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type MargaPreview = {
    id: number;
    name: string;
    identity_person_id: number;
    identity_person_name: string | null;
    people_count: number;
};

type Props = {
    margas: MargaPreview[];
};

export default function PublicSilsilahPreview({ margas }: Props) {
    return (
        <>
            <Head title="Silsilah Saya" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Silsilah Saya
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Preview silsilah marga yang tersedia untuk publik.
                        </p>
                    </div>
                    <Button
                        asChild
                        className="w-fit rounded-full bg-tb-primary hover:bg-tb-primary-light"
                    >
                        <Link href={people.index()}>
                            <Users className="size-4" /> Data Anggota
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="font-display text-xl text-tb-on-surface">
                            Preview Silsilah
                        </CardTitle>
                        <p className="text-sm text-tb-on-surface-variant">
                            Daftar akar marga dan jumlah anggota yang telah
                            dipublikasikan.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {margas.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-tb-outline-variant bg-tb-surface-container/40 px-4 py-10 text-center text-sm text-tb-on-surface-variant">
                                Belum ada silsilah publik yang tersedia.
                            </div>
                        ) : (
                            <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {margas.map((marga, index) => (
                                    <li
                                        key={marga.id}
                                        className="flex items-start gap-3 rounded-xl border border-tb-outline-variant bg-tb-surface-container/35 p-4"
                                    >
                                        <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-tb-surface-bright text-sm font-bold text-tb-on-surface-variant ring-1 ring-tb-outline-variant">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate font-display font-semibold text-tb-on-surface">
                                                {marga.identity_person_name ??
                                                    marga.name}
                                            </p>
                                            <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                Marga {marga.name} ·{' '}
                                                {marga.people_count} anggota
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PublicSilsilahPreview.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Silsilah Saya', href: people.publicPreview() },
    ],
};
