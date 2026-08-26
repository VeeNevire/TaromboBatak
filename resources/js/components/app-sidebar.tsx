import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    BellRing,
    CalendarDays,
    LayoutGrid,
    MessageCircle,
    Newspaper,
    Shapes,
    ShieldCheck,
    TreePine,
    Users,
} from 'lucide-react';
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
import contacts from '@/routes/contacts';
import contributions from '@/routes/contributions';
import events from '@/routes/events';
import marga from '@/routes/marga';
import newsFeed from '@/routes/news-feed';
import people from '@/routes/people';
import stories from '@/routes/stories';
import subAdmins from '@/routes/sub-admins';
import tarombo from '@/routes/tarombo';
import type { NavGroup } from '@/types';

export function AppSidebar() {
    const {
        auth,
        unreadContributionCount,
        unreadEventCount,
        unreadStoryCount,
    } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';
    const isStaff = isAdmin || auth.user?.role === 'subadmin';
    const isContributor =
        auth.user?.role === 'contributor_main' ||
        auth.user?.role === 'contributor_member';

    const navGroups: NavGroup[] = [
        {
            label: 'Utama',
            items: [
                {
                    title: 'Beranda',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
                {
                    title: 'News Feed',
                    href: newsFeed.index(),
                    icon: Newspaper,
                },
                {
                    title: 'Pohon Tarombo',
                    href: tarombo.index(),
                    icon: TreePine,
                },
                ...(!isAdmin
                    ? [
                          {
                              title: 'Daftar Kontak',
                              href: contacts.index(),
                              icon: MessageCircle,
                          },
                      ]
                    : []),
            ],
        },
        ...(isStaff
            ? [
                  {
                      label: 'Kelola Data',
                      items: [
                          {
                              title: 'Data Anggota',
                              href: people.index(),
                              icon: Users,
                          },
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
                      ],
                  } satisfies NavGroup,
              ]
            : [
                  {
                      label: 'Keluarga',
                      items: [
                          {
                              title: 'Silsilah Saya',
                              href: people.index(),
                              icon: Users,
                          },
                          {
                              title: 'Event & Kegiatan',
                              href: events.index(),
                              icon: CalendarDays,
                          },
                          {
                              title: 'Cerita Leluhur & Budaya',
                              href: stories.index(),
                              icon: BookOpen,
                          },
                      ],
                  } satisfies NavGroup,
              ]),
        ...(isAdmin
            ? [
                  {
                      label: 'Administrasi',
                      items: [
                          {
                              title: 'Sub Admin',
                              href: subAdmins.index(),
                              icon: ShieldCheck,
                          },
                          {
                              title: 'Kontribusi',
                              href: contributions.index(),
                              icon: BellRing,
                              badge: unreadContributionCount,
                          },
                      ],
                  } satisfies NavGroup,
              ]
            : []),
        ...(isContributor
            ? [
                  {
                      label: 'Kontribusi',
                      items: [
                          {
                              title: 'Pengajuan Kontribusi',
                              href: contributions.index(),
                              icon: BellRing,
                              badge:
                                  unreadContributionCount +
                                  unreadEventCount +
                                  unreadStoryCount,
                          },
                      ],
                  } satisfies NavGroup,
              ]
            : []),
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

            <SidebarContent className="bg-tb-gorga">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
