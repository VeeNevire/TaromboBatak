import { Head } from '@inertiajs/react';
import { TaromboExplorer } from '@/components/tarombo/tarombo-explorer';
import type {
    MargaInfo,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
};

export default function TaromboIndex({ people, margas }: Props) {
    return (
        <>
            <Head title="Pohon Tarombo" />
            <TaromboExplorer people={people} margas={margas} />
        </>
    );
}

TaromboIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pohon Tarombo', href: tarombo.index() },
    ],
};
