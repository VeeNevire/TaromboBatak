import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Bookmark,
    BookOpen,
    CalendarDays,
    ExternalLink,
    Heart,
    Megaphone,
    MessageCircle,
    MoreHorizontal,
    Newspaper,
    Send,
} from 'lucide-react';
import { store as storeFeedComment } from '@/actions/App/Http/Controllers/FeedCommentController';
import { store as storeFeedPost } from '@/actions/App/Http/Controllers/FeedPostController';
import { AppAvatar } from '@/components/app-avatar';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { login } from '@/routes';
import newsFeed from '@/routes/news-feed';

type FeedComment = {
    id: number;
    author: string;
    body: string;
    created_at: string | null;
};

type FeedItem = {
    key: string;
    type: 'status' | 'story' | 'announcement';
    id: number;
    author: string;
    title: string | null;
    body: string;
    image: string | null;
    url: string | null;
    meta: string | null;
    created_at: string | null;
    comments: FeedComment[];
};

type NewsFeedProps = {
    items: FeedItem[];
};

const feedLabels = {
    status: {
        label: 'Status',
        icon: MessageCircle,
        className: 'bg-tb-primary/10 text-tb-primary',
    },
    story: {
        label: 'Cerita',
        icon: BookOpen,
        className: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    announcement: {
        label: 'Pengumuman',
        icon: Megaphone,
        className: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    },
} as const;

function formatDate(value: string | null): string {
    if (!value) {
        return 'Baru saja';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function NewsFeed({ items }: NewsFeedProps) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="News Feed" />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4 md:p-8">
                <div className="border-b border-tb-outline-variant pb-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                        <Newspaper className="size-6 text-tb-primary" />
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            News Feed
                        </h1>
                        </div>
                        <span className="text-xs text-tb-on-surface-variant">
                            Kabar keluarga
                        </span>
                    </div>
                </div>

                {auth.user ? (
                    <Card className="overflow-hidden rounded-xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardContent className="p-4">
                            <Form
                                {...storeFeedPost.form()}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                            >
                                {({ errors, processing }) => (
                                    <div className="grid gap-3">
                                        <div className="flex items-start gap-3">
                                            <AppAvatar name={auth.user!.name} className="mt-0.5" />
                                            <textarea
                                                name="body"
                                                rows={3}
                                                maxLength={2000}
                                                required
                                                placeholder={`Apa yang ingin Anda bagikan, ${auth.user!.name.split(' ')[0]}?`}
                                                className="min-h-20 flex-1 resize-none rounded-2xl border border-tb-outline-variant bg-tb-surface-container/40 px-4 py-3 text-sm text-tb-on-surface outline-none placeholder:text-tb-on-surface-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                            />
                                        </div>
                                        <InputError message={errors.body} className="ml-11" />
                                        <div className="flex justify-end">
                                            <Button type="submit" disabled={processing} className="rounded-full px-5">
                                                <Send className="size-4" />
                                                {processing ? 'Membagikan...' : 'Bagikan Status'}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="overflow-hidden rounded-xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardContent className="p-4">
                            <Link
                                href={login()}
                                className="flex items-center gap-3"
                            >
                                <AppAvatar name="Anda" className="shrink-0" />
                                <span className="flex h-11 flex-1 items-center rounded-2xl border border-tb-outline-variant bg-tb-surface-container/40 px-4 text-sm text-tb-on-surface-variant transition-colors hover:bg-tb-surface-container"
                                >
                                    Bagikan cerita Anda...
                                </span>
                                <span className="hidden rounded-full bg-tb-primary px-4 py-2 text-xs font-semibold text-white sm:inline-flex">
                                    Masuk untuk mengirim
                                </span>
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {items.length === 0 ? (
                    <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Newspaper className="size-10 text-tb-outline" />
                            <div>
                                <p className="font-semibold text-tb-on-surface">
                                    News Feed masih kosong
                                </p>
                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                    Jadilah orang pertama yang membagikan
                                    status.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    items.map((item) => <FeedCard key={item.key} item={item} />)
                )}
            </div>
        </>
    );
}

function FeedCard({ item }: { item: FeedItem }) {
    const feedLabel = feedLabels[item.type];
    const TypeIcon = feedLabel.icon;

    return (
        <Card className="overflow-hidden rounded-xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
            <CardHeader className="flex-row items-center gap-3 space-y-0 px-4 py-3">
                <AppAvatar name={item.author} />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-semibold text-tb-on-surface">
                            {item.author}
                        </p>
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ${feedLabel.className}`}
                        >
                            <TypeIcon className="size-3" />
                            {feedLabel.label}
                        </span>
                    </div>
                    <p className="mt-0.5 text-xs text-tb-on-surface-variant">
                        {formatDate(item.created_at)}
                    </p>
                </div>
                <Button variant="ghost" size="icon" className="size-8" aria-label="Opsi postingan">
                    <MoreHorizontal className="size-5" />
                </Button>
            </CardHeader>

            <CardContent className="grid gap-3 px-4 pb-3">
                {item.title && (
                    <h2 className="font-display text-lg font-bold text-tb-on-surface">
                        {item.title}
                    </h2>
                )}
                <p className="text-sm leading-6 whitespace-pre-line text-tb-on-surface">
                    {item.body}
                </p>
                {item.image && (
                    <img
                        src={item.image}
                        alt={item.title ?? 'Gambar cerita'}
                        className="max-h-[560px] w-full rounded-lg border border-tb-outline-variant object-cover"
                        onError={(event) => {
                            event.currentTarget.hidden = true;
                        }}
                    />
                )}
                {item.meta && (
                    <p className="flex items-center gap-1.5 text-xs font-medium text-tb-on-surface-variant">
                        <CalendarDays className="size-3.5" />
                        {item.meta}
                    </p>
                )}
            </CardContent>

            <div className="flex items-center gap-1 px-3 pb-2">
                <Button variant="ghost" size="icon" className="size-9" aria-label="Suka">
                    <Heart className="size-5" />
                </Button>
                <Button variant="ghost" size="icon" className="size-9" aria-label="Komentar">
                    <MessageCircle className="size-5" />
                </Button>
                <Button variant="ghost" size="icon" className="size-9" aria-label="Bagikan">
                    <Send className="size-5" />
                </Button>
                <Button variant="ghost" size="icon" className="ml-auto size-9" aria-label="Simpan">
                    <Bookmark className="size-5" />
                </Button>
            </div>

            {item.type === 'status' ? (
                <StatusComments postId={item.id} comments={item.comments} />
            ) : (
                item.url && (
                    <CardFooter className="border-t border-tb-outline-variant px-4 pt-3 pb-4">
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={item.url}
                                target="_blank"
                                rel="noreferrer noopener"
                            >
                                Lihat selengkapnya
                                <ExternalLink className="size-3.5" />
                            </a>
                        </Button>
                    </CardFooter>
                )
            )}
        </Card>
    );
}

function StatusComments({
    postId,
    comments,
}: {
    postId: number;
    comments: FeedComment[];
}) {
    const { auth } = usePage().props;

    return (
        <CardFooter className="grid gap-3 border-t border-tb-outline-variant bg-tb-surface-container/20 px-4 pt-3 pb-4">
            {comments.length > 0 && (
                <div className="grid gap-3">
                    {comments.map((comment) => (
                        <div
                            key={comment.id}
                            className="flex items-start gap-2.5"
                        >
                            <AppAvatar
                                name={comment.author}
                                className="size-8"
                            />
                            <div className="min-w-0 rounded-xl bg-tb-surface-container px-3 py-2">
                                <div className="flex flex-wrap items-baseline gap-x-2">
                                    <p className="text-xs font-semibold text-tb-on-surface">
                                        {comment.author}
                                    </p>
                                    <p className="text-[10px] text-tb-on-surface-variant">
                                        {formatDate(comment.created_at)}
                                    </p>
                                </div>
                                <p className="mt-0.5 text-sm leading-5 whitespace-pre-line text-tb-on-surface">
                                    {comment.body}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {auth.user && <Form
                {...storeFeedComment.form(postId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <div className="grid gap-1.5">
                        <div className="flex items-center gap-2.5">
                            <AppAvatar
                                name={auth.user.name}
                                className="size-8"
                            />
                            <input
                                name="body"
                                maxLength={500}
                                required
                                placeholder="Tulis komentar..."
                                className="h-9 min-w-0 flex-1 rounded-full border border-tb-outline-variant bg-tb-surface-bright px-4 text-sm text-tb-on-surface outline-none placeholder:text-tb-on-surface-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                            />
                            <Button
                                type="submit"
                                size="icon"
                                className="size-9 rounded-full"
                                disabled={processing}
                                aria-label="Kirim komentar"
                            >
                                <Send className="size-4" />
                            </Button>
                        </div>
                        <InputError message={errors.body} className="ml-11" />
                    </div>
                )}
            </Form>}
        </CardFooter>
    );
}

NewsFeed.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'News Feed', href: newsFeed.index() },
    ],
};
