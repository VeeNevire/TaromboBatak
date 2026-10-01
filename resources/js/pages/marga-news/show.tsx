import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    MargaChips,
    Pager,
    formatNewsDate,
} from '@/components/marga-news/news-parts';
import type {
    MargaNewsItem,
    Paginated,
} from '@/components/marga-news/news-parts';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import margaNews from '@/routes/marga-news';

type Comment = {
    id: number;
    author: string;
    body: string;
    created_at: string | null;
};

export default function MargaNewsShow({
    news,
    comments,
}: {
    news: MargaNewsItem;
    comments: Paginated<Comment>;
}) {
    const { auth } = usePage().props;
    const form = useForm({ body: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(margaNews.comments.store.url(news.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title={news.title} />
            <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 text-tb-on-surface md:p-6">
                <Link
                    href={margaNews.index()}
                    className="text-sm font-semibold text-tb-primary"
                >
                    ← Semua berita
                </Link>
                <article className="flex flex-col gap-4">
                    <h1 className="font-display text-2xl font-bold md:text-3xl">
                        {news.title}
                    </h1>
                    <p className="text-sm text-tb-on-surface-variant">
                        {news.publisher} · Diperbarui{' '}
                        {formatNewsDate(news.updated_at)}
                    </p>
                    <MargaChips margas={news.margas} />
                    {news.image_url && (
                        <img
                            src={news.image_url}
                            alt={news.title}
                            className="max-h-96 w-full rounded-xl object-cover"
                        />
                    )}
                    <p className="leading-8 whitespace-pre-line">
                        {news.content ?? news.summary ?? news.excerpt}
                    </p>
                    <a
                        href={news.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm font-semibold text-tb-primary hover:underline"
                    >
                        Baca sumber asli: {news.publisher ?? 'Portal berita'}
                    </a>
                </article>
                <section className="flex flex-col gap-4 border-t border-tb-outline-variant pt-5">
                    <h2 className="text-xl font-bold">
                        Komentar ({comments.total})
                    </h2>
                    {auth.user ? (
                        <form onSubmit={submit} className="flex flex-col gap-2">
                            <label
                                htmlFor="comment-body"
                                className="text-sm font-semibold"
                            >
                                Tulis komentar
                            </label>
                            <textarea
                                id="comment-body"
                                className="rounded-lg border border-tb-outline-variant bg-tb-surface-bright p-3 text-sm"
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                maxLength={1000}
                                required
                                rows={3}
                            />
                            {form.errors.body && (
                                <p
                                    role="alert"
                                    className="text-sm text-red-600"
                                >
                                    {form.errors.body}
                                </p>
                            )}
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="self-start"
                            >
                                {form.processing
                                    ? 'Mengirim…'
                                    : 'Kirim komentar'}
                            </Button>
                        </form>
                    ) : (
                        <p className="text-sm">
                            <Link
                                href={login()}
                                className="font-semibold text-tb-primary"
                            >
                                Masuk
                            </Link>{' '}
                            untuk menulis komentar.
                        </p>
                    )}
                    {comments.data.length === 0 && (
                        <p className="text-sm text-tb-on-surface-variant">
                            Belum ada komentar.
                        </p>
                    )}
                    {comments.data.map((comment) => (
                        <div
                            key={comment.id}
                            className="rounded-lg border border-tb-outline-variant bg-tb-surface-bright p-4"
                        >
                            <p className="font-semibold">{comment.author}</p>
                            <p className="text-xs text-tb-on-surface-variant">
                                {formatNewsDate(comment.created_at)}
                            </p>
                            <p className="mt-2 break-words whitespace-pre-wrap">
                                {comment.body}
                            </p>
                        </div>
                    ))}
                    <Pager page={comments} />
                </section>
            </main>
        </>
    );
}

MargaNewsShow.layout = {
    breadcrumbs: [{ title: 'Berita Marga-Marga', href: margaNews.index() }],
};
