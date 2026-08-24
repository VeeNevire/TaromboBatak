import { Handle, Position, useStore } from '@xyflow/react';
import type { NodeProps } from '@xyflow/react';
import { getGenerationLabel, getInitials } from '@/data/tarombo-tree';
import type { TaromboNodeData } from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';

const HONORIFICS = new Set([
    'si',
    'raja',
    'tuan',
    'guru',
    'datu',
    'tunggal',
    'siraja',
]);

function getShortName(name: string): string {
    const words = name.split(' ');

    if (words.length <= 1) {
        return name;
    }

    const meaningful = words.filter(
        (word) => !HONORIFICS.has(word.toLowerCase()),
    );

    if (meaningful.length === 0) {
        return words[words.length - 1];
    }

    return meaningful.length === 1
        ? meaningful[0]
        : meaningful.slice(0, 2).join(' ');
}

function GenderBadge({ gender }: { gender?: string | null }) {
    if (!gender) {
        return null;
    }

    const isMale = gender.toUpperCase() === 'L';

    return (
        <span
            className={cn(
                'flex h-4 w-4 items-center justify-center rounded-full text-[8px] font-bold text-white ring-2 ring-white',
                isMale ? 'bg-blue-600' : 'bg-pink-600',
            )}
        >
            {isMale ? 'L' : 'P'}
        </span>
    );
}

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

function PersonBubble({
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
                    label="Generasi"
                    value={`Gen ${person.generation} (${getGenerationLabel(person.generation)})`}
                />
                <DetailRow
                    label="Jenis Kelamin"
                    value={person.gender?.toUpperCase()}
                />
                <DetailRow label="Pasangan" value={person.spouse} />
            </div>
        </div>
    );
}

export function PersonNode({ data }: NodeProps) {
    const { person, ringColor, selected, related, bubble } =
        data as TaromboNodeData;
    const zoom = useStore((state) => state.transform[2]);

    const avatarShadow = selected
        ? `0 0 0 3px #ffffff, 0 0 25px 5px ${ringColor}aa`
        : related
          ? `0 0 0 2px #ffffff, 0 0 0 4px ${ringColor}66`
          : '0 2px 6px rgba(44, 37, 24, 0.18)';

    return (
        <div
            className={cn(
                'tb-node-enter relative flex h-[104px] w-[80px] cursor-pointer flex-col items-center transition-all duration-300 ease-out',
                selected ? 'scale-110' : 'hover:scale-105',
            )}
            style={{ animationDelay: `${(person.generation - 1) * 90}ms` }}
        >
            {bubble && (
                <div
                    className="absolute bottom-full left-1/2 z-30 mb-3"
                    style={{
                        transform: `translateX(-50%) scale(${1 / zoom})`,
                        transformOrigin: 'bottom center',
                    }}
                >
                    <PersonBubble person={person} ringColor={ringColor} />
                    <div className="absolute -bottom-1 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-r border-b border-tb-outline-variant bg-tb-surface-bright" />
                </div>
            )}

            <div className="relative">
                {selected && (
                    <span
                        className="tb-pulse-ring"
                        style={{ inset: -3, borderColor: ringColor }}
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
                <div
                    className="flex h-16 w-16 items-center justify-center rounded-full p-[3px]"
                    style={{
                        background: `linear-gradient(135deg, ${ringColor}, color-mix(in srgb, ${ringColor} 55%, white))`,
                        boxShadow: avatarShadow,
                    }}
                >
                    <div className="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-tb-surface-bright">
                        <div className="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-tb-surface-container text-[11px] font-bold text-tb-on-surface-variant">
                            {person.image ? (
                                <img
                                    src={person.image}
                                    alt={person.name}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <span>{getInitials(person.name)}</span>
                            )}
                        </div>
                    </div>
                </div>
                <div className="absolute -right-0.5 -bottom-0.5">
                    <GenderBadge gender={person.gender} />
                </div>
            </div>
            <p
                className={cn(
                    'mt-1 line-clamp-1 text-center text-[10px] leading-snug font-semibold transition-colors',
                    selected ? 'text-tb-primary' : 'text-tb-on-surface',
                )}
            >
                {getShortName(person.name)}
            </p>
            <span
                className="mt-0.5 rounded-full px-1.5 py-px text-[8px] font-semibold tracking-wide uppercase"
                style={{ backgroundColor: `${ringColor}1f`, color: ringColor }}
            >
                {person.marga}
            </span>
        </div>
    );
}
