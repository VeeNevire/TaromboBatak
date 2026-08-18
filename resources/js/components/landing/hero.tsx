import { motion } from 'framer-motion';
import { ArrowRight, CirclePlay } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ProfileCard } from '@/components/landing/profile-card';
import { TaromboDiagram } from '@/components/landing/tarombo-diagram';
import { stats } from '@/data/landing';
import {
    findPerson,
    findPersonChildren,
    MOCK_TAROMBO,
} from '@/data/tarombo-tree';
import type { TaromboPerson } from '@/data/tarombo-tree';

const DEFAULT_PERSON_ID = 'tuan-sorimangaraja';

const container = {
    hidden: {},
    visible: { transition: { staggerChildren: 0.1, delayChildren: 0.05 } },
};

const item = {
    hidden: { opacity: 0, y: 20 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.5, ease: 'easeOut' as const },
    },
};

export function Hero() {
    const [selected, setSelected] = useState<TaromboPerson | null>(null);
    const [centerPersonId, setCenterPersonId] =
        useState<string>(DEFAULT_PERSON_ID);
    const [history, setHistory] = useState<string[]>([]);
    const children = useMemo(
        () => (selected ? findPersonChildren(MOCK_TAROMBO, selected.id) : []),
        [selected],
    );

    const handlePersonSelect = (person: TaromboPerson) => {
        setHistory((prev) => [...prev, centerPersonId]);
        setSelected(person);
        setCenterPersonId(person.id);
    };

    const handleBack = () => {
        if (history.length === 0) {
            return;
        }

        const prev = history[history.length - 1];
        setHistory((prevHistory) => prevHistory.slice(0, -1));

        if (prev === DEFAULT_PERSON_ID) {
            setSelected(null);
            setCenterPersonId(DEFAULT_PERSON_ID);
        } else {
            setSelected(findPerson(MOCK_TAROMBO, prev) ?? null);
            setCenterPersonId(prev);
        }
    };

    const handleCloseProfile = () => {
        setSelected(null);
    };

    return (
        <section
            id="beranda"
            className="mx-auto flex max-w-7xl flex-col items-center gap-12 px-6 py-12 md:py-20 lg:flex-row"
        >
            <motion.div
                className="space-y-8 lg:w-[28%]"
                variants={container}
                initial="hidden"
                animate="visible"
            >
                <motion.h2
                    variants={item}
                    className="font-display text-5xl leading-tight font-bold text-tb-on-surface md:text-6xl"
                >
                    Lestarikan Silsilah,
                    <br />
                    Kenali Akar Budaya
                </motion.h2>
                <motion.p
                    variants={item}
                    className="text-lg leading-relaxed text-tb-on-surface-variant"
                >
                    Tarombo Batak adalah platform digital untuk menelusuri
                    silsilah keluarga, menjaga warisan leluhur, dan
                    menghubungkan generasi masa kini dengan akar budaya Batak.
                </motion.p>
                <motion.div
                    variants={item}
                    className="flex w-fit gap-6 border-t border-tb-outline-variant pt-4"
                >
                    {stats.map((stat) => (
                        <div key={stat.label}>
                            <p className="flex items-center text-xl font-bold">
                                {stat.icon && (
                                    <stat.icon className="mr-1 h-4 w-4 text-tb-on-surface-variant" />
                                )}
                                {stat.value}
                            </p>
                            <p className="text-xs text-tb-on-surface-variant">
                                {stat.label}
                            </p>
                        </div>
                    ))}
                </motion.div>
                <motion.div
                    variants={item}
                    className="flex flex-col gap-4 pt-4"
                >
                    <button
                        type="button"
                        className="flex w-full items-center justify-between gap-2 rounded-full bg-tb-primary px-6 py-3 font-medium whitespace-nowrap text-white transition-colors hover:bg-tb-primary-light"
                    >
                        Mulai Telusuri Tarombo{' '}
                        <ArrowRight className="h-4 w-4 shrink-0" />
                    </button>

                    <button
                        type="button"
                        className="flex w-full items-center justify-between gap-2 rounded-full border border-tb-primary px-6 py-3 font-medium whitespace-nowrap transition-colors hover:bg-tb-primary hover:text-white"
                    >
                        <span className="flex items-center gap-2">
                            <CirclePlay className="h-5 w-5 shrink-0" /> Pelajari
                            Lebih Lanjut
                        </span>
                    </button>
                </motion.div>
            </motion.div>
            <motion.div
                className="w-full lg:w-[44%]"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.25 }}
            >
                <TaromboDiagram
                    onSelect={handlePersonSelect}
                    onPaneClick={() => setSelected(null)}
                    onBack={handleBack}
                    canGoBack={history.length > 0}
                    selectedId={selected?.id}
                    centerPersonId={centerPersonId}
                    context="descendants"
                    maxDepth={2}
                />
            </motion.div>
            <motion.div
                className="flex justify-end lg:w-[28%]"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.4 }}
            >
                <ProfileCard
                    person={selected}
                    childrenList={children}
                    onClose={handleCloseProfile}
                />
            </motion.div>
        </section>
    );
}
