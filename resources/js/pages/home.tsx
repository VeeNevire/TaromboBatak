import { Head } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import { EventsSection } from '@/components/landing/events-section';
import { FeaturesSection } from '@/components/landing/features-section';
import { Hero } from '@/components/landing/hero';
import { MargaDirectory } from '@/components/landing/marga-directory';
import { NewsletterSection } from '@/components/landing/newsletter-section';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { StoriesSection } from '@/components/landing/stories-section';
import { TestimonialsSection } from '@/components/landing/testimonials-section';

type StoryItem = {
    id: number;
    title: string;
    description: string;
    image: string | null;
};

type EventItem = {
    id: number;
    title: string;
    description: string;
    location: string | null;
    month: string;
    day: string;
};

type MargaItem = {
    name: string;
    color: string | null;
    count: number;
};

type Props = {
    stories: StoryItem[];
    events: EventItem[];
    margas: MargaItem[];
};

export default function Home({ stories, events, margas }: Props) {
    return (
        <MotionConfig reducedMotion="user">
            <div className="bg-tb-surface font-body text-tb-on-surface antialiased">
                <Head title="Tarombo Batak" />
                <SiteHeader />
                <main>
                    <Hero />
                    <FeaturesSection />
                    <MargaDirectory margas={margas} />
                    <section id="cerita" className="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-6 py-8 pb-16 lg:grid-cols-3">
                        <StoriesSection stories={stories} />
                        <EventsSection events={events} />
                        <TestimonialsSection />
                    </section>
                    <NewsletterSection />
                </main>
                <SiteFooter />
            </div>
        </MotionConfig>
    );
}