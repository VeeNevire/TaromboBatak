import { Head } from '@inertiajs/react';
import { ExternalLink, LineChart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type Props = {
    reportUrl: string | null;
};

export default function TrafficMonitor({ reportUrl }: Props) {
    return (
        <>
            <Head title="Monitor Traffic" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Monitor Traffic
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Ringkasan pengunjung dan aktivitas website dari
                            Google Analytics.
                        </p>
                    </div>
                    {reportUrl && (
                        <Button asChild variant="outline" className="shrink-0">
                            <a
                                href={reportUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Buka di Looker Studio{' '}
                                <ExternalLink className="size-4" />
                            </a>
                        </Button>
                    )}
                </div>

                {reportUrl ? (
                    <Card className="min-h-[calc(100dvh-13rem)] overflow-hidden border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="h-[calc(100dvh-13rem)] min-h-160 p-0">
                            <iframe
                                title="Dashboard traffic Tarombo Batak"
                                src={reportUrl}
                                className="size-full border-0"
                                allowFullScreen
                                sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-tb-on-surface">
                                <LineChart className="size-5 text-tb-primary" />{' '}
                                Laporan belum dihubungkan
                            </CardTitle>
                            <CardDescription>
                                Tambahkan URL embed Looker Studio pada
                                konfigurasi aplikasi untuk menampilkan laporan
                                traffic di halaman ini.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}
            </div>
        </>
    );
}

TrafficMonitor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitor Traffic', href: '#' },
    ],
};
