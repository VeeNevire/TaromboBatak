import { Head } from '@inertiajs/react';
import { TaromboExplorer } from '@/components/tarombo/tarombo-explorer';
import type { TaromboIdentity } from '@/components/tarombo/tarombo-explorer';
import type {
    MargaInfo,
    TaromboAlternativeTreeRow,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import type { TaromboFamilyTreeOption } from '@/pages/tarombo';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    alternativeTrees: TaromboAlternativeTreeRow[];
    view: 'diagram' | 'tree';
    identity: TaromboIdentity;
    initialPersonId?: string;
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

export default function TaromboFullscreen({
    people,
    margas,
    alternativeTrees,
    view,
    identity,
    initialPersonId,
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
                fullscreen
                fullscreenView={view}
                identity={identity}
                initialPersonId={initialPersonId}
                familyTreeOptions={familyTreeOptions}
                selectedFamilyTreeId={selectedFamilyTreeId}
                selectedMargaId={selectedMargaId}
                selectedTreePeople={selectedTreePeople}
                margaTree={margaTree}
            />
        </>
    );
}
