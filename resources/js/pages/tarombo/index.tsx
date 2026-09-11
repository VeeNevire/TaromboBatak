import { Head } from '@inertiajs/react';
import { TaromboExplorer } from '@/components/tarombo/tarombo-explorer';
import type { TaromboIdentity } from '@/components/tarombo/tarombo-explorer';
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
    selectedMargaId: number | null;
    selectedTreePeople: TaromboPersonRow[] | null;
    margaTree: {
        margaName: string;
        identityPersonId: string | null;
        direction: 'upper' | 'lower';
    } | null;
};

export type TaromboFamilyTreeOption = {
    id: number;
    value: string;
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
    selectedMargaId,
    selectedTreePeople,
    margaTree,
}: Props) {
    const selectedTreeKey =
        selectedFamilyTreeId !== null
            ? `account-${selectedFamilyTreeId}`
            : selectedMargaId !== null
              ? `marga-${selectedMargaId}`
              : 'default';

    return (
        <>
            <Head title="Pohon Tarombo" />
            <TaromboExplorer
                key={selectedTreeKey}
                people={people}
                margas={margas}
                alternativeTrees={alternativeTrees}
                identity={identity}
                familyTreeOptions={familyTreeOptions}
                selectedFamilyTreeId={selectedFamilyTreeId}
                selectedMargaId={selectedMargaId}
                selectedTreePeople={selectedTreePeople}
                margaTree={margaTree}
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
