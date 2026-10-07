import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Clock3, ImageUp, QrCode, ReceiptText, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import qris from '@/routes/qris';

type Payment = {
    id: number;
    amount: number;
    reference: string | null;
    note: string | null;
    status: 'pending' | 'approved' | 'rejected';
    review_note: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    user: { id: number; name: string; email: string } | null;
    proof_url: string;
};

type PaymentPage = {
    data: Payment[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const formatRupiah = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);

const statusLabel: Record<Payment['status'], string> = {
    pending: 'Menunggu verifikasi',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

export default function QrisPaymentPage({
    canManage,
    configuration,
    payments,
    statusFilter,
    summary,
}: {
    canManage: boolean;
    configuration: { image_url: string | null; instructions: string | null };
    payments: PaymentPage;
    statusFilter: 'all' | Payment['status'];
    summary: {
        pending: number;
        approved_total: number;
        approved_count: number;
    } | null;
}) {
    const paymentForm = useForm({
        amount: '',
        reference: '',
        note: '',
        proof: null as File | null,
    });
    const configurationForm = useForm({
        image: null as File | null,
        instructions: configuration.instructions ?? '',
    });

    const submitPayment = (event: FormEvent) => {
        event.preventDefault();
        paymentForm.post(qris.store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => paymentForm.reset(),
        });
    };

    const saveConfiguration = (event: FormEvent) => {
        event.preventDefault();
        configurationForm.post(qris.configuration.update().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => configurationForm.reset('image'),
        });
    };

    const review = (payment: Payment, status: 'approved' | 'rejected') => {
        const message =
            status === 'approved'
                ? `Setujui pembayaran ${formatRupiah(payment.amount)} dari ${payment.user?.name ?? 'pengguna'}?`
                : `Tolak pembayaran ${formatRupiah(payment.amount)} dari ${payment.user?.name ?? 'pengguna'}?`;

        if (!window.confirm(message)) {
            return;
        }

        const reviewNote =
            status === 'rejected'
                ? window.prompt('Alasan penolakan (opsional):')
                : null;

        if (status === 'rejected' && reviewNote === null) {
            return;
        }

        router.post(
            qris.review(payment.id).url,
            {
                status,
                review_note: reviewNote ?? '',
            },
            { preserveScroll: true },
        );
    };

    const statusBadge = (status: Payment['status']) => (
        <Badge
            variant={
                status === 'approved'
                    ? 'default'
                    : status === 'rejected'
                      ? 'destructive'
                      : 'secondary'
            }
        >
            {status === 'pending' && <Clock3 className="mr-1 size-3" />}
            {statusLabel[status]}
        </Badge>
    );

    return (
        <>
            <Head title="QRIS Payment" />
            <div className="flex min-h-full flex-1 flex-col gap-6 bg-tb-surface p-4 text-tb-on-surface md:p-6 lg:p-8">
                <div>
                    <h1 className="flex items-center gap-2 font-display text-2xl font-bold">
                        <QrCode className="size-6 text-tb-primary" /> QRIS
                        Payment
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        {canManage
                            ? 'Kelola QRIS dan verifikasi pembayaran dukungan dari pengguna.'
                            : 'Dukung pengembangan Tarombo Batak melalui pembayaran QRIS.'}
                    </p>
                </div>

                {canManage && summary && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardContent className="p-5">
                                <p className="text-sm text-tb-on-surface-variant">
                                    Menunggu verifikasi
                                </p>
                                <p className="mt-1 text-2xl font-bold">
                                    {summary.pending}
                                </p>
                            </CardContent>
                        </Card>
                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardContent className="p-5">
                                <p className="text-sm text-tb-on-surface-variant">
                                    Pembayaran disetujui
                                </p>
                                <p className="mt-1 text-2xl font-bold">
                                    {summary.approved_count}
                                </p>
                            </CardContent>
                        </Card>
                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardContent className="p-5">
                                <p className="text-sm text-tb-on-surface-variant">
                                    Total terverifikasi
                                </p>
                                <p className="mt-1 text-2xl font-bold">
                                    {formatRupiah(summary.approved_total)}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {canManage && (
                    <Card className="rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ImageUp className="size-5" /> Pengaturan QRIS
                                statis
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-6 lg:grid-cols-[minmax(220px,320px)_1fr]">
                            <div className="flex min-h-64 items-center justify-center rounded-xl border border-dashed border-tb-outline-variant bg-white p-4">
                                {configuration.image_url ? (
                                    <img
                                        src={configuration.image_url}
                                        alt="QRIS pembayaran Tarombo Batak"
                                        className="max-h-80 max-w-full object-contain"
                                    />
                                ) : (
                                    <p className="text-center text-sm text-tb-on-surface-variant">
                                        Gambar QRIS belum diatur.
                                    </p>
                                )}
                            </div>
                            <form
                                onSubmit={saveConfiguration}
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="qris-image">
                                        Ganti gambar QRIS
                                    </Label>
                                    <Input
                                        id="qris-image"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(event) =>
                                            configurationForm.setData(
                                                'image',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <p className="text-xs text-tb-on-surface-variant">
                                        PNG, JPG, atau WEBP · maksimal 5 MB.
                                        Gambar tersimpan secara privat dan hanya
                                        ditampilkan kepada pengguna yang login.
                                    </p>
                                    {configurationForm.errors.image && (
                                        <p className="text-sm text-destructive">
                                            {configurationForm.errors.image}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="qris-instructions">
                                        Petunjuk pembayaran
                                    </Label>
                                    <textarea
                                        id="qris-instructions"
                                        value={
                                            configurationForm.data.instructions
                                        }
                                        onChange={(event) =>
                                            configurationForm.setData(
                                                'instructions',
                                                event.target.value,
                                            )
                                        }
                                        rows={4}
                                        placeholder="Contoh: Pastikan nama merchant Tarombo Batak sebelum membayar."
                                        className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    />
                                    {configurationForm.errors.instructions && (
                                        <p className="text-sm text-destructive">
                                            {
                                                configurationForm.errors
                                                    .instructions
                                            }
                                        </p>
                                    )}
                                </div>
                                <Button
                                    type="submit"
                                    disabled={configurationForm.processing}
                                >
                                    {configurationForm.processing
                                        ? 'Menyimpan…'
                                        : 'Simpan pengaturan QRIS'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div
                    className={
                        canManage
                            ? 'space-y-6'
                            : 'grid items-start gap-6 lg:grid-cols-[minmax(280px,0.85fr)_minmax(0,1.15fr)]'
                    }
                >
                    {!canManage && (
                        <div className="space-y-5">
                            <Card className="overflow-hidden rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <QrCode className="size-5" /> Scan QRIS
                                        untuk membayar
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {configuration.image_url ? (
                                        <div className="rounded-xl bg-white p-4">
                                            <img
                                                src={configuration.image_url}
                                                alt="QRIS pembayaran Tarombo Batak"
                                                className="mx-auto max-h-[420px] w-full object-contain"
                                            />
                                        </div>
                                    ) : (
                                        <div className="rounded-xl bg-tb-surface p-8 text-center text-sm text-tb-on-surface-variant">
                                            QRIS belum tersedia. Silakan kembali
                                            lagi nanti.
                                        </div>
                                    )}
                                    {configuration.instructions && (
                                        <p className="mt-4 text-sm whitespace-pre-line text-tb-on-surface-variant">
                                            {configuration.instructions}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                                <CardHeader>
                                    <CardTitle>
                                        Kirim bukti pembayaran
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={submitPayment}
                                        className="space-y-4"
                                    >
                                        <div className="space-y-2">
                                            <Label htmlFor="qris-amount">
                                                Nominal (rupiah)
                                            </Label>
                                            <Input
                                                id="qris-amount"
                                                type="number"
                                                min="1000"
                                                max="1000000000"
                                                step="1000"
                                                required
                                                value={paymentForm.data.amount}
                                                onChange={(event) =>
                                                    paymentForm.setData(
                                                        'amount',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Contoh: 50000"
                                            />
                                            {paymentForm.errors.amount && (
                                                <p className="text-sm text-destructive">
                                                    {paymentForm.errors.amount}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="qris-reference">
                                                Nomor referensi transaksi
                                                (opsional)
                                            </Label>
                                            <Input
                                                id="qris-reference"
                                                value={
                                                    paymentForm.data.reference
                                                }
                                                onChange={(event) =>
                                                    paymentForm.setData(
                                                        'reference',
                                                        event.target.value,
                                                    )
                                                }
                                                maxLength={120}
                                                placeholder="Nomor dari aplikasi pembayaran"
                                            />
                                            {paymentForm.errors.reference && (
                                                <p className="text-sm text-destructive">
                                                    {
                                                        paymentForm.errors
                                                            .reference
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="qris-proof">
                                                Bukti pembayaran
                                            </Label>
                                            <Input
                                                id="qris-proof"
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                required
                                                onChange={(event) =>
                                                    paymentForm.setData(
                                                        'proof',
                                                        event.target
                                                            .files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                            <p className="text-xs text-tb-on-surface-variant">
                                                Unggah tangkapan layar yang
                                                menampilkan status transaksi
                                                berhasil. Maksimal 5 MB.
                                            </p>
                                            {paymentForm.errors.proof && (
                                                <p className="text-sm text-destructive">
                                                    {paymentForm.errors.proof}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="qris-note">
                                                Catatan (opsional)
                                            </Label>
                                            <textarea
                                                id="qris-note"
                                                value={paymentForm.data.note}
                                                onChange={(event) =>
                                                    paymentForm.setData(
                                                        'note',
                                                        event.target.value,
                                                    )
                                                }
                                                rows={2}
                                                maxLength={1000}
                                                placeholder="Pesan untuk admin"
                                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            />
                                            {paymentForm.errors.note && (
                                                <p className="text-sm text-destructive">
                                                    {paymentForm.errors.note}
                                                </p>
                                            )}
                                        </div>
                                        {paymentForm.progress && (
                                            <progress
                                                className="w-full"
                                                value={
                                                    paymentForm.progress
                                                        .percentage
                                                }
                                                max="100"
                                            >
                                                {
                                                    paymentForm.progress
                                                        .percentage
                                                }
                                                %
                                            </progress>
                                        )}
                                        <Button
                                            type="submit"
                                            className="w-full"
                                            disabled={
                                                paymentForm.processing ||
                                                !configuration.image_url
                                            }
                                        >
                                            {paymentForm.processing
                                                ? 'Mengirim bukti…'
                                                : 'Kirim bukti pembayaran'}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    <Card className="rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardHeader className="flex flex-wrap items-center justify-between gap-3 sm:flex-row">
                            <CardTitle className="flex items-center gap-2">
                                <ReceiptText className="size-5" />{' '}
                                {canManage
                                    ? 'Daftar pembayaran'
                                    : 'Riwayat pembayaran'}
                            </CardTitle>
                            {canManage && (
                                <div className="flex flex-wrap gap-2">
                                    {(
                                        [
                                            'all',
                                            'pending',
                                            'approved',
                                            'rejected',
                                        ] as const
                                    ).map((status) => (
                                        <Button
                                            key={status}
                                            variant={
                                                statusFilter === status
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={qris.index({
                                                    query: {
                                                        status:
                                                            status === 'all'
                                                                ? undefined
                                                                : status,
                                                    },
                                                })}
                                            >
                                                {status === 'all'
                                                    ? 'Semua'
                                                    : statusLabel[status]}
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </CardHeader>
                        <CardContent>
                            {payments.data.length === 0 ? (
                                <div className="py-12 text-center">
                                    <ReceiptText className="mx-auto size-8 text-tb-on-surface-variant" />
                                    <p className="mt-3 font-medium">
                                        Belum ada pembayaran
                                    </p>
                                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                                        Transaksi dan status verifikasi akan
                                        muncul di sini.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y divide-tb-outline-variant">
                                    {payments.data.map((payment) => (
                                        <article
                                            key={payment.id}
                                            className="space-y-3 py-4"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p className="font-semibold">
                                                        {formatRupiah(
                                                            payment.amount,
                                                        )}
                                                    </p>
                                                    <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                        {payment.user && (
                                                            <>
                                                                {
                                                                    payment.user
                                                                        .name
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    payment.user
                                                                        .email
                                                                }{' '}
                                                                ·{' '}
                                                            </>
                                                        )}
                                                        {payment.created_at ??
                                                            'Tanggal tidak tersedia'}
                                                    </p>
                                                </div>
                                                {statusBadge(payment.status)}
                                            </div>
                                            {(payment.reference ||
                                                payment.note) && (
                                                <p className="text-sm whitespace-pre-line text-tb-on-surface-variant">
                                                    {payment.reference && (
                                                        <>
                                                            Referensi:{' '}
                                                            {payment.reference}
                                                            {payment.note
                                                                ? ' · '
                                                                : ''}
                                                        </>
                                                    )}
                                                    {payment.note}
                                                </p>
                                            )}
                                            {payment.review_note && (
                                                <p className="rounded-lg bg-tb-surface p-3 text-sm">
                                                    Catatan admin:{' '}
                                                    {payment.review_note}
                                                </p>
                                            )}
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={payment.proof_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        Lihat bukti
                                                    </a>
                                                </Button>
                                                {canManage &&
                                                    payment.status ===
                                                        'pending' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    review(
                                                                        payment,
                                                                        'approved',
                                                                    )
                                                                }
                                                            >
                                                                <Check className="mr-1 size-4" />{' '}
                                                                Setujui
                                                            </Button>
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() =>
                                                                    review(
                                                                        payment,
                                                                        'rejected',
                                                                    )
                                                                }
                                                            >
                                                                <X className="mr-1 size-4" />{' '}
                                                                Tolak
                                                            </Button>
                                                        </>
                                                    )}
                                            </div>
                                            {payment.reviewed_at && (
                                                <p className="text-xs text-tb-on-surface-variant">
                                                    Ditinjau{' '}
                                                    {payment.reviewed_at}
                                                    {payment.reviewed_by
                                                        ? ` oleh ${payment.reviewed_by}`
                                                        : ''}
                                                </p>
                                            )}
                                        </article>
                                    ))}
                                </div>
                            )}
                            {(payments.prev_page_url ||
                                payments.next_page_url) && (
                                <div className="mt-4 flex items-center justify-between border-t border-tb-outline-variant pt-4 text-sm">
                                    {payments.prev_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={payments.prev_page_url}>
                                                Sebelumnya
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span />
                                    )}
                                    <span className="text-tb-on-surface-variant">
                                        Halaman {payments.current_page} dari{' '}
                                        {payments.last_page} · {payments.total}{' '}
                                        transaksi
                                    </span>
                                    {payments.next_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={payments.next_page_url}>
                                                Berikutnya
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span />
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

QrisPaymentPage.layout = {
    breadcrumbs: [{ title: 'QRIS Payment', href: qris.index() }],
};
