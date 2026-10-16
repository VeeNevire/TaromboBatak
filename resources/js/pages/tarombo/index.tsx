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
    selectedRoot?: string | null;
    accountTreePersonIds: string[];
    familyName: string | null;
    margaTree: {
        margaId: number;
        margaName: string;
        identityPersonId: string | null;
        direction: 'upper' | 'lower';
        canReorderSiblings: boolean;
    } | null;
};

export type TaromboFamilyTreeOption = {
    id: number;
    value: string;
    name: string;
    rootName: string;
    rootPersonId?: number | null;
    group: 'root' | 'account' | 'family' | 'marga';
    canManage?: boolean;
    isAuto?: boolean;
    margaName?: string | null;
};

export default function TaromboIndex({
    people,
    margas,
    alternativeTrees,
    identity,
    familyTreeOptions,
    selectedFamilyTreeId,
    selectedMargaId,
    selectedRoot = null,
    accountTreePersonIds,
    familyName,
    margaTree,
}: Props) {
    const selectedTreeKey =
        selectedFamilyTreeId !== null
            ? `account-${selectedFamilyTreeId}`
            : selectedMargaId !== null
              ? `marga-${selectedMargaId}`
              : selectedRoot !== null
                ? `root-${selectedRoot}`
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
                selectedRoot={selectedRoot}
                accountTreePersonIds={accountTreePersonIds}
                familyName={familyName}
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
