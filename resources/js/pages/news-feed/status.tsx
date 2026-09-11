import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FeedCard } from '@/components/news-feed/feed-card';
import type { FeedItem } from '@/components/news-feed/feed-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import newsFeed from '@/routes/news-feed';

export default function StatusShow({ item }: { item: FeedItem }) {
    return (
        <>
            <Head title={`Status ${item.author}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4 md:p-8">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="w-fit gap-1.5 px-2"
                >
                    <Link href={newsFeed.index()}>
                        <ArrowLeft className="size-4" /> Kembali ke News Feed
                    </Link>
                </Button>

                <FeedCard item={item} />
            </div>
        </>
    );
}

StatusShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'News Feed', href: newsFeed.index() },
    ],
};
