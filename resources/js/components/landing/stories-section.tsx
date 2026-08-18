import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import cerita from '@/routes/cerita';

type StoryItem = {
    id: number;
    title: string;
    description: string;
    image: string | null;
};

export function StoriesSection({ stories }: { stories: StoryItem[] }) {
    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="font-display text-xl font-bold">
                    Cerita Leluhur & Budaya
                </h2>
                <Link
                    href={cerita.index()}
                    className="text-xs font-medium text-tb-outline hover:text-tb-primary"
                >
                    Lihat Semua
                </Link>
            </div>
            <div className="space-y-4">
                {stories.length === 0 && (
                    <p className="text-sm text-tb-on-surface-variant">
                        Belum ada cerita untuk ditampilkan.
                    </p>
                )}
                {stories.map((story, index) => (
                    <Reveal key={story.id} delay={index * 0.08}>
                        <Link
                            href={cerita.show(story.id)}
                            className="group -m-2 flex gap-4 rounded-lg p-2 transition-colors hover:bg-tb-surface-container"
                        >
                            {story.image ? (
                                <img
                                    alt="Thumbnail"
                                    className="h-20 w-20 rounded-lg object-cover"
                                    src={story.image}
                                />
                            ) : (
                                <div className="h-20 w-20 rounded-lg bg-tb-surface-container" />
                            )}
                            <div className="flex-1">
                                <h3 className="mb-1 text-sm font-bold transition-colors group-hover:text-tb-primary">
                                    {story.title}
                                </h3>
                                <p className="line-clamp-2 text-xs text-tb-on-surface-variant">
                                    {story.description}
                                </p>
                            </div>
                            <ChevronRight className="h-4 w-4 self-center text-tb-outline transition-colors group-hover:text-tb-primary" />
                        </Link>
                    </Reveal>
                ))}
            </div>
        </div>
    );
}
