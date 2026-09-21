import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { ArrowLeft, Send, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import marga from '@/routes/marga';

type Member = { id: number; name: string };
type Message = {
    id: number;
    sender_id: number | null;
    sender_name: string;
    body: string;
    created_at: string | null;
};

export default function MargaChat({
    marga: margaItem,
    members,
    messages: initialMessages,
}: {
    marga: { id: number; name: string; color: string | null };
    members: Member[];
    messages: Message[];
}) {
    const { auth } = usePage().props;
    const endRef = useRef<HTMLDivElement>(null);
    const [messages, setMessages] = useState(initialMessages);
    const form = useForm({ body: '' });

    useEcho<Message>(
        `marga.${margaItem.id}`,
        '.marga.message.sent',
        (incoming) => {
            setMessages((current) =>
                [
                    ...current.filter((item) => item.id !== incoming.id),
                    incoming,
                ].sort((a, b) => a.id - b.id),
            );
        },
        [margaItem.id],
    );

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const send = (event: FormEvent) => {
        event.preventDefault();
        form.post(marga.messages.store(margaItem.id).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title={`Chat Marga ${margaItem.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-center gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link href={marga.index()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface">
                            Chat Marga {margaItem.name}
                        </h1>
                        <p className="text-sm text-tb-on-surface-variant">
                            {members.length} anggota dan pengurus marga
                        </p>
                    </div>
                </div>

                <div className="grid min-h-[70vh] gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
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
                                                    className={`mb-1 text-xs font-semibold ${mine ? 'text-white/80' : 'text-tb-primary'}`}
                                                >
                                                    {mine
                                                        ? 'Anda'
                                                        : message.sender_name}
                                                </div>
                                                <p className="text-sm break-words whitespace-pre-wrap">
                                                    {message.body}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                                {messages.length === 0 && (
                                    <div className="grid h-full place-items-center py-20 text-sm text-tb-outline">
                                        Belum ada pesan di marga ini. Mulai
                                        percakapan.
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
                                        value={form.data.body}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        rows={2}
                                        maxLength={2000}
                                        placeholder="Tulis pesan untuk marga ini…"
                                        className="w-full resize-none rounded-xl border border-tb-outline-variant bg-transparent px-3 py-2 text-sm outline-none focus:border-tb-primary"
                                    />
                                    <InputError message={form.errors.body} />
                                </div>
                                <Button
                                    size="icon"
                                    disabled={
                                        form.processing ||
                                        !form.data.body.trim()
                                    }
                                >
                                    <Send className="size-4" />
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="h-fit border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="size-5" />
                                Anggota
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="max-h-80 space-y-2 overflow-y-auto">
                            {members.length === 0 && (
                                <p className="text-sm text-tb-on-surface-variant">
                                    Belum ada akun anggota terdaftar.
                                </p>
                            )}
                            {members.map((member) => (
                                <div
                                    key={member.id}
                                    className="rounded-lg bg-tb-surface-container px-3 py-2 text-sm"
                                >
                                    {member.name}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

MargaChat.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Daftar Marga', href: marga.index() },
    ],
};
