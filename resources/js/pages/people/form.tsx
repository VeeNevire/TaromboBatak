import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FamilyTreeHistoryCard } from '@/components/people/family-tree-history-card';
import type {
    ApprovedMargaTreeEntry,
    FamilyTreeHistoryEntry,
} from '@/components/people/family-tree-history-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    regions: {
        code: string;
        name: string;
        regencies: { code: string; name: string }[];
    }[];
    margas: { id: number; name: string }[];
    spouseMargas?: { id: number; name: string }[];
    nameSuggestions: NameSuggestion[];
    fatherSuggestions: NameSuggestion[];
    lockedMarga?: { id: number; name: string } | null;
    lineage?: MargaLineageEntry[];
    familyTrees?: FamilyTreeHistoryEntry[];
    approvedMargaTrees?: ApprovedMargaTreeEntry[];
    margaAccessMargaId?: number | null;
    margaAccessStatus?: 'pending' | 'approved' | 'rejected' | null;
    versionTrees?: FamilyTreeHistoryEntry[];
    selectedVersionName?: string | null;
    selectedFamilyName?: string | null;
    selectedVersionId?: number | null;
    shareableAccounts?: ShareableAccount[];
    pendingTreeShares?: PendingTreeShare[];
    canPublish: boolean;
};

export default function PersonForm({
    person,
    regions,
    margas,
    spouseMargas,
    nameSuggestions,
    fatherSuggestions,
    lockedMarga,
    lineage,
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
}: Props) {
    const isEdit = person !== null;
    const activeMargaName =
        lockedMarga?.name ??
        margas.find((marga) => marga.id === person?.marga_id)?.name ??
        null;

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

                    <FamilyTreeHistoryCard
                        entries={familyTrees ?? []}
                        approvedEntries={approvedMargaTrees ?? []}
                        margaName={activeMargaName}
                        margaId={margaAccessMargaId}
                        margaAccessStatus={margaAccessStatus}
                        shareableAccounts={shareableAccounts ?? []}
                        pendingTreeShares={pendingTreeShares ?? []}
                    />
                </div>

                {person && (
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Orang Tua Tercatat
                            </CardTitle>
                            <CardDescription>
                                Hubungan orang tua yang saat ini tersimpan untuk{' '}
                                {person.name}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-tb-outline-variant bg-tb-surface-container/35 p-4">
                                <p className="text-xs font-medium tracking-wide text-tb-on-surface-variant uppercase">
                                    Ayah
                                </p>
                                <p className="mt-1 font-semibold text-tb-on-surface">
                                    {person.father?.name ?? 'Belum dicatat'}
                                </p>
                                {person.father?.marga && (
                                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                                        Marga {person.father.marga}
                                    </p>
                                )}
                            </div>
                            <div className="rounded-lg border border-tb-outline-variant bg-tb-surface-container/35 p-4">
                                <p className="text-xs font-medium tracking-wide text-tb-on-surface-variant uppercase">
                                    Ibu
                                </p>
                                <p className="mt-1 font-semibold text-tb-on-surface">
                                    {person.mother?.name ?? 'Belum dicatat'}
                                </p>
                                {person.mother?.marga && (
                                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                                        Marga {person.mother.marga}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <FamilyForm
                    key={`person-${person?.id ?? 'new'}-version-${selectedVersionId ?? 'base'}`}
                    person={person}
                    regions={regions}
                    margas={margas}
                    spouseMargas={spouseMargas}
                    nameSuggestions={nameSuggestions}
                    fatherSuggestions={fatherSuggestions}
                    lockedMarga={lockedMarga}
                    lineage={lineage}
                    familyTrees={familyTrees}
                    approvedMargaTrees={approvedMargaTrees}
                    margaAccessMargaId={margaAccessMargaId}
                    margaAccessStatus={margaAccessStatus}
                    versionTrees={versionTrees}
                    selectedVersionName={selectedVersionName}
                    selectedFamilyName={selectedFamilyName}
                    selectedVersionId={selectedVersionId}
                    showFamilyTreeHistory={false}
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
