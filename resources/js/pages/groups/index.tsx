import { Head, Link, useForm } from '@inertiajs/react';
import { MessageCircle, Plus, Send, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import announcements from '@/routes/announcements';
import groups from '@/routes/groups';

type Group = {
    id: number;
    name: string;
    owner_name: string;
    members_count: number;
    telegram_linked: boolean;
    latest_message: string | null;
};

type Contact = { id: number; name: string; telegram_linked: boolean };

export default function GroupsIndex({
    groups: items,
    contacts,
}: {
    groups: Group[];
    contacts: Contact[];
}) {
    const form = useForm<{ name: string; member_ids: number[] }>({
        name: '',
        member_ids: [],
    });

    const toggleMember = (id: number, checked: boolean) => {
        form.setData(
            'member_ids',
            checked
                ? [...form.data.member_ids, id]
                : form.data.member_ids.filter((memberId) => memberId !== id),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(groups.store().url, { onSuccess: () => form.reset() });
    };

    return (
        <>
            <Head title="Grup" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Grup
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Chat bersama anggota satu marga dan sinkronkan ke
                            Telegram.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={announcements.index()}>
                            <Send className="size-4" />
                            Kirim Pengumuman
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <div className="grid content-start gap-3 sm:grid-cols-2">
                        {items.map((group) => (
                            <Link
                                key={group.id}
                                href={groups.show(group.id)}
                                className="block"
                            >
                                <Card className="h-full border-tb-outline-variant bg-tb-surface-bright transition hover:border-tb-primary/50 hover:shadow-md">
                                    <CardContent className="space-y-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <div className="grid size-10 shrink-0 place-items-center rounded-full bg-tb-primary/10 text-tb-primary">
                                                    <Users className="size-5" />
                                                </div>
                                                <div className="min-w-0">
                                                    <h2 className="truncate font-semibold">
                                                        {group.name}
                                                    </h2>
                                                    <p className="text-xs text-tb-on-surface-variant">
                                                        Pemilik:{' '}
                                                        {group.owner_name}
                                                    </p>
                                                </div>
                                            </div>
                                            {group.telegram_linked && (
                                                <Badge>Telegram</Badge>
                                            )}
                                        </div>
                                        <p className="line-clamp-2 min-h-10 text-sm text-tb-on-surface-variant">
                                            {group.latest_message ??
                                                'Belum ada pesan.'}
                                        </p>
                                        <div className="flex items-center gap-1 text-xs text-tb-outline">
                                            <MessageCircle className="size-3.5" />
                                            {group.members_count} anggota
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                        {items.length === 0 && (
                            <Card className="sm:col-span-2">
                                <CardContent className="py-10 text-center text-sm text-tb-on-surface-variant">
                                    Belum ada grup. Buat grup pertama dari
                                    formulir di samping.
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="h-fit border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Plus className="size-5 text-tb-primary" />
                                Buat Grup
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                <div className="space-y-2">
                                    <label
                                        htmlFor="group-name"
                                        className="text-sm font-medium"
                                    >
                                        Nama grup
                                    </label>
                                    <Input
                                        id="group-name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Contoh: Pomparan Ompu Raja"
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">
                                        Pilih anggota
                                    </p>
                                    <div className="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-tb-outline-variant p-2">
                                        {contacts.map((contact) => (
                                            <label
                                                key={contact.id}
                                                className="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-tb-surface-container"
                                            >
                                                <Checkbox
                                                    checked={form.data.member_ids.includes(
                                                        contact.id,
                                                    )}
                                                    onCheckedChange={(value) =>
                                                        toggleMember(
                                                            contact.id,
                                                            value === true,
                                                        )
                                                    }
                                                />
                                                <span className="text-sm">
                                                    {contact.name}
                                                </span>
                                                <Badge
                                                    className={`ml-auto text-[10px] ${contact.telegram_linked ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-tb-surface-container text-tb-outline'}`}
                                                >
                                                    {contact.telegram_linked
                                                        ? 'connected'
                                                        : 'not-connected'}
                                                </Badge>
                                            </label>
                                        ))}
                                        {contacts.length === 0 && (
                                            <p className="p-2 text-sm text-tb-outline">
                                                Belum ada kontak satu marga.
                                            </p>
                                        )}
                                    </div>
                                    <InputError
                                        message={form.errors.member_ids}
                                    />
                                </div>
                                <Button
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    <Plus className="size-4" />
                                    {form.processing
                                        ? 'Membuat...'
                                        : 'Buat Grup'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
