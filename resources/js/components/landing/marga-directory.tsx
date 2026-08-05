import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import marga from '@/routes/marga';

type MargaItem = {
    name: string;
    color: string | null;
    count: number;
};

export function MargaDirectory({ margas }: { margas: MargaItem[] }) {
    return (
        <section className="mx-auto max-w-7xl px-6 py-16">
            <div className="mb-8 flex items-center justify-between">
                <h2 className="font-display text-2xl font-bold">Direktori Marga Populer</h2>
                <Link
                    href={marga.view()}
                    className="flex items-center rounded-full bg-tb-surface-container px-4 py-2 text-sm font-medium transition-colors hover:bg-tb-surface-container-high"
                >
                    Lihat Semua Marga <ChevronRight className="ml-1 h-4 w-4" />
                </Link>
            </div>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                {margas.map((margaItem, index) => (
                    <Reveal key={margaItem.name} delay={index * 0.08}>
                        <a
                            href="#"
                            className="group relative flex h-40 flex-col justify-end overflow-hidden rounded-2xl p-4 shadow-md transition-transform duration-300 hover:-translate-y-1"
                            style={{
                                backgroundColor: margaItem.color ?? 'var(--color-tb-primary)',
                            }}
                        >
                            <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-40 transition-opacity group-hover:opacity-60" />
                            <div className="relative z-10">
                                <h3 className="font-display text-xl font-bold text-white">
                                    {margaItem.name}
                                </h3>
                                <p className="text-xs text-white/90">
                                    {margaItem.count} orang
                                </p>
                            </div>
                        </a>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}
