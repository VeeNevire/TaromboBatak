import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import subAdmins from '@/routes/sub-admins';

type SubAdminFormValue = {
    id: number;
    name: string;
    email: string;
    marga_id: number | null;
};

type MargaOption = { id: number; name: string };

type Props = {
    subAdmin: SubAdminFormValue | null;
    margas: MargaOption[];
};

export default function SubAdminForm({ subAdmin, margas }: Props) {
    const isEdit = subAdmin !== null;

    const { data, setData, post, put, processing, errors, transform } = useForm({
        name: subAdmin?.name ?? '',
        email: subAdmin?.email ?? '',
        marga_id: subAdmin?.marga_id !== null && subAdmin?.marga_id !== undefined ? String(subAdmin.marga_id) : '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((values) => ({
            name: values.name,
            email: values.email,
            marga_id: values.marga_id ? Number(values.marga_id) : null,
            password: values.password,
            password_confirmation: values.password_confirmation,
        }));

        if (isEdit && subAdmin) {
            put(subAdmins.update(subAdmin.id).url);
        } else {
            post(subAdmins.store().url);
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Sub Admin' : 'Tambah Sub Admin'} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit text-tb-on-surface-variant">
                        <Link href={subAdmins.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Sub Admin
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {isEdit ? 'Ubah Sub Admin' : 'Tambah Sub Admin'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Sub admin dapat mengelola data anggota, marga, cerita, dan event, namun tidak
                            dapat mengelola akun.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Detail Akun
                            </CardTitle>
                            <CardDescription>Informasi akun dan password sub admin.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name" className="text-tb-on-surface">
                                    Nama <span className="text-red-600">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Mis. Budi Simanjuntak"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email" className="text-tb-on-surface">
                                    Email <span className="text-red-600">*</span>
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="nama@email.com"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="marga_id" className="text-tb-on-surface">
                                    Marga
                                </Label>
                                <Select
                                    value={data.marga_id}
                                    onValueChange={(value) => setData('marga_id', value)}
                                >
                                    <SelectTrigger
                                        id="marga_id"
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright"
                                    >
                                        <SelectValue placeholder="Pilih marga (opsional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Tanpa marga</SelectItem>
                                        {margas.map((marga) => (
                                            <SelectItem key={marga.id} value={String(marga.id)}>
                                                {marga.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.marga_id} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password" className="text-tb-on-surface">
                                    Password {isEdit ? '' : <span className="text-red-600">*</span>}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder={isEdit ? 'Kosongkan bila tidak diubah' : 'Password sub admin'}
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password_confirmation" className="text-tb-on-surface">
                                    Konfirmasi Password
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    placeholder="Ulangi password"
                                    className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3 pb-6">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-tb-primary px-6 hover:bg-tb-primary-light"
                        >
                            {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Sub Admin'}
                        </Button>
                        <Button asChild variant="ghost" className="text-tb-on-surface-variant">
                            <Link href={subAdmins.index()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

SubAdminForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sub Admin', href: subAdmins.index() },
        { title: 'Form Sub Admin', href: subAdmins.create() },
    ],
};
