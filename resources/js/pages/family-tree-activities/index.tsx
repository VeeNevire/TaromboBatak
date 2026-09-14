import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import familyTreeActivities from '@/routes/family-tree-activities';

type Activity = {
    id: number;
    tree_name: string;
    action: string;
    description: string;
    actor: string;
    created_at: string | null;
};

export default function FamilyTreeActivitiesIndex({
    activities,
}: {
    activities: Activity[];
}) {
    return (
        <>
            <Head title="Log Aktivitas Silsilah" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 font-display text-xl text-tb-on-surface">
                            <History className="size-5 text-tb-primary" /> Log
                            Aktivitas Silsilah
                        </CardTitle>
                        <CardDescription>
                            Riwayat penambahan, perubahan, dan penghapusan pada silsilah milik akun.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {activities.length === 0 ? (
                            <p className="py-10 text-center text-sm text-tb-on-surface-variant">
                                Belum ada aktivitas silsilah tercatat.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-tb-outline-variant">
                                <div className="divide-y divide-tb-outline-variant">
                                    {activities.map((activity) => (
                                        <div key={activity.id} className="grid gap-1 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                                            <div>
                                                <p className="text-sm font-semibold text-tb-on-surface">
                                                    {activity.description}
                                                </p>
                                                <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                    {activity.tree_name} · oleh {activity.actor}
                                                </p>
                                            </div>
                                            <time className="text-xs text-tb-on-surface-variant">
                                                {activity.created_at ?? '-'}
                                            </time>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

FamilyTreeActivitiesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Log Aktivitas', href: familyTreeActivities.index() },
    ],
};
