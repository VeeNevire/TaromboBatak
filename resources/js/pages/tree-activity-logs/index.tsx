import { Head, router } from '@inertiajs/react';
import { Check, Clock3, GitBranch, Pencil, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import treeActivityLogs from '@/routes/tree-activity-logs';

type ChangeRequest = {
    id: number;
    action: 'update' | 'delete';
    scope: 'contributor' | 'shared_tree_owner';
    person: string;
    requester: string;
    tree: string | null;
    created_at: string | null;
};

type TreeLog = {
    id: number;
    action: string;
    summary: string;
    person: string | null;
    tree: string | null;
    actor: string | null;
    marga: string | null;
    scope: string | null;
    details: Record<string, unknown> | null;
    changed_fields: string[];
    date: string | null;
    time: string | null;
};

type Props = {
    logs: { data: TreeLog[]; total: number };
    changeRequests: ChangeRequest[];
};

const actionIcon = (action: string) => {
    if (action === 'added') {
return <GitBranch className="size-4 text-emerald-600" />;
}

    if (action === 'removed' || action === 'delete') {
return <Trash2 className="size-4 text-red-600" />;
}

    return <Pencil className="size-4 text-amber-600" />;
};

export default function TreeActivityLogs({ logs, changeRequests }: Props) {
    const review = (change: ChangeRequest, approved: boolean) => {
        if (!approved) {
            const reason = window.prompt('Alasan penolakan (opsional):');

            if (reason === null) {
return;
}

            router.post(
                treeActivityLogs.reject(change.id).url,
                { reason },
                { preserveScroll: true },
            );

            return;
        }

        if (!window.confirm(`Setujui ${change.action === 'delete' ? 'penghapusan' : 'perubahan'} ${change.person}?`)) {
return;
}

        router.post(treeActivityLogs.approve(change.id).url, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Log Pohon Besar" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        Log Pohon Besar
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Riwayat penambahan, perubahan, dan penghapusan nama yang terhubung ke Pohon Besar atau silsilah yang dibagikan.
                    </p>
                </div>

                {changeRequests.length > 0 && (
                    <Card className="border-amber-300 bg-amber-50/60 dark:bg-amber-950/20">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-tb-on-surface">
                                <Clock3 className="size-5 text-amber-600" /> Pengajuan menunggu persetujuan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {changeRequests.map((change) => (
                                <div key={change.id} className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-tb-surface-bright p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="font-semibold text-tb-on-surface">
                                            {change.action === 'delete' ? 'Hapus' : 'Ubah'} {change.person}
                                        </p>
                                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                                            Diajukan oleh {change.requester} · {change.scope === 'contributor' ? 'Persetujuan Kontributor' : 'Persetujuan pemilik silsilah'}
                                            {change.tree ? ` · ${change.tree}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button type="button" size="sm" variant="outline" onClick={() => review(change, false)}>
                                            <X className="size-4" /> Tolak
                                        </Button>
                                        <Button type="button" size="sm" onClick={() => review(change, true)} className="bg-emerald-600 hover:bg-emerald-700">
                                            <Check className="size-4" /> Setujui
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle>Riwayat aktivitas ({logs.total})</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {logs.data.map((log) => (
                            <div key={log.id} className="flex gap-3 rounded-xl border border-tb-outline-variant p-4">
                                <div className="pt-0.5">{actionIcon(log.action)}</div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-tb-on-surface">{log.summary}</p>
                                    <p className="mt-1 text-xs text-tb-on-surface-variant">
                                        Tanggal: {log.date ?? '-'} · Jam: {log.time ?? '-'}
                                        {' · '}{log.actor ?? 'Sistem'}
                                        {log.marga ? ` · ${log.marga}` : ''}
                                        {log.tree ? ` · ${log.tree}` : ''}
                                    </p>
                                    {log.changed_fields.length > 0 && (
                                        <p className="mt-1 text-xs text-tb-on-surface-variant">
                                            Data yang diubah: {log.changed_fields.join(', ')}
                                        </p>
                                    )}
                                </div>
                            </div>
                        ))}
                        {logs.data.length === 0 && (
                            <p className="py-10 text-center text-sm text-tb-on-surface-variant">
                                Belum ada aktivitas pada pohon yang terlindungi.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TreeActivityLogs.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Log Pohon Besar', href: treeActivityLogs.index() },
    ],
};
