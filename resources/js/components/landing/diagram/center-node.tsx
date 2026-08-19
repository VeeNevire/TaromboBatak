import { Handle, Position, useStore } from '@xyflow/react';
import type { NodeProps } from '@xyflow/react';
import { Crown } from 'lucide-react';
import { getInitials } from '@/data/tarombo-tree';
import type { TaromboNodeData } from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';

function DetailRow({
    label,
    value,
}: {
    label: string;
    value?: string | number | null;
}) {
    if (value === undefined || value === null || value === '') {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-2 text-[11px]">
            <span className="text-tb-on-surface-variant">{label}</span>
            <span className="font-semibold text-tb-on-surface">{value}</span>
        </div>
    );
}

function CenterBubble({
    person,
    ringColor,
}: {
    person: TaromboNodeData['person'];
    ringColor: string;
}) {
    return (
        <div className="w-56 rounded-xl border border-tb-outline-variant bg-tb-surface-bright p-3 shadow-xl">
            <div className="flex items-start gap-2.5">
                <div className="h-11 w-11 shrink-0 overflow-hidden rounded-full bg-tb-surface-container">
                    {person.image ? (
                        <img
                            src={person.image}
                            alt={person.name}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <span className="flex h-full w-full items-center justify-center text-xs font-bold text-tb-on-surface-variant">
                            {getInitials(person.name)}
                        </span>
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold text-tb-on-surface">
                        {person.name}
                    </p>
                    {person.alias && (
                        <p className="truncate text-[11px] text-tb-on-surface-variant italic">
                            {person.alias}
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-2 flex items-center gap-1.5">
                <span
                    className="rounded-full px-2 py-0.5 text-[10px] font-semibold text-white"
                    style={{ backgroundColor: ringColor }}
                >
                    {person.marga}
                </span>
                {person.birthYear && (
                    <span className="text-[10px] text-tb-on-surface-variant">
                        {person.birthYear}
                    </span>
                )}
            </div>

            <div className="mt-2 space-y-1 border-t border-tb-outline-variant pt-2">
                <DetailRow label="Anak ke" value={person.birthOrder} />
                <DetailRow
                    label="Jenis Kelamin"
                    value={person.gender?.toUpperCase()}
                />
                <DetailRow label="Pasangan" value={person.spouse} />
            </div>
        </div>
    );
}

export function CenterNode({ data }: NodeProps) {
    const { person, ringColor, selected, related } = data as TaromboNodeData;
    const zoom = useStore((state) => state.transform[2]);

    const boxShadow = selected
        ? `0 0 0 4px #ffffff, 0 0 35px 10px ${ringColor}aa`
        : related
          ? `0 0 0 3px #ffffff, 0 0 0 6px ${ringColor}55`
          : '0 10px 24px rgba(44, 37, 24, 0.22)';

    return (
        <div
            className={cn(
                'tb-node-enter relative flex h-[112px] w-[112px] cursor-pointer items-center justify-center rounded-full p-[5px] transition-all duration-300 ease-out',
                selected ? 'scale-105' : 'hover:scale-105',
            )}
            style={{
                background: `linear-gradient(135deg, ${ringColor}, color-mix(in srgb, ${ringColor} 55%, white))`,
                boxShadow,
                animationDelay: `${(person.generation - 1) * 90}ms`,
            }}
        >
            {selected && (
                <span
                    className="tb-pulse-ring"
                    style={{ inset: -4, borderColor: ringColor }}
                />
            )}
            <Handle
                type="source"
                position={Position.Top}
                className="!top-1/2 !left-1/2 !h-1 !w-1 !-translate-x-1/2 !-translate-y-1/2"
            />
            <Handle
                type="target"
                position={Position.Bottom}
                className="!top-1/2 !left-1/2 !h-1 !w-1 !-translate-x-1/2 !-translate-y-1/2"
            />
            {selected && (
                <div
                    className="absolute bottom-full left-1/2 z-30 mb-3"
                    style={{
                        transform: `translateX(-50%) scale(${1 / zoom})`,
                        transformOrigin: 'bottom center',
                    }}
                >
                    <CenterBubble person={person} ringColor={ringColor} />
                    <div className="absolute -bottom-1 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-r border-b border-tb-outline-variant bg-tb-surface-bright" />
                </div>
            )}

            <div className="relative flex h-full w-full flex-col items-center justify-center overflow-hidden rounded-full bg-tb-surface-bright px-3 text-center">
                <div className="absolute -top-1 flex h-8 w-8 items-center justify-center rounded-full bg-tb-primary text-white shadow-md ring-2 ring-white">
                    <Crown className="h-4 w-4" />
                </div>
                {person.image ? (
                    <img
                        src={person.image}
                        alt={person.name}
                        className="h-full w-full rounded-full object-cover opacity-95"
                    />
                ) : (
                    <>
                        <p className="font-display text-[15px] leading-tight font-bold text-tb-on-surface">
                            {person.name}
                        </p>
                        {person.alias && (
                            <p className="mt-0.5 line-clamp-2 text-[8px] leading-tight text-tb-on-surface-variant italic">
                                {person.alias}
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
