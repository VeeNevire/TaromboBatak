import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ListTree } from 'lucide-react';
import { useMemo, useState } from 'react';
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

type Account = {
    id: number;
    name: string;
    email: string;
    role: string;
    marga_id: number | null;
};
type Marga = { id: number; name: string };

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
}: {
    account: Account | null;
    margas: Marga[];
    managedMargaOptions: Marga[];
    managedMargaIds: number[];
}) {
    const isEdit = account !== null;
    const [managementOpen, setManagementOpen] = useState(false);
    const [margaSearch, setMargaSearch] = useState('');
    const { data, setData, post, put, processing, errors, transform } = useForm(
        {
            name: account?.name ?? '',
            email: account?.email ?? '',
            role: account?.role ?? 'user',
            marga_id: account?.marga_id ? String(account.marga_id) : '',
            managed_marga_ids: managedMargaIds,
            password: '',
            password_confirmation: '',
        },
    );

    const isContributor = contributorRoles.includes(data.role);
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
                id !== 'password_confirmation' ? (
                    <span className="text-red-600"> *</span>
                ) : null}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
