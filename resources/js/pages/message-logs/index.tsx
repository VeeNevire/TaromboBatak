import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, MessageCircle, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type MessageLog = {
    id: number;
    counterpart: string;
    body: string | null;
    direction: 'sent' | 'received';
    created_at: string | null;
};

type Props = {
    messages: {
        data: MessageLog[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function MessageLogIndex({ messages }: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Log Pesan" />
            <div>
                <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                    Log Pesan
                </h1>
                <p className="mt-1 text-sm text-tb-on-surface-variant">
                    Riwayat pesan masuk dan keluar Anda.
                </p>
            </div>
            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                <CardHeader>
                    <CardTitle className="text-base">Pesan terbaru</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {messages.data.length === 0 && (
                        <p className="py-8 text-center text-sm text-tb-on-surface-variant">
                            Belum ada riwayat pesan.
                        </p>
                    )}
                    {messages.data.map((message) => (
                        <article
                            key={message.id}
                            className="flex gap-3 rounded-xl border border-tb-outline-variant p-3"
                        >
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-tb-primary/10 text-tb-primary">
                                {message.direction === 'sent' ? (
                                    <Send className="size-4" />
                                ) : (
                                    <MessageCircle className="size-4" />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                    <p className="text-sm font-semibold text-tb-on-surface">
                                        {message.direction === 'sent' ? 'Ke' : 'Dari'} {message.counterpart}
                                    </p>
                                    <p className="text-xs text-tb-on-surface-variant">
                                        {message.created_at}
                                    </p>
                                </div>
                                <p className="mt-1 text-sm whitespace-pre-line text-tb-on-surface-variant">
                                    {message.body ?? 'Lampiran'}
                                </p>
                            </div>
                        </article>
                    ))}
                    <div className="flex justify-between pt-2">
                        {messages.prev_page_url ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={messages.prev_page_url}>
                                    <ArrowLeft className="size-4" /> Sebelumnya
                                </Link>
                            </Button>
                        ) : <span />}
                        {messages.next_page_url && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={messages.next_page_url}>
                                    Berikutnya <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
