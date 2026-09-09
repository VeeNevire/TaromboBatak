import { Head } from '@inertiajs/react';
import { FamilyTreeHistoryCard } from '@/components/people/family-tree-history-card';
import type { FamilyTreeHistoryEntry } from '@/components/people/family-tree-history-card';
import type {
    PendingTreeShare,
    ShareableAccount,
} from '@/pages/people/family-form';
import { dashboard } from '@/routes';
import familyTrees from '@/routes/family-trees';

type Props = {
    familyTrees: FamilyTreeHistoryEntry[];
    shareableAccounts: ShareableAccount[];
    pendingTreeShares: PendingTreeShare[];
};

export default function FamilyTreesIndex({
    familyTrees: entries,
    shareableAccounts,
    pendingTreeShares,
}: Props) {
    return (
        <>
            <Head title="Daftar Silsilah Milik Akun" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        Daftar Silsilah Milik Akun
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Seluruh silsilah yang dibuat dan dikelola melalui akun,
                        diurutkan dari pembaruan terbaru.
                    </p>
                </div>

                <FamilyTreeHistoryCard
                    entries={entries}
                    shareableAccounts={shareableAccounts}
                    pendingTreeShares={pendingTreeShares}
                    accountTreesOnly
                />
            </div>
        </>
    );
}

FamilyTreesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Daftar Silsilah Milik Akun', href: familyTrees.index() },
    ],
};
