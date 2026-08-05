import { SendHorizontal } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';

export function NewsletterSection() {
    return (
        <section className="mx-auto max-w-7xl px-6 pb-16">
            <Reveal>
                <div className="flex flex-col items-center justify-between gap-6 rounded-2xl border border-tb-outline-variant bg-tb-surface-container bg-tb-gorga bg-blend-soft-light p-6 md:flex-row md:p-8">
                    <div className="flex-1">
                        <h2 className="mb-2 font-display text-xl font-bold">
                            Dapatkan Update & Inspirasi Budaya Batak
                        </h2>
                        <p className="text-sm text-tb-on-surface-variant">
                            Berlangganan newsletter kami untuk info terbaru seputar tarombo, budaya, dan event komunitas.
                        </p>
                    </div>
                    <div className="flex w-full flex-col gap-3 sm:flex-row md:w-auto">
                        <input
                            type="email"
                            placeholder="Masukkan email Anda"
                            className="min-w-[250px] rounded-lg border-tb-outline-variant px-4 py-3 text-sm shadow-sm focus:border-tb-primary focus:ring-tb-primary"
                        />
                        <button
                            type="button"
                            className="flex items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-tb-primary px-6 py-3 font-medium text-white shadow-sm transition-colors hover:bg-tb-primary-light"
                        >
                            Berlangganan <SendHorizontal className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </Reveal>
        </section>
    );
}