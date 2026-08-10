import { Head, Link } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import { ArrowRight, Building2, Mail, MessageCircle } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import {
    caraBergabung,
    komunitasOrganisasi,
    komunitasStats,
    regionalGroups,
} from '@/data/komunitas';
import { home } from '@/routes';

export default function KomunitasIndex() {
    return (
        <MotionConfig reducedMotion="user">
            <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
            <Head title="Komunitas Batak" />
            <SiteHeader />

            <main>
                <section className="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6 py-16 text-center md:py-24">
                    <Reveal>
                        <div className="inline-flex items-center gap-2 rounded-full border border-tb-primary/20 bg-tb-primary/10 px-4 py-2 text-sm font-medium text-tb-primary">
                            <MessageCircle className="h-4 w-4" />
                            Bersama Membangun Komunitas
                        </div>
                    </Reveal>
                    <Reveal delay={0.1}>
                        <h1 className="font-display text-4xl font-bold leading-tight md:text-5xl lg:text-6xl">
                            Komunitas Batak di Seluruh Indonesia
                        </h1>
                    </Reveal>
                    <Reveal delay={0.2}>
                        <p className="mx-auto max-w-3xl text-lg leading-relaxed text-tb-on-surface-variant">
                            Bergabunglah dengan ribuan keluarga Batak di berbagai organisasi marga dan komunitas
                            regional. Bersama kita jaga warisan leluhur dan pererat tali persaudaraan.
                        </p>
                    </Reveal>
                    <Reveal delay={0.3}>
                        <div className="mt-8 flex flex-wrap justify-center gap-4">
                            <a
                                href="#organisasi"
                                className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                            >
                                Lihat Organisasi <ArrowRight className="h-4 w-4" />
                            </a>
                            <a
                                href="#bergabung"
                                className="flex items-center gap-2 rounded-full border border-tb-primary px-6 py-3 font-medium transition-colors hover:bg-tb-primary hover:text-white"
                            >
                                <MessageCircle className="h-4 w-4" /> Cara Bergabung
                            </a>
                        </div>
                    </Reveal>
                </section>

                <section className="border-y border-tb-outline-variant bg-tb-surface-bright">
                    <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-6 py-10 sm:grid-cols-2 lg:grid-cols-4">
                        {komunitasStats.map((stat, index) => (
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

                <section id="organisasi" className="mx-auto max-w-7xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="mb-10 text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Organisasi Marga</h2>
                            <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                Berbagai organisasi marga yang aktif di seluruh Indonesia. Bergabunglah dan jalin
                                silaturahmi dengan keluarga besar marga Anda.
                            </p>
                        </div>
                    </Reveal>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {komunitasOrganisasi.map((org, index) => (
                            <Reveal key={org.name} delay={index * 0.08}>
                                <div className="flex h-full flex-col rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                                    <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-tb-primary/10 text-tb-primary">
                                        <org.icon className="size-6" />
                                    </div>
                                    <h3 className="font-display text-lg font-bold">{org.name}</h3>
                                    <p className="mt-1 text-sm text-tb-primary">{org.region}</p>
                                    <p className="mt-3 flex-1 text-sm leading-relaxed text-tb-on-surface-variant">
                                        {org.description}
                                    </p>
                                    <div className="mt-4 flex items-center justify-between border-t border-tb-outline-variant pt-4">
                                        <span className="text-sm font-medium text-tb-on-surface-variant">
                                            {org.members} anggota
                                        </span>
                                        <div className="flex gap-2">
                                            {org.contact.whatsapp && (
                                                <a
                                                    href={`https://wa.me/${org.contact.whatsapp.replace(/\D/g, '')}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 text-green-700 transition-colors hover:bg-green-200"
                                                    aria-label="WhatsApp"
                                                >
                                                    <MessageCircle className="h-4 w-4" />
                                                </a>
                                            )}
                                            {org.contact.email && (
                                                <a
                                                    href={`mailto:${org.contact.email}`}
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700 transition-colors hover:bg-blue-200"
                                                    aria-label="Email"
                                                >
                                                    <Mail className="h-4 w-4" />
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                <section id="regional" className="bg-tb-surface-bright py-16 md:py-20">
                    <div className="mx-auto max-w-7xl px-6">
                        <Reveal>
                            <div className="mb-10 text-center">
                                <h2 className="font-display text-3xl font-bold md:text-4xl">Komunitas Regional</h2>
                                <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                    Komunitas lintas marga di berbagai kota dan wilayah Indonesia. Temukan komunitas
                                    terdekat di kota Anda.
                                </p>
                            </div>
                        </Reveal>
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {regionalGroups.map((group, index) => (
                                <Reveal key={group.name} delay={index * 0.08}>
                                    <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface p-6 text-center">
                                        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-tb-primary text-white">
                                            <group.icon className="size-7" />
                                        </div>
                                        <h3 className="font-display text-base font-bold">{group.name}</h3>
                                        <p className="mt-1 text-sm text-tb-primary">{group.region}</p>
                                        <p className="mt-3 text-sm leading-relaxed text-tb-on-surface-variant">
                                            {group.description}
                                        </p>
                                        <p className="mt-4 text-xs font-medium text-tb-on-surface-variant">
                                            {group.members} anggota
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="bergabung" className="mx-auto max-w-7xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="mb-10 text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Cara Bergabung</h2>
                            <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                Ikuti langkah-langkah mudah berikut untuk bergabung dengan komunitas atau organisasi
                                marga pilihan Anda.
                            </p>
                        </div>
                    </Reveal>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        {caraBergabung.map((step, index) => (
                            <Reveal key={step.step} delay={index * 0.08}>
                                <div className="relative rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                                    <div className="absolute -top-4 left-6 flex h-8 w-8 items-center justify-center rounded-full bg-tb-primary font-display text-sm font-bold text-white">
                                        {step.step}
                                    </div>
                                    <div className="mb-4 mt-4 flex h-12 w-12 items-center justify-center rounded-xl bg-tb-surface-container text-tb-primary">
                                        <step.icon className="size-6" />
                                    </div>
                                    <h3 className="font-display text-lg font-bold">{step.title}</h3>
                                    <p className="mt-2 text-sm leading-relaxed text-tb-on-surface-variant">
                                        {step.description}
                                    </p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-6 py-16">
                    <Reveal>
                        <div className="rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-8 text-center md:p-12">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-tb-primary/10 text-tb-primary">
                                <Building2 className="h-8 w-8" />
                            </div>
                            <h2 className="font-display text-2xl font-bold md:text-3xl">
                                Daftarkan Organisasi Anda
                            </h2>
                            <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                Apakah Anda pengurus organisasi marga atau komunitas Batak? Daftarkan organisasi Anda
                                agar lebih mudah ditemukan oleh anggota baru.
                            </p>
                            <div className="mt-8 flex flex-wrap justify-center gap-4">
                                <a
                                    href="mailto:info@tarombobatak.com"
                                    className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                                >
                                    <Mail className="h-4 w-4" /> Hubungi Kami
                                </a>
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
