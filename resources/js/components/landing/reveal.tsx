import { motion } from 'framer-motion';
import type { Transition } from 'framer-motion';
import type { ReactNode } from 'react';

const variants = {
    fadeUp: {
        hidden: { opacity: 0, y: 20 },
        visible: { opacity: 1, y: 0 },
    },
    fadeDown: {
        hidden: { opacity: 0, y: -20 },
        visible: { opacity: 1, y: 0 },
    },
    fadeLeft: {
        hidden: { opacity: 0, x: -20 },
        visible: { opacity: 1, x: 0 },
    },
    fadeRight: {
        hidden: { opacity: 0, x: 20 },
        visible: { opacity: 1, x: 0 },
    },
    fade: {
        hidden: { opacity: 0 },
        visible: { opacity: 1 },
    },
    scaleUp: {
        hidden: { opacity: 0, scale: 0.9 },
        visible: { opacity: 1, scale: 1 },
    },
    scaleDown: {
        hidden: { opacity: 0, scale: 1.1 },
        visible: { opacity: 1, scale: 1 },
    },
    flipX: {
        hidden: { opacity: 0, rotateX: -90 },
        visible: { opacity: 1, rotateX: 0 },
    },
    flipY: {
        hidden: { opacity: 0, rotateY: -90 },
        visible: { opacity: 1, rotateY: 0 },
    },
};

export type RevealVariant = keyof typeof variants;

interface RevealProps {
    children: ReactNode;
    variant?: RevealVariant;
    delay?: number;
    className?: string;
    duration?: number;
    ease?: Transition['ease'];
    staggerChildren?: number;
}

export function Reveal({
    children,
    variant = 'fadeUp',
    delay = 0,
    className,
    duration = 0.5,
    ease = 'easeOut',
    staggerChildren,
}: RevealProps) {
    return (
        <motion.div
            className={className}
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: '-60px' }}
            variants={variants[variant]}
            transition={{ duration, ease, delay, staggerChildren }}
        >
            {children}
        </motion.div>
    );
}

export function RevealGroup({
    children,
    delay = 0,
    staggerChildren = 0.1,
    className,
}: {
    children: ReactNode;
    delay?: number;
    staggerChildren?: number;
    className?: string;
}) {
    return (
        <motion.div
            className={className}
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: '-100px' }}
            variants={{
                hidden: { opacity: 1 },
                visible: {
                    opacity: 1,
                    transition: { staggerChildren },
                },
            }}
            transition={{ staggerChildren, delay }}
        >
            {children}
        </motion.div>
    );
}

export function StaggeredItem({
    children,
    variant = 'fadeUp',
    className,
    delay = 0,
}: {
    children: ReactNode;
    variant?: RevealVariant;
    className?: string;
    delay?: number;
}) {
    return (
        <motion.div
            className={className}
            variants={variants[variant]}
            initial="hidden"
            animate="visible"
            transition={{ delay }}
        >
            {children}
        </motion.div>
    );
}
