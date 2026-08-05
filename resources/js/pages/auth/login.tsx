import { Form, Head, Link } from '@inertiajs/react';
import { User, Mail, Loader2, Shapes } from 'lucide-react';
import { useState } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { store as loginStore } from '@/routes/login';
import { request as passwordRequest } from '@/routes/password';
import { store as registerStore } from '@/routes/register';

type MargaOption = { id: number; name: string };

type Props = {
    canResetPassword: boolean;
    margas: MargaOption[];
};

type FormMode = 'login' | 'register';

export default function Login({ canResetPassword, margas }: Props) {
    const [mode, setMode] = useState<FormMode>('login');

    return (
        <>
            <Head title={mode === 'login' ? 'Masuk' : 'Daftar'} />

            <div className="flex items-center justify-center min-h-svh px-4 py-12 bg-tb-surface bg-tb-gorga">
                <div className="w-full max-w-md">
                    <div className="text-center mb-8">
                        <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-tb-primary">
                            <BrandLogo className="size-10" />
                        </div>
                        <h1 className="font-display text-3xl font-bold text-tb-on-surface">
                            {mode === 'login' ? 'Selamat Datang Kembali' : 'Bergabung dengan Tarombo Batak'}
                        </h1>
                        <p className="mt-2 text-tb-on-surface-variant">
                            {mode === 'login'
                                ? 'Masuk untuk melanjutkan menjelajahi silsilah Anda'
                                : 'Buat akun untuk memulai perjalanan melacak akar leluhur'}
                        </p>
                    </div>

                    <div className="bg-tb-surface-bright rounded-3xl border border-tb-outline-variant shadow-xl p-6 sm:p-8">
                        <div className="flex mb-6 border-b border-tb-outline-variant" role="tablist">
                            <button
                                role="tab"
                                aria-selected={mode === 'login'}
                                onClick={() => setMode('login')}
                                className={`flex-1 py-3 px-4 text-sm font-medium transition-colors ${
                                    mode === 'login'
                                        ? 'text-tb-primary border-b-2 border-tb-primary -mb-px'
                                        : 'text-tb-on-surface-variant hover:text-tb-on-surface'
                                }`}
                            >
                                Masuk
                            </button>
                            <button
                                role="tab"
                                aria-selected={mode === 'register'}
                                onClick={() => setMode('register')}
                                className={`flex-1 py-3 px-4 text-sm font-medium transition-colors ${
                                    mode === 'register'
                                        ? 'text-tb-primary border-b-2 border-tb-primary -mb-px'
                                        : 'text-tb-on-surface-variant hover:text-tb-on-surface'
                                }`}
                            >
                                Daftar
                            </button>
                        </div>

                        {mode === 'login' && (
                            <LoginForm canResetPassword={canResetPassword} />
                        )}

                        {mode === 'register' && (
                            <RegisterForm margas={margas} />
                        )}
                    </div>

                    <p className="mt-6 text-center text-sm text-tb-on-surface-variant">
                        {mode === 'login' ? (
                            <>
                                Belum punya akun?{' '}
                                <button
                                    type="button"
                                    onClick={() => setMode('register')}
                                    className="text-tb-primary font-medium hover:underline"
                                >
                                    Daftar di sini
                                </button>
                            </>
                        ) : (
                            <>
                                Sudah punya akun?{' '}
                                <button
                                    type="button"
                                    onClick={() => setMode('login')}
                                    className="text-tb-primary font-medium hover:underline"
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

function LoginForm({ canResetPassword }: { canResetPassword: boolean }) {
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
                            <Label htmlFor="email" className="font-medium text-tb-on-surface">
                                Alamat Email
                            </Label>
                            <div className="relative">
                                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-tb-outline" />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className="pl-11 bg-tb-surface-bright border-tb-outline-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                />
                            </div>
                            <InputError message={errors.email} className="text-sm text-red-600" />
                        </div>

                        <div className="grid gap-1.5">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password" className="font-medium text-tb-on-surface">
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
                                className="bg-tb-surface-bright border-tb-outline-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.password} className="text-sm text-red-600" />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                                className="rounded border-tb-outline-variant text-tb-primary focus:ring-tb-primary"
                            />
                            <Label htmlFor="remember" className="text-sm text-tb-on-surface-variant cursor-pointer">
                                Ingat saya
                            </Label>
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

function RegisterForm({ margas }: { margas: MargaOption[] }) {
    const [margaId, setMargaId] = useState('');

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
                            <Label htmlFor="name" className="font-medium text-tb-on-surface">
                                Nama Lengkap
                            </Label>
                            <div className="relative">
                                <User className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-tb-outline" />
                                <Input
                                    id="name"
                                    type="text"
                                    name="name"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder="Nama lengkap"
                                    className="pl-11 bg-tb-surface-bright border-tb-outline-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                />
                            </div>
                            <InputError message={errors.name} className="text-sm text-red-600" />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="email" className="font-medium text-tb-on-surface">
                                Alamat Email
                            </Label>
                            <div className="relative">
                                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-tb-outline" />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className="pl-11 bg-tb-surface-bright border-tb-outline-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                />
                            </div>
                            <InputError message={errors.email} className="text-sm text-red-600" />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="marga" className="font-medium text-tb-on-surface">
                                Marga Keluarga
                            </Label>
                            <div className="relative">
                                <Shapes className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-tb-outline" />
                                <Select value={margaId} onValueChange={setMargaId}>
                                    <SelectTrigger
                                        id="marga"
                                        className="w-full pl-11 bg-tb-surface-bright border-tb-outline-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                    >
                                        <SelectValue placeholder="Pilih marga keluarga" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {margas.map((marga) => (
                                            <SelectItem key={marga.id} value={String(marga.id)}>
                                                {marga.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input type="hidden" name="marga_id" value={margaId} />
                            </div>
                            <InputError message={errors.marga_id} className="text-sm text-red-600" />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="password" className="font-medium text-tb-on-surface">
                                Kata Sandi
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={3}
                                autoComplete="new-password"
                                placeholder="Minimal 8 karakter"
                                className="bg-tb-surface-bright border-tb-outline-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.password} className="text-sm text-red-600" />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="password_confirmation" className="font-medium text-tb-on-surface">
                                Konfirmasi Kata Sandi
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                tabIndex={4}
                                autoComplete="new-password"
                                placeholder="Ulangi kata sandi"
                                className="bg-tb-surface-bright border-tb-outline-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                            />
                            <InputError message={errors.password_confirmation} className="text-sm text-red-600" />
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