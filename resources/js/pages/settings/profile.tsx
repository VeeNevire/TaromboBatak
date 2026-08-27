import { Form, Head, usePage } from '@inertiajs/react';
import { Link2, MapPin, Send, Shapes, Unlink } from 'lucide-react';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit } from '@/routes/profile';
import telegramConnection from '@/routes/telegram-connection';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

type MargaOption = { id: number; name: string };
type RegencyOption = { code: string; name: string };
type RegionOption = {
    code: string;
    name: string;
    regencies: RegencyOption[];
};

type Props = {
    margas: MargaOption[];
    regions: RegionOption[];
    telegramConnection: {
        username: string | null;
        display_name: string;
        linked_at: string;
    } | null;
    telegramBotConfigured: boolean;
};

const EMPTY_VALUE = 'none';

export default function Profile({
    margas,
    regions,
    telegramConnection: telegramAccount,
    telegramBotConfigured,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const [margaId, setMargaId] = useState(
        auth.user.marga_id ? String(auth.user.marga_id) : '',
    );
    const [provinceCode, setProvinceCode] = useState(
        auth.user.province_code ?? '',
    );
    const [regencyCode, setRegencyCode] = useState(
        auth.user.regency_code ?? '',
    );
    const regencies =
        regions.find((region) => region.code === provinceCode)?.regencies ?? [];

    const selectProvince = (code: string) => {
        const nextCode = code === EMPTY_VALUE ? '' : code;

        setProvinceCode(nextCode);
        setRegencyCode('');
    };

    return (
        <>
            <Head title="Pengaturan Profil" />

            <h1 className="sr-only">Pengaturan Profil</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profil"
                    description="Ubah informasi akun, marga keluarga, dan domisili Anda."
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Nama lengkap"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Alamat Email</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Alamat email"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="marga_id">Marga Keluarga</Label>

                                <div className="relative">
                                    <Shapes className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-tb-outline" />
                                    <Select
                                        value={margaId || EMPTY_VALUE}
                                        onValueChange={(value) =>
                                            setMargaId(
                                                value === EMPTY_VALUE
                                                    ? ''
                                                    : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="marga_id"
                                            className="w-full pl-10"
                                        >
                                            <SelectValue placeholder="Pilih marga keluarga" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={EMPTY_VALUE}>
                                                Belum memilih marga
                                            </SelectItem>
                                            {margas.map((marga) => (
                                                <SelectItem
                                                    key={marga.id}
                                                    value={String(marga.id)}
                                                >
                                                    {marga.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="hidden"
                                        name="marga_id"
                                        value={margaId}
                                    />
                                </div>

                                <InputError message={errors.marga_id} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="province_code">
                                        Provinsi
                                    </Label>

                                    <div className="relative">
                                        <MapPin className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-tb-outline" />
                                        <Select
                                            value={provinceCode || EMPTY_VALUE}
                                            onValueChange={selectProvince}
                                        >
                                            <SelectTrigger
                                                id="province_code"
                                                className="w-full pl-10"
                                            >
                                                <SelectValue placeholder="Pilih provinsi" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={EMPTY_VALUE}>
                                                    Belum memilih provinsi
                                                </SelectItem>
                                                {regions.map((region) => (
                                                    <SelectItem
                                                        key={region.code}
                                                        value={region.code}
                                                    >
                                                        {region.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="province_code"
                                            value={provinceCode}
                                        />
                                    </div>

                                    <InputError
                                        message={errors.province_code}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="regency_code">
                                        Kota/Kabupaten
                                    </Label>

                                    <div className="relative">
                                        <MapPin className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-tb-outline" />
                                        <Select
                                            value={regencyCode || EMPTY_VALUE}
                                            onValueChange={(value) =>
                                                setRegencyCode(
                                                    value === EMPTY_VALUE
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                            disabled={!provinceCode}
                                        >
                                            <SelectTrigger
                                                id="regency_code"
                                                className="w-full pl-10"
                                            >
                                                <SelectValue
                                                    placeholder={
                                                        provinceCode
                                                            ? 'Pilih kota/kabupaten'
                                                            : 'Pilih provinsi dahulu'
                                                    }
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={EMPTY_VALUE}>
                                                    Belum memilih kota/kabupaten
                                                </SelectItem>
                                                {regencies.map((regency) => (
                                                    <SelectItem
                                                        key={regency.code}
                                                        value={regency.code}
                                                    >
                                                        {regency.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="regency_code"
                                            value={regencyCode}
                                        />
                                    </div>

                                    <InputError message={errors.regency_code} />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Simpan
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Card className="border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Send className="size-5 text-sky-500" />
                            Telegram
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {telegramAccount ? (
                            <>
                                <div className="flex flex-col gap-3 rounded-xl bg-emerald-500/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="font-semibold text-emerald-700">
                                            Telegram terhubung
                                        </p>
                                        <p className="text-sm text-tb-on-surface-variant">
                                            {telegramAccount.display_name}
                                            {telegramAccount.username
                                                ? ` (@${telegramAccount.username})`
                                                : ''}
                                        </p>
                                    </div>
                                    <Form
                                        {...telegramConnection.destroy.form()}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                <Unlink className="size-4" />
                                                Putuskan
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                                <p className="text-xs text-tb-outline">
                                    Akun ini dapat menerima pengumuman dan
                                    digunakan untuk memasangkan grup Telegram.
                                </p>
                            </>
                        ) : (
                            <div className="space-y-4">
                                <p className="text-sm text-tb-on-surface-variant">
                                    Hubungkan Telegram agar Anda dapat menerima
                                    pengumuman dan memasangkan grup Telegram ke
                                    aplikasi.
                                </p>
                                <Form {...telegramConnection.store.form()}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                !telegramBotConfigured
                                            }
                                        >
                                            <Link2 className="size-4" />
                                            Hubungkan Telegram
                                        </Button>
                                    )}
                                </Form>
                                {!telegramBotConfigured && (
                                    <p className="text-sm text-red-600">
                                        Bot Telegram belum dikonfigurasi oleh
                                        administrator.
                                    </p>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan Profil',
            href: edit(),
        },
    ],
};
