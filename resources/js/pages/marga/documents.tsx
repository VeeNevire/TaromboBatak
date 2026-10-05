import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileText, Trash2, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import margaRoutes from '@/routes/marga';

type DocumentItem = {
    id: number;
    title: string;
    original_name: string;
    mime_type: string | null;
    size_bytes: number;
    uploaded_by: string;
    created_at: string | null;
    download_url: string;
};

export default function MargaDocuments({
    marga,
    documents,
}: {
    marga: { id: number; name: string; color: string | null };
    documents: DocumentItem[];
}) {
    const form = useForm({ title: '', document: null as File | null });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(margaRoutes.documents.store(marga.id).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };
    const size = (bytes: number) =>
        bytes < 1024 * 1024
            ? `${Math.max(1, Math.round(bytes / 1024))} KB`
            : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

    return (
        <>
            <Head title={`Dokumen ${marga.name}`} />
            <div className="flex min-h-full flex-1 flex-col gap-6 bg-tb-surface p-4 text-tb-on-surface md:p-6 lg:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link
                            href={margaRoutes.index()}
                            className="text-sm text-tb-primary"
                        >
                            ← Kelola Marga
                        </Link>
                        <h1 className="mt-2 font-display text-2xl font-bold">
                            Dokumen {marga.name}
                        </h1>
                        <p className="text-sm text-tb-on-surface-variant">
                            Simpan arsip, catatan, dan dokumen keluarga
                            berdasarkan marga.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={margaRoutes.ai.show(marga.id)}>
                            Tanya Ito Tarombo
                        </Link>
                    </Button>
                </div>
                <Card className="rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Upload className="size-5" /> Unggah dokumen
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-[1fr_1.5fr_auto] md:items-end"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="document-title">Judul</Label>
                                <Input
                                    id="document-title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    placeholder="Contoh: Sejarah Marga"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="document-file">Berkas</Label>
                                <Input
                                    id="document-file"
                                    type="file"
                                    onChange={(e) =>
                                        form.setData(
                                            'document',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                Unggah
                            </Button>
                        </form>
                        {form.errors.title && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.title}
                            </p>
                        )}
                        {form.errors.document && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.document}
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card className="rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                    <CardHeader>
                        <CardTitle>Arsip dokumen</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {documents.length === 0 ? (
                            <p className="py-8 text-center text-sm text-tb-on-surface-variant">
                                Belum ada dokumen.
                            </p>
                        ) : (
                            <div className="divide-y">
                                {documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <FileText className="size-5 shrink-0 text-tb-primary" />
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {document.title}
                                                </p>
                                                <p className="truncate text-xs text-tb-on-surface-variant">
                                                    {document.original_name} ·{' '}
                                                    {size(document.size_bytes)}{' '}
                                                    · {document.uploaded_by}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a href={document.download_url}>
                                                    <Download className="mr-1 size-4" />{' '}
                                                    Unduh
                                                </a>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Hapus dokumen"
                                                onClick={() =>
                                                    router.delete(
                                                        margaRoutes.documents.destroy(
                                                            {
                                                                marga: marga.id,
                                                                document:
                                                                    document.id,
                                                            },
                                                        ).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MargaDocuments.layout = {
    breadcrumbs: [{ title: 'Daftar Marga', href: margaRoutes.index() }],
};
