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

    const haloWidth = active ? 4.5 : 2.5;
    const haloOpacity = dimmed ? 0.03 : active ? 0.18 : 0.08;
    const mainWidth = active ? 2.0 : 1.2;
    const mainOpacity = dimmed ? 0.25 : 1;

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
                strokeWidth={1.8}
                strokeDasharray="3 8"
                strokeLinecap="round"
                strokeOpacity={dimmed ? 0.1 : active ? 0.45 : 0.3}
            />
        </g>
    );
}