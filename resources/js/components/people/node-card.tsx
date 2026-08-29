import { PersonImage } from '@/components/people/person-image';
import { cn } from '@/lib/utils';

export type TreeNode = {
    id: string;
    name: string;
    alias?: string | null;
    marga: string;
    margaColor?: string | null;
    birthYear?: string | null;
    birthOrder?: number | null;
    displayNumber?: number;
    image?: string | null;
    pending?: boolean;
};

const PASTELS = ['#DCE7DE', '#EFE2C9', '#E6D6E3', '#D6E1EC', '#F0DAD0'];
export const FOREST = '#2F4538';
export const GOLD = '#B8934A';
export const INK = '#24322B';
export const INK_SOFT = '#5B6A61';
export const LINE = '#A79E8C';

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
    onAvatarClick,
    onNameClick,
    dashed = false,
}: {
    node: TreeNode;
    highlighted?: boolean;
    badge?: string;
    onAvatarClick?: () => void;
    onNameClick?: () => void;
    dashed?: boolean;
}) {
    return (
        <div className="flex max-w-[150px] min-w-[96px] flex-col items-center">
            <div
                role={onAvatarClick ? 'button' : undefined}
                tabIndex={onAvatarClick ? 0 : undefined}
                aria-label={
                    onAvatarClick
                        ? `Tampilkan jalur silsilah ${node.name}`
                        : undefined
                }
                title={onAvatarClick ? 'Tampilkan jalur silsilah' : undefined}
                onClick={onAvatarClick}
                onKeyDown={(event) => {
                    if (
                        onAvatarClick &&
                        (event.key === 'Enter' || event.key === ' ')
                    ) {
                        event.preventDefault();
                        onAvatarClick();
                    }
                }}
                className={cn(
                    'flex h-12 w-12 items-center justify-center overflow-hidden border transition-shadow',
                    dashed && 'border-dashed',
                    highlighted ? 'rounded-xl' : 'rounded-full',
                    onAvatarClick &&
                        'cursor-pointer hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-[#B8934A] focus-visible:outline-none',
                )}
                style={{
                    background: node.margaColor ?? pastelFor(node.id),
                    borderColor: highlighted ? GOLD : '#E3DFD2',
                    boxShadow: highlighted
                        ? '0 3px 10px rgba(184,147,74,0.3)'
                        : '0 2px 6px rgba(36,50,43,0.06)',
                }}
            >
                <PersonImage
                    src={node.image}
                    name={node.name}
                    className="h-full w-full object-cover"
                    fallbackClassName="text-xs font-bold text-[#2F4538] opacity-80"
                />
            </div>
            <div
                role={onNameClick ? 'button' : undefined}
                tabIndex={onNameClick ? 0 : undefined}
                aria-label={
                    onNameClick ? `Lihat ringkasan ${node.name}` : undefined
                }
                title={onNameClick ? 'Lihat ringkasan anggota' : undefined}
                onClick={onNameClick}
                onKeyDown={(event) => {
                    if (
                        onNameClick &&
                        (event.key === 'Enter' || event.key === ' ')
                    ) {
                        event.preventDefault();
                        onNameClick();
                    }
                }}
                className={cn(
                    'mt-2 rounded-md border bg-white px-2 py-1 text-center text-[11px] leading-snug font-semibold',
                    dashed && 'border-dashed',
                    onNameClick &&
                        'cursor-pointer hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-[#B8934A] focus-visible:outline-none',
                )}
                style={{
                    borderColor: highlighted ? GOLD : '#E3DFD2',
                    color: highlighted ? FOREST : INK,
                }}
            >
                {node.pending ? (
                    <span className="block text-[9px] font-semibold tracking-wide text-[#B8934A] uppercase">
                        —
                    </span>
                ) : null}
                <span className="block break-words">
                    {node.displayNumber != null
                        ? `${node.displayNumber}. `
                        : ''}
                    {node.name}
                    {node.birthOrder != null ? ` (${node.birthOrder})` : ''}
                </span>
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
