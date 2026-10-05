import { Head, Link, useForm } from '@inertiajs/react';
import { Bot, Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import margaRoutes from '@/routes/marga';

type Message = { role: 'user' | 'assistant'; text: string };
export default function TanyaTarombo({
    marga,
}: {
    marga: {
        id: number;
        name: string;
        color: string | null;
        description: string | null;
    };
}) {
    const form = useForm({ question: '' });
    const [messages, setMessages] = useState<Message[]>([]);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const question = form.data.question.trim();

        if (!question || form.processing) {
            return;
        }

        setMessages((current) => [
            ...current,
            { role: 'user', text: question },
        ]);
        form.post(margaRoutes.ai.ask(marga.id).url, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash =
                    (
                        page.props as unknown as {
                            flash?: {
                                tarombo_answer?: string;
                                tarombo_error?: string;
                            };
                        }
                    ).flash ?? {};
                setMessages((current) => [
                    ...current,
                    {
                        role: 'assistant',
                        text:
                            flash.tarombo_answer ??
                            flash.tarombo_error ??
                            'Ito Tarombo belum memberikan jawaban.',
                    },
                ]);
            },
            onError: () =>
                setMessages((current) => [
                    ...current,
                    {
                        role: 'assistant',
                        text: 'Ito Tarombo belum dapat menjawab.',
                    },
                ]),
            onFinish: () => form.reset('question'),
        });
    };

    return (
        <>
            <Head title={`Tanya Ito Tarombo · ${marga.name}`} />
            <div className="flex min-h-full flex-1 flex-col gap-6 bg-tb-surface p-4 text-tb-on-surface md:p-6 lg:p-8">
                <div>
                    <Link
                        href={margaRoutes.documents.index(marga.id)}
                        className="text-sm text-tb-primary"
                    >
                        ← Dokumen {marga.name}
                    </Link>
                    <h1 className="mt-2 flex items-center gap-2 font-display text-2xl font-bold">
                        <Bot className="size-6 text-tb-primary" /> Tanya Ito
                        Tarombo
                    </h1>
                    <p className="text-sm text-tb-on-surface-variant">
                        Tanyakan hal seputar tarombo dan marga {marga.name}. Ito
                        Tarombo akan membantu menjawab.
                    </p>
                </div>
                <Card className="flex min-h-[55vh] flex-1 flex-col rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-lg">Percakapan</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-1 flex-col gap-4">
                        <div className="flex-1 space-y-3 overflow-y-auto rounded-xl bg-tb-surface p-4">
                            {messages.length === 0 && (
                                <p className="py-16 text-center text-sm text-tb-on-surface-variant">
                                    Mulai dengan pertanyaan tentang {marga.name}
                                    .
                                </p>
                            )}
                            {messages.map((message, index) => (
                                <div
                                    key={`${message.role}-${index}`}
                                    className={`max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap ${message.role === 'user' ? 'ml-auto bg-tb-primary text-white' : 'bg-tb-surface-bright text-tb-on-surface'}`}
                                >
                                    {message.text}
                                </div>
                            ))}
                        </div>
                        <form onSubmit={submit} className="flex gap-2">
                            <textarea
                                className="min-h-16 flex-1 resize-none rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm"
                                value={form.data.question}
                                onChange={(e) =>
                                    form.setData('question', e.target.value)
                                }
                                placeholder="Tulis pertanyaan..."
                                rows={2}
                            />
                            <Button
                                type="submit"
                                size="icon"
                                disabled={form.processing}
                                aria-label="Kirim pertanyaan"
                            >
                                <Send className="size-4" />
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TanyaTarombo.layout = {
    breadcrumbs: [{ title: 'Daftar Marga', href: margaRoutes.index() }],
};
