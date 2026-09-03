import { Form, Head, Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Loader2, Mail, MapPin, Shapes, User } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import TurnstileWidget from '@/components/turnstile-widget';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as loginStore } from '@/routes/login';
import { request as passwordRequest } from '@/routes/password';
import regionRoutes from '@/routes/regions';
import { store as registerStore } from '@/routes/register';

type MargaOption = { id: number; name: string };
type RegencyOption = { code: string; name: string };
type AreaOption = { code: string; name: string };
type RegionOption = {
    code: string;
    name: string;
    regencies: RegencyOption[];
};

type Props = {
    canResetPassword: boolean;
    margas: MargaOption[];
    regions: RegionOption[];
    turnstileSiteKey: string | null;
};

type FormMode = 'login' | 'register';

export default function Login({
    canResetPassword,
    margas,
    regions,
    turnstileSiteKey,
}: Props) {
    const { url } = usePage();
    const initialMode = new URLSearchParams(url.split('?')[1] ?? '').get(
        'mode',
    );
    const [mode, setMode] = useState<FormMode>(
        initialMode === 'register' ? 'register' : 'login',
    );
    const [direction, setDirection] = useState<1 | -1>(1);

    const switchMode = (next: FormMode) => {
        if (next === mode) {
            return;
        }

        setDirection(next === 'register' ? 1 : -1);
        setMode(next);
    };

    return (
        <>
            <Head title={mode === 'login' ? 'Masuk' : 'Daftar'} />

            <div className="bg-tb-gorga flex min-h-svh items-center justify-center bg-tb-surface px-4 py-12">
                <div className="w-full max-w-md">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-tb-primary">
                            <BrandLogo className="size-10" />
                        </div>
                        <h1 className="font-display text-3xl font-bold text-tb-on-surface">
                            {mode === 'login'
                                ? 'Selamat Datang Kembali'
                                : 'Bergabung dengan Tarombo Batak'}
                        </h1>
                        <p className="mt-2 text-tb-on-surface-variant">
                            {mode === 'login'
                                ? 'Masuk untuk melanjutkan menjelajahi silsilah Anda'
                                : 'Buat akun untuk memulai perjalanan melacak akar leluhur'}
                        </p>
                    </div>

                    <div className="rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-6 shadow-xl sm:p-8">
                        <div
                            className="relative mb-6 flex border-b border-tb-outline-variant"
                            role="tablist"
                        >
                            <button
                                role="tab"
                                aria-selected={mode === 'login'}
                                onClick={() => switchMode('login')}
                                className={`relative flex-1 px-4 py-3 text-sm font-medium transition-colors ${
                                    mode === 'login'
                                        ? 'text-tb-primary'
                                        : 'text-tb-on-surface-variant hover:text-tb-on-surface'
                                }`}
                            >
                                Masuk
                                {mode === 'login' && (
                                    <motion.span
                                        layoutId="tab-active"
                                        className="absolute inset-x-0 bottom-0 h-0.5 bg-tb-primary"
                                        transition={{
                                            type: 'spring',
                                            stiffness: 420,
                                            damping: 32,
                                        }}
                                    />
                                )}
                            </button>
                            <button
                                role="tab"
                                aria-selected={mode === 'register'}
                                onClick={() => switchMode('register')}
                                className={`relative flex-1 px-4 py-3 text-sm font-medium transition-colors ${
                                    mode === 'register'
                                        ? 'text-tb-primary'
                                        : 'text-tb-on-surface-variant hover:text-tb-on-surface'
                                }`}
                            >
                                Daftar
                                {mode === 'register' && (
                                    <motion.span
                                        layoutId="tab-active"
                                        className="absolute inset-x-0 bottom-0 h-0.5 bg-tb-primary"
                                        transition={{
                                            type: 'spring',
                                            stiffness: 420,
                                            damping: 32,
                                        }}
                                    />
                                )}
                            </button>
                        </div>

                        <SlideSwap
                            mode={mode}
                            direction={direction}
                            margas={margas}
                            regions={regions}
                            canResetPassword={canResetPassword}
                            turnstileSiteKey={turnstileSiteKey}
                        />
                    </div>

                    <p className="mt-6 text-center text-sm text-tb-on-surface-variant">
                        {mode === 'login' ? (
                            <>
                                Belum punya akun?{' '}
                                <button
                                    type="button"
                                    onClick={() => switchMode('register')}
                                    className="font-medium text-tb-primary hover:underline"
                                >
                                    Daftar di sini
                                </button>
                            </>
                        ) : (
                            <>
                                Sudah punya akun?{' '}
                                <button
                                    type="button"
                                    onClick={() => switchMode('login')}
                                    className="font-medium text-tb-primary hover:underline"
                                >
                                    Masuk di sini
                                </button>
                            </>
                        )}
                    </p>
                </div>
            </div>
        </>
    );
}

