import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Megaphone,
    Send,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import announcements from '@/routes/announcements';
import groupsRoute from '@/routes/groups';

type Contact = { id: number; name: string; telegram_linked: boolean };
type Group = { id: number; name: string; telegram_linked: boolean };
type Recent = {
    id: number;
    body: string;
    target_type: string;
    sent_count: number;
    failed_count: number;
    skipped_count: number;
    completed: boolean;
    created_at: string | null;
};

export default function AnnouncementsIndex({
    contacts,
    groups,
    mayContactAnnounce,
    recent,
}: {
    contacts: Contact[];
    groups: Group[];
    mayContactAnnounce: boolean;
    recent: Recent[];
}) {
    const queryGroup =
        typeof window !== 'undefined'
            ? Number(
                  new URLSearchParams(window.location.search).get('group'),
              ) || null
            : null;
    const form = useForm<{
        target_type: 'contacts' | 'group';
        body: string;
        contact_ids: number[];
        chat_group_id: number | null;
    }>({
        target_type: queryGroup
            ? 'group'
            : mayContactAnnounce
              ? 'contacts'
              : 'group',
        body: '',
        contact_ids: [],
        chat_group_id: queryGroup,
    });

    const toggleContact = (id: number, checked: boolean) =>
        form.setData(
            'contact_ids',
            checked
                ? [...form.data.contact_ids, id]
                : form.data.contact_ids.filter((contactId) => contactId !== id),
        );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(announcements.store().url, {
            onSuccess: () => form.reset('body', 'contact_ids'),
        });
    };

    return (
        <>
            <Head title="Pengumuman Telegram" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link href={groupsRoute.index()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold md:text-3xl">
                            Pengumuman Telegram
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Kirim pengumuman ke kontak atau grup Telegram yang
                            telah terhubung.
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                    <Card className="border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Megaphone className="size-5 text-tb-primary" />
                                Pengumuman Baru
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {mayContactAnnounce && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'target_type',
                                                    'contacts',
                                                )
                                            }
                                            className={`rounded-xl border p-4 text-left ${form.data.target_type === 'contacts' ? 'border-tb-primary bg-tb-primary/5' : 'border-tb-outline-variant'}`}
                                        >
                                            <Users className="mb-2 size-5" />
                                            <span className="font-semibold">
                                                Daftar Kontak
                                            </span>
                                            <p className="mt-1 text-xs text-tb-outline">
                                                Pesan pribadi ke kontak terpilih
                                            </p>
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            form.setData('target_type', 'group')
                                        }
                                        className={`rounded-xl border p-4 text-left ${form.data.target_type === 'group' ? 'border-tb-primary bg-tb-primary/5' : 'border-tb-outline-variant'}`}
                                    >
                                        <Send className="mb-2 size-5" />
                                        <span className="font-semibold">
                                            Grup Telegram
                                        </span>
                                        <p className="mt-1 text-xs text-tb-outline">
                                            Kirim ke satu grup terhubung
                                        </p>
                                    </button>
                                </div>
                                {form.data.target_type === 'contacts' ? (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">
                                            Pilih penerima
                                        </p>
                                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-tb-outline-variant p-2">
                                            {contacts.map((contact) => (
                                                <label
                                                    key={contact.id}
                                                    className={`flex items-center gap-3 rounded-lg p-2 ${contact.telegram_linked ? 'cursor-pointer hover:bg-tb-surface-container' : 'opacity-60'}`}
                                                >
                                                    <Checkbox
                                                        disabled={
                                                            !contact.telegram_linked
                                                        }
                                                        checked={form.data.contact_ids.includes(
                                                            contact.id,
                                                        )}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleContact(
                                                                contact.id,
                                                                value === true,
                                                            )
                                                        }
                                                    />
                                                    <span className="flex-1 text-sm">
                                                        {contact.name}
                                                    </span>
                                                    <Badge
                                                        variant={
                                                            contact.telegram_linked
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {contact.telegram_linked
                                                            ? 'Terhubung'
                                                            : 'Belum terhubung'}
                                                    </Badge>
                                                </label>
                                            ))}
                                        </div>
                                        <InputError
                                            message={form.errors.contact_ids}
                                        />
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        <label
                                            htmlFor="announcement-group"
                                            className="text-sm font-medium"
                                        >
                                            Pilih grup
                                        </label>
                                        <select
                                            id="announcement-group"
                                            value={
                                                form.data.chat_group_id ?? ''
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'chat_group_id',
                                                    event.target.value
                                                        ? Number(
                                                              event.target
                                                                  .value,
                                                          )
                                                        : null,
                                                )
                                            }
                                            className="h-10 w-full rounded-md border border-tb-outline-variant bg-transparent px-3 text-sm"
                                        >
                                            <option value="">
                                                Pilih grup Telegram
                                            </option>
                                            {groups.map((group) => (
                                                <option
                                                    key={group.id}
                                                    value={group.id}
                                                    disabled={
                                                        !group.telegram_linked
                                                    }
                                                >
                                                    {group.name}
                                                    {group.telegram_linked
                                                        ? ''
                                                        : ' — belum terhubung'}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={form.errors.chat_group_id}
                                        />
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <label
                                        htmlFor="announcement-body"
                                        className="text-sm font-medium"
                                    >
                                        Isi pengumuman
                                    </label>
                                    <textarea
                                        id="announcement-body"
                                        rows={7}
                                        maxLength={2000}
                                        value={form.data.body}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Tulis pengumuman…"
                                        className="w-full resize-y rounded-xl border border-tb-outline-variant bg-transparent px-3 py-2 text-sm outline-none focus:border-tb-primary"
                                    />
                                    <div className="flex justify-between">
                                        <InputError
                                            message={form.errors.body}
                                        />
                                        <span className="text-xs text-tb-outline">
                                            {form.data.body.length}/2000
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    <Send className="size-4" />
                                    {form.processing
                                        ? 'Memasukkan antrean…'
                                        : 'Kirim Pengumuman'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="h-fit border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle>Riwayat Pengiriman</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recent.map((item) => (
                                <div
                                    key={item.id}
                                    className="space-y-2 rounded-xl border border-tb-outline-variant p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <Badge variant="outline">
                                            {item.target_type === 'contacts'
                                                ? 'Kontak'
                                                : 'Grup'}
                                        </Badge>
                                        {item.completed ? (
                                            <CheckCircle2 className="size-4 text-emerald-500" />
                                        ) : (
                                            <Clock className="size-4 text-amber-500" />
                                        )}
                                    </div>
                                    <p className="line-clamp-3 text-sm">
                                        {item.body}
                                    </p>
                                    <div className="flex flex-wrap gap-3 text-xs">
                                        <span className="text-emerald-600">
                                            Terkirim {item.sent_count}
                                        </span>
                                        <span className="text-red-600">
                                            Gagal {item.failed_count}
                                        </span>
                                        <span className="text-amber-600">
                                            Dilewati {item.skipped_count}
                                        </span>
                                    </div>
                                </div>
                            ))}
                            {recent.length === 0 && (
                                <p className="py-8 text-center text-sm text-tb-outline">
                                    Belum ada pengumuman.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
