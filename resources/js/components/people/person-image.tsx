import { useState } from 'react';
import { getInitials } from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';

type PersonImageProps = {
    src?: string | null;
    name: string;
    className?: string;
    fallbackClassName?: string;
};

export function PersonImage({
    src,
    name,
    className,
    fallbackClassName,
}: PersonImageProps) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);

    if (!src || failedSrc === src) {
        return (
            <span
                role="img"
                aria-label={`Inisial ${name}`}
                className={cn(
                    'flex h-full w-full items-center justify-center',
                    fallbackClassName,
                )}
            >
                {getInitials(name)}
            </span>
        );
    }

    return (
        <img
            src={src}
            alt={name}
            className={className}
            onError={() => setFailedSrc(src)}
        />
    );
}
