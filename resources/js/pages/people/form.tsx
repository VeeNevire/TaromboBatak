import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FamilyTreeHistoryEntry } from '@/components/people/family-tree-history-card';
import { Button } from '@/components/ui/button';
import type { NameSuggestion } from '@/components/ui/name-combobox';
import FamilyForm from '@/pages/people/family-form';
import type {
    FamilyData,
    MargaLineageEntry,
    PendingTreeShare,
    ShareableAccount,
} from '@/pages/people/family-form';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type Props = {
    person: FamilyData | null;
    margas: { id: number; name: string }[];
    nameSuggestions: NameSuggestion[];
    fatherSuggestions: NameSuggestion[];
    lockedMarga?: { id: number; name: string } | null;
    lineage?: MargaLineageEntry[];
    familyTrees?: FamilyTreeHistoryEntry[];
    approvedMargaTrees?: FamilyTreeHistoryEntry[];
    versionTrees?: FamilyTreeHistoryEntry[];
    selectedVersionName?: string | null;
    shareableAccounts?: ShareableAccount[];
    pendingTreeShares?: PendingTreeShare[];
    canPublish: boolean;
};

export default function PersonForm({
    person,
    margas,
    nameSuggestions,
    fatherSuggestions,
    lockedMarga,
    lineage,
    familyTrees,
    approvedMargaTrees,
    versionTrees,
    selectedVersionName,
    shareableAccounts,
    pendingTreeShares,
    canPublish,
}: Props) {
    const isEdit = person !== null;

    return (
        <>
            <Head title={isEdit ? 'Ubah Keluarga' : 'Tambah Keluarga'} />

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
                            {isEdit ? 'Ubah Keluarga' : 'Tambah Keluarga'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            {lockedMarga
                                ? `Isi jejak keluarga pada marga ${lockedMarga.name}: orang tua dan daftar saudara/anak dari ayah yang sama.`
                                : 'Isi jejak keluarga: orang tua dan daftar saudara/anak dari ayah yang sama.'}
                        </p>
                    </div>
                </div>

                <FamilyForm
                    person={person}
                    margas={margas}
                    nameSuggestions={nameSuggestions}
                    fatherSuggestions={fatherSuggestions}
                    lockedMarga={lockedMarga}
                    lineage={lineage}
                    familyTrees={familyTrees}
                    approvedMargaTrees={approvedMargaTrees}
                    versionTrees={versionTrees}
                    selectedVersionName={selectedVersionName}
                    shareableAccounts={shareableAccounts}
                    pendingTreeShares={pendingTreeShares}
                    canPublish={canPublish}
                />
            </div>
        </>
    );
}

PersonForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Form Keluarga', href: people.create() },
    ],
};
