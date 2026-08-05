import type { NodeProps } from '@xyflow/react';
import { getInitials } from '@/data/tarombo-tree';
import type { TaromboNodeData } from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';

export function PersonNode({ data }: NodeProps) {
    const { person, ringColor, selected, related } = data as TaromboNodeData;

    const avatarShadow = selected
        ? `0 0 0 3px #ffffff, 0 0 22px 4px ${ringColor}99`
        : related
          ? `0 0 0 2px #ffffff, 0 0 0 4px ${ringColor}55`
          : '0 2px 6px rgba(44, 37, 24, 0.18)';

    return (
        <div
            className={cn(
                'flex h-[104px] w-[72px] cursor-pointer flex-col items-center transition-transform',
                selected ? 'scale-110' : 'hover:scale-105',
            )}
        >
            <div
                className="flex h-14 w-14 items-center justify-center rounded-full p-[3px]"
                style={{
                    background: ringColor,
                    boxShadow: avatarShadow,
                }}
            >
                <div className="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-tb-surface-bright">
                    <div className="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-tb-surface-container text-[11px] font-bold text-tb-on-surface-variant">
                        {person.image ? (
                            <img src={person.image} alt={person.name} className="h-full w-full object-cover" />
                        ) : (
                            <span>{getInitials(person.name)}</span>
                        )}
                    </div>
                </div>
            </div>
            <p
                className={cn(
                    'mt-1 line-clamp-2 text-center text-[10px] font-semibold leading-tight transition-colors',
                    selected ? 'text-tb-primary' : 'text-tb-on-surface',
                )}
            >
                {person.name}
            </p>
            <span
                className="mt-0.5 rounded-full px-1.5 py-px text-[8px] font-semibold uppercase tracking-wide"
                style={{ backgroundColor: `${ringColor}1f`, color: ringColor }}
            >
                {person.marga}
            </span>
        </div>
    );
}