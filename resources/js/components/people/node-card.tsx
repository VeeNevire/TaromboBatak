import { cn } from '@/lib/utils';

export type TreeNode = {
    id: string;
    name: string;
    alias?: string | null;
    marga: string;
    margaColor?: string | null;
    birthYear?: string | null;
    birthOrder?: number | null;
    chain?: string | null;
    image?: string | null;
    pending?: boolean;
};

const PASTELS = ['#DCE7DE', '#EFE2C9', '#E6D6E3', '#D6E1EC', '#F0DAD0'];
export const FOREST = '#2F4538';
export const GOLD = '#B8934A';
export const INK = '#24322B';
export const INK_SOFT = '#5B6A61';
export const LINE = '#A79E8C';

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join('');
}

function pastelFor(id: string): string {
    let hash = 0;

    for (let i = 0; i < id.length; i++) {
        hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
    }

    return PASTELS[hash % PASTELS.length];
}

export function NodeCard({
    node,
    highlighted = false,
    badge,
}: {
    node: TreeNode;
    highlighted?: boolean;
    badge?: string;
}) {
    return (
        <div className="flex max-w-[170px] min-w-[110px] flex-col items-center">
            <div
                className={cn(
                    'flex h-16 w-16 items-center justify-center overflow-hidden border transition-shadow',
                    highlighted ? 'rounded-xl' : 'rounded-full',
                )}
                style={{
                    background: node.image
                        ? 'transparent'
                        : (node.margaColor ?? pastelFor(node.id)),
                    borderColor: highlighted ? GOLD : '#E3DFD2',
                    boxShadow: highlighted
                        ? '0 3px 10px rgba(184,147,74,0.3)'
                        : '0 2px 6px rgba(36,50,43,0.06)',
                }}
            >
                {node.image ? (
                    <img
                        alt={node.name}
                        src={node.image}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div
                        style={{ color: FOREST }}
                        className="text-sm font-bold opacity-80"
                    >
                        {initials(node.name)}
                    </div>
                )}
            </div>
            <div
                className="mt-2 rounded-md border bg-white px-2 py-1 text-center text-[11px] leading-snug font-semibold"
                style={{
                    borderColor: highlighted ? GOLD : '#E3DFD2',
                    color: highlighted ? FOREST : INK,
                }}
            >
                {node.chain ? (
                    <span className="block text-[9px] font-semibold tracking-wide text-[#5B6A61] uppercase">
                        No. {node.chain}
                    </span>
                ) : node.pending ? (
                    <span className="block text-[9px] font-semibold tracking-wide text-[#B8934A] uppercase">
                        —
                    </span>
                ) : null}
                <span className="block break-words">{node.name}</span>
                {badge ? (
                    <span
                        className="mt-0.5 block rounded-full px-1.5 py-px text-[9px] font-semibold"
                        style={{
                            backgroundColor: highlighted ? GOLD : '#F0F1EA',
                            color: highlighted ? '#1B241F' : INK_SOFT,
                        }}
                    >
                        {badge}
                    </span>
                ) : null}
                {node.pending ? (
                    <span className="mt-0.5 block rounded-full bg-[#FDEBD0] px-1.5 py-px text-[9px] font-semibold text-[#92400E]">
                        Belum tersambung
                    </span>
                ) : null}
            </div>
        </div>
    );
}