function SlideSwap({
    mode,
    direction,
    margas,
    regions,
    canResetPassword,
    turnstileSiteKey,
}: {
    mode: FormMode;
    direction: 1 | -1;
    margas: MargaOption[];
    regions: RegionOption[];
    canResetPassword: boolean;
    turnstileSiteKey: string | null;
}) {
    const boxRef = useRef<HTMLDivElement>(null);
    const [height, setHeight] = useState<number>();

    useLayoutEffect(() => {
        if (boxRef.current) {
            setHeight(boxRef.current.offsetHeight);
        }
    }, [mode]);

    return (
        <motion.div
            initial={false}
            animate={{ height: height ?? 'auto' }}
            transition={{ duration: 0.25, ease: 'easeInOut' }}
            className="relative overflow-x-clip"
        >
            <AnimatePresence
                mode="popLayout"
                initial={false}
                custom={direction}
            >
                <motion.div
                    key={mode}
                    ref={boxRef}
                    custom={direction}
                    initial={{ x: direction * -56, opacity: 0 }}
                    animate={{ x: 0, opacity: 1 }}
                    exit={{ x: direction * 56, opacity: 0 }}
                    transition={{ duration: 0.25, ease: 'easeInOut' }}
                >
                    {mode === 'login' ? (
                        <LoginForm
                            canResetPassword={canResetPassword}
                            turnstileSiteKey={turnstileSiteKey}
                        />
                    ) : (
                        <RegisterForm margas={margas} regions={regions} />
                    )}
                </motion.div>
            </AnimatePresence>
        </motion.div>
    );
}

