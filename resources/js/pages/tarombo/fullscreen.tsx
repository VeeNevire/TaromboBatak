import { Head } from '@inertiajs/react';
import { TaromboExplorer } from '@/components/tarombo/tarombo-explorer';
import type { MargaInfo, TaromboPersonRow } from '@/data/tarombo-tree';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    view: 'diagram' | 'tree';
};

export default function TaromboFullscreen({ people, margas, view }: Props) {
    return (
        <>
            <Head title="Pohon Tarombo" />
            <TaromboExplorer
                people={people}
                margas={margas}
                fullscreen
                fullscreenView={view}
            />
        </>
    );
}
