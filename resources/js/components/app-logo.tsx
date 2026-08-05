import { BrandLogo } from '@/components/brand-logo';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-tb-primary">
                <BrandLogo className="size-6" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate font-display text-base leading-tight font-bold">
                    Tarombo Batak
                </span>
                <span className="truncate text-xs text-tb-on-surface-variant">
                    Silsilah • Budaya • Identitas
                </span>
            </div>
        </>
    );
}
