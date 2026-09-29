import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, Bot, Clock3, Save } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import margaNewsAutomation from '@/routes/marga-news-automation';

type Automation = {
    enabled: boolean;
    interval_minutes: number;
    prompt: string;
    next_run_at: string | null;
    last_status: 'idle' | 'running' | 'succeeded' | 'partial' | 'failed';
    last_started_at: string | null;
    last_finished_at: string | null;
    last_accepted: number;
    last_duplicates: number;
    last_error: string | null;
};

const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const formatDate = (value: string | null) =>
    value ? dateTime.format(new Date(value)) : 'Belum ada';

const statusLabel: Record<Automation['last_status'], string> = {
    idle: 'Belum berjalan',
    running: 'Sedang berjalan',
    succeeded: 'Berhasil',
    partial: 'Sebagian berhasil',
    failed: 'Gagal',
};

export default function MargaNewsAutomation({
    automation,
    hermes,
}: {
    automation: Automation;
    hermes: { configured: boolean };
}) {
    const form = useForm({
        enabled: automation.enabled,
        interval_minutes: automation.interval_minutes,
        prompt: automation.prompt,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(margaNewsAutomation.update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Otomatisasi Berita Marga" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        <Bot className="size-6 text-tb-primary" />
                        Otomatisasi Berita Marga
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Atur kapan Hermes mencari berita. Hermes melakukan
                        pencarian lewat API; hasil yang diterima disimpan
                        sebagai berita menunggu review.
                    </p>
                </div>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Clock3 className="size-4 text-tb-primary" />
                            Pengaturan jadwal
                        </CardTitle>
                        <CardDescription>
                            Prompt dan interval yang disimpan digunakan pada
                            pencarian terjadwal berikutnya.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-5">
                            <div className="flex items-start gap-3 rounded-lg border border-tb-outline-variant p-4">
                                <Checkbox
                                    id="automation-enabled"
                                    checked={form.data.enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'enabled',
                                            checked === true,
                                        )
                                    }
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="automation-enabled"
                                        className="font-semibold"
                                    >
                                        Aktifkan pencarian otomatis
                                    </Label>
                                    <p className="text-sm text-tb-on-surface-variant">
                                        Scheduler akan memanggil API Hermes
                                        sesuai interval di bawah.
                                    </p>
                                </div>
                            </div>

                            <div className="grid max-w-sm gap-1.5">
                                <Label htmlFor="interval-minutes">
                                    Jeda antar pencarian (menit)
                                </Label>
                                <Input
                                    id="interval-minutes"
                                    type="number"
                                    min={1}
                                    max={10080}
                                    value={form.data.interval_minutes}
                                    onChange={(event) =>
                                        form.setData(
                                            'interval_minutes',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <p className="text-xs text-tb-on-surface-variant">
                                    Minimal 1 menit, maksimal 7 hari (10.080
                                    menit). Jeda dihitung setelah satu siklus
                                    selesai.
                                </p>
                                <InputError
                                    message={form.errors.interval_minutes}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="hermes-prompt">
                                    Prompt untuk Hermes
                                </Label>
                                <textarea
                                    id="hermes-prompt"
                                    value={form.data.prompt}
                                    onChange={(event) =>
                                        form.setData(
                                            'prompt',
                                            event.target.value,
                                        )
                                    }
                                    rows={12}
                                    maxLength={20000}
                                    aria-invalid={Boolean(form.errors.prompt)}
                                    placeholder={
                                        'Contoh: Cari berita terbaru tentang kegiatan marga Batak berdasarkan kata kunci dan konteks yang diberikan. Buka URL sumber asli dan ambil isi artikel minimal 200 kata serta URL gambar utamanya. Hindari duplikat, jangan mengarang fakta atau gambar, lalu kembalikan output.items sebagai JSON dengan field title, url, publisher, published_at, excerpt, summary, content, image_url, dan margas. Jika tidak ada artikel yang memenuhi syarat, kembalikan array kosong.'
                                    }
                                    className="min-h-56 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                />
                                <p className="text-xs text-tb-on-surface-variant">
                                    Tulis instruksi pencarian. Artikel di bawah
                                    200 kata tidak akan disimpan; gambar utama
                                    ditampilkan jika sumber menyediakannya.
                                    Prompt wajib diisi saat otomatisasi
                                    diaktifkan; contoh di atas hanya placeholder
                                    dan tidak dikirim. Maksimal 20.000 karakter.
                                </p>
                                <InputError message={form.errors.prompt} />
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <Save className="size-4" />
                                    Simpan pengaturan
                                </Button>
                                {!hermes.configured && (
                                    <Badge variant="destructive">
                                        HERMES_BASE_URL belum diisi
                                    </Badge>
                                )}
                            </div>
                            <InputError message={form.errors.enabled} />
                        </form>
                    </CardContent>
                </Card>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Status pengambilan berita
                        </CardTitle>
                        <CardDescription>
                            Siklus terjadwal memanggil Hermes, lalu menyimpan
                            berita baru untuk ditinjau admin.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <StatusItem label="Status terakhir">
                            <Badge
                                variant={
                                    automation.last_status === 'failed'
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {statusLabel[automation.last_status]}
                            </Badge>
                        </StatusItem>
                        <StatusItem label="Run berikutnya">
                            {automation.enabled
                                ? formatDate(automation.next_run_at)
                                : 'Nonaktif'}
                        </StatusItem>
                        <StatusItem label="Mulai terakhir">
                            {formatDate(automation.last_started_at)}
                        </StatusItem>
                        <StatusItem label="Selesai terakhir">
                            {formatDate(automation.last_finished_at)}
                        </StatusItem>
                        <StatusItem label="Berita baru diterima">
                            {automation.last_accepted}
                        </StatusItem>
                        <StatusItem label="Duplikat dilewati">
                            {automation.last_duplicates}
                        </StatusItem>
                        {automation.last_error && (
                            <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive sm:col-span-2">
                                <p className="mb-1 flex items-center gap-2 font-semibold">
                                    <AlertTriangle className="size-4" />
                                    Pesan error terakhir
                                </p>
                                <pre className="font-sans whitespace-pre-wrap">
                                    {automation.last_error}
                                </pre>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function StatusItem({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1 rounded-lg bg-tb-surface-container/50 p-3">
            <span className="text-xs font-medium text-tb-on-surface-variant">
                {label}
            </span>
            <span className="text-sm font-semibold text-tb-on-surface">
                {children}
            </span>
        </div>
    );
}

MargaNewsAutomation.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Otomatisasi Berita Marga',
            href: margaNewsAutomation.index(),
        },
    ],
};
