import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { FamilyBranchDialog } from '@/components/people/family-branch-dialog';
import { FamilyTreeHistoryCard } from '@/components/people/family-tree-history-card';
import type {
    ApprovedMargaTreeEntry,
    FamilyTreeHistoryEntry,
} from '@/components/people/family-tree-history-card';
import { Button } from '@/components/ui/button';
import type { NameSuggestion } from '@/components/ui/name-combobox';
import FamilyForm from '@/pages/people/family-form';
import type {
    FamilyData,
    PendingTreeShare,
    ShareableAccount,
} from '@/pages/people/family-form';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type Props = {
    person: FamilyData;
    regions: {
        code: string;
        name: string;
        regencies: { code: string; name: string }[];
    }[];
    margas: { id: number; name: string }[];
    spouseMargas?: { id: number; name: string }[];
    nameSuggestions: NameSuggestion[];
    fatherSuggestions: NameSuggestion[];
    familyTrees: FamilyTreeHistoryEntry[];
    approvedMargaTrees: ApprovedMargaTreeEntry[];
    margaAccessMargaId: number | null;
    margaAccessStatus: 'pending' | 'approved' | 'rejected' | null;
    versionTrees: FamilyTreeHistoryEntry[];
    selectedVersionName?: string | null;
    selectedFamilyName?: string | null;
    selectedVersionId?: number | null;
    shareableAccounts: ShareableAccount[];
    pendingTreeShares: PendingTreeShare[];
    canPublish: boolean;
    readOnly?: boolean;
    appendTarget: {
        familyTreeId: number;
        fatherNodeId: number;
        requiresApproval: boolean;
    } | null;
};

export default function PersonShow({
    person,
    regions,
    margas,
    spouseMargas,
    nameSuggestions,
    fatherSuggestions,
    familyTrees,
    approvedMargaTrees,
    margaAccessMargaId,
    margaAccessStatus,
    versionTrees,
    selectedVersionName,
    selectedFamilyName,
    selectedVersionId,
    shareableAccounts,
    pendingTreeShares,
    canPublish,
    readOnly = false,
    appendTarget,
}: Props) {
    const [branchDialogOpen, setBranchDialogOpen] = useState(false);
    const activeMargaName =
        margas.find((marga) => marga.id === person.marga_id)?.name ?? null;
    return (
        <>
            <Head title={`Jejak Keluarga ${person.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="w-fit text-tb-on-surface-variant"
                    >
                        <Link href={people.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Data
                            Anggota
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Jejak Keluarga {person.name}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Ayah, ibu, dan daftar saudara dari {person.name}.
                            Perubahan disimpan sekaligus.
                        </p>
                    </div>
                    {!readOnly && appendTarget && (
                        <Button
                            type="button"
                            className="w-fit"
                            onClick={() => setBranchDialogOpen(true)}
                        >
                            <UserPlus className="size-4" /> Tambah Anggota
                            Ranting
                        </Button>
                    )}

                    <FamilyTreeHistoryCard
                        entries={familyTrees}
                        approvedEntries={approvedMargaTrees}
                        margaName={activeMargaName}
                        margaId={margaAccessMargaId}
                        margaAccessStatus={margaAccessStatus}
                        shareableAccounts={shareableAccounts}
                        pendingTreeShares={pendingTreeShares}
                    />
                </div>

                <FamilyForm
                    key={`person-${person.id}-version-${
                        selectedVersionId ?? 'base'
                    }`}
                    person={person}
                    regions={regions}
                    margas={margas}
                    spouseMargas={spouseMargas}
                    nameSuggestions={nameSuggestions}
                    fatherSuggestions={fatherSuggestions}
                    familyTrees={familyTrees}
                    approvedMargaTrees={approvedMargaTrees}
                    versionTrees={versionTrees}
                    selectedVersionName={selectedVersionName}
                    selectedFamilyName={selectedFamilyName}
                    selectedVersionId={selectedVersionId}
                    shareableAccounts={shareableAccounts}
                    pendingTreeShares={pendingTreeShares}
                    canPublish={canPublish}
                    readOnly={readOnly}
                    showFamilyTreeHistory={false}
                />
            </div>

            {appendTarget && (
                <FamilyBranchDialog
                    open={branchDialogOpen}
                    onOpenChange={setBranchDialogOpen}
                    familyTree={{
                        id: appendTarget.familyTreeId,
                        requiresApproval: appendTarget.requiresApproval,
                    }}
                    father={{
                        nodeId: appendTarget.fatherNodeId,
                        name: person.name,
                    }}
                />
            )}
        </>
    );
}

PersonShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Jejak Keluarga', href: people.index() },
    ],
};
