import { motion, HTMLMotionProps } from 'motion/react';
import type { ButtonHTMLAttributes, AnchorHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

interface AnimatedButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost';
    scaleOnHover?: boolean;
    scaleOnTap?: boolean;
}

export function AnimatedButton({
    className,
    variant = 'primary',
    scaleOnHover = true,
    scaleOnTap = true,
    children,
    ...props
}: AnimatedButtonProps) {
    const baseClass = cn(
        'inline-flex items-center justify-center gap-2 font-medium transition-colors',
        'rounded-full px-5 py-2.5',
        {
            'bg-tb-primary text-white hover:bg-tb-primary-light': variant === 'primary',
            'bg-tb-surface-container text-tb-on-surface hover:bg-tb-surface-container-high':
                variant === 'secondary',
            'border border-tb-outline-variant bg-transparent hover:bg-tb-surface-container':
                variant === 'outline',
            'bg-transparent hover:bg-tb-surface-container': variant === 'ghost',
        },
        className,
    );

    return (
        <motion.button
            className={baseClass}
            whileHover={scaleOnHover ? { scale: 1.02 } : undefined}
            whileTap={scaleOnTap ? { scale: 0.98 } : undefined}
            transition={{ type: 'spring', stiffness: 300, damping: 20 }}
            {...props}
        >
            {children}
        </motion.button>
    );
}

export function AnimatedLink({
    className,
    scaleOnHover = true,
    underlineSlide = true,
    children,
    ...props
}: AnchorHTMLAttributes<HTMLAnchorElement> & {
    underlineSlide?: boolean;
}) {
    return (
        <motion.a
            className={cn(
                'inline-flex items-center gap-1.5 transition-colors',
                className,
            )}
            whileHover={scaleOnHover ? { scale: 1.02 } : undefined}
            transition={{ type: 'spring', stiffness: 300, damping: 20 }}
            {...props}
        >
            {children}
            {underlineSlide && (
                <motion.span
                    layoutId="underline"
                    className="absolute bottom-0 left-0 h-0.5 w-full bg-current origin-left scale-x-0"
                    initial={{ scaleX: 0 }}
                    animate={{ scaleX: 1 }}
                    transition={{ type: 'spring', stiffness: 300, damping: 20 }}
                />
            )}
        </motion.a>
    );
}

export function AnimatedCard({
    className,
    hoverLift = true,
    hoverScale = false,
    children,
    ...props
}: HTMLMotionProps<'div'> & {
    hoverLift?: boolean;
    hoverScale?: boolean;
}) {
    return (
        <motion.div
            className={className}
            whileHover={
                hoverLift
                    ? { y: -4, boxShadow: '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)' }
                    : hoverScale
                      ? { scale: 1.02 }
                      : undefined
            }
            transition={{ type: 'spring', stiffness: 300, damping: 20 }}
            {...props}
        >
            {children}
        </motion.div>
    );
}

export function AnimatedAvatar({
    className,
    pulseOnHover = true,
    ...props
}: HTMLMotionProps<'div'> & {
    pulseOnHover?: boolean;
}) {
    return (
        <motion.div
            className={className}
            whileHover={
                pulseOnHover
                    ? { scale: [1, 1.05, 1], transition: { duration: 0.4, repeat: Infinity } }
                    : undefined
            }
            {...props}
        />
    );
}