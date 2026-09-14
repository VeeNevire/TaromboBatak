import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    BellRing,
    CalendarDays,
    History,
    Images,
    LayoutGrid,
    MessageCircle,
    MessagesSquare,
    Megaphone,
    Newspaper,
    Shapes,
    ShieldCheck,
    ScrollText,
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
import { home } from '@/routes';
import accounts from '@/routes/accounts';
import announcements from '@/routes/announcements';
import contacts from '@/routes/contacts';
import contributions from '@/routes/contributions';
import events from '@/routes/events';
import familyTreeActivities from '@/routes/family-tree-activities';
import familyTrees from '@/routes/family-trees';
import groups from '@/routes/groups';
import marga from '@/routes/marga';
import messageLogs from '@/routes/message-logs';
import newsFeed from '@/routes/news-feed';
import people from '@/routes/people';
import stories from '@/routes/stories';
import subAdmins from '@/routes/sub-admins';
import tarombo from '@/routes/tarombo';
import taromboFrames from '@/routes/tarombo-frames';
import treeActivityLogs from '@/routes/tree-activity-logs';
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
                    title: 'Daftar Marga',
                    href: marga.index(),
                    icon: Shapes,
                },
                {
                    title: 'Pohon Tarombo',
                    href: tarombo.index(),
                    icon: TreePine,
                },
                {
                    title: 'Tarombo Tersimpan',
                    href: tarombo.snapshots.index(),
                    icon: Images,
                },
                {
                    title: 'Log Pohon Besar',
                    href: treeActivityLogs.index(),
                    icon: ScrollText,
                },
                ...(!isAdmin
                    ? [
                          {
                              title: 'Daftar Kontak',
                              href: contacts.index(),
                              icon: MessageCircle,
                          },
                          {
                              title: 'Log Pesan',
                              href: messageLogs.index(),
                              icon: ScrollText,
                          },
                          {
                              title: 'Grup',
                              href: groups.index(),
                              icon: MessagesSquare,
                          },
                          {
                              title: 'Pengumuman',
                              href: announcements.index(),
                              icon: Megaphone,
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
                              title: 'Log Aktivitas',
                              href: familyTreeActivities.index(),
                              icon: History,
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
                              href: people.publicPreview(),
                              icon: Users,
                          },
                          {
                              title: 'Log Aktivitas',
                              href: familyTreeActivities.index(),
                              icon: History,
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
                              title: 'Data Pengguna',
                              href: accounts.index(),
                              icon: Users,
                          },
                          {
                              title: 'Daftar Silsilah Milik Akun',
                              href: familyTrees.index(),
                              icon: TreePine,
                          },
                          {
                              title: 'Template Frame Tarombo',
                              href: taromboFrames.index(),
                              icon: Images,
                          },
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
                            <Link href={home()} prefetch>
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
