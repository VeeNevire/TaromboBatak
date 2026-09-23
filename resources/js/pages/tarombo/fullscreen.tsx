import { Head } from '@inertiajs/react';
import { AppHeaderUser } from '@/components/app-header-user';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/landing/theme-toggle';
import { TaromboExplorer } from '@/components/tarombo/tarombo-explorer';
import type { TaromboIdentity } from '@/components/tarombo/tarombo-explorer';
import type {
    MargaInfo,
    TaromboAlternativeTreeRow,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import type { TaromboFamilyTreeOption } from '@/pages/tarombo';
import { dashboard } from '@/routes';
import tarombo from '@/routes/tarombo';

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
    accountTreePersonIds: string[];
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
    accountTreePersonIds,
    margaTree,
}: Props) {
    const selectedTreeKey =
        selectedFamilyTreeId !== null
            ? `account-${selectedFamilyTreeId}`
            : selectedMargaId !== null
              ? `marga-${selectedMargaId}`
              : 'default';

    return (
        <div className="flex h-dvh flex-col">
            <Head title="Pohon Tarombo" />
            <div className="flex shrink-0 items-center justify-between gap-2 border-b border-tb-outline-variant/70 bg-tb-surface-bright/60 px-4 py-2 backdrop-blur-xl md:px-6">
                <Breadcrumbs
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Pohon Tarombo', href: tarombo.index() },
                    ]}
                />
                <div className="flex items-center gap-2">
                    <ThemeToggle />
                    <AppHeaderUser />
                </div>
            </div>
            <div className="min-h-0 flex-1">
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
                    accountTreePersonIds={accountTreePersonIds}
                    margaTree={margaTree}
                />
            </div>
        </div>
    );
}
