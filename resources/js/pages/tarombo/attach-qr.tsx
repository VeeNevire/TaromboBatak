import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent, PointerEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import tarombo from '@/routes/tarombo';

type Account = { id: number; name: string; email: string };
type Snapshot = { id: number; title: string; image_url: string };
const positions = [
    { label: 'Kanan bawah', x: 98, y: 98 },
    { label: 'Kiri bawah', x: 2, y: 98 },
    { label: 'Kanan atas', x: 98, y: 2 },
    { label: 'Kiri atas', x: 2, y: 2 },
];

export default function AttachQr({
    snapshot,
    users,
    token,
    hasPreview,
    qrModules,
    qrImageUrl,
    imageSize,
}: {
    snapshot: Snapshot;
    users: Account[];
    token: string;
    hasPreview: boolean;
    qrModules: number;
    qrImageUrl: string;
    imageSize: { width: number; height: number };
}) {
    const form = useForm({ user_id: '', token, x: 98, y: 98, size: 18 });
    const [search, setSearch] = useState('');
    const [imageFailed, setImageFailed] = useState(false);
    const previewRef = useRef<HTMLDivElement>(null);
    const drag = useRef<{
        x: number;
        y: number;
        left: number;
        top: number;
    } | null>(null);
    const recipient = users.find(
        (user) => String(user.id) === form.data.user_id,
    );
    const visibleUsers = users.filter(
        (user) =>
            `${user.name} ${user.email}`
                .toLowerCase()
                .includes(search.toLowerCase()) ||
            String(user.id) === form.data.user_id,
    );
    // The QR endpoint uses whole modules with a four-module white border.
    const modules = qrModules;
    const requestedPixels = Math.round(
        (Math.min(imageSize.width, imageSize.height) * form.data.size) / 100,
    );
    const qrSize = Math.max(2, Math.floor(requestedPixels / modules)) * modules;
    const qrWidth = (qrSize / imageSize.width) * 100;
    const qrHeight = (qrSize / imageSize.height) * 100;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(tarombo.qr.store.url(snapshot.id));
    };
    const move = (event: PointerEvent<HTMLButtonElement>) => {
        if (!drag.current || !previewRef.current) {
            return;
        }

        const rect = previewRef.current.getBoundingClientRect();
        const availableX = rect.width * (1 - qrWidth / 100);
        const availableY = rect.height * (1 - qrHeight / 100);
        form.setData((data) => ({
            ...data,
            x: Math.max(
                0,
                Math.min(
                    100,
                    drag.current!.left +
                        ((event.clientX - drag.current!.x) /
                            Math.max(1, availableX)) *
                            100,
                ),
            ),
            y: Math.max(
                0,
                Math.min(
                    100,
                    drag.current!.top +
                        ((event.clientY - drag.current!.y) /
                            Math.max(1, availableY)) *
                            100,
                ),
            ),
        }));
    };

    return (
        <>
            <Head title={`Tempel QR · ${snapshot.title}`} />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 text-tb-on-surface md:p-6">
                <Link
                    href={tarombo.snapshots.index({
                        query: { filter: 'compiled', display: 'images' },
                    })}
                    className="text-sm font-semibold text-tb-primary"
                >
                    ← Hasil Compile
                </Link>
                <div>
                    <h1 className="font-display text-2xl font-bold">
                        Tempel QR Code
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        {snapshot.title}
                    </p>
                </div>
                <div className="rounded-xl border border-tb-outline-variant bg-tb-surface-container p-4">
                    <h2 className="text-sm font-semibold">
                        Alur Tempel QR Code
                    </h2>
                    <p className="mt-2 text-sm leading-7 text-tb-on-surface-variant">
                        Pilih akun tujuan → Atur posisi QR → Sistem membuat link
                        viewer unik dan QR → QR ditempel ke gambar → Hasil
                        disimpan ke Hasil Compile akun tujuan.
                    </p>
                    <p className="mt-2 text-xs leading-relaxed text-tb-on-surface-variant">
                        Saat QR dipindai lewat HP, halaman publik menampilkan
                        judul Tarombo, gambar yang bisa diperbesar, dan tombol
                        Unduh. Tidak perlu login.
                    </p>
                </div>
                {!hasPreview ? (
                    <p
                        role="alert"
                        className="rounded-lg border border-tb-outline-variant p-4"
                    >
                        Gambar Hasil Compile tidak tersedia. Kembali ke daftar
                        dan pilih gambar lain.
                    </p>
                ) : (
                    <form
                        onSubmit={submit}
                        className="grid items-start gap-5 lg:grid-cols-[20rem_1fr]"
                    >
                        <div className="flex flex-col gap-5 rounded-xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                            <fieldset className="flex flex-col gap-2">
                                <legend className="mb-3 font-semibold">
                                    1. Pilih akun tujuan
                                </legend>
                                <Label htmlFor="qr-account-search">
                                    Cari nama atau email
                                </Label>
                                <Input
                                    id="qr-account-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Misalnya Andi"
                                />
                                <Label htmlFor="qr-user">Simpan ke akun</Label>
                                <select
                                    id="qr-user"
                                    required
                                    value={form.data.user_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'user_id',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright p-2 text-sm"
                                >
                                    <option value="">Pilih akun user...</option>
                                    {visibleUsers.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name} · {user.email}
                                        </option>
                                    ))}
                                </select>
                                {visibleUsers.length === 0 && (
                                    <p className="text-sm text-tb-on-surface-variant">
                                        Akun tidak ditemukan.
                                    </p>
                                )}
                            </fieldset>
                            <fieldset
                                disabled={!recipient}
                                className="flex flex-col gap-3 disabled:opacity-50"
                            >
                                <legend className="mb-3 font-semibold">
                                    2. Tentukan posisi QR
                                </legend>
                                <div className="grid grid-cols-2 gap-2">
                                    {positions.map((position) => (
                                        <Button
                                            key={position.label}
                                            type="button"
                                            variant={
                                                form.data.x === position.x &&
                                                form.data.y === position.y
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                form.setData((data) => ({
                                                    ...data,
                                                    x: position.x,
                                                    y: position.y,
                                                }))
                                            }
                                        >
                                            {position.label}
                                        </Button>
                                    ))}
                                </div>
                                <p className="text-xs leading-relaxed text-tb-on-surface-variant">
                                    Untuk posisi bebas, seret QR pada pratinjau.
                                    Letakkan di area yang tidak menutupi nama
                                    atau garis silsilah.
                                </p>
                                <Label htmlFor="qr-size">
                                    Ukuran QR: {form.data.size}%
                                </Label>
                                <input
                                    id="qr-size"
                                    type="range"
                                    min={10}
                                    max={30}
                                    value={form.data.size}
                                    onChange={(event) =>
                                        form.setData(
                                            'size',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="w-full accent-tb-primary"
                                />
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label htmlFor="qr-x">
                                            Posisi horizontal (%)
                                        </Label>
                                        <Input
                                            id="qr-x"
                                            type="number"
                                            min={0}
                                            max={100}
                                            step="any"
                                            value={Math.round(form.data.x)}
                                            onChange={(event) =>
                                                form.setData(
                                                    'x',
                                                    Math.max(
                                                        0,
                                                        Math.min(
                                                            100,
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        ),
                                                    ),
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="qr-y">
                                            Posisi vertikal (%)
                                        </Label>
                                        <Input
                                            id="qr-y"
                                            type="number"
                                            min={0}
                                            max={100}
                                            step="any"
                                            value={Math.round(form.data.y)}
                                            onChange={(event) =>
                                                form.setData(
                                                    'y',
                                                    Math.max(
                                                        0,
                                                        Math.min(
                                                            100,
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        ),
                                                    ),
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </fieldset>
                            <div className="flex flex-col gap-3">
                                <h2 className="font-semibold">
                                    3. Simpan gambar baru
                                </h2>
                                {recipient && (
                                    <p className="text-sm leading-relaxed">
                                        Hasil akan masuk ke{' '}
                                        <strong>Hasil Compile</strong> akun{' '}
                                        <strong>{recipient.name}</strong>.
                                    </p>
                                )}
                                {Object.entries(form.errors).map(
                                    ([key, error]) => (
                                        <p
                                            key={key}
                                            role="alert"
                                            className="text-sm text-red-600"
                                        >
                                            {error}
                                        </p>
                                    ),
                                )}
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !recipient ||
                                        !qrImageUrl ||
                                        imageFailed
                                    }
                                >
                                    {form.processing
                                        ? 'Menyimpan...'
                                        : 'Tempel QR & simpan ke akun'}
                                </Button>
                                <p className="text-xs leading-relaxed text-tb-on-surface-variant">
                                    Gambar sumber tetap tersimpan. QR membuka
                                    halaman publik: siapa pun yang memiliki link
                                    dapat melihat dan mengunduh gambar ini.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-col gap-3">
                            <h2 className="font-semibold">Pratinjau hasil</h2>
                            <p className="text-sm text-tb-on-surface-variant">
                                {recipient
                                    ? 'QR sudah dibuat. Pilih pojok atau seret QR untuk mengatur posisinya.'
                                    : 'Pilih akun tujuan terlebih dahulu untuk menampilkan QR Code pada gambar.'}
                            </p>
                            {imageFailed && (
                                <p
                                    role="alert"
                                    className="text-sm text-red-600"
                                >
                                    Gambar atau QR gagal dimuat. Muat ulang
                                    halaman untuk mencoba lagi.
                                </p>
                            )}
                            <div
                                ref={previewRef}
                                className="relative overflow-hidden rounded-lg border border-tb-outline-variant"
                            >
                                <img
                                    src={snapshot.image_url}
                                    alt={snapshot.title}
                                    className="block h-auto w-full"
                                    width={imageSize.width}
                                    height={imageSize.height}
                                    onError={() => setImageFailed(true)}
                                />
                                {recipient && (
                                    <button
                                        type="button"
                                        aria-label="Geser posisi QR Code"
                                        className="absolute cursor-move touch-none"
                                        style={{
                                            width: `${qrWidth}%`,
                                            left: `${((100 - qrWidth) * form.data.x) / 100}%`,
                                            top: `${((100 - qrHeight) * form.data.y) / 100}%`,
                                        }}
                                        onPointerDown={(event) => {
                                            event.preventDefault();
                                            event.currentTarget.setPointerCapture(
                                                event.pointerId,
                                            );
                                            drag.current = {
                                                x: event.clientX,
                                                y: event.clientY,
                                                left: form.data.x,
                                                top: form.data.y,
                                            };
                                        }}
                                        onPointerMove={move}
                                        onPointerUp={() => {
                                            drag.current = null;
                                        }}
                                        onPointerCancel={() => {
                                            drag.current = null;
                                        }}
                                    >
                                        <img
                                            src={qrImageUrl}
                                            alt="QR Code untuk hasil Tarombo"
                                            draggable={false}
                                            className="block w-full"
                                            onError={() => setImageFailed(true)}
                                        />
                                    </button>
                                )}
                            </div>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}
AttachQr.layout = {
    breadcrumbs: [
        { title: 'Hasil Compile', href: tarombo.snapshots.index() },
        { title: 'Tempel QR Code', href: '#' },
    ],
};
