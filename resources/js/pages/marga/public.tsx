import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Handshake, Layers, Shapes, UserRound, Users } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { home } from '@/routes';
import marga from '@/routes/marga';

type MargaItem = {
    name: string;
    color: string | null;
    image_url: string | null;
    count: number;
};

type Props = {
    margas: MargaItem[];
    stats: {
        totalMargas: number;
        totalPeople: number;
        totalGenerations: number;
    };
};

const daltol = [
    {
        title: 'Hula-hula',
        description: 'Pihak keluarga istri yang sangat dihormati — pemberi berkat dalam adat.',
        icon: Handshake,
    },
    {
        title: 'Dongan Sabutuha',
        description: 'Saudara semarga / sekutu — teman seperjuangan dalam suka dan duka.',
        icon: UserRound,
    },
    {
        title: 'Boru',
        description: 'Pihak anak perempuan / penerima istri — yang senantiasa melayani dan memberi.',
        icon: Users,
    },
];

export default function MargaPublic({ margas, stats }: Props) {
    return (
        <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
            <Head title="Marga" />
            <SiteHeader />

            <main>
                <section className="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6 py-16 text-center md:py-24">
                    <Reveal>
                        <span className="rounded-full border border-tb-outline-variant bg-tb-surface-container px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-tb-primary">
                            Identitas Batak
                        </span>
                        <h1 className="mx-auto mt-6 max-w-3xl font-display text-4xl font-bold leading-tight md:text-6xl">
                            Apa itu Marga?
                        </h1>
                        <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-tb-on-surface-variant">
                            Marga adalah nama keluarga (klan) masyarakat Batak yang diwariskan dari ayah
                            kepada anaknya. Marga menjadi penanda identitas, menentukan hubungan kekerabatan,
                            dan mengatur peran setiap orang dalam adat.
                        </p>
                        <div className="mt-8 flex flex-wrap justify-center gap-4">
                            <a
                                href="#daftar"
                                className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                            >
                                Lihat Daftar Marga <ArrowRight className="h-4 w-4" />
                            </a>
                            <a
                                href="#budaya"
                                className="flex items-center gap-2 rounded-full border border-tb-primary px-6 py-3 font-medium transition-colors hover:bg-tb-primary hover:text-white"
                            >
                                <Shapes className="h-4 w-4" /> Marga dalam Budaya
                            </a>
                        </div>
                    </Reveal>
                </section>

                <section className="border-y border-tb-outline-variant bg-tb-surface-bright">
                    <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-6 py-10 sm:grid-cols-3">
                        {[
                            { value: stats.totalMargas, label: 'Marga Tercatat', icon: Shapes },
                            { value: stats.totalPeople, label: 'Anggota', icon: Users },
                            { value: stats.totalGenerations, label: 'Generasi', icon: Layers },
                        ].map((stat) => (
                            <div key={stat.label} className="flex items-center justify-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-tb-primary text-white">
                                    <stat.icon className="size-6" />
                                </div>
                                <div>
                                    <p className="font-display text-3xl font-bold">{stat.value}</p>
                                    <p className="text-sm text-tb-on-surface-variant">{stat.label}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="budaya" className="mx-auto max-w-7xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="mb-10 text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Marga dalam Budaya Batak</h2>
                            <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-tb-on-surface-variant">
                                Marga diwariskan secara patrilineal (dari ayah). Satu marga dianggap sebagai
                                saudara, sehingga pernikahan antarsemarga tidak dibenarkan. Marga juga menjadi
                                dasar dari filosofi hidup masyarakat Batak, yaitu Dalihan Na Tolu.
                            </p>
                        </div>
                    </Reveal>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        {daltol.map((item, index) => (
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

                <section id="daftar" className="bg-tb-surface-bright py-16 md:py-20">
                    <div className="mx-auto max-w-7xl px-6">
                        <Reveal>
                            <div className="mb-10 text-center">
                                <h2 className="font-display text-3xl font-bold md:text-4xl">Daftar Marga</h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm text-tb-on-surface-variant">
                                    Marga-marga yang tercatat dalam silsilah tarombo kami.
                                </p>
                            </div>
                        </Reveal>

                        {margas.length === 0 ? (
                            <div className="mx-auto max-w-md rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center">
                                <p className="text-sm text-tb-on-surface-variant">Belum ada data marga.</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                {margas.map((item, index) => (
                                    <Reveal key={item.name} delay={(index % 5) * 0.05}>
                                        <div
                                            className="group relative flex h-40 flex-col justify-end overflow-hidden rounded-2xl p-4 shadow-md transition-transform duration-300 hover:-translate-y-1"
                                            style={{
                                                backgroundColor: item.color ?? 'var(--color-tb-primary)',
                                            }}
                                        >
                                            {item.image_url ? (
                                                <img
                                                    src={item.image_url}
                                                    alt={item.name}
                                                    className="absolute inset-0 h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-40 transition-opacity group-hover:opacity-60" />
                                            )}
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-60 transition-opacity group-hover:opacity-70" />
                                            <div className="relative z-10">
                                                <h3 className="font-display text-xl font-bold text-white">
                                                    {item.name}
                                                </h3>
                                                <p className="text-xs text-white/90">{item.count} orang</p>
                                            </div>
                                        </div>
                                    </Reveal>
                                ))}
                            </div>
                        )}

                        <div className="mt-12 text-center">
                            <Link
                                href={home()}
                                className="inline-flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                            >
                                Kembali ke Beranda <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </section>
            </main>

            <SiteFooter />
        </div>
    );
}

MargaPublic.layout = {
    breadcrumbs: [{ title: 'Marga', href: marga.view() }],
};
