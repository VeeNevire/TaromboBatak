import { Head, Link } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import { ArrowRight, Heart, Palette, Sparkles, Users } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { budayaStats, dalihanNaTolu, gorgaSymbols, upacara, ulosTypes } from '@/data/budaya';
import { home } from '@/routes';
import cerita from '@/routes/cerita';

export default function BudayaIndex() {
    return (
        <MotionConfig reducedMotion="user">
            <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
            <Head title="Budaya Batak" />
            <SiteHeader />

            <main>
                <section className="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6 py-16 text-center md:py-24">
                    <Reveal>
                        <div className="inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-2 text-sm font-medium text-tb-primary">
                            <Sparkles className="h-4 w-4" />
                            Warisan Leluhur
                        </div>
                    </Reveal>
                    <Reveal delay={0.1}>
                        <h1 className="font-display text-4xl font-bold leading-tight md:text-5xl lg:text-6xl">
                            Kekayaan Budaya Batak
                        </h1>
                    </Reveal>
                    <Reveal delay={0.2}>
                        <p className="mx-auto max-w-3xl text-lg leading-relaxed text-tb-on-surface-variant">
                            Budaya Batak kaya akan filosofi, seni, dan tradisi yang diwariskan turun-temurun.
                            Setiap aspek kehidupan masyarakat Batak mencerminkan nilai-nilai luhur leluhur yang
                            tetap relevan hingga kini.
                        </p>
                    </Reveal>
                    <Reveal delay={0.3}>
                        <div className="mt-8 flex flex-wrap justify-center gap-4">
                            <a
                                href="#dalihan"
                                className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                            >
                                Pelajari Dalihan Na Tolu <ArrowRight className="h-4 w-4" />
                            </a>
                            <a
                                href="#ulos"
                                className="flex items-center gap-2 rounded-full border border-tb-primary px-6 py-3 font-medium transition-colors hover:bg-tb-primary hover:text-white"
                            >
                                <Palette className="h-4 w-4" /> Jelajahi Ulos
                            </a>
                        </div>
                    </Reveal>
                </section>

                <section className="border-y border-tb-outline-variant bg-tb-surface-bright">
                    <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-6 py-10 sm:grid-cols-2 lg:grid-cols-4">
                        {budayaStats.map((stat, index) => (
                            <Reveal key={stat.label} delay={index * 0.08}>
                                <div className="flex items-center justify-center gap-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-tb-primary text-white">
                                        <stat.icon className="size-6" />
                                    </div>
                                    <div>
                                        <p className="font-display text-3xl font-bold">{stat.value}</p>
                                        <p className="text-sm text-tb-on-surface-variant">{stat.label}</p>
                                    </div>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                <section id="dalihan" className="mx-auto max-w-7xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="mb-10 text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Dalihan Na Tolu</h2>
                            <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                Tiga pilar filosofi kehidupan masyarakat Batak yang mengatur harmoni sosial dalam
                                setiap aspek kehidupan bermasyarakat dan upacara adat.
                            </p>
                        </div>
                    </Reveal>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        {dalihanNaTolu.map((item, index) => (
                            <Reveal key={item.title} delay={index * 0.08}>
                                <div className="h-full rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                                    <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-tb-surface-container text-tb-primary">
                                        <item.icon className="size-6" />
                                    </div>
                                    <h3 className="font-display text-lg font-bold">{item.title}</h3>
                                    <p className="mt-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                        {item.description}
                                    </p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                <section id="ulos" className="bg-tb-surface-bright py-16 md:py-20">
                    <div className="mx-auto max-w-7xl px-6">
                        <Reveal>
                            <div className="mb-10 text-center">
                                <h2 className="font-display text-3xl font-bold md:text-4xl">Ulos Batak</h2>
                                <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                    Kain tradisional Batak dengan motif khas yang memiliki makna mendalam dalam setiap
                                    upacara adat dan perayaan. Setiap jenis ulos memiliki fungsi dan simbolisme
                                    tersendiri.
                                </p>
                            </div>
                        </Reveal>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                            {ulosTypes.map((item, index) => (
                                <Reveal key={item.title} delay={index * 0.08}>
                                    <div className="h-full rounded-2xl border border-tb-outline-variant bg-tb-surface p-6">
                                        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
                                            <item.icon className="size-6" />
                                        </div>
                                        <h3 className="font-display text-base font-bold">{item.title}</h3>
                                        <p className="mt-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                            {item.description}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="gorga" className="mx-auto max-w-7xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="mb-10 text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Gorga & Ukiran</h2>
                            <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                Ukiran dan ornamen Batak yang sarat makna filosofis, menggambarkan kehidupan,
                                kesuburan, dan perlindungan. Setiap motif memiliki cerita dan fungsi spiritual.
                            </p>
                        </div>
                    </Reveal>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        {gorgaSymbols.map((item, index) => (
                            <Reveal key={item.title} delay={index * 0.08}>
                                <div className="h-full rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                                    <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-pink-100 text-pink-700">
                                        <item.icon className="size-6" />
                                    </div>
                                    <h3 className="font-display text-base font-bold">{item.title}</h3>
                                    <p className="mt-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                        {item.description}
                                    </p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                <section id="upacara" className="bg-tb-surface-bright py-16 md:py-20">
                    <div className="mx-auto max-w-7xl px-6">
                        <Reveal>
                            <div className="mb-10 text-center">
                                <h2 className="font-display text-3xl font-bold md:text-4xl">Upacara Adat</h2>
                                <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                    Rangkaian ritual yang menjaga tradisi leluhur tetap hidup dari generasi ke
                                    generasi. Setiap upacara memiliki tata cara dan makna yang telah diwariskan
                                    turun-temurun.
                                </p>
                            </div>
                        </Reveal>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                            {upacara.map((item, index) => (
                                <Reveal key={item.title} delay={index * 0.08}>
                                    <div className="h-full rounded-2xl border border-tb-outline-variant bg-tb-surface p-6">
                                        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                                            <item.icon className="size-6" />
                                        </div>
                                        <h3 className="font-display text-base font-bold">{item.title}</h3>
                                        <p className="mt-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                            {item.description}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-6 py-16">
                    <Reveal>
                        <div className="rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-8 text-center md:p-12">
                            <h2 className="font-display text-2xl font-bold md:text-3xl">
                                Pelajari Lebih Dalam Tentang Budaya Batak
                            </h2>
                            <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                Jelajahi cerita-cerita leluhur dan artikel budaya yang lebih mendalam untuk memahami
                                kearifan lokal masyarakat Batak.
                            </p>
                            <div className="mt-8 flex flex-wrap justify-center gap-4">
                                <Link
                                    href={cerita.index()}
                                    className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                >
                                    Baca Cerita Budaya <ArrowRight className="h-4 w-4" />
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
                </section>
            </main>

            <SiteFooter />
        </div>
        </MotionConfig>
    );
}
