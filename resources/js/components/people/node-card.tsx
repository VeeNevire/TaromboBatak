import { useEffect, useRef } from 'react';
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
    claimed?: boolean;
    spouses?: string[];
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
    compact = false,
    narrow = false,
    badge,
    onAvatarClick,
    onAvatarDoubleClick,
    onNameClick,
    dashed = false,
    showAvatar = true,
    showSpouseNames = false,
}: {
    node: TreeNode;
    highlighted?: boolean;
    compact?: boolean;
    narrow?: boolean;
    badge?: string;
    onAvatarClick?: () => void;
    onAvatarDoubleClick?: () => void;
    onNameClick?: () => void;
    dashed?: boolean;
    showAvatar?: boolean;
    showSpouseNames?: boolean;
}) {
    const clickTimer = useRef<number | null>(null);

    useEffect(
        () => () => {
            if (clickTimer.current !== null) {
                window.clearTimeout(clickTimer.current);
            }
        },
        [],
    );

    // With a double-click action the single click waits a moment, so the
    // two clicks of a double-click do not also fire the single-click action.
    const handleAvatarClick = onAvatarDoubleClick
        ? () => {
              if (clickTimer.current !== null) {
                  window.clearTimeout(clickTimer.current);
              }

              clickTimer.current = window.setTimeout(() => {
                  clickTimer.current = null;
                  onAvatarClick?.();
              }, 250);
          }
        : onAvatarClick;
    const handleAvatarDoubleClick = onAvatarDoubleClick
        ? () => {
              if (clickTimer.current !== null) {
                  window.clearTimeout(clickTimer.current);
                  clickTimer.current = null;
              }

              onAvatarDoubleClick();
          }
        : undefined;

    return (
        <div
            className={cn(
                'flex flex-col items-center',
                compact
                    ? narrow
                        ? 'w-[64px]'
                        : 'max-w-[88px] min-w-[48px]'
                    : narrow
                      ? 'w-[80px]'
                      : 'max-w-[150px] min-w-[96px]',
            )}
        >
            {showAvatar && (
                <div
                    role={onAvatarClick ? 'button' : undefined}
                    tabIndex={onAvatarClick ? 0 : undefined}
                    aria-label={
                        onAvatarClick
                            ? `Tampilkan jalur silsilah ${node.name}`
                            : undefined
                    }
                    title={
                        onAvatarClick
                            ? onAvatarDoubleClick
                                ? 'Klik: tampilkan jalur silsilah · Klik 2x: jadikan paling atas'
                                : 'Tampilkan jalur silsilah'
                            : undefined
                    }
                    onClick={handleAvatarClick}
                    onDoubleClick={handleAvatarDoubleClick}
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
                        'flex items-center justify-center overflow-hidden border transition-shadow',
                        compact ? 'h-8 w-8' : 'h-12 w-12',
                        dashed && 'border-dashed',
                        highlighted ? 'rounded-xl' : 'rounded-full',
                        onAvatarClick &&
                            'cursor-pointer hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-[#B8934A] focus-visible:outline-none',
                    )}
                    style={{
                        background: node.margaColor ?? pastelFor(node.id),
                        borderColor: highlighted
                            ? GOLD
                            : 'var(--tb-ring, #E3DFD2)',
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
            )}
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
                 compact
    ? cn(
          narrow && 'w-full',
          'mt-0.5 rounded-md border px-1 py-0.5 text-center text-[length:var(--tb-name-size,8px)] leading-tight font-semibold',
      )
    : cn(
          showAvatar ? 'mt-2' : 'mt-0',
          narrow ? 'w-full px-1' : 'px-2',
          'rounded-md border py-1 text-center text-[length:var(--tb-name-size,11px)] leading-snug font-semibold',
      ),
node.claimed &&
    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
                    dashed && 'border-dashed',
                    onNameClick &&
                        'cursor-pointer hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-[#B8934A] focus-visible:outline-none',
                )}
                style={{
                    backgroundColor: node.claimed
                        ? undefined
                        : 'var(--tb-name-bg, #ffffff)',
                    borderColor: highlighted
                        ? GOLD
                        : node.claimed
                          ? '#6ee7b7'
                          : 'var(--tb-name-border, #E3DFD2)',
                    color: node.claimed
                        ? '#166534'
                        : highlighted
                          ? FOREST
                          : `var(--tb-name-color, ${INK})`,
                    fontFamily: 'var(--tb-name-font, inherit)',
                    fontWeight: 'var(--tb-name-weight, 600)',
                }}
            >
                {node.pending ? (
                    <span className="block text-[9px] font-semibold tracking-wide text-[#B8934A] uppercase">
                        —
                    </span>
                ) : null}
                <span
                    className={cn(
                        'block break-words',
                        // A long single word must break inside a fixed-width
                        // card instead of widening it over its neighbour.
                        narrow && 'wrap-anywhere',
                    )}
                >
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
                {showSpouseNames && (node.spouses?.length ?? 0) > 0 && (
                    <span className="mt-0.5 block border-t border-current/15 pt-0.5 text-[9px] leading-tight font-medium text-tb-primary">
                        {node.spouses?.join(', ')}
                    </span>
                )}
            </div>
        </div>
    );
}
