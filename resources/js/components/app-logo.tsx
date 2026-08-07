import { motion } from 'framer-motion';
import { BrandLogo } from '@/components/brand-logo';

export default function AppLogo() {
    return (
        <motion.div
            className="flex min-w-0 flex-1 items-center"
            initial={{ opacity: 0, y: 6, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ duration: 0.4, ease: 'easeOut' }}
        >
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-tb-primary shadow-md ring-1 ring-white/20">
                <BrandLogo className="size-6" />
            </div>
            <div className="ml-2 grid min-w-0 flex-1 text-left text-sm">
                <span className="mb-0.5 truncate font-display text-base leading-tight font-bold text-sidebar-foreground">
                    Tarombo Batak
                </span>
                <span className="truncate text-xs text-sidebar-foreground/70">
                    Silsilah • Budaya • Identitas
                </span>
            </div>
        </motion.div>
    );
}
