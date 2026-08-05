import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Heart, TreePine } from 'lucide-react';
import { MotionConfig } from 'framer-motion';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { impactStats, nilaiNilai, tentangStory, visiMisi } from '@/data/tentang';
import { home } from '@/routes';

export default function TentangIndex() {
    return (
        <MotionConfig reducedMotion="user">
            <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
            <Head title="Tentang Kami" />
            <SiteHeader />

            <main>
                <section className="relative overflow-hidden bg-gradient-to-br from-tb-primary/10 via-tb-surface to-tb-surface px-6 py-20 md:py-32">
                    <div className="mx-auto max-w-4xl text-center">
                        <Reveal>
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-2 text-sm font-medium text-tb-primary">
                                <Heart className="h-4 w-4" />
                                Tentang Kami
                            </div>
                        </Reveal>
                        <Reveal delay={0.1}>
                            <h1 className="font-display text-4xl font-bold leading-tight md:text-5xl lg:text-6xl">
                                Melestarikan Warisan Leluhur untuk Generasi Masa Depan
                            </h1>
                        </Reveal>
                        <Reveal delay={0.2}>
                            <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-tb-on-surface-variant">
                                Setiap keluarga Batak memiliki cerita yang patut diceritakan, silsilah yang patut
                                dijaga, dan tradisi yang patut dilestarikan. Tarombo Batak adalah rumah digital bagi
                                warisan itu.
                            </p>
                        </Reveal>
                    </div>
                </section>

                <section id="cerita" className="mx-auto max-w-4xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">{tentangStory.title}</h2>
                        </div>
                    </Reveal>
                    <div className="mt-8 space-y-6">
                        {tentangStory.paragraphs.map((paragraph, index) => (
                            <Reveal key={index} delay={index * 0.1}>
                                <p className="text-lg leading-relaxed text-tb-on-surface-variant">{paragraph}</p>
                            </Reveal>
                        ))}
                    </div>
                </section>

                <section className="border-y border-tb-outline-variant bg-tb-surface-bright py-16">
                    <div className="mx-auto max-w-7xl px-6">
                        <Reveal>
                            <div className="mb-10 text-center">
                                <h2 className="font-display text-2xl font-bold md:text-3xl">Dampak Kami</h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm text-tb-on-surface-variant">
                                    Bersama-sama kita telah mencapai milestone penting dalam pelestarian warisan Batak.
                                </p>
                            </div>
                        </Reveal>
                        <div className="grid grid-cols-2 gap-6 lg:grid-cols-4">
                            {impactStats.map((stat, index) => (
                                <Reveal key={stat.label} delay={index * 0.08}>
                                    <div className="text-center">
                                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-tb-primary/10 text-tb-primary">
                                            <stat.icon className="size-8" />
                                        </div>
                                        <p className="font-display text-4xl font-bold">{stat.value}</p>
                                        <p className="mt-2 text-sm text-tb-on-surface-variant">{stat.label}</p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="visi-misi" className="mx-auto max-w-7xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="mb-12 text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Visi & Misi</h2>
                        </div>
                    </Reveal>
                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <Reveal delay={0.1}>
                            <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-8">
                                <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-tb-primary text-white">
                                    <TreePine className="h-6 w-6" />
                                </div>
                                <h3 className="font-display text-2xl font-bold">Visi</h3>
                                <p className="mt-4 leading-relaxed text-tb-on-surface-variant">{visiMisi.visi}</p>
                            </div>
                        </Reveal>
                        <Reveal delay={0.2}>
                            <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-8">
                                <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-tb-primary text-white">
                                    <Heart className="h-6 w-6" />
                                </div>
                                <h3 className="font-display text-2xl font-bold">Misi</h3>
                                <ul className="mt-4 space-y-3">
                                    {visiMisi.misi.map((item, index) => (
                                        <li key={index} className="flex items-start gap-3">
                                            <div className="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-tb-primary/20">
                                                <div className="h-2 w-2 rounded-full bg-tb-primary" />
                                            </div>
                                            <span className="text-tb-on-surface-variant">{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Reveal>
                    </div>
                </section>

                <section id="nilai" className="bg-tb-surface-bright py-16 md:py-20">
                    <div className="mx-auto max-w-7xl px-6">
                        <Reveal>
                            <div className="mb-10 text-center">
                                <h2 className="font-display text-3xl font-bold md:text-4xl">Nilai-Nilai Kami</h2>
                                <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                    Prinsip-prinsip yang memandu setiap keputusan dan tindakan kami dalam melestarikan
                                    warisan Batak.
                                </p>
                            </div>
                        </Reveal>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                            {nilaiNilai.map((nilai, index) => (
                                <Reveal key={nilai.title} delay={index * 0.08}>
                                    <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface p-6 text-center">
                                        <div
                                            className={`mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl ${nilai.iconClass}`}
                                        >
                                            <nilai.icon className="size-7" />
                                        </div>
                                        <h3 className="font-display text-lg font-bold">{nilai.title}</h3>
                                        <p className="mt-3 text-sm leading-relaxed text-tb-on-surface-variant">
                                            {nilai.description}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="relative overflow-hidden bg-gradient-to-br from-tb-primary to-tb-primary-light py-16 text-white md:py-20">
                    <div className="absolute inset-0 opacity-10">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_120%,rgba(255,255,255,0.1),transparent)]" />
                    </div>
                    <div className="relative mx-auto max-w-4xl px-6 text-center">
                        <Reveal>
                            <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-white/20">
                                <Heart className="h-10 w-10" />
                            </div>
                        </Reveal>
                        <Reveal delay={0.1}>
                            <h2 className="font-display text-3xl font-bold md:text-4xl">
                                Bergabunglah dalam Misi Kami
                            </h2>
                        </Reveal>
                        <Reveal delay={0.2}>
                            <p className="mx-auto mt-4 max-w-2xl text-lg leading-relaxed text-white/90">
                                Mari bersama-sama menjaga warisan leluhur tetap hidup. Mulai dokumentasikan silsilah
                                keluarga Anda hari ini dan jadilah bagian dari gerakan pelestarian budaya Batak.
                            </p>
                        </Reveal>
                        <Reveal delay={0.3}>
                            <div className="mt-8 flex flex-wrap justify-center gap-4">
                                <Link
                                    href="/register"
                                    className="flex items-center gap-2 rounded-full bg-white px-8 py-3 font-medium text-tb-primary transition-all hover:bg-white/90 hover:shadow-lg"
                                >
                                    Mulai Buat Tarombo <ArrowRight className="h-4 w-4" />
                                </Link>
                                <Link
                                    href={home()}
                                    className="flex items-center gap-2 rounded-full border-2 border-white px-8 py-3 font-medium transition-all hover:bg-white/10"
                                >
                                    Kembali ke Beranda
                                </Link>
                            </div>
                        </Reveal>
                    </div>
                </section>
            </main>

            <SiteFooter />
        </div>
        </MotionConfig>
    );
}
