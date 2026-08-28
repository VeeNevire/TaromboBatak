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

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    alternativeTrees: TaromboAlternativeTreeRow[];
    view: 'diagram' | 'tree';
    identity: TaromboIdentity;
};

export default function TaromboFullscreen({
    people,
    margas,
    alternativeTrees,
    view,
    identity,
}: Props) {
    return (
        <>
            <Head title="Pohon Tarombo" />
            <TaromboExplorer
                people={people}
                margas={margas}
                alternativeTrees={alternativeTrees}
                fullscreen
                fullscreenView={view}
                identity={identity}
            />
        </>
    );
}
