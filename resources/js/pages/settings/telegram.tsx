import { Form, Head, Link, router } from '@inertiajs/react';
import { Link2, QrCode, Unlink } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import telegramMessages from '@/routes/telegram-messages';
import telegramMtproto from '@/routes/telegram-mtproto';

type Account = { username: string | null; display_name: string; connection_status: string } | null;
type Props = { configured: boolean; account: Account; pendingStatus: string | null; qrSvg: string | null; qrExpiresAt: string | null };

export default function TelegramSettings({ configured, account, pendingStatus, qrSvg, qrExpiresAt }: Props) {
    const [mode, setMode] = useState<'phone' | 'qr'>(pendingStatus?.startsWith('qr_') ? 'qr' : 'phone');

    useEffect(() => {
        if (mode !== 'qr' || pendingStatus !== 'qr_pending') return;
        const timer = window.setInterval(async () => {
            const response = await fetch(telegramMtproto.qrStatus.url(), { headers: { Accept: 'application/json' } });
            const result = (await response.json()) as { status: string };
            if (result.status === 'connected') {
                window.clearInterval(timer);
                router.reload({ only: ['account', 'pendingStatus', 'qrSvg', 'qrExpiresAt'] });
            } else if (result.status !== 'qr_pending') {
                window.clearInterval(timer);
                router.reload({ only: ['account', 'pendingStatus', 'qrSvg', 'qrExpiresAt'] });
            }
        }, 2500);
        return () => window.clearInterval(timer);
    }, [mode, pendingStatus]);

    return <>
        <Head title="Koneksi Telegram MTProto" />
        <div className="space-y-6">
            <Heading variant="small" title="Koneksi Telegram" description="Hubungkan Telegram dengan nomor telepon atau scan QR untuk membaca chat dan channel." />
            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader><CardTitle>Telegram MTProto</CardTitle></CardHeader>
                <CardContent className="space-y-5">
                    {!configured && <p className="rounded-lg bg-amber-500/10 p-3 text-sm text-amber-700">Integrasi MTProto belum dikonfigurasi administrator.</p>}
                    {account ? <>
                        <div className="rounded-xl bg-emerald-500/10 p-4"><p className="font-semibold text-emerald-700">Terhubung</p><p className="text-sm text-tb-on-surface-variant">{account.display_name}{account.username ? ` (@${account.username})` : ''}</p><p className="mt-1 text-xs text-tb-outline">Status listener: {account.connection_status}</p></div>
                        <Form {...telegramMtproto.destroy.form()} options={{ preserveScroll: true, onBefore: () => window.confirm('Putuskan Telegram dan hapus semua session serta pesan lokal?') }}>{({ processing }) => <Button type="submit" variant="outline" disabled={processing}><Unlink className="size-4" /> Putuskan & hapus semua session</Button>}</Form>
                        <Link href={telegramMessages.index()} className="text-sm text-sky-600 hover:underline">Lihat pesan Telegram</Link>
                    </> : mode === 'qr' && pendingStatus === 'qr_pending' ? <div className="space-y-4">
                        <p className="text-sm text-tb-on-surface-variant">Buka Telegram di ponsel → Settings → Devices → Link Desktop Device, lalu scan QR ini.</p>
                        {qrSvg && <div className="flex justify-center rounded-xl bg-white p-4" dangerouslySetInnerHTML={{ __html: qrSvg }} />}
                        <p className="text-center text-xs text-tb-outline">Menunggu scan QR{qrExpiresAt ? ` · berlaku sampai ${qrExpiresAt}` : ''}...</p>
                        <Form {...telegramMtproto.qr.form()} options={{ preserveScroll: true }}>{({ processing }) => <Button variant="outline" disabled={processing}><QrCode className="size-4" /> Buat QR baru</Button>}</Form>
                    </div> : pendingStatus === 'phone_code_required' ? <div className="space-y-3">
                        <Form {...telegramMtproto.code.form()} options={{ preserveScroll: true }}>{({ processing, errors }) => <><Label htmlFor="code">Kode Telegram</Label><Input id="code" name="code" inputMode="numeric" autoComplete="one-time-code" required /><InputError message={errors.code} /><Button disabled={processing}>Verifikasi kode</Button></>}</Form>
                        <Form {...telegramMtproto.resend.form()} options={{ preserveScroll: true }}>{({ processing }) => <Button type="submit" variant="outline" disabled={processing}>Kirim ulang kode</Button>}</Form>
                    </div> : pendingStatus === 'password_required' ? <Form {...telegramMtproto.password.form()} options={{ preserveScroll: true }}>{({ processing, errors }) => <div className="space-y-3"><Label htmlFor="password">Password 2FA Telegram</Label><Input id="password" name="password" type="password" autoComplete="current-password" required /><InputError message={errors.password} /><Button disabled={processing}>Verifikasi 2FA</Button></div>}</Form> : <>
                        <div className="flex gap-2 rounded-lg bg-tb-surface-container p-1"><Button type="button" variant={mode === 'phone' ? 'default' : 'ghost'} onClick={() => setMode('phone')} className="flex-1">Nomor telepon</Button><Button type="button" variant={mode === 'qr' ? 'default' : 'ghost'} onClick={() => setMode('qr')} className="flex-1"><QrCode className="size-4" /> Scan QR</Button></div>
                        {mode === 'qr' ? <Form {...telegramMtproto.qr.form()} options={{ preserveScroll: true }}>{({ processing }) => <div className="space-y-3"><p className="text-sm text-tb-on-surface-variant">Scan QR menggunakan Telegram di ponsel Anda.</p><Button disabled={processing || !configured}><QrCode className="size-4" /> Tampilkan QR Telegram</Button></div>}</Form> : <Form {...telegramMtproto.store.form()} options={{ preserveScroll: true }}>{({ processing, errors }) => <div className="space-y-3"><p className="text-sm text-tb-on-surface-variant">Nomor telepon hanya digunakan untuk proses login Telegram.</p><Label htmlFor="phone">Nomor telepon internasional</Label><Input id="phone" name="phone" placeholder="+628123456789" inputMode="tel" required /><InputError message={errors.phone} /><Button disabled={processing || !configured}><Link2 className="size-4" /> Hubungkan akun Telegram</Button></div>}</Form>}
                    </>}
                </CardContent>
            </Card>
        </div>
    </>;
}

TelegramSettings.layout = { breadcrumbs: [{ title: 'Koneksi Telegram', href: telegramMtproto.index() }] };
