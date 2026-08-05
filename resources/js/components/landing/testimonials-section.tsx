import { Quote } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import { testimonial } from '@/data/landing';

export function TestimonialsSection() {
    return (
        <div className="space-y-6">
            <h2 className="font-display text-xl font-bold">Testimoni Anggota</h2>
            <Reveal>
                <div className="relative rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6">
                <Quote className="absolute left-4 top-4 h-10 w-10 text-tb-surface-container opacity-50" />
                <p className="relative z-10 mb-6 pt-4 text-sm leading-relaxed text-tb-on-surface-variant">
                    "{testimonial.quote}"
                </p>
                <div className="flex items-center gap-3">
                    <img
                        alt={testimonial.name}
                        className="h-10 w-10 rounded-full"
                        src={testimonial.image}
                    />
                    <div>
                        <h4 className="text-sm font-bold">{testimonial.name}</h4>
                        <p className="text-xs text-tb-outline">{testimonial.since}</p>
                    </div>
                </div>
                <div className="mt-6 flex justify-center gap-1.5">
                    <div className="h-2 w-2 rounded-full bg-tb-primary" />
                    <div className="h-2 w-2 rounded-full bg-tb-outline-variant" />
                    <div className="h-2 w-2 rounded-full bg-tb-outline-variant" />
                    <div className="h-2 w-2 rounded-full bg-tb-outline-variant" />
                </div>
            </div>
            </Reveal>
        </div>
    );
}