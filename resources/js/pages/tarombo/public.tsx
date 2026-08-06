import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Layers, MousePointerClick, Search, Shapes, Users } from 'lucide-react';
import { useState } from 'react';
import { ProfileCard } from '@/components/landing/profile-card';
import { Reveal } from '@/components/landing/reveal';
import { SiteFooter } from '@/components/landing/site-footer';
import { SiteHeader } from '@/components/landing/site-header';
import { TaromboDiagram } from '@/components/landing/tarombo-diagram';
import { Input } from '@/components/ui/input';
import { buildTaromboPeople, findPerson, findPersonChildren } from '@/data/tarombo-tree';
import type { MargaInfo, TaromboPersonRow } from '@/data/tarombo-tree';
import { home } from '@/routes';
import tarombo from '@/routes/tarombo';

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    stats: {
        totalPeople: number;
        totalMargas: number;
        totalGenerations: number;
    };
};

const caraItems = [
    {
        title: 'Pusat, lalu Mengikuti Generasi',
        description:
            'Si Raja Batak berada di tengah. Setiap lingkaran ke luar menandai satu generasi berikutnya.',
        icon: Layers,
    },
    {
        title: 'Setiap Marga Punya Warna',
        description:
            'Setiap sektor dan badge mewakili marga. Warna membantu membedakan cabang silsilah.',
        icon: Shapes,
    },
    {
        title: 'Klik untuk Menelusuri',
        description:
            'Klik anggota mana pun untuk melihat detail, orang tua, anak, dan keterangannya.',
        icon: MousePointerClick,
    },
];

