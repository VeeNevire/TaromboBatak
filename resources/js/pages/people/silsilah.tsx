import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { SilsilahTree, type SilsilahPayload } from '@/components/people/silsilah-tree';
import { dashboard } from '@/routes';
import people from '@/routes/people';

const FOREST = '#2F4538';
const INK_SOFT = '#5B6A61';
const LINE = '#A79E8C';

export default function PersonSilsilah(props: SilsilahPayload) {
    return (
        <>
            <Head title={`Silsilah ${props.person.name}`} />

            <div className="min-h-screen">
                <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                    <Button asChild variant="ghost" size="sm" className="w-fit text-tb-on-surface-variant">
                        <Link href={people.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Data Anggota
                        </Link>
                    </Button>

                    <div className="mt-4 rounded-3xl border border-tb-outline-variant p-4 sm:p-8">
                        <div style={{ backgroundColor: '#F6F7F1' }} className="rounded-2xl p-6 sm:p-10">
                            <div style={{ color: FOREST }} className="text-center">
                                <p
                                    className="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    style={{ color: INK_SOFT }}
                                >
                                    Jejak Silsilah Keluarga
                                </p>
                                <h1 className="font-display text-3xl font-semibold sm:text-4xl">
                                    {props.person.name}
                                </h1>
                                <div className="mt-4 flex items-center justify-center gap-3">
                                    <span className="h-px w-7" style={{ backgroundColor: LINE }} />
                                    <p className="font-display text-base italic sm:text-lg">
                                        Anak ke {props.person.birthOrder ?? '?'} · Marga {props.person.marga}
                                    </p>
                                    <span className="h-px w-7" style={{ backgroundColor: LINE }} />
                                </div>
                            </div>

                            <div className="mt-6">
                                <SilsilahTree payload={props} />
                            </div>
                        </div>
                    </div>
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