import { Head } from '@inertiajs/react';
import {
    TaromboExplorer,
    type TaromboIdentity,
} from '@/components/tarombo/tarombo-explorer';
import type {
    MargaInfo,
    TaromboAlternativeTreeRow,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    alternativeTrees: TaromboAlternativeTreeRow[];
    identity: TaromboIdentity;
};

export default function TaromboIndex({
    people,
    margas,
    alternativeTrees,
    identity,
}: Props) {
    return (
        <>
            <Head title="Pohon Tarombo" />
            <TaromboExplorer
                people={people}
                margas={margas}
                alternativeTrees={alternativeTrees}
                identity={identity}
            />
        </>
    );
}

TaromboIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pohon Tarombo', href: tarombo.index() },
    ],
};