export default function TaromboPublic({ people: rows, margas, stats }: Props) {
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [search, setSearch] = useState('');

    const people = buildTaromboPeople(rows);
    const rootPerson = people.find((p) => !p.parentId) ?? people[0];
    const [centerPersonId, setCenterPersonId] = useState<string>(rootPerson?.id ?? '');
    const selected = selectedId ? findPerson(people, selectedId) : null;
    const childrenList = selected ? findPersonChildren(people, selected.id) : [];

    const searchSelect = (value: string) => {
        const person = people.find((p) => p.name.toLowerCase().includes(value.toLowerCase()));

        if (person) {
            setSelectedId(person.id);
            setCenterPersonId(person.id);
        }
    };

    const handlePersonSelect = (person: any) => {
        setSelectedId(person.id);
        setCenterPersonId(person.id);
    };

    const handleCloseProfile = () => {
        setSelectedId(null);
    };

    return (
        <div className="scroll-smooth bg-tb-surface font-body text-tb-on-surface antialiased">
            <Head title="Tarombo" />
            <SiteHeader />

            <main>
                <section className="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6 py-16 text-center md:py-24">
                    <Reveal>
                        <span className="rounded-full border border-tb-outline-variant bg-tb-surface-container px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-tb-primary">
                            Silsilah Batak
                        </span>
                        <h1 className="mx-auto mt-6 max-w-3xl font-display text-4xl font-bold leading-tight md:text-6xl">
                            Apa itu Tarombo?
                        </h1>
                        <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-tb-on-surface-variant">
                            Tarombo adalah silsilah keluarga masyarakat Batak yang mencatat garis keturunan
                            dari Si Raja Batak hingga generasi sekarang. Melalui tarombo, setiap marga
                            menelusuri asal-usul, identitas, dan hubungan kekerabatan leluhurnya.
                        </p>
                        <div className="mt-8 flex flex-wrap justify-center gap-4">
                            <a
                                href="#pohon"
                                className="flex items-center gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium text-white transition-colors hover:bg-tb-primary-light"
                            >
                                Lihat Pohon Tarombo <ArrowRight className="h-4 w-4" />
                            </a>
                            <a
                                href="#sejarah"
                                className="flex items-center gap-2 rounded-full border border-tb-primary px-6 py-3 font-medium transition-colors hover:bg-tb-primary hover:text-white"
                            >
                                <BookOpen className="h-4 w-4" /> Pelajari Sejarah
                            </a>
                        </div>
                    </Reveal>
                </section>

                <section className="border-y border-tb-outline-variant bg-tb-surface-bright">
                    <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-6 py-10 sm:grid-cols-3">
                        {[
                            { value: stats.totalPeople, label: 'Anggota Tercatat', icon: Users },
                            { value: stats.totalMargas, label: 'Marga', icon: Shapes },
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

                <section id="sejarah" className="mx-auto grid max-w-7xl grid-cols-1 items-center gap-10 px-6 py-16 md:py-20 lg:grid-cols-2">
                    <Reveal>
                        <div>
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Sejarah Singkat</h2>
                            <div className="mt-4 h-1 w-16 rounded-full bg-tb-primary" />
                            <p className="mt-6 leading-relaxed text-tb-on-surface-variant">
                                Si Raja Batak dipercaya sebagai leluhur pertama Bangso Batak. Ia berasal dari
                                Sianjur Mulamula, Samosir, dan diperkirakan hidup sekitar abad ke-13.
                            </p>
                            <p className="mt-4 leading-relaxed text-tb-on-surface-variant">
                                Dari keturunannya lahir Guru Tatea Bulan dan Raja Isumbaon, lalu menyebar menjadi
                                marga-marga besar yang kita kenal hari ini. Tarombo diturunkan secara turun-temurun
                                untuk menjaga ingatan akan asal-usul setiap keluarga.
                            </p>
                        </div>
                    </Reveal>
                    <Reveal delay={0.1}>
                        <div className="grid gap-4">
                            <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                                <p className="font-display text-lg font-bold">Guru Tatea Bulan & Raja Isumbaon</p>
                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                    Dua putra Si Raja Batak yang menjadi induk cabang tarombo.
                                </p>
                            </div>
                            <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                                <p className="font-display text-lg font-bold">Dari Leluhur ke Marga</p>
                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                    Setiap tokoh di tarombo menjadi cikal bakal sebuah marga atau cabang marga.
                                </p>
                            </div>
                            <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                                <p className="font-display text-lg font-bold">Identitas & Kekerabatan</p>
                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                    Tarombo membantu setiap orang mengenali posisinya dalam keluarga besar Batak.
                                </p>
                            </div>
                        </div>
                    </Reveal>
                </section>

                <section className="bg-tb-surface-bright py-16 md:py-20">
                    <div className="mx-auto max-w-7xl px-6">
                        <Reveal>
                            <div className="mb-10 text-center">
                                <h2 className="font-display text-3xl font-bold md:text-4xl">Cara Membaca Tarombo</h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm text-tb-on-surface-variant">
                                    Pohon tarombo disusun secara radial agar mudah menelusuri cabang silsilah.
                                </p>
                            </div>
                        </Reveal>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                            {caraItems.map((item, index) => (
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
                    </div>
                </section>

                <section id="pohon" className="mx-auto max-w-7xl px-6 py-16 md:py-20">
                    <Reveal>
                        <div className="mb-8 text-center">
                            <h2 className="font-display text-3xl font-bold md:text-4xl">Pohon Tarombo</h2>
                            <p className="mx-auto mt-3 max-w-xl text-sm text-tb-on-surface-variant">
                                Telusuri setiap cabang — klik anggota untuk melihat detailnya.
                            </p>
                        </div>
                    </Reveal>

                    <div className="mx-auto mb-6 max-w-md">
                        <div className="relative">
                            <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-tb-outline" />
                            <Input
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    searchSelect(e.target.value);
                                }}
                                placeholder="Cari anggota..."
                                className="border-tb-outline-variant bg-tb-surface-bright pl-11 focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                        </div>
                    </div>

                    {people.length === 0 ? (
                        <div className="mx-auto max-w-md rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center">
                            <p className="text-sm text-tb-on-surface-variant">Belum ada data tarombo.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_320px]">
                            <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                                <TaromboDiagram
                                    onSelect={handlePersonSelect}
                                    selectedId={selectedId ?? undefined}
                                    centerPersonId={centerPersonId}
                                    people={people}
                                    margas={margas}
                                />
                            </div>
                            <div className="flex justify-center lg:justify-start">
                                <ProfileCard person={selected ?? null} childrenList={childrenList} onClose={handleCloseProfile} />
                            </div>
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
                </section>
            </main>

            <SiteFooter />
        </div>
    );
}

TaromboPublic.layout = {
    breadcrumbs: [{ title: 'Tarombo', href: tarombo.view() }],
};
