import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    ArrowLeft,
    Check,
    Copy,
    Link2,
    Megaphone,
    Send,
    Trash2,
    Unlink,
    Users,
} from 'lucide-react';
import type { FormEvent} from 'react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import announcements from '@/routes/announcements';
import groups from '@/routes/groups';
import { edit as editProfile } from '@/routes/profile';

type Member = { id: number; name: string; role: string };
type Contact = { id: number; name: string };
type Message = {
    id: number;
    sender_id: number | null;
    sender_name: string | null;
    body: string;
    source: string;
    delivery_status: string | null;
    created_at: string | null;
};
type Group = {
    id: number;
    name: string;
    owner_id: number;
    owner_name: string;
    telegram_title: string | null;
    telegram_linked: boolean;
    telegram_account_linked: boolean;
    can_manage: boolean;
    can_announce: boolean;
    members: Member[];
};

export default function GroupShow({
    group,
    messages: initialMessages,
    availableContacts,
    telegramLinkCode,
}: {
    group: Group;
    messages: Message[];
    availableContacts: Contact[];
    telegramLinkCode: string | null;
}) {
    const { auth } = usePage().props;
    const [messages, setMessages] = useState(initialMessages);
    const endRef = useRef<HTMLDivElement>(null);
    const messageForm = useForm({ body: '' });
    const memberForm = useForm({
        member_ids: group.members
            .filter((member) => member.id !== group.owner_id)
            .map((member) => member.id),
    });

    useEcho<Message>(
        `groups.${group.id}`,
        '.group.message.sent',
        (incoming) => {
            setMessages((current) =>
                [
                    ...current.filter((item) => item.id !== incoming.id),
                    incoming,
                ].sort((a, b) => a.id - b.id),
            );
        },
        [group.id],
    );

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const send = (event: FormEvent) => {
        event.preventDefault();
        messageForm.post(groups.messages.store(group.id).url, {
            preserveScroll: true,
            onSuccess: () => messageForm.reset(),
        });
    };

    const toggleMember = (id: number, checked: boolean) =>
        memberForm.setData(
            'member_ids',
            checked
                ? [...memberForm.data.member_ids, id]
                : memberForm.data.member_ids.filter(
                      (memberId) => memberId !== id,
                  ),
        );

    return (
        <>
            <Head title={group.name} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="outline" size="icon">
                            <Link href={groups.index()}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="font-display text-2xl font-bold">
                                {group.name}
                            </h1>
                            <p className="text-sm text-tb-on-surface-variant">
                                {group.members.length} anggota · Pemilik{' '}
                                {group.owner_name}
                            </p>
                        </div>
                    </div>
                    {group.can_announce && (
                        <Button asChild variant="outline">
                            <Link
                                href={announcements.index({
                                    query: { group: group.id },
                                })}
                            >
                                <Megaphone className="size-4" />
                                Pengumuman
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid min-h-[70vh] gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card className="min-h-0 border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="flex min-h-[65vh] flex-col gap-4">
                            <div className="flex-1 space-y-3 overflow-y-auto pr-1">
                                {messages.map((message) => {
                                    const mine =
                                        message.sender_id === auth.user.id;

                                    return (
                                        <div
                                            key={message.id}
                                            className={`flex ${mine ? 'justify-end' : 'justify-start'}`}
                                        >
                                            <div
                                                className={`max-w-[82%] rounded-2xl px-4 py-2.5 ${mine ? 'bg-tb-primary text-white' : 'bg-tb-surface-container text-tb-on-surface'}`}
                                            >
                                                <div
                                                    className={`mb-1 flex items-center gap-2 text-xs font-semibold ${mine ? 'text-white/80' : 'text-tb-primary'}`}
                                                >
                                                    {message.sender_name ??
                                                        'Pengguna Telegram'}
                                                    {message.source ===
                                                        'telegram' && (
                                                        <span>· Telegram</span>
                                                    )}
                                                </div>
                                                <p className="text-sm break-words whitespace-pre-wrap">
                                                    {message.body}
                                                </p>
                                                {mine &&
                                                    message.delivery_status && (
                                                        <p className="mt-1 text-right text-[10px] text-white/70">
                                                            {message.delivery_status ===
                                                            'pending'
                                                                ? 'Mengirim ke Telegram…'
                                                                : message.delivery_status ===
                                                                    'failed'
                                                                  ? 'Telegram gagal'
                                                                  : 'Terkirim ke Telegram'}
                                                        </p>
                                                    )}
                                            </div>
                                        </div>
                                    );
                                })}
                                {messages.length === 0 && (
                                    <div className="grid h-full place-items-center py-20 text-sm text-tb-outline">
                                        Belum ada pesan di grup ini.
                                    </div>
                                )}
                                <div ref={endRef} />
                            </div>
                            <form
                                onSubmit={send}
                                className="flex items-end gap-2 border-t border-tb-outline-variant pt-4"
                            >
                                <div className="flex-1">
                                    <textarea
                                        value={messageForm.data.body}
                                        onChange={(event) =>
                                            messageForm.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        rows={2}
                                        maxLength={2000}
                                        placeholder="Tulis pesan grup…"
                                        className="w-full resize-none rounded-xl border border-tb-outline-variant bg-transparent px-3 py-2 text-sm outline-none focus:border-tb-primary"
                                    />
                                    <InputError
                                        message={messageForm.errors.body}
                                    />
                                </div>
                                <Button
                                    size="icon"
                                    disabled={messageForm.processing}
                                >
                                    <Send className="size-4" />
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card className="border-tb-outline-variant bg-tb-surface-bright">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Users className="size-5" />
                                    Anggota
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {group.members.map((member) => (
                                    <div
                                        key={member.id}
                                        className="flex items-center justify-between rounded-lg bg-tb-surface-container px-3 py-2 text-sm"
                                    >
                                        <span>{member.name}</span>
                                        {member.role === 'owner' && (
                                            <Badge>Pemilik</Badge>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        {group.can_manage && (
                            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                <CardHeader>
                                    <CardTitle>Kelola anggota</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="max-h-52 space-y-1 overflow-y-auto">
                                        {availableContacts.map((contact) => (
                                            <label
                                                key={contact.id}
                                                className="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-tb-surface-container"
                                            >
                                                <Checkbox
                                                    checked={memberForm.data.member_ids.includes(
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
                                            </label>
                                        ))}
                                    </div>
                                    <Button
                                        className="w-full"
                                        variant="outline"
                                        disabled={memberForm.processing}
                                        onClick={() =>
                                            memberForm.put(
                                                groups.members.update(group.id)
                                                    .url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Simpan anggota
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                        {group.can_manage && (
                            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Send className="size-5 text-sky-500" />
                                        Telegram
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {group.telegram_linked ? (
                                        <>
                                            <div className="rounded-xl bg-emerald-500/10 p-3 text-sm text-emerald-700">
                                                <Check className="mr-1 inline size-4" />
                                                Terhubung ke{' '}
                                                {group.telegram_title ??
                                                    'grup Telegram'}
                                            </div>
                                            <Button
                                                className="w-full"
                                                variant="outline"
                                                onClick={() =>
                                                    router.delete(
                                                        groups.telegramLink.destroy(
                                                            group.id,
                                                        ).url,
                                                    )
                                                }
                                            >
                                                <Unlink className="size-4" />
                                                Putuskan
                                            </Button>
                                        </>
                                    ) : !group.telegram_account_linked ? (
                                        <>
                                            <p className="text-sm text-tb-on-surface-variant">
                                                Hubungkan akun Telegram Anda
                                                terlebih dahulu sebelum
                                                memasangkan grup.
                                            </p>
                                            <Button
                                                asChild
                                                className="w-full"
                                                variant="outline"
                                            >
                                                <Link href={editProfile()}>
                                                    <Link2 className="size-4" />
                                                    Buka Pengaturan Profil
                                                </Link>
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            <p className="text-sm text-tb-on-surface-variant">
                                                Tambahkan bot sebagai admin grup
                                                Telegram, lalu kirim kode
                                                berikut.
                                            </p>
                                            {telegramLinkCode && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        navigator.clipboard.writeText(
                                                            `/link ${telegramLinkCode}`,
                                                        )
                                                    }
                                                    className="flex w-full items-center justify-between rounded-xl bg-tb-surface-container p-3 font-mono text-sm"
                                                >
                                                    <span>
                                                        /link {telegramLinkCode}
                                                    </span>
                                                    <Copy className="size-4" />
                                                </button>
                                            )}
                                            <Button
                                                className="w-full"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        groups.telegramLink.store(
                                                            group.id,
                                                        ).url,
                                                    )
                                                }
                                            >
                                                <Link2 className="size-4" />
                                                Buat kode pemasangan
                                            </Button>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                        {group.can_manage && (
                            <Button
                                className="w-full"
                                variant="destructive"
                                onClick={() => {
                                    if (
                                        confirm(
                                            'Hapus grup beserta seluruh pesan?',
                                        )
                                    ) {
router.delete(
                                            groups.destroy(group.id).url,
                                        );
}
                                }}
                            >
                                <Trash2 className="size-4" />
                                Hapus Grup
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
