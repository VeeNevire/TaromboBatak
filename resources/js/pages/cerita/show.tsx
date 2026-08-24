import { Head, Link } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import { ArrowLeft, ArrowRight, BookOpen, Calendar } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { home } from '@/routes';
import cerita from '@/routes/cerita';

type StoryDetail = {
    id: number;
    title: string;
    description: string;
    image: string | null;
    classification: 'umum' | 'marga';
    marga: string | null;
    created_at: string | null;
};

type Props = {
    story: StoryDetail;
};

export default function CeritaShow({ story }: Props) {
    return (
        <MotionConfig reducedMotion="user">
            <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
                <Head title={story.title} />
                <SiteHeader />

                <main>
                    <article className="mx-auto max-w-3xl px-6 py-12 md:py-16">
                        <Reveal>
                            <Link
                                href={cerita.index()}
                                className="inline-flex items-center gap-2 text-sm font-medium text-tb-outline transition-colors hover:text-tb-primary"
                            >
                                <ArrowLeft className="h-4 w-4" /> Kembali ke
                                Cerita
                            </Link>

                            <div className="mt-8 inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-2 text-sm font-medium text-tb-primary">
                                <BookOpen className="h-4 w-4" />
                                {story.classification === 'umum'
                                    ? 'Cerita Umum'
                                    : `Cerita Marga ${story.marga ?? ''}`}
                            </div>

                            <h1 className="mt-6 font-display text-3xl leading-tight font-bold md:text-5xl">
                                {story.title}
                            </h1>

                            {story.created_at && (
                                <div className="mt-4 flex items-center gap-2 text-sm text-tb-outline">
                                    <Calendar className="h-4 w-4" />
                                    {story.created_at}
                                </div>
                            )}
                        </Reveal>

                        {story.image ? (
                            <Reveal delay={0.1}>
                                <div className="mt-8 overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-container">
                                    <img
                                        src={story.image}
                                        alt={story.title}
                                        className="aspect-video w-full object-cover"
                                    />
                                </div>
                            </Reveal>
                        ) : (
                            <Reveal delay={0.1}>
                                <div className="mt-8 flex aspect-video w-full items-center justify-center rounded-2xl border border-tb-outline-variant bg-tb-surface-container">
                                    <BookOpen className="h-16 w-16 text-tb-outline" />
                                </div>
                            </Reveal>
                        )}

                        <Reveal delay={0.15}>
                            <div className="mt-10 text-base leading-8 whitespace-pre-line text-tb-on-surface">
                                {story.description}
                            </div>
                        </Reveal>

                        <Reveal delay={0.2}>
                            <div className="mt-16 rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-8 text-center md:p-10">
                                <h2 className="font-display text-xl font-bold md:text-2xl">
                                    Terus Jelajahi Warisan Budaya
                                </h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-tb-on-surface-variant">
                                    Temukan lebih banyak cerita leluhur atau
                                    kenali kembali akar budaya Batak.
                                </p>
                                <div className="mt-8 flex flex-wrap justify-center gap-4">
                                    <Link
                                        href={cerita.index()}
                                        className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                    >
                                        Lihat Cerita Lainnya{' '}
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                    <Link
                                        href={home()}
                                        className="flex items-center gap-2 rounded-full border border-tb-primary px-6 py-3 font-medium transition-colors hover:bg-tb-primary hover:text-white"
                                    >
                                        Kembali ke Beranda
                                    </Link>
                                </div>
                            </div>
                        </Reveal>
                    </article>
                </main>

                <SiteFooter />
            </div>
        </MotionConfig>
    );
}
