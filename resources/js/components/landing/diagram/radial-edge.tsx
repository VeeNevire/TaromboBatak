import { BaseEdge } from '@xyflow/react';
import type { EdgeProps } from '@xyflow/react';
import type { RadialEdgeData } from '@/data/tarombo-tree';

export function RadialEdge({ sourceX, sourceY, targetX, targetY, data }: EdgeProps) {
    const { stroke, sourceRadius, targetRadius, active, dimmed } = data as RadialEdgeData;

    const dx = targetX - sourceX;
    const dy = targetY - sourceY;
    const distance = Math.hypot(dx, dy) || 1;
    const ux = dx / distance;
    const uy = dy / distance;

    const startX = sourceX + ux * sourceRadius;
    const startY = sourceY + uy * sourceRadius;
    const endX = targetX - ux * targetRadius;
    const endY = targetY - uy * targetRadius;

    const path = `M ${startX} ${startY} L ${endX} ${endY}`;

    const haloWidth = active ? 5.5 : 3.5;
    const haloOpacity = dimmed ? 0.04 : active ? 0.24 : 0.1;
    const mainWidth = active ? 2.4 : 1.6;
    const mainOpacity = dimmed ? 0.3 : 1;

    return (
        <g>
            <path
                d={path}
                fill="none"
                stroke={stroke}
                strokeOpacity={haloOpacity}
                strokeWidth={haloWidth}
                strokeLinecap="round"
            />
            <BaseEdge
                path={path}
                style={{ stroke, strokeWidth: mainWidth, strokeOpacity: mainOpacity }}
                strokeLinecap="round"
            />
            <path
                className="tarombo-flow-edge"
                d={path}
                fill="none"
                stroke={stroke}
                strokeWidth={2.2}
                strokeDasharray="4 10"
                strokeLinecap="round"
                strokeOpacity={dimmed ? 0.12 : active ? 0.55 : 0.4}
            />
        </g>
    );
}