function LoginForm({
    canResetPassword,
    turnstileSiteKey,
}: {
    canResetPassword: boolean;
    turnstileSiteKey: string | null;
}) {
    return (
        <Form
            {...loginStore.form()}
            resetOnSuccess={['password']}
            className="flex flex-col gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-5">
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="email"
                                className="font-medium text-tb-on-surface"
                            >
                                Alamat Email
                            </Label>
                            <div className="relative">
                                <Mail className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className="border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                />
                            </div>
                            <InputError
                                message={errors.email}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <div className="flex items-center justify-between">
                                <Label
                                    htmlFor="password"
                                    className="font-medium text-tb-on-surface"
                                >
                                    Kata Sandi
                                </Label>
                                {canResetPassword && (
                                    <Link
                                        href={passwordRequest()}
                                        className="text-sm text-tb-primary hover:underline"
                                        tabIndex={5}
                                    >
                                        Lupa kata sandi?
                                    </Link>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Kata sandi"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                            />
                            <InputError
                                message={errors.password}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                                className="rounded border-tb-outline-variant text-tb-primary focus:ring-tb-primary"
                            />
                            <Label
                                htmlFor="remember"
                                className="cursor-pointer text-sm text-tb-on-surface-variant"
                            >
                                Ingat saya
                            </Label>
                        </div>

                        <div className="grid gap-1.5">
                            <TurnstileWidget siteKey={turnstileSiteKey} />
                            <InputError
                                message={errors.turnstile}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light disabled:opacity-50"
                            tabIndex={4}
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Memproses...
                                </>
                            ) : (
                                'Masuk'
                            )}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function RegisterForm({
    margas,
    regions,
}: {
    margas: MargaOption[];
    regions: RegionOption[];
}) {
    const [margaId, setMargaId] = useState('');
    const [provinceCode, setProvinceCode] = useState('');
    const [regencyCode, setRegencyCode] = useState('');
    const [districtCode, setDistrictCode] = useState('');
    const [villageCode, setVillageCode] = useState('');
    const [districts, setDistricts] = useState<AreaOption[]>([]);
    const [villages, setVillages] = useState<AreaOption[]>([]);
    const [districtsLoading, setDistrictsLoading] = useState(false);
    const [villagesLoading, setVillagesLoading] = useState(false);
    const regencies =
        regions.find((region) => region.code === provinceCode)?.regencies ?? [];

    useEffect(() => {
        if (!regencyCode) {
            return;
        }

        const controller = new AbortController();

        fetch(regionRoutes.districts(regencyCode).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat kecamatan.');
                }

                return response.json() as Promise<{ data: AreaOption[] }>;
            })
            .then((payload) => {
                if (!controller.signal.aborted) {
                    setDistricts(payload.data);
                }
            })
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setDistricts([]);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setDistrictsLoading(false);
                }
            });

        return () => controller.abort();
    }, [regencyCode]);

    useEffect(() => {
        if (!districtCode) {
            return;
        }

        const controller = new AbortController();

        fetch(regionRoutes.villages(districtCode).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat desa/kelurahan.');
                }

                return response.json() as Promise<{ data: AreaOption[] }>;
            })
            .then((payload) => {
                if (!controller.signal.aborted) {
                    setVillages(payload.data);
                }
            })
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setVillages([]);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setVillagesLoading(false);
                }
            });

        return () => controller.abort();
    }, [districtCode]);

    const selectProvince = (code: string) => {
        setProvinceCode(code);
        setRegencyCode('');
        setDistrictCode('');
        setVillageCode('');
        setDistricts([]);
        setVillages([]);
        setDistrictsLoading(false);
        setVillagesLoading(false);
    };

    const selectRegency = (code: string) => {
        setRegencyCode(code);
        setDistrictCode('');
        setVillageCode('');
        setDistricts([]);
        setVillages([]);
        setDistrictsLoading(Boolean(code));
        setVillagesLoading(false);
    };

    const selectDistrict = (code: string) => {
        setDistrictCode(code);
        setVillageCode('');
        setVillages([]);
        setVillagesLoading(Boolean(code));
    };

    return (
        <Form
            {...registerStore.form()}
            resetOnSuccess={['password', 'password_confirmation']}
            className="flex flex-col gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-5">
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="name"
                                className="font-medium text-tb-on-surface"
                            >
                                Nama Lengkap
                            </Label>
                            <div className="relative">
                                <User className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Input
                                    id="name"
                                    type="text"
                                    name="name"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder="Nama lengkap"
                                    className="border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                />
                            </div>
                            <InputError
                                message={errors.name}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="email"
                                className="font-medium text-tb-on-surface"
                            >
                                Alamat Email
                            </Label>
                            <div className="relative">
                                <Mail className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className="border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                />
                            </div>
                            <InputError
                                message={errors.email}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="marga"
                                className="font-medium text-tb-on-surface"
                            >
                                Marga Keluarga
                            </Label>
                            <div className="relative">
                                <Shapes className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Select
                                    value={margaId}
                                    onValueChange={setMargaId}
                                >
                                    <SelectTrigger
                                        id="marga"
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                    >
                                        <SelectValue placeholder="Pilih marga keluarga" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                            <InputError
                                message={errors.marga_id}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="province"
                                className="font-medium text-tb-on-surface"
                            >
                                Provinsi Domisili
                            </Label>
                            <div className="relative">
                                <MapPin className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Select
                                    value={provinceCode}
                                    onValueChange={selectProvince}
                                    required
                                >
                                    <SelectTrigger
                                        id="province"
                                        tabIndex={3}
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                    >
                                        <SelectValue placeholder="Pilih provinsi domisili" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="regency"
                                className="font-medium text-tb-on-surface"
                            >
                                Kabupaten/Kota Domisili
                            </Label>
                            <div className="relative">
                                <MapPin className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Select
                                    value={regencyCode}
                                    onValueChange={selectRegency}
                                    disabled={!provinceCode}
                                    required
                                >
                                    <SelectTrigger
                                        id="regency"
                                        tabIndex={4}
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <SelectValue
                                            placeholder={
                                                provinceCode
                                                    ? 'Pilih kabupaten/kota domisili'
                                                    : 'Pilih provinsi terlebih dahulu'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
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
                            <InputError
                                message={errors.regency_code}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="district"
                                className="font-medium text-tb-on-surface"
                            >
                                Kecamatan Domisili
                            </Label>
                            <div className="relative">
                                <MapPin className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Select
                                    value={districtCode}
                                    onValueChange={selectDistrict}
                                    disabled={!regencyCode || districtsLoading}
                                    required
                                >
                                    <SelectTrigger
                                        id="district"
                                        tabIndex={5}
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <SelectValue
                                            placeholder={
                                                districtsLoading
                                                    ? 'Memuat kecamatan...'
                                                    : regencyCode
                                                      ? 'Pilih kecamatan domisili'
                                                      : 'Pilih kabupaten/kota terlebih dahulu'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {districts.map((district) => (
                                            <SelectItem
                                                key={district.code}
                                                value={district.code}
                                            >
                                                {district.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="district_code"
                                    value={districtCode}
                                />
                            </div>
                            <InputError
                                message={errors.district_code}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="village"
                                className="font-medium text-tb-on-surface"
                            >
                                Desa/Kelurahan Domisili
                            </Label>
                            <div className="relative">
                                <MapPin className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-tb-outline" />
                                <Select
                                    value={villageCode}
                                    onValueChange={setVillageCode}
                                    disabled={!districtCode || villagesLoading}
                                    required
                                >
                                    <SelectTrigger
                                        id="village"
                                        tabIndex={6}
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <SelectValue
                                            placeholder={
                                                villagesLoading
                                                    ? 'Memuat desa/kelurahan...'
                                                    : districtCode
                                                      ? 'Pilih desa/kelurahan domisili'
                                                      : 'Pilih kecamatan terlebih dahulu'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {villages.map((village) => (
                                            <SelectItem
                                                key={village.code}
                                                value={village.code}
                                            >
                                                {village.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="village_code"
                                    value={villageCode}
                                />
                            </div>
                            <InputError
                                message={errors.village_code}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="password"
                                className="font-medium text-tb-on-surface"
                            >
                                Kata Sandi
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={7}
                                autoComplete="new-password"
                                placeholder="Minimal 8 karakter"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                            />
                            <InputError
                                message={errors.password}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="password_confirmation"
                                className="font-medium text-tb-on-surface"
                            >
                                Konfirmasi Kata Sandi
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                tabIndex={8}
                                autoComplete="new-password"
                                placeholder="Ulangi kata sandi"
                                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                            />
                            <InputError
                                message={errors.password_confirmation}
                                className="text-sm text-red-600"
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light disabled:opacity-50"
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Memproses...
                                </>
                            ) : (
                                'Daftar'
                            )}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
