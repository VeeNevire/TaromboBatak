import { Head } from '@inertiajs/react';
import {
    TaromboExplorer
    
} from '@/components/tarombo/tarombo-explorer';
import type {TaromboIdentity} from '@/components/tarombo/tarombo-explorer';
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
    familyTreeOptions: TaromboFamilyTreeOption[];
    selectedFamilyTreeId: number | null;
    selectedTreePeople: TaromboPersonRow[] | null;
};

export type TaromboFamilyTreeOption = {
    id: number;
    name: string;
    rootName: string;
    group: 'account' | 'marga';
};

export default function TaromboIndex({
    people,
    margas,
    alternativeTrees,
    identity,
    familyTreeOptions,
    selectedFamilyTreeId,
    selectedTreePeople,
}: Props) {
    return (
        <>
            <Head title="Pohon Tarombo" />
            <TaromboExplorer
                people={people}
                margas={margas}
                alternativeTrees={alternativeTrees}
                identity={identity}
                familyTreeOptions={familyTreeOptions}
                selectedFamilyTreeId={selectedFamilyTreeId}
                selectedTreePeople={selectedTreePeople}
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
