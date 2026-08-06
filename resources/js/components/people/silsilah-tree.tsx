import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

export type SilsilahNode = {
    id: string;
    name: string;
    alias?: string | null;
    marga: string;
    margaColor?: string | null;
    birthYear?: string | null;
    birthOrder?: number | null;
    image?: string | null;
    parents?: (SilsilahNode | null)[] | null;
    siblings?: SilsilahNode[] | null;
};

export type SilsilahPayload = {
    centerId: string;
    person: SilsilahNode;
    father: SilsilahNode | null;
    mother: SilsilahNode | null;
    children: SilsilahNode[];
    margas: { name: string; color: string }[];
};

const PASTELS = ['#DCE7DE', '#EFE2C9', '#E6D6E3', '#D6E1EC', '#F0DAD0'];
const FOREST = '#2F4538';
const GOLD = '#B8934A';
const INK = '#24322B';
const INK_SOFT = '#5B6A61';
const LINE = '#A79E8C';

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

function NodeCard({
    node,
    highlighted = false,
    badge,
}: {
    node: SilsilahNode;
    highlighted?: boolean;
    badge?: string;
}) {
    const highlight = highlighted || node.parents !== undefined;

    return (
        <div data-node-id={node.id} className="flex min-w-[110px] max-w-[170px] flex-col items-center">
            <div
                className={cn(
                    'flex h-16 w-16 items-center justify-center overflow-hidden border transition-shadow',
                    highlight ? 'rounded-xl' : 'rounded-full',
                )}
                style={{
                    background: node.image ? 'transparent' : (node.margaColor ?? pastelFor(node.id)),
                    borderColor: highlight ? GOLD : '#E3DFD2',
                    boxShadow: highlight ? '0 3px 10px rgba(184,147,74,0.3)' : '0 2px 6px rgba(36,50,43,0.06)',
                }}
            >
                {node.image ? (
                    <img alt={node.name} src={node.image} className="h-full w-full object-cover" />
                ) : (
                    <div style={{ color: FOREST }} className="text-sm font-bold opacity-80">
                        {initials(node.name)}
                    </div>
                )}
            </div>
            <div
                className="mt-2 rounded-md border bg-white px-2 py-1 text-center text-[11px] font-semibold leading-snug"
                style={{ borderColor: highlight ? GOLD : '#E3DFD2', color: highlight ? FOREST : INK }}
            >
                <span className="block break-words">{node.name}</span>
                {badge ? (
                    <span
                        className="mt-0.5 block rounded-full px-1.5 py-px text-[9px] font-semibold"
                        style={{
                            backgroundColor: highlight ? GOLD : '#F0F1EA',
                            color: highlight ? '#1B241F' : INK_SOFT,
                        }}
                    >
                        {badge}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

function ParentNode({ node, badge }: { node: SilsilahNode; badge: string }) {
    const siblings = node.siblings ?? [];
    const parents = node.parents?.filter((parent) => parent !== null) ?? [];

    return (
        <div className="flex flex-col items-center gap-12 pb-4">
            <div className="flex flex-wrap items-start justify-center gap-6">
                {parents.map((parent) => <NodeCard key={parent.id} node={parent} />)}
            </div>
            <div className="flex flex-wrap items-start justify-center gap-6">
                {siblings.map((sibling) => <NodeCard key={sibling.id} node={sibling} />)}
                <NodeCard node={node} highlighted badge={badge} />
            </div>
        </div>
    );
}

export function SilsilahTree({ payload }: { payload: SilsilahPayload }) {
    const containerRef = useRef<HTMLDivElement>(null);
    const svgRef = useRef<SVGSVGElement>(null);
    const rafRef = useRef(0);

    const drawTree = () => {
        const container = containerRef.current;
        const svg = svgRef.current;

        if (!container || !svg) {
            return;
        }

        const rect = container.getBoundingClientRect();
        svg.setAttribute('width', String(rect.width));
        svg.setAttribute('height', String(rect.height));
        svg.innerHTML = '';

        const q = (id: string | null | undefined): HTMLElement | null =>
            container.querySelector(`[data-node-id="${id}"]`);

        const centerOf = (el: HTMLElement) => {
            const r = el.getBoundingClientRect();
            const c = container.getBoundingClientRect();
            return { x: r.left + r.width / 2 - c.left, top: r.top - c.top, bottom: r.bottom - c.top };
        };

        const addPath = (d: string, cls = '') => {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            path.setAttribute('stroke', cls === 'marriage' ? GOLD : LINE);
            path.setAttribute('stroke-width', '1.5');
            path.setAttribute('fill', 'none');
            if (cls) {
                path.setAttribute('class', cls);
            }
            svg.appendChild(path);
        };

        const drawSpouse = (aEl: HTMLElement, bEl: HTMLElement, cls = '') => {
            const a = centerOf(aEl);
            const b = centerOf(bEl);
            const y = a.top + 28;
            addPath(`M ${a.x} ${y} L ${b.x} ${y}`, cls);
            return { x: (a.x + b.x) / 2, top: y };
        };

        const drawComb = (
            from: { x: number; bottom: number },
            toEls: HTMLElement[],
            midY: number,
            cls = '',
        ) => {
            const pts = toEls.map(centerOf);
            const xs = pts.map((p) => p.x);
            const minX = Math.min(...xs, from.x);
            const maxX = Math.max(...xs, from.x);
            const legs = pts.map((p) => ` M ${p.x} ${midY} L ${p.x} ${p.top}`).join('');
            addPath(`M ${from.x} ${from.bottom} L ${from.x} ${midY} M ${minX} ${midY} L ${maxX} ${midY}${legs}`, cls);
        };

        const drawBranch = (node: SilsilahNode | null) => {
            if (!node) {
                return;
            }

            const parents = (node.parents ?? []).filter((p) => p !== null) as SilsilahNode[];
            const selfEl = q(node.id);

            if (!selfEl) {
                return;
            }

            const pEls = parents.map((p) => q(p.id)).filter((el): el is HTMLElement => el !== null);

            if (pEls.length > 0) {
                let from: { x: number; bottom: number };

                if (pEls.length >= 2) {
                    const midTop = drawSpouse(pEls[0], pEls[1]);
                    const bottoms = pEls.map((el) => centerOf(el).bottom);
                    from = { x: midTop.x, bottom: Math.max(...bottoms) };
                } else {
                    const only = centerOf(pEls[0]);
                    from = { x: only.x, bottom: only.bottom };
                }

                const siblingEls = (node.siblings ?? [])
                    .map((s) => q(s.id))
                    .filter((el): el is HTMLElement => el !== null);
                const rowEls = [...siblingEls, selfEl];
                const rowTops = rowEls.map((el) => centerOf(el).top);
                const midY = from.bottom + (Math.min(...rowTops) - from.bottom) * 0.5;
                drawComb(from, rowEls, midY);
            }
        };

        drawBranch(payload.father);
        drawBranch(payload.mother);

        const fatherEl = payload.father ? q(payload.father.id) : null;
        const motherEl = payload.mother ? q(payload.mother.id) : null;
        const childEls = (payload.children ?? [])
            .map((c) => q(c.id))
            .filter((el): el is HTMLElement => el !== null);

        let coupleAnchor: { x: number; bottom: number } | null = null;

        if (fatherEl && motherEl) {
            const mid = drawSpouse(fatherEl, motherEl, 'marriage');

            const icon = document.createElement('div');
            icon.style.position = 'absolute';
            icon.style.left = `${mid.x - 9}px`;
            icon.style.top = `${mid.top - 9}px`;
            icon.style.width = '18px';
            icon.style.height = '18px';
            icon.style.display = 'flex';
            icon.style.alignItems = 'center';
            icon.style.justifyContent = 'center';
            icon.style.background = '#FFFFFF';
            icon.style.border = `1px solid ${GOLD}`;
            icon.style.borderRadius = '50%';
            icon.style.color = GOLD;
            icon.style.fontSize = '10px';
            icon.style.zIndex = '2';
            icon.textContent = '♥';
            container.appendChild(icon);

            const fb = centerOf(fatherEl);
            const mb = centerOf(motherEl);
            coupleAnchor = { x: (fb.x + mb.x) / 2, bottom: Math.max(fb.bottom, mb.bottom) };
        } else {
            const soloEl = fatherEl ?? motherEl;

            if (soloEl) {
                const s = centerOf(soloEl);
                coupleAnchor = { x: s.x, bottom: s.bottom };
            }
        }

        if (coupleAnchor && childEls.length > 0 && fatherEl) {
            // Couple (or solo parent) heads down to the children row. Keep the
            // gold "marriage" stroke when both parents exist.
            const marriage = Boolean(fatherEl && motherEl);
            const childTop = Math.min(...childEls.map((el) => centerOf(el).top));
            const gap = childTop - coupleAnchor.bottom;
            const midY = coupleAnchor.bottom + gap * 0.35;

            if (childEls.length === 1) {
                const only = centerOf(childEls[0]);
                addPath(
                    `M ${coupleAnchor.x} ${coupleAnchor.bottom} L ${coupleAnchor.x} ${only.top}`,
                    marriage ? 'marriage' : '',
                );
            } else {
                drawComb(coupleAnchor, childEls, midY, marriage ? 'marriage' : '');
            }
        }
    };

    useEffect(() => {
        const schedule = () => {
            cancelAnimationFrame(rafRef.current);
            rafRef.current = requestAnimationFrame(drawTree);
        };

        schedule();

        // Redraw once fonts/images settle so measured coordinates are final.
        const settleTimer = window.setTimeout(schedule, 350);

        window.addEventListener('resize', schedule);
        return () => {
            cancelAnimationFrame(rafRef.current);
            window.clearTimeout(settleTimer);
            window.removeEventListener('resize', schedule);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [payload]);

    return (
        <div ref={containerRef} className="relative px-2 py-4 sm:px-6">
            <svg ref={svgRef} className="pointer-events-none absolute inset-0 z-0 h-full w-full" />

            <div className="relative z-10 flex items-start justify-center gap-8">
                {payload.father ? (
                    <div className="flex flex-1 justify-center">
                        <ParentNode node={payload.father} badge="Ayah" />
                    </div>
                ) : null}
                {payload.mother ? (
                    <div className="flex flex-1 justify-center">
                        <ParentNode node={payload.mother} badge="Ibu" />
                    </div>
                ) : null}
                {!payload.father && !payload.mother ? (
                    <div className="flex flex-1 justify-center">
                        <NodeCard node={payload.person} highlighted />
                    </div>
                ) : null}
            </div>

            {payload.children.length > 0 && (
                <div className="relative z-10 mt-14 flex flex-wrap items-start justify-center gap-6">
                    {payload.children.map((child) => (
                        <NodeCard
                            key={child.id}
                            node={child}
                            highlighted={child.id === payload.centerId}
                            badge={`Anak ke ${child.birthOrder ?? '?'}`}
                        />
                    ))}
                </div>
            )}

            {payload.margas.length > 0 && (
                <div className="mt-10 flex flex-wrap justify-center gap-2">
                    {payload.margas.map((marga) => (
                        <span
                            key={marga.name}
                            className="flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-[11px]"
                            style={{ color: INK_SOFT }}
                        >
                            <span className="h-2 w-2 rounded-sm" style={{ backgroundColor: marga.color }} />
                            {marga.name}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}