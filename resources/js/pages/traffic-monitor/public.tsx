import { Head, Link } from '@inertiajs/react';
import { BarChart3, ExternalLink } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import home from '@/routes/home';

type TrafficMonitorPublicProps = {
    reportUrl: string | null;
};

export default function PublicTrafficMonitor({ reportUrl }: TrafficMonitorPublicProps) {
    return (
        <>
            <Head title="Monitor Traffic" />

            <main className="min-h-screen bg-muted/30 px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex gap-3">
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <BarChart3 className="size-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight">Monitor Traffic</h1>
                                <p className="mt-1 text-sm text-muted-foreground">Statistik kunjungan website Tarombo Batak.</p>
                            </div>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={home()}>Kembali ke beranda</Link>
                        </Button>
                    </div>

                    {reportUrl ? (
                        <Card className="overflow-hidden">
                            <CardContent className="p-0">
                                <div className="flex items-center justify-end border-b px-4 py-3">
                                    <Button variant="ghost" size="sm" asChild>
                                        <a href={reportUrl} target="_blank" rel="noreferrer">
                                            Buka laporan
                                            <ExternalLink className="size-4" aria-hidden="true" />
                                        </a>
                                    </Button>
                                </div>
                                <iframe
                                    title="Laporan traffic Tarombo Batak"
                                    src={reportUrl}
                                    className="h-[calc(100vh-13rem)] min-h-[600px] w-full border-0"
                                    allowFullScreen
                                    sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                />
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="py-12 text-center text-sm text-muted-foreground">Laporan traffic belum dikonfigurasi.</CardContent>
                        </Card>
                    )}
                </div>
            </main>
        </>
    );
}
