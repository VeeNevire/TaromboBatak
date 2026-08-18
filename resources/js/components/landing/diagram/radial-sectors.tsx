import type { NodeProps } from '@xyflow/react';
import { INNER_RADIUS, sectorPath } from '@/data/tarombo-tree';
import type { SectorNodeData } from '@/data/tarombo-tree';

const PAD = 36;
const ORNAMENT_OFFSET = 14;

export function RadialSectors({ data }: NodeProps) {
    const { sectors, guides, labels, extent } = data as SectorNodeData;
    const half = extent / 2;
    const labelRadius = extent / 2 - PAD;
    const wedgeOuter = labelRadius;

    const ornamentRadius = labelRadius - ORNAMENT_OFFSET;
    const ornamentCount = Math.max(
        14,
        Math.round((2 * Math.PI * ornamentRadius) / 34),
    );
    const ornamentStep = (2 * Math.PI) / ornamentCount;

    return (
        <svg
            width={extent}
            height={extent}
            viewBox={`${-half} ${-half} ${extent} ${extent}`}
            className="block"
        >
            <defs>
                <radialGradient id="tb-bg-grad" cx="50%" cy="50%" r="70%">
                    <stop
                        offset="0%"
                        style={{ stopColor: 'var(--color-tb-surface-bright)' }}
                        stopOpacity={0.95}
                    />
                    <stop
                        offset="55%"
                        style={{ stopColor: 'var(--color-tb-surface-bright)' }}
                        stopOpacity={0.5}
                    />
                    <stop
                        offset="100%"
                        style={{ stopColor: 'var(--color-tb-surface-bright)' }}
                        stopOpacity={0}
                    />
                </radialGradient>
                <radialGradient id="tb-vignette" cx="50%" cy="50%" r="72%">
                    <stop
                        offset="68%"
                        style={{ stopColor: 'var(--color-tb-outline)' }}
                        stopOpacity={0}
                    />
                    <stop
                        offset="100%"
                        style={{ stopColor: 'var(--color-tb-outline)' }}
                        stopOpacity={0.2}
                    />
                </radialGradient>
                {sectors.map((sector, index) => (
                    <radialGradient
                        key={`sector-grad-${sector.marga}`}
                        id={`tb-sector-${index}`}
                        cx="18%"
                        cy="18%"
                        r="92%"
                    >
                        <stop
                            offset="0%"
                            stopColor={sector.color}
                            stopOpacity={0.05}
                        />
                        <stop
                            offset="100%"
                            stopColor={sector.color}
                            stopOpacity={0.17}
                        />
                    </radialGradient>
                ))}
            </defs>
            <circle cx={0} cy={0} r={half - 6} fill="url(#tb-bg-grad)" />
            <circle cx={0} cy={0} r={half - 6} fill="url(#tb-vignette)" />
            {guides.map((radius, index) => (
                <circle
                    key={`guide-${index}`}
                    cx={0}
                    cy={0}
                    r={radius}
                    fill="none"
                    stroke="var(--color-tb-outline-variant)"
                    strokeWidth={0.75}
                    strokeDasharray="2 5"
                />
            ))}
            {sectors.map((sector, index) => (
                <path
                    key={`sector-${sector.marga}`}
                    d={sectorPath(
                        0,
                        0,
                        INNER_RADIUS,
                        wedgeOuter,
                        sector.start,
                        sector.end,
                    )}
                    fill={`url(#tb-sector-${index})`}
                />
            ))}
            {sectors.map((sector) => {
                const largeArc = sector.end - sector.start > Math.PI ? 1 : 0;
                const startX = wedgeOuter * Math.cos(sector.start);
                const startY = wedgeOuter * Math.sin(sector.start);
                const endX = wedgeOuter * Math.cos(sector.end);
                const endY = wedgeOuter * Math.sin(sector.end);

                return (
                    <path
                        key={`arc-${sector.marga}`}
                        d={`M ${startX} ${startY} A ${wedgeOuter} ${wedgeOuter} 0 ${largeArc} 1 ${endX} ${endY}`}
                        fill="none"
                        stroke={sector.color}
                        strokeOpacity={0.28}
                        strokeWidth={1.5}
                    />
                );
            })}
            {sectors.map((sector) =>
                [sector.start, sector.end].map((angle) => {
                    const cos = Math.cos(angle);
                    const sin = Math.sin(angle);
                    const x1 = INNER_RADIUS * cos;
                    const y1 = INNER_RADIUS * sin;
                    const x2 = wedgeOuter * cos;
                    const y2 = wedgeOuter * sin;

                    return (
                        <g key={`divider-${sector.marga}-${angle.toFixed(3)}`}>
                            <path
                                d={`M ${x1} ${y1} L ${x2} ${y2}`}
                                stroke={sector.color}
                                strokeOpacity={0.32}
                                strokeWidth={1}
                            />
                            <path
                                d={`M ${x1 - sin * 1.5} ${y1 + cos * 1.5} L ${x2 - sin * 1.5} ${y2 + cos * 1.5}`}
                                stroke={sector.color}
                                strokeOpacity={0.18}
                                strokeWidth={0.75}
                            />
                        </g>
                    );
                }),
            )}
            <g>
                <circle
                    cx={0}
                    cy={0}
                    r={ornamentRadius - 5}
                    fill="none"
                    stroke="var(--color-tb-primary)"
                    strokeOpacity={0.16}
                    strokeWidth={1}
                />
                <circle
                    cx={0}
                    cy={0}
                    r={ornamentRadius + 5}
                    fill="none"
                    stroke="var(--color-tb-primary)"
                    strokeOpacity={0.16}
                    strokeWidth={1}
                />
                {Array.from({ length: ornamentCount }, (_, index) => {
                    const angle = index * ornamentStep;
                    const midX = ornamentRadius * Math.cos(angle);
                    const midY = ornamentRadius * Math.sin(angle);

                    return (
                        <g key={`ornament-${index}`}>
                            <rect
                                x={midX - 2.6}
                                y={midY - 2.6}
                                width={5.2}
                                height={5.2}
                                fill="none"
                                stroke="var(--color-tb-primary)"
                                strokeWidth={0.9}
                                transform={`rotate(${(angle * 180) / Math.PI - 45} ${midX} ${midY})`}
                                opacity={0.45}
                            />
                            <circle
                                cx={midX}
                                cy={midY}
                                r={0.9}
                                fill="var(--color-tb-primary)"
                                opacity={0.55}
                            />
                        </g>
                    );
                })}
            </g>
            {/* Generation tags hidden for cleaner look - info available in legend */}
            {labels.map((label) => {
                const x = label.radius * Math.cos(label.angle);
                const y = label.radius * Math.sin(label.angle);
                const width = label.text.length * 6.4 + 20;
                const height = 20;

                return (
                    <g key={`label-${label.text}`}>
                        <rect
                            x={x - width / 2}
                            y={y - height / 2}
                            width={width}
                            height={height}
                            rx={height / 2}
                            fill="var(--color-tb-surface-bright)"
                            stroke={label.color}
                            strokeOpacity={0.5}
                            strokeWidth={1}
                        />
                        <text
                            x={x}
                            y={y}
                            textAnchor="middle"
                            dominantBaseline="middle"
                            fill={label.color}
                            fontSize={11}
                            fontWeight={700}
                        >
                            {label.text}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
}
