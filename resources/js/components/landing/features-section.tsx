import { Reveal } from '@/components/landing/reveal';
import { features } from '@/data/landing';

export function FeaturesSection() {
    return (
        <section id="fitur" className="bg-tb-surface-bright px-6 py-16">
            <div className="mx-auto max-w-7xl">
                <div className="mb-12 flex items-center justify-center gap-4">
                    <div className="h-px w-16 bg-tb-outline-variant" />
                    <h2 className="font-display text-2xl font-bold text-center">Fitur Unggulan</h2>
                    <div className="h-px w-16 bg-tb-outline-variant" />
                </div>
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3 lg:grid-cols-6">
                    {features.map((feature, index) => (
                        <Reveal key={feature.title} delay={index * 0.07}>
                            <div className="flex flex-col items-center rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center shadow-sm">
                                <div
                                    className={`mb-4 flex h-12 w-12 items-center justify-center rounded-full text-xl ${feature.iconClass}`}
                                >
                                    <feature.icon className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-sm font-bold">{feature.title}</h3>
                                <p className="text-xs leading-relaxed text-tb-on-surface-variant">{feature.description}</p>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}