import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    const handleToggle = (e: React.MouseEvent<HTMLButtonElement>) => {
        const next = isDark ? 'light' : 'dark';
        const { clientX, clientY } = e;
        const root = document.documentElement;

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        if (prefersReducedMotion) {
            updateAppearance(next);

            return;
        }

        root.style.setProperty('--tb-theme-x', `${clientX}px`);
        root.style.setProperty('--tb-theme-y', `${clientY}px`);

        const doUpdate = () => updateAppearance(next);

        if (typeof document.startViewTransition === 'function') {
            document.startViewTransition(doUpdate);
        } else {
            doUpdate();
        }
    };

    return (
        <button
            type="button"
            onClick={handleToggle}
            aria-label={
                isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'
            }
            className="flex h-10 w-10 items-center justify-center rounded-full border border-tb-outline-variant text-tb-on-surface transition-colors hover:bg-tb-surface-container hover:text-tb-primary"
        >
            {isDark ? (
                <Sun className="h-5 w-5" />
            ) : (
                <Moon className="h-5 w-5" />
            )}
        </button>
    );
}
