import type { NodeProps } from '@xyflow/react';
import { INNER_RADIUS, sectorPath } from '@/data/tarombo-tree';
import type { SectorNodeData } from '@/data/tarombo-tree';

const PAD = 36;

export function RadialSectors({ data }: NodeProps) {
    const { sectors, guides, labels, generationTags, extent } = data as SectorNodeData;
    const half = extent / 2;
    const labelRadius = extent / 2 - PAD;
    const wedgeOuter = labelRadius;

    return (
        <svg width={extent} height={extent} viewBox={`${-half} ${-half} ${extent} ${extent}`} className="block">
            <defs>
                <radialGradient id="tb-bg-grad" cx="50%" cy="50%" r="70%">
                    <stop offset="0%" stopColor="#ffffff" stopOpacity="0.7" />
                    <stop offset="55%" stopColor="#fdfcf7" stopOpacity="0.25" />
                    <stop offset="100%" stopColor="#f9f6ef" stopOpacity="0" />
                </radialGradient>
            </defs>
            <circle cx={0} cy={0} r={half - 6} fill="url(#tb-bg-grad)" />
            {guides.map((radius, index) => (
                <circle
                    key={`guide-${index}`}
                    cx={0}
                    cy={0}
                    r={radius}
                    fill="none"
                    stroke="#ece4d3"
                    strokeWidth={0.75}
                />
            ))}
            {sectors.map((sector) => (
                <path
                    key={`sector-${sector.marga}`}
                    d={sectorPath(0, 0, INNER_RADIUS, wedgeOuter, sector.start, sector.end)}
                    fill={sector.color}
                    fillOpacity={0.12}
                />
            ))}
            {sectors.map((sector) =>
                [sector.start, sector.end].map((angle) => (
                    <path
                        key={`divider-${sector.marga}-${angle.toFixed(3)}`}
                        d={`M ${INNER_RADIUS * Math.cos(angle)} ${INNER_RADIUS * Math.sin(angle)} L ${
                            wedgeOuter * Math.cos(angle)
                        } ${wedgeOuter * Math.sin(angle)}`}
                        stroke={sector.color}
                        strokeOpacity={0.4}
                        strokeWidth={1}
                    />
                )),
            )}
            {generationTags.map((tag) => {
                const x = tag.radius * Math.cos(tag.angle);
                const y = tag.radius * Math.sin(tag.angle);
                const width = tag.label.length * 5.8 + 16;
                const height = 16;

                return (
                    <g key={tag.label}>
                        <rect
                            x={x - width / 2}
                            y={y - height / 2}
                            width={width}
                            height={height}
                            rx={height / 2}
                            fill="#ffffff"
                            fillOpacity={0.92}
                            stroke="#e0d5be"
                            strokeWidth={1}
                        />
                        <text
                            x={x}
                            y={y}
                            textAnchor="middle"
                            dominantBaseline="middle"
                            fill="#5e523f"
                            fontSize={9}
                            fontWeight={700}
                            letterSpacing={0.4}
                        >
                            {tag.label}
                        </text>
                    </g>
                );
            })}
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
                            fill="#fffdf6"
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