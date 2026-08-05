import type { NodeProps } from '@xyflow/react';
import { Crown } from 'lucide-react';
import type { TaromboNodeData } from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';

export function CenterNode({ data }: NodeProps) {
    const { person, ringColor, selected, related } = data as TaromboNodeData;

    const boxShadow = selected
        ? `0 0 0 4px #ffffff, 0 0 30px 8px ${ringColor}88`
        : related
          ? `0 0 0 3px #ffffff, 0 0 0 6px ${ringColor}44`
          : '0 10px 24px rgba(44, 37, 24, 0.22)';

    return (
        <div
            className={cn(
                'relative flex h-[112px] w-[112px] cursor-pointer items-center justify-center rounded-full p-[5px] transition-transform',
                selected ? 'scale-105' : 'hover:scale-105',
            )}
            style={{
                background: ringColor,
                boxShadow,
            }}
        >
            <div className="relative flex h-full w-full flex-col items-center justify-center rounded-full bg-tb-surface-bright px-3 text-center">
                <div className="absolute -top-1 flex h-8 w-8 items-center justify-center rounded-full bg-tb-primary text-white shadow-md ring-2 ring-white">
                    <Crown className="h-4 w-4" />
                </div>
                <p className="font-display text-[15px] font-bold leading-tight text-tb-on-surface">{person.name}</p>
                {person.alias && (
                    <p className="mt-0.5 line-clamp-2 text-[8px] italic leading-tight text-tb-on-surface-variant">
                        {person.alias}
                    </p>
                )}
            </div>
        </div>
    );
}