// Components
import { Form, Head, Link } from '@inertiajs/react';
import { KeyRound, ArrowLeft, Mail, Loader2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login as loginRoute } from '@/routes/';
import { email as passwordEmailStore } from '@/routes/password/';

type Props = { status?: string };

export default function ForgotPassword({ status }: Props) {
    return (
        <>
            <Head title="Lupa Kata Sandi" />

            <div className="bg-tb-gorga flex min-h-svh items-center justify-center bg-tb-surface px-4 py-12">
                <div className="w-full max-w-md">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-tb-primary text-white">
                            <KeyRound className="h-7 w-7" />
                        </div>
                        <h1 className="font-display text-3xl font-bold text-tb-on-surface">
                            Lupa Kata Sandi?
                        </h1>
                        <p className="mt-2 text-tb-on-surface-variant">
                            Masukkan email Anda dan kami akan mengirimkan tautan
                            untuk mengatur ulang kata sandi
                        </p>
                    </div>

                    <div className="rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-6 shadow-xl sm:p-8">
                        {status && (
                            <div className="mb-5 rounded-xl border border-tb-primary/20 bg-tb-primary/10 px-4 py-3 text-sm font-medium text-tb-primary">
                                {status}
                            </div>
                        )}

                        <Form
                            {...passwordEmailStore.form()}
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

                                        <Button
                                            type="submit"
                                            className="mt-2 w-full rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light disabled:opacity-50"
                                            tabIndex={2}
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Mengirim...
                                                </>
                                            ) : (
                                                'Kirim Tautan Reset'
                                            )}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>

                    <p className="mt-6 text-center text-sm text-tb-on-surface-variant">
                        <Link
                            href={loginRoute()}
                            className="inline-flex items-center gap-1.5 font-medium text-tb-primary hover:underline"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Kembali ke halaman masuk
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
