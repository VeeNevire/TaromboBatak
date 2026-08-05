import type { EdgeTypes, NodeTypes } from '@xyflow/react';
import { CenterNode } from '@/components/landing/diagram/center-node';
import { PersonNode } from '@/components/landing/diagram/person-node';
import { RadialEdge } from '@/components/landing/diagram/radial-edge';
import { RadialSectors } from '@/components/landing/diagram/radial-sectors';

export const nodeTypes: NodeTypes = {
    center: CenterNode,
    person: PersonNode,
    sectors: RadialSectors,
};

export const edgeTypes: EdgeTypes = {
    radial: RadialEdge,
};
