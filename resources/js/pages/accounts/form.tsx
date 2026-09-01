import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ListTree, MapPin } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import accounts from '@/routes/accounts';
import regionRoutes from '@/routes/regions';

type Account = {
    id: number;
    name: string;
    email: string;
    role: string;
    marga_id: number | null;
    province_code: string | null;
    regency_code: string | null;
    district_code: string | null;
    village_code: string | null;
};
type Marga = { id: number; name: string };
type RegionOption = { code: string; name: string };
type ProvinceOption = RegionOption & { regencies: RegionOption[] };

const contributorRoles = ['contributor_main', 'contributor_member'];

const roles = [
    ['admin', 'Admin'],
    ['subadmin', 'Sub Admin'],
    ['contributor_main', 'Kontributor Utama'],
    ['contributor_member', 'Anggota Kontributor'],
    ['user', 'Pengguna'],
] as const;

export default function AccountForm({
    account,
    margas,
    managedMargaOptions,
    managedMargaIds,
    regions,
}: {
    account: Account | null;
    margas: Marga[];
    managedMargaOptions: Marga[];
    managedMargaIds: number[];
    regions: ProvinceOption[];
}) {
    const isEdit = account !== null;
    const [managementOpen, setManagementOpen] = useState(false);
    const [margaSearch, setMargaSearch] = useState('');
    const [districtOptions, setDistrictOptions] = useState<RegionOption[]>([]);
    const [villageOptions, setVillageOptions] = useState<RegionOption[]>([]);
    const [districtsLoading, setDistrictsLoading] = useState(
        Boolean(account?.regency_code),
    );
    const [villagesLoading, setVillagesLoading] = useState(
        Boolean(account?.district_code),
    );
    const { data, setData, post, put, processing, errors, transform } = useForm(
        {
            name: account?.name ?? '',
            email: account?.email ?? '',
            role: account?.role ?? 'user',
            marga_id: account?.marga_id ? String(account.marga_id) : '',
            province_code: account?.province_code ?? '',
            regency_code: account?.regency_code ?? '',
            district_code: account?.district_code ?? '',
            village_code: account?.village_code ?? '',
            managed_marga_ids: managedMargaIds,
            password: '',
            password_confirmation: '',
        },
    );

    const isContributor = contributorRoles.includes(data.role);
    const regencyOptions =
        regions.find((province) => province.code === data.province_code)
            ?.regencies ?? [];

    useEffect(() => {
        if (!data.regency_code) {
            return;
        }

        const controller = new AbortController();

        fetch(regionRoutes.districts(data.regency_code).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat kecamatan.');
                }

                return response.json() as Promise<{ data: RegionOption[] }>;
            })
            .then((payload) => {
                if (!controller.signal.aborted) {
                    setDistrictOptions(payload.data);
                }
            })
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setDistrictOptions([]);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setDistrictsLoading(false);
                }
            });

        return () => controller.abort();
    }, [data.regency_code]);

    useEffect(() => {
        if (!data.district_code) {
            return;
        }

        const controller = new AbortController();

        fetch(regionRoutes.villages(data.district_code).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat desa/kelurahan.');
                }

                return response.json() as Promise<{ data: RegionOption[] }>;
            })
            .then((payload) => {
                if (!controller.signal.aborted) {
                    setVillageOptions(payload.data);
                }
            })
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setVillageOptions([]);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setVillagesLoading(false);
                }
            });

        return () => controller.abort();
    }, [data.district_code]);
    const filteredManagedMargas = useMemo(() => {
        const query = margaSearch.trim().toLowerCase();

        return managedMargaOptions.filter((marga) =>
            query ? marga.name.toLowerCase().includes(query) : true,
        );
    }, [managedMargaOptions, margaSearch]);

    const toggleManagedMarga = (margaId: number, checked: boolean) => {
        setData(
            'managed_marga_ids',
            checked
                ? [...data.managed_marga_ids, margaId]
                : data.managed_marga_ids.filter((id) => id !== margaId),
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        transform((values) => ({
            ...values,
            marga_id: values.marga_id ? Number(values.marga_id) : null,
        }));

        if (account) {
put(accounts.update(account.id).url);
} else {
post(accounts.store().url);
}
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Akun' : 'Tambah Akun'} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="w-fit text-tb-on-surface-variant"
                    >
                        <Link href={accounts.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Data
                            Akun
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {isEdit ? 'Ubah Akun' : 'Tambah Akun'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Kelola identitas, peran, marga, dan akses login
                            akun.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Detail Akun
                            </CardTitle>
                            <CardDescription>
                                Semua kolom bertanda bintang wajib diisi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <Field label="Nama" id="name" error={errors.name}>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Nama lengkap"
                                />
                            </Field>
                            <Field
                                label="Email"
                                id="email"
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="nama@email.com"
                                />
                            </Field>
                            <Field label="Peran" id="role" error={errors.role}>
                                <select
                                    id="role"
                                    value={data.role}
                                    onChange={(e) => {
                                        const role = e.target.value;
                                        setData('role', role);

                                        if (!contributorRoles.includes(role)) {
                                            setData('managed_marga_ids', []);
                                        }
                                    }}
                                    className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                                >
                                    {roles.map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label="Marga"
                                id="marga_id"
                                error={errors.marga_id}
                            >
                                <select
                                    id="marga_id"
                                    value={data.marga_id}
                                    onChange={(e) =>
                                        setData('marga_id', e.target.value)
                                    }
                                    className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                                >
                                    <option value="">Tanpa marga</option>
                                    {margas.map((marga) => (
                                        <option key={marga.id} value={marga.id}>
                                            {marga.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <div className="grid gap-4 rounded-xl border border-tb-outline-variant bg-tb-surface-container/30 p-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <div className="flex items-center gap-2 font-semibold text-tb-on-surface">
                                        <MapPin className="size-4 text-tb-primary" />
                                        Domisili
                                    </div>
                                    <p className="mt-1 text-xs text-tb-on-surface-variant">
                                        Opsional. Jika diisi, pilih alamat secara lengkap dan berurutan.
                                    </p>
                                </div>
                                <Field
                                    label="Provinsi"
                                    id="province_code"
                                    error={errors.province_code}
                                >
                                    <select
                                        id="province_code"
                                        value={data.province_code}
                                        onChange={(event) => {
                                            setData('province_code', event.target.value);
                                            setData('regency_code', '');
                                            setData('district_code', '');
                                            setData('village_code', '');
                                            setDistrictOptions([]);
                                            setVillageOptions([]);
                                            setDistrictsLoading(false);
                                            setVillagesLoading(false);
                                        }}
                                        className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none"
                                    >
                                        <option value="">Pilih provinsi</option>
                                        {regions.map((province) => (
                                            <option key={province.code} value={province.code}>
                                                {province.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Kabupaten/Kota"
                                    id="regency_code"
                                    error={errors.regency_code}
                                >
                                    <select
                                        id="regency_code"
                                        value={data.regency_code}
                                        disabled={!data.province_code}
                                        onChange={(event) => {
                                            const regencyCode = event.target.value;

                                            setData('regency_code', regencyCode);
                                            setData('district_code', '');
                                            setData('village_code', '');
                                            setDistrictOptions([]);
                                            setVillageOptions([]);
                                            setDistrictsLoading(Boolean(regencyCode));
                                            setVillagesLoading(false);
                                        }}
                                        className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <option value="">
                                            {data.province_code
                                                ? 'Pilih kabupaten/kota'
                                                : 'Pilih provinsi dahulu'}
                                        </option>
                                        {regencyOptions.map((regency) => (
                                            <option key={regency.code} value={regency.code}>
                                                {regency.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Kecamatan"
                                    id="district_code"
                                    error={errors.district_code}
                                >
                                    <select
                                        id="district_code"
                                        value={data.district_code}
                                        disabled={!data.regency_code || districtsLoading}
                                        aria-busy={districtsLoading}
                                        onChange={(event) => {
                                            const districtCode = event.target.value;

                                            setData('district_code', districtCode);
                                            setData('village_code', '');
                                            setVillageOptions([]);
                                            setVillagesLoading(Boolean(districtCode));
                                        }}
                                        className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <option value="">
                                            {districtsLoading
                                                ? 'Memuat kecamatan...'
                                                : data.regency_code
                                                  ? 'Pilih kecamatan'
                                                  : 'Pilih kabupaten/kota dahulu'}
                                        </option>
                                        {districtOptions.map((district) => (
                                            <option key={district.code} value={district.code}>
                                                {district.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Desa/Kelurahan"
                                    id="village_code"
                                    error={errors.village_code}
                                >
                                    <select
                                        id="village_code"
                                        value={data.village_code}
                                        disabled={!data.district_code || villagesLoading}
                                        aria-busy={villagesLoading}
                                        onChange={(event) =>
                                            setData('village_code', event.target.value)
                                        }
                                        className="h-10 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <option value="">
                                            {villagesLoading
                                                ? 'Memuat desa/kelurahan...'
                                                : data.district_code
                                                  ? 'Pilih desa/kelurahan'
                                                  : 'Pilih kecamatan dahulu'}
                                        </option>
                                        {villageOptions.map((village) => (
                                            <option key={village.code} value={village.code}>
                                                {village.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            {isContributor && (
                                <Field
                                    label="Manajemen Marga"
                                    id="managed_marga_ids"
                                    error={errors.managed_marga_ids}
                                >
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-fit border-tb-primary text-tb-primary hover:bg-tb-primary/10"
                                            onClick={() => setManagementOpen(true)}
                                        >
                                            <ListTree className="size-4" />
                                            Daftar Manajemen Marga
                                        </Button>
                                        <span className="text-xs text-tb-on-surface-variant">
                                            {data.managed_marga_ids.length} marga dipilih
                                        </span>
                                    </div>
                                </Field>
                            )}
                            <Field
                                label={`Password${isEdit ? '' : ' *'}`}
                                id="password"
                                error={errors.password}
                            >
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    placeholder={
                                        isEdit
                                            ? 'Kosongkan bila tidak diubah'
                                            : 'Password akun'
                                    }
                                />
                            </Field>
                            <Field
                                label="Konfirmasi Password"
                                id="password_confirmation"
                                error={errors.password_confirmation}
                            >
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ulangi password"
                                />
                            </Field>
                        </CardContent>
                    </Card>
                    <div className="flex items-center gap-3 pb-6">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-tb-primary px-6 hover:bg-tb-primary-light"
                        >
                            {processing
                                ? 'Menyimpan...'
                                : isEdit
                                  ? 'Simpan Perubahan'
                                  : 'Tambah Akun'}
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-tb-on-surface-variant"
                        >
                            <Link href={accounts.index()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
            <Dialog open={managementOpen} onOpenChange={setManagementOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Daftar Manajemen Marga</DialogTitle>
                        <DialogDescription>
                            Pilih marga yang memiliki Pohon Silsilah Bawah dan dapat dikelola akun ini.
                        </DialogDescription>
                    </DialogHeader>
                    <Input
                        value={margaSearch}
                        onChange={(event) => setMargaSearch(event.target.value)}
                        placeholder="Cari marga..."
                    />
                    <div className="grid max-h-72 gap-2 overflow-y-auto rounded-lg border border-tb-outline-variant p-2">
                        {filteredManagedMargas.map((marga) => (
                            <label
                                key={marga.id}
                                className="flex cursor-pointer items-center gap-3 rounded-md px-3 py-2 hover:bg-tb-surface-container"
                            >
                                <Checkbox
                                    checked={data.managed_marga_ids.includes(marga.id)}
                                    onCheckedChange={(checked) =>
                                        toggleManagedMarga(marga.id, checked === true)
                                    }
                                />
                                <span className="text-sm text-tb-on-surface">{marga.name}</span>
                            </label>
                        ))}
                        {filteredManagedMargas.length === 0 && (
                            <p className="px-3 py-5 text-center text-sm text-tb-on-surface-variant">
                                Marga dengan Pohon Silsilah Bawah belum tersedia.
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button type="button" onClick={() => setManagementOpen(false)}>
                            Selesai ({data.managed_marga_ids.length})
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AccountForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Akun', href: accounts.index() },
        { title: 'Form Akun', href: accounts.create() },
    ],
};

function Field({
    label,
    id,
    error,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="text-tb-on-surface">
                {label}
                {!label.includes('*') &&
                id !== 'marga_id' &&
                id !== 'province_code' &&
                id !== 'regency_code' &&
                id !== 'district_code' &&
                id !== 'village_code' &&
                id !== 'password_confirmation' ? (
                    <span className="text-red-600"> *</span>
                ) : null}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
