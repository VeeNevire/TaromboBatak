import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

const itemVariants = {
    hidden: { opacity: 0, y: 8 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.35, ease: 'easeOut' as const },
    },
};

const iconVariants = {
    hidden: { scale: 0.4, opacity: 0 },
    visible: (delay: number) => ({
        scale: 1,
        opacity: 1,
        transition: { type: 'spring' as const, stiffness: 260, damping: 16, delay },
    }),
};

export function NavMain({ groups = [] }: { groups?: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {groups.map((group, groupIndex) => (
                <SidebarGroup key={group.label ?? groupIndex} className="px-2 py-0">
                    {group.label && (
                        <SidebarGroupLabel className="px-2 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider">
                            {group.label}
                        </SidebarGroupLabel>
                    )}
                    <SidebarMenu>
                        {group.items.map((item, itemIndex) => {
                            const isActive = isCurrentUrl(item.href);
                            const delay = 0.08 + groupIndex * 0.1 + itemIndex * 0.06;

                            return (
                                <motion.li
                                    key={item.title}
                                    className="group/menu-item relative"
                                    variants={itemVariants}
                                    initial="hidden"
                                    animate="visible"
                                    transition={{ delay, duration: 0.35, ease: 'easeOut' }}
                                >
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive}
                                        tooltip={{ children: item.title }}
                                        className={
                                            isActive
                                                ? 'relative data-[active=true]:bg-transparent! data-[active=true]:text-white! font-semibold group-data-[collapsible=icon]:data-[active=true]:bg-tb-primary!'
                                                : 'relative'
                                        }
                                    >
                                        <Link href={item.href} prefetch>
                                            {isActive && (
                                                <motion.span
                                                    layoutId="sidebar-active-pill"
                                                    className="tb-sheen absolute inset-0 overflow-hidden rounded-md bg-tb-primary shadow-md shadow-tb-primary/25 group-data-[collapsible=icon]:hidden"
                                                    transition={{
                                                        type: 'spring',
                                                        stiffness: 500,
                                                        damping: 40,
                                                    }}
                                                />
                                            )}
                                            <span className="relative z-10 flex min-w-0 flex-1 items-center gap-2">
                                                {item.icon && (
                                                    <motion.span
                                                        className="flex shrink-0 items-center"
                                                        variants={iconVariants}
                                                        custom={delay + 0.06}
                                                    >
                                                        <item.icon className="size-4 transition-transform duration-300 group-hover/menu-item:-rotate-6 group-hover/menu-item:scale-110" />
                                                    </motion.span>
                                                )}
                                                <span className="min-w-0 truncate group-data-[collapsible=icon]:hidden">
                                                    {item.title}
                                                </span>
                                                {isActive && (
                                                    <span className="ml-auto size-1.5 shrink-0 rounded-full bg-white/90 group-data-[collapsible=icon]:hidden" />
                                                )}
                                            </span>
                                        </Link>
                                    </SidebarMenuButton>
                                </motion.li>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
