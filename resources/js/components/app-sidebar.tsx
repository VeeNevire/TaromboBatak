import { Link, usePage } from '@inertiajs/react';
import { BookOpen, CalendarDays, LayoutGrid, Shapes, TreePine, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import events from '@/routes/events';
import marga from '@/routes/marga';
import people from '@/routes/people';
import stories from '@/routes/stories';
import tarombo from '@/routes/tarombo';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';

    const mainNavItems: NavItem[] = [
        {
            title: 'Beranda',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: isAdmin ? 'Data Anggota' : 'Silsilah Saya',
            href: people.index(),
            icon: Users,
        },
        ...(isAdmin
            ? ([
                  {
                      title: 'Daftar Marga',
                      href: marga.index(),
                      icon: Shapes,
                  },
                  {
                      title: 'Cerita Leluhur & Budaya',
                      href: stories.index(),
                      icon: BookOpen,
                  },
                  {
                      title: 'Event & Kegiatan',
                      href: events.index(),
                      icon: CalendarDays,
                  },
              ] satisfies NavItem[])
            : []),
        {
            title: 'Pohon Tarombo',
            href: tarombo.index(),
            icon: TreePine,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
