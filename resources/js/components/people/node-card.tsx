import { createContext, useContext, useEffect, useRef } from 'react';
import { PersonImage } from '@/components/people/person-image';
import { cn } from '@/lib/utils';

export type TreeNode = {
    id: string;
    name: string;
    gender?: string | null;
    alias?: string | null;
    marga: string;
    margaColor?: string | null;
    birthYear?: string | null;
    birthOrder?: number | null;
    displayNumber?: number;
    image?: string | null;
    pending?: boolean;
    claimed?: boolean;
    isMargaIdentity?: boolean;
    identityMargaColor?: string | null;
    spouses?: string[];
    spouseMargas?: string[];
};

/**
 * When on, name cards take their marga's colour from Daftar Marga. When off,
 * cards stay plain and only marga identity figures are drawn sky blue.
 */
export const MargaColorContext = createContext(false);

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

/** Dark or light text, whichever reads better on the given hex background. */
function readableTextOn(hex: string): string {
    const value = hex.replace('#', '');
    const full =
        value.length === 3
            ? value
                  .split('')
                  .map((char) => char + char)
                  .join('')
            : value;
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16));

    if ([r, g, b].some(Number.isNaN)) {
        return INK;
    }

    return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? INK : '#ffffff';
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
    showSpouseMargas = false,
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
    showSpouseMargas?: boolean;
}) {
    const clickTimer = useRef<number | null>(null);
    const showMargaColors = useContext(MargaColorContext);
    // Identity cards take the colour of the marga they found; everyone else
    // takes the colour of their own marga from Daftar Marga.
    const fillColor = showMargaColors
        ? ((node.isMargaIdentity ? node.identityMargaColor : null) ??
          node.margaColor ??
          null)
        : null;

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
                    data-node-fill
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
                        background: pastelFor(node.id),
                        borderColor: highlighted
                            ? GOLD
                            : node.gender?.toUpperCase() === 'P'
                              ? 'var(--tb-female-ring, var(--tb-ring, #E3DFD2))'
                              : 'var(--tb-male-ring, var(--tb-ring, #E3DFD2))',
                        borderWidth:
                            node.gender?.toUpperCase() === 'P'
                                ? 'var(--tb-female-ring-width, 1px)'
                                : 'var(--tb-male-ring-width, 1px)',
                        borderRadius:
                            node.gender?.toUpperCase() === 'P'
                                ? `var(--tb-female-ring-radius, ${highlighted ? '12px' : '50%'})`
                                : `var(--tb-male-ring-radius, ${highlighted ? '12px' : '50%'})`,
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
                title={
                    node.isMargaIdentity
                        ? 'Tokoh identitas marga'
                        : onNameClick
                          ? 'Lihat ringkasan anggota'
                          : undefined
                }
                data-node-fill
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
                    !fillColor &&
                        !node.isMargaIdentity &&
                        node.claimed &&
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
                    dashed && 'border-dashed',
                    onNameClick &&
                        'cursor-pointer hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-[#B8934A] focus-visible:outline-none',
                )}
                style={{
                    backgroundColor: fillColor
                        ? fillColor
                        : node.isMargaIdentity
                          ? '#BAE6FD'
                          : node.claimed
                            ? undefined
                            : 'var(--tb-name-bg, #ffffff)',
                    borderColor: highlighted
                        ? GOLD
                        : node.claimed && !node.isMargaIdentity
                          ? '#6ee7b7'
                          : fillColor
                            ? fillColor
                            : node.isMargaIdentity
                              ? '#7DD3FC'
                              : 'var(--tb-name-border, #E3DFD2)',
                    color: fillColor
                        ? readableTextOn(fillColor)
                        : node.isMargaIdentity
                          ? '#0C4A6E'
                          : node.claimed
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
                {showSpouseNames &&
                    ((showSpouseMargas
                        ? node.spouseMargas?.length
                        : node.spouses?.length) ?? 0) > 0 && (
                        <span
                            className={cn(
                                'mt-0.5 block border-t border-current/15 pt-0.5 text-[9px] leading-tight font-medium',
                                !fillColor && 'text-tb-primary',
                            )}
                        >
                            {(showSpouseMargas
                                ? node.spouseMargas
                                : node.spouses
                            )?.join(', ')}
                        </span>
                    )}
            </div>
        </div>
    );
}
