import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <button
            type="button"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            aria-label={isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'}
            className="flex h-10 w-10 items-center justify-center rounded-full border border-tb-outline-variant text-tb-on-surface transition-colors hover:bg-tb-surface-container hover:text-tb-primary"
        >
            {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
        </button>
    );
}
