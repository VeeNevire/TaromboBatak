import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ZoomableImage } from '@/components/zoomable-image';

export default function QrResult({
    snapshot,
}: {
    snapshot: { title: string; image_url: string; download_url: string };
}) {
    return (
        <>
            <Head title={snapshot.title} />
            <main className="min-h-dvh bg-tb-surface p-4 text-tb-on-surface md:p-6">
                <div className="mx-auto flex max-w-6xl flex-col gap-4">
                    <header className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold text-tb-primary">
                                Tarombo Batak
                            </p>
                            <h1 className="mt-1 font-display text-xl font-bold md:text-2xl">
                                {snapshot.title}
                            </h1>
                        </div>
                    </header>
                    <p className="text-sm text-tb-on-surface-variant">
                        Gunakan tombol +/−, scroll, atau ketuk dua kali untuk
                        zoom. Seret gambar untuk melihat bagian lainnya.
                    </p>
                    <ZoomableImage
                        src={snapshot.image_url}
                        alt={snapshot.title}
                        className="h-[75dvh] rounded-xl border border-tb-outline-variant bg-tb-surface-container"
                    />
                </div>
            </main>
        </>
    );
}